<?php

declare(strict_types=1);

namespace Switon\Testing\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Switon\Core\App;
use Switon\Core\ConsoleInterface;
use Switon\Core\ContextManagerInterface;
use Switon\Core\InputInterface;
use Switon\Core\PathAliasInterface;
use Switon\Eventing\EventDispatcherInterface;
use Switon\ComposerExtra\ComposerExtraInterface;
use Switon\Testing\Container;
use Switon\Testing\InMemoryContextManager;
use Switon\Testing\Mock\MockConsole;
use ArrayObject;
use stdClass;

use function class_exists;
use function substr;

class ContainerTest extends TestCase
{
    public function testKeepsUserProvidedPsrContainerBinding(): void
    {
        $inner = new ArrayObject();
        $container = new Container([
            \Psr\Container\ContainerInterface::class => $inner,
        ]);

        $this->assertSame($inner, $container->get(\Psr\Container\ContainerInterface::class));
        $this->assertSame($container, $container->get(\Switon\Core\ContainerInterface::class));
    }

    public function testKeepsUserProvidedConsoleBinding(): void
    {
        $customConsole = new MockConsole();
        $container = new Container([
            ConsoleInterface::class => $customConsole,
        ]);

        $this->assertSame($customConsole, $container->get(ConsoleInterface::class));
    }

    public function testRegistersResourceAliasesFromProviderAttributes(): void
    {
        $container = new Container();
        $pathAlias = $container->get(PathAliasInterface::class);

        $validatorAlias = $pathAlias->get('@switon.validator.resources');
        $openapiAlias = $pathAlias->get('@switon.openapi.resources');

        if (class_exists(\Switon\Validating\ServiceProvider::class)) {
            $this->assertNotNull($validatorAlias);
        } else {
            $this->assertNull($validatorAlias);
        }

        if (class_exists(\Switon\OpenApi\ServiceProvider::class)) {
            $this->assertNotNull($openapiAlias);
        } else {
            $this->assertNull($openapiAlias);
        }

    }

    public function testPrebindsInputInterfaceFromComposerExtra(): void
    {
        $defaultInputClass = substr(InputInterface::class, 0, -9);
        if (!interface_exists(InputInterface::class) || !class_exists($defaultInputClass)) {
            $this->markTestSkipped('Current core release cannot auto-resolve InputInterface to a default Input class.');
        }

        $container = new Container();

        $this->assertInstanceOf(
            InputInterface::class,
            $container->get(InputInterface::class)
        );
    }

    public function testPrebindsInMemoryContextManager(): void
    {
        $container = new Container();

        $this->assertInstanceOf(
            InMemoryContextManager::class,
            $container->get(ContextManagerInterface::class)
        );
    }

    public function testAutoRegistrationSkipsProvidersWithMissingClassFiles(): void
    {
        $warnings = [];
        set_error_handler(static function (int $severity, string $message) use (&$warnings): bool {
            if ($severity === E_WARNING) {
                $warnings[] = $message;
            }

            return true;
        });
        try {
            new Container();
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $warnings);
    }

    public function testProviderAutoRegistrationSkipsWhenComposerExtraBindingIsInvalid(): void
    {
        $container = new Container([
            ComposerExtraInterface::class => new stdClass(),
        ]);

        $this->assertInstanceOf(stdClass::class, $container->get(ComposerExtraInterface::class));
    }

    public function testProviderAutoRegistrationIgnoresInvalidProviderEntries(): void
    {
        $composerExtra = $this->createStub(ComposerExtraInterface::class);
        $composerExtra->method('collect')->willReturn([
            '',
            123,
            'Switon\\Testing\\Tests\\Unit\\DefinitelyMissingProviderClass',
        ]);

        new Container([
            ComposerExtraInterface::class => $composerExtra,
        ]);

        $this->addToAssertionCount(1);
    }

    public function testCanAutoloadClassResolvesComposerMappedClasses(): void
    {
        $container = new class () extends Container {
            public function canAutoloadClassPublic(string $class): bool
            {
                return $this->canAutoloadClass($class);
            }
        };

        $this->assertTrue($container->canAutoloadClassPublic(\Switon\Core\App::class));
    }

    public function testPreservesMockConsoleBindingDuringProviderAutoRegistration(): void
    {
        $container = new Container();

        $this->assertInstanceOf(
            MockConsole::class,
            $container->get(ConsoleInterface::class)
        );
    }

    public function testProviderAutoRegistrationRunsOnlyOnce(): void
    {
        $container = new class () extends Container {
            public int $providerRegistrationCalls = 0;

            protected function registerProvidersFromComposerExtra(): void
            {
                if ($this->providersRegistered) {
                    parent::registerProvidersFromComposerExtra();

                    return;
                }

                ++$this->providerRegistrationCalls;
                parent::registerProvidersFromComposerExtra();
            }

            public function registerProvidersAgain(): void
            {
                $this->registerProvidersFromComposerExtra();
            }
        };

        $this->assertSame(1, $container->providerRegistrationCalls);
        $container->registerProvidersAgain();
        $this->assertSame(1, $container->providerRegistrationCalls);
    }

    public function testProviderAutoRegistrationSkipsProvidersThatCannotAutoload(): void
    {
        $composerExtra = $this->createStub(ComposerExtraInterface::class);
        $composerExtra->method('collect')->willReturnCallback(
            static fn (string $extra): array => $extra === 'switon.providers'
                ? ['Switon\\Testing\\Tests\\Unit\\DefinitelyMissingProviderClass']
                : [],
        );

        putenv('SWITON_TESTS_DISABLE_PROVIDER_AUTO_REGISTER=');
        try {
            $container = new Container([
                ComposerExtraInterface::class => $composerExtra,
            ]);

            $this->assertInstanceOf(ConsoleInterface::class, $container->get(ConsoleInterface::class));
        } finally {
            putenv('SWITON_TESTS_DISABLE_PROVIDER_AUTO_REGISTER');
        }
    }

    public function testProviderAutoRegistrationCanBeDisabledViaEnvironment(): void
    {
        putenv('SWITON_TESTS_DISABLE_PROVIDER_AUTO_REGISTER=1');
        try {
            $container = new Container();
            $this->assertTrue($container->get(EventDispatcherInterface::class)->isDispatchingEvents());
        } finally {
            putenv('SWITON_TESTS_DISABLE_PROVIDER_AUTO_REGISTER');
        }
    }

    public function testDisableAndEnableEventDispatchingToggleDispatcherState(): void
    {
        $container = new Container();
        $dispatcher = $container->get(EventDispatcherInterface::class);

        $this->assertTrue($dispatcher->isDispatchingEvents());

        $container->disableEventDispatching();
        $this->assertFalse($dispatcher->isDispatchingEvents());

        $container->enableEventDispatching();
        $this->assertTrue($dispatcher->isDispatchingEvents());
    }
}
