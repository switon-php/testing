<?php

declare(strict_types=1);

namespace Switon\Testing;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Switon\Core\App;
use Switon\Core\InjectorInterface;
use Switon\Core\MakerInterface;
use Switon\Testing\Container\Container;

/**
 * Base PHPUnit test case with a preconfigured Switon test container.
 *
 * Use when package tests need autowiring plus ready-to-use defaults from \Switon\Testing\Container\Container.
 *
 * @see \Switon\Testing\Container\Container
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
     * @return self For method chaining
     */
    protected function enableEventDispatching(): self
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
     * @return self For method chaining
     */
    protected function disableEventDispatching(): self
    {
        $this->container->disableEventDispatching();
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function make(string $name, array $parameters = []): mixed
    {
        return $this->container->make($name, $parameters);
    }
}
