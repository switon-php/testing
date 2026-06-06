<?php

declare(strict_types=1);

namespace Switon\Testing\Tests\Unit;

use Switon\Core\App;
use Switon\Core\Attribute\Autowired;
use Switon\Core\InjectorInterface;
use Switon\Eventing\EventDispatcherInterface;
use Switon\Testing\MockLogger;
use Switon\Testing\TestCase as SwitonTestCase;

final class TestCaseBehaviorTest extends SwitonTestCase
{
    #[Autowired] protected MockLogger $logger;

    protected bool $containerConfigured = false;

    protected function setUpContainer(): void
    {
        $this->containerConfigured = true;
        $this->container->set(TestCaseMarkerService::class, new TestCaseMarkerService('configured'));
    }

    public function testSetUpInjectsContainerAndAutowiredProperties(): void
    {
        self::assertTrue($this->containerConfigured);
        self::assertInstanceOf(InjectorInterface::class, $this->injector);
        self::assertSame($this->container, App::getContainer());
        self::assertInstanceOf(MockLogger::class, $this->logger);
        self::assertSame('configured', $this->make(TestCaseMarkerService::class)->value);
    }

    public function testMakePassesConstructorParametersForUnregisteredClass(): void
    {
        $made = $this->make(TestCaseMakeWithParametersFixture::class, ['token' => 'from-parameters']);

        self::assertSame('from-parameters', $made->token);
    }

    public function testEnableAndDisableEventDispatchingToggleWrappedDispatcher(): void
    {
        $dispatcher = $this->container->get(EventDispatcherInterface::class);

        self::assertTrue($dispatcher->isDispatchingEvents());

        $this->disableEventDispatching();
        self::assertFalse($dispatcher->isDispatchingEvents());

        $this->enableEventDispatching();
        self::assertTrue($dispatcher->isDispatchingEvents());
    }
}

final class TestCaseMarkerService
{
    public function __construct(public string $value)
    {
    }
}

final class TestCaseMakeWithParametersFixture
{
    public function __construct(public string $token = 'default')
    {
    }
}
