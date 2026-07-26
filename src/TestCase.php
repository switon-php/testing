<?php

declare(strict_types=1);

namespace Switon\Testing;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Switon\Core\App;
use Switon\Core\InjectorInterface;
use Switon\Core\MakerInterface;

/**
 * Base PHPUnit test case with a preconfigured Switon test container.
 *
 * Use when package tests need autowiring plus ready-to-use defaults from \Switon\Testing\Container.
 *
 * Road-signs:
 * - setUpContainer() for per-test bindings
 * - make() for autowired object creation
 * - enableEventDispatching()/disableEventDispatching()
 *
 * @see \Switon\Testing\Container
 * @see \Switon\Testing\EventDispatcher
 */
abstract class TestCase extends BaseTestCase implements MakerInterface
{
    protected Container $container;
    protected InjectorInterface $injector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new Container();

        // Set container in App for make() function
        App::setContainer($this->container);

        $this->injector = $this->container->get(InjectorInterface::class);

        // Allow subclasses to configure container dependencies
        $this->setUpContainer();

        // Automatically inject #[Autowired] properties
        $this->injector->inject($this);
    }

    /**
     * Configures container dependencies before autowiring.
     *
     * Override in subclasses to bind test doubles or override framework services.
     */
    protected function setUpContainer(): void
    {
        // Default: no additional configuration needed
        // Container is already initialized with default services
    }

    /**
     * Enable event dispatching.
     *
     * This is the default behavior - events are both collected and dispatched to listeners.
     *
     * @return static For method chaining
     */
    protected function enableEventDispatching(): static
    {
        $this->container->enableEventDispatching();
        return $this;
    }

    /**
     * Disable event dispatching.
     *
     * When disabled, events are collected but not dispatched to listeners.
     * This is equivalent to a no-op dispatcher, but still allows event verification.
     *
     * @return static For method chaining
     */
    protected function disableEventDispatching(): static
    {
        $this->container->disableEventDispatching();
        return $this;
    }

    /**
     * {@inheritDoc}
     *
     * @template T of object
     *
     * @param class-string<T> $name
     *
     * @phpstan-return T
     */
    public function make(string $name, array $parameters = []): mixed
    {
        return $this->container->make($name, $parameters);
    }
}
