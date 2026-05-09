<?php

declare(strict_types=1);

namespace Switon\Testing\Container;

use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface as PsrListenerProviderInterface;
use Psr\Log\LoggerInterface;
use Switon\Core\App;
use Switon\Core\ClassScanner;
use Switon\Core\Clock;
use Switon\Core\ConsoleInterface;
use Switon\Core\ContextManagerInterface;
use Switon\Core\AppInterface;
use Switon\Core\ClassScannerInterface;
use Switon\Core\ClockInterface;
use Switon\Core\FilesystemInterface;
use Switon\Core\PathAliasInterface;
use Switon\Core\RandomInterface;
use Switon\Core\SceneManagerInterface;
use Switon\Core\ServiceProviderInterface;
use Switon\Core\Filesystem;
use Switon\Core\PathAlias;
use Switon\Core\SceneManager;
use Switon\ComposerExtra\ComposerExtraInterface;
use Switon\Di\Container as DiContainer;
use Switon\Di\ServiceProvider as DiServiceProvider;
use Switon\Eventing\EventDispatcher;
use Switon\Eventing\EventDispatcherInterface;
use Switon\Eventing\ListenerProvider;
use Switon\Eventing\ListenerProviderInterface;
use Switon\Core\ResourceAliasRegistrar;
use Switon\Testing\ComposerExtra;
use Switon\Testing\EventDispatcher as TestEventDispatcher;
use Switon\Testing\InMemoryContextManager;
use Switon\Testing\Mock\MockConsole;
use Switon\Testing\MockLogger;
use Switon\Testing\MockRandom;

/**
 * Preconfigured DI container for package and component tests.
 *
 * Use when tests need framework-like wiring with built-in defaults
 * (mock logger/console/random, test event dispatcher, in-memory context storage, real filesystem/class scanning/clock/app bindings).
 * Additional doubles such as `MockClock`, `MockListenerProvider`, and `MockCache`
 * are available for manual binding in test setup; they are not preconfigured here by default.
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

    public function __construct(array $definitions = [])
    {
        parent::__construct($definitions);

        // Ensure DI core services (InjectorInterface, InvokerInterface) are
        // registered in the same way as in application runtime.
        $diProvider = new DiServiceProvider();
        $diProvider->register($this);
        $diProvider->boot();

        App::setContainer($this);

        // Register container interfaces (if user didn't provide in definitions)
        // Note: autoRegisterSelf may have registered them, but we check definitions for consistency
        if (!isset($this->definitions[\Switon\Core\ContainerInterface::class])) {
            $this->set(\Switon\Core\ContainerInterface::class, $this);
        }
        if (!isset($this->definitions[ContainerInterface::class])) {
            $this->set(ContainerInterface::class, $this);
        }

        // Pre-configure Logger (if user didn't provide in definitions)
        // Use MockLogger directly - even if verification isn't needed, collected data can be ignored
        if (!isset($this->definitions[LoggerInterface::class])) {
            $this->set(LoggerInterface::class, new MockLogger());
        }

        // Pre-configure PathAliasInterface (if user didn't provide in definitions)
        // Set up common aliases needed by tests (@view, @public, @runtime)
        if (!isset($this->definitions[PathAliasInterface::class])) {
            $pathAlias = new PathAlias();
            $pathAlias->set('@view', sys_get_temp_dir() . '/switon_test_view_' . uniqid());
            $pathAlias->set('@public', sys_get_temp_dir() . '/switon_test_public_' . uniqid());
            $pathAlias->set('@runtime', sys_get_temp_dir() . '/switon_test_runtime_' . uniqid());
            $this->set(PathAlias::class, $pathAlias);
            $this->set(PathAliasInterface::class, $pathAlias);
        }

        // Pre-configure ConsoleInterface (if user didn't provide in definitions)
        // Use MockConsole for testing CLI commands without actual console output
        // Register as singleton so all commands share the same instance
        if (!isset($this->definitions[ConsoleInterface::class])) {
            $mockConsole = new MockConsole();
            $this->set(ConsoleInterface::class, $mockConsole);
            $this->set(MockConsole::class, $mockConsole);
        }

        // Pre-configure EventDispatcher and ListenerProvider (if user didn't provide in definitions)
        // Use real implementations to support event-driven functionality
        // Handle circular dependencies through lazy loading
        if (!isset($this->definitions[PsrEventDispatcherInterface::class]) &&
            !isset($this->definitions[EventDispatcherInterface::class]) &&
            !isset($this->definitions[PsrListenerProviderInterface::class])) {
            $this->setupEventSystem();
        } else {
            // If any event-related service is provided, register only the missing ones
            // Register PSR-14 interfaces for compatibility
            if (!isset($this->definitions[PsrEventDispatcherInterface::class]) &&
                !isset($this->definitions[EventDispatcherInterface::class])) {
                $this->set(PsrEventDispatcherInterface::class, EventDispatcherInterface::class);
            }
            if (!isset($this->definitions[PsrListenerProviderInterface::class]) &&
                !isset($this->definitions[ListenerProviderInterface::class])) {
                $this->set(PsrListenerProviderInterface::class, ListenerProviderInterface::class);
            }
        }

        // Pre-configure ContextManagerInterface (if user didn't provide in definitions)
        // Use a lightweight in-memory implementation to keep testing self-contained
        if (!isset($this->definitions[ContextManagerInterface::class])) {
            $this->set(ContextManagerInterface::class, InMemoryContextManager::class);
        }

        // Pre-configure App (if user didn't provide in definitions)
        // Use default test configuration: id='test-app', env='test', debug=true
        // Class name can be inferred from service ID, so 'class' key is not needed
        if (!isset($this->definitions[App::class])) {
            $this->set(App::class, [
                'id' => 'test-app',
                'env' => 'test',
                'debug' => true,
            ]);
        }
        // Auto-register (register-only) Switon service providers from composer extra.
        // This makes test containers behave closer to real apps, without requiring each package's
        // tests/TestCase to manually register dependent providers.
        //
        // NOTE: We intentionally call only register() here (not boot()), to avoid side effects and
        // to keep unit tests predictable.
        $this->registerProvidersFromComposerExtra();
    }

    protected function registerProvidersFromComposerExtra(): void
    {
        if ($this->providersRegistered) {
            return;
        }

        // Opt-out for debugging very isolated unit tests.
        if (getenv('SWITON_TESTS_DISABLE_PROVIDER_AUTO_REGISTER') !== false) {
            return;
        }

        // Bind ComposerExtra in the container so other code (e.g. kernel ServiceBootstrapper) can reuse it.
        // Use testing-aware loader to support split package CI where vendor cache may be absent.
        if (!isset($this->definitions[ComposerExtraInterface::class])) {
            $extra = new ComposerExtra();
            $this->set(ComposerExtraInterface::class, $extra);
        }

        $composerExtra = $this->get(ComposerExtraInterface::class);
        if (!$composerExtra instanceof ComposerExtraInterface) {
            return;
        }

        $providerClasses = $composerExtra->getClasses('switon.providers');
        $pathAlias = $this->get(PathAliasInterface::class);
        $resourceAliasRegistrar = $this->make(ResourceAliasRegistrar::class);

        foreach ($providerClasses as $providerClass) {
            // The test container already pre-configures some core test doubles (PathAlias with @view/@public/@runtime,
            // ConsoleInterface => MockConsole, etc.). The Core provider also owns broader bootstrap wiring that would
            // replace these defaults, so it remains opt-out here.
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

        // Some tests intentionally override ContainerInterface with a mock using $container->set(...).
        // If ContainerInterface was resolved during provider registration, Di\Container::set() would reject
        // overriding it (ServiceAlreadyResolvedException). Clearing these cached instances keeps tests flexible.
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
        $ids = [
            ConsoleInterface::class,
            ContextManagerInterface::class,
        ];

        $preserved = [];
        foreach ($ids as $id) {
            if (array_key_exists($id, $this->definitions)) {
                $preserved[$id] = $this->definitions[$id];
            }
        }

        return $preserved;
    }

    /**
     * Restore test-owned bindings after a provider mutates them.
     *
     * @param array<string, mixed> $definitions
     */
    protected function restorePreservedDefinitions(array $definitions): void
    {
        foreach ($definitions as $id => $definition) {
            $this->definitions[$id] = $definition;
            unset($this->instances[$id]);
        }
    }

    /**
     * Disable event dispatching.
     *
     * When disabled, events are collected but not dispatched to listeners.
     * This is equivalent to a no-op dispatcher, but still allows event verification.
     *
     * @return self For method chaining
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
     *
     * This is the default behavior - events are both collected and dispatched to listeners.
     *
     * @return self For method chaining
     */
    public function enableEventDispatching(): self
    {
        $dispatcher = $this->get(EventDispatcherInterface::class);
        if ($dispatcher instanceof TestEventDispatcher) {
            $dispatcher->setDispatchEvents(true);
        }
        return $this;
    }

    /**
     * Sets up the event system with real EventDispatcher and ListenerProvider.
     *
     * Handles circular dependencies by using lazy loading:
     * - EventDispatcher depends on ListenerProvider (via Lazy)
     * - ListenerProvider depends on EventDispatcher (via Lazy)
     * - ContextManager depends on EventDispatcher (via Lazy)
     *
     * The EventDispatcher is wrapped with TestEventDispatcher to provide
     * event collection and toggle functionality for testing.
     */
    protected function setupEventSystem(): void
    {
        // Register real EventDispatcher class (will be wrapped later)
        $this->set(EventDispatcher::class, EventDispatcher::class);

        // Register PSR-14 interface mappings for compatibility
        $this->set(PsrEventDispatcherInterface::class, EventDispatcherInterface::class);
        $this->set(PsrListenerProviderInterface::class, ListenerProviderInterface::class);
        $this->set(EventDispatcherInterface::class, function () {
            $realDispatcher = $this->make(EventDispatcher::class);
            return new TestEventDispatcher($realDispatcher);
        });
    }
}
