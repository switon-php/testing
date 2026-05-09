<?php

declare(strict_types=1);

namespace Switon\Testing\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Switon\Core\ConsoleInterface;
use Switon\Core\InputInterface;
use Switon\Core\ContextManagerInterface;
use Switon\Core\PathAliasInterface;
use Switon\Testing\Container\Container;
use Switon\Testing\InMemoryContextManager;
use Switon\Testing\Mock\MockConsole;
use function class_exists;

class ContainerTest extends TestCase
{
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
        if (!interface_exists(InputInterface::class)) {
            $this->markTestSkipped('Current switon/core release does not expose InputInterface.');
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

    public function testPreservesMockConsoleBindingDuringProviderAutoRegistration(): void
    {
        $container = new Container();

        $this->assertInstanceOf(
            MockConsole::class,
            $container->get(ConsoleInterface::class)
        );
    }
}
