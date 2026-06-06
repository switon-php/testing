<?php

declare(strict_types=1);

namespace Switon\Testing;

use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface as PsrListenerProviderInterface;
use Psr\Log\LoggerInterface;
use Switon\ComposerExtra\ComposerExtraInterface;
use Switon\Core\App;
use Switon\Core\ClassScanner;
use Switon\Core\Clock;
use Switon\Core\ConsoleInterface;
use Switon\Core\ContextManagerInterface;
use Switon\Core\Filesystem;
use Switon\Core\FilesystemInterface;
use Switon\Core\PathAlias;
use Switon\Core\PathAliasInterface;
use Switon\Core\RandomInterface;
use Switon\Core\ResourceAliasRegistrar;
use Switon\Core\SceneManager;
use Switon\Core\ServiceProviderInterface;
use Switon\Core\TranslatorInterface;
use Switon\Di\Container as DiContainer;
use Switon\Di\ServiceProvider as DiServiceProvider;
use Switon\Eventing\EventDispatcher as RealEventDispatcher;
use Switon\Eventing\EventDispatcherInterface;
use Switon\Eventing\ListenerProviderInterface;
use Switon\Testing\EventDispatcher as TestEventDispatcher;
use Switon\Testing\Mock\MockConsole;
use Switon\Testing\Mock\StubTranslator;

/**
 * Preconfigured DI container for package and component tests.
 *
 * Use when tests need framework-like wiring with built-in defaults
 * (mock logger/console/random, stub translator, test event dispatcher, in-memory context storage, real filesystem/class scanning/clock/app bindings).
 * Additional test doubles such as `MockClock`, `MockListenerProvider`, and `MockCache`
 * are available for manual binding in test setup; they are not preconfigured here by default.
 *
 * Road-signs:
 * - constructor seeds common testing defaults
 * - setupEventSystem() wires test event dispatcher
 * - provider auto-registration via ComposerExtra
 * - enableEventDispatching()/disableEventDispatching()
 *
 * @see \Switon\Testing\TestCase
 * @see \Switon\Testing\EventDispatcher
 * @see \Switon\Testing\MockEventDispatcher
 * @see \Switon\Testing\MockLogger
 * @see \Switon\Testing\MockClock
 * @see \Switon\Testing\MockRandom
 * @see \Switon\Testing\InMemoryContextManager
 * @see \Switon\Testing\MockListenerProvider
 * @see \Switon\Testing\Mock\MockConsole
 * @see \Switon\Testing\Mock\MockCache
 */
class Container extends DiContainer
{
    protected bool $providersRegistered = false;

    /**
     * Creates a test container with framework-like defaults and test-owned bindings.
     */
    public function __construct(array $definitions = [])
    {
        parent::__construct($definitions);

        $diProvider = new DiServiceProvider();
        $diProvider->register($this);
        $diProvider->boot();

        App::setContainer($this);

        if (!isset($this->definitions[\Switon\Core\ContainerInterface::class])) {
            $this->set(\Switon\Core\ContainerInterface::class, $this);
        }
        if (!isset($this->definitions[ContainerInterface::class])) {
            $this->set(ContainerInterface::class, $this);
        }

        if (!isset($this->definitions[LoggerInterface::class])) {
            $this->set(LoggerInterface::class, new MockLogger());
        }

        if (!isset($this->definitions[TranslatorInterface::class])) {
            $this->set(TranslatorInterface::class, StubTranslator::class);
        }

        if (!isset($this->definitions[PathAliasInterface::class])) {
            $pathAlias = new PathAlias();
            $pathAlias->set('@view', sys_get_temp_dir() . '/switon_test_view_' . uniqid('', true));
            $pathAlias->set('@public', sys_get_temp_dir() . '/switon_test_public_' . uniqid('', true));
            $pathAlias->set('@runtime', sys_get_temp_dir() . '/switon_test_runtime_' . uniqid('', true));
            $this->set(PathAlias::class, $pathAlias);
            $this->set(PathAliasInterface::class, $pathAlias);
        }

        if (!isset($this->definitions[ConsoleInterface::class])) {
            $mockConsole = new MockConsole();
            $this->set(ConsoleInterface::class, $mockConsole);
            $this->set(MockConsole::class, $mockConsole);
        }

        $this->setupEventSystem();

        if (!isset($this->definitions[ContextManagerInterface::class])) {
            $this->set(ContextManagerInterface::class, InMemoryContextManager::class);
        }

        if (!isset($this->definitions[App::class])) {
            $this->set(App::class, [
                'id' => 'test-app',
                'env' => 'test',
                'debug' => true,
            ]);
        }

        $this->registerProvidersFromComposerExtra();
    }

    protected function registerProvidersFromComposerExtra(): void
    {
        if ($this->providersRegistered) {
            return;
        }

        if (getenv('SWITON_TESTS_DISABLE_PROVIDER_AUTO_REGISTER') !== false) {
            return;
        }

        if (!isset($this->definitions[ComposerExtraInterface::class])) {
            $extra = new ComposerExtra();
            $this->set(ComposerExtraInterface::class, $extra);
        }

        $composerExtra = $this->get(ComposerExtraInterface::class);
        if (!$composerExtra instanceof ComposerExtraInterface) {
            return;
        }

        $providerClasses = $composerExtra->collect('switon.providers');
        $pathAlias = $this->get(PathAliasInterface::class);
        $resourceAliasRegistrar = $this->make(ResourceAliasRegistrar::class);

        foreach ($providerClasses as $providerClass) {
            if (!class_exists($providerClass)) {
                continue;
            }

            $resourceAliasRegistrar->register($pathAlias, $providerClass);

            $provider = new $providerClass();
            if ($provider instanceof ServiceProviderInterface) {
                $preservedDefinitions = $this->capturePreservedDefinitions();
                $provider->register($this);
                $this->restorePreservedDefinitions($preservedDefinitions);
            }
        }

        unset($this->instances[\Switon\Core\ContainerInterface::class]);
        unset($this->instances[ContainerInterface::class]);

        $this->providersRegistered = true;
    }

    /**
     * Capture test-owned bindings that provider auto-registration must not override.
     *
     * @return array<string, mixed>
     */
    protected function capturePreservedDefinitions(): array
    {
        $preserved = [];
        foreach ([
                     LoggerInterface::class,
                     TranslatorInterface::class,
                     PathAliasInterface::class,
                     PathAlias::class,
                     ConsoleInterface::class,
                     MockConsole::class,
                     PsrEventDispatcherInterface::class,
                     EventDispatcherInterface::class,
                     PsrListenerProviderInterface::class,
                     ListenerProviderInterface::class,
                     ContextManagerInterface::class,
                     App::class,
                     ComposerExtraInterface::class,
                     FilesystemInterface::class,
                     ClassScanner::class,
                     Clock::class,
                     RandomInterface::class,
                     SceneManager::class,
                     Filesystem::class,
                     ClassScanner::class,
                     Clock::class,
                     SceneManager::class,
                     MockLogger::class,
                     MockRandom::class,
                     InMemoryContextManager::class,
                 ] as $id) {
            if (array_key_exists($id, $this->definitions)) {
                $preserved[$id] = $this->definitions[$id];
            }
        }

        return $preserved;
    }

    /**
     * Restore test-owned bindings after provider registration.
     *
     * @param array<string, mixed> $preservedDefinitions
     */
    protected function restorePreservedDefinitions(array $preservedDefinitions): void
    {
        foreach ($preservedDefinitions as $id => $definition) {
            $this->definitions[$id] = $definition;
        }
    }

    protected function setupEventSystem(): void
    {
        if (!isset($this->definitions[RealEventDispatcher::class])) {
            $this->set(RealEventDispatcher::class, RealEventDispatcher::class);
        }

        if (!isset($this->definitions[EventDispatcherInterface::class])) {
            $this->set(EventDispatcherInterface::class, function (): TestEventDispatcher {
                return new TestEventDispatcher($this->get(RealEventDispatcher::class));
            });
        }

        if (!isset($this->definitions[PsrEventDispatcherInterface::class])) {
            $this->set(PsrEventDispatcherInterface::class, EventDispatcherInterface::class);
        }

        if (!isset($this->definitions[PsrListenerProviderInterface::class])) {
            $this->set(PsrListenerProviderInterface::class, ListenerProviderInterface::class);
        }
    }

    /**
     * Disable event dispatching while keeping collected events available.
     */
    public function disableEventDispatching(): self
    {
        $dispatcher = $this->get(EventDispatcherInterface::class);
        if ($dispatcher instanceof TestEventDispatcher) {
            $dispatcher->setDispatchEvents(false);
        }

        return $this;
    }

    /**
     * Enable event dispatching.
     */
    public function enableEventDispatching(): self
    {
        $dispatcher = $this->get(EventDispatcherInterface::class);
        if ($dispatcher instanceof TestEventDispatcher) {
            $dispatcher->setDispatchEvents(true);
        }

        return $this;
    }
}
