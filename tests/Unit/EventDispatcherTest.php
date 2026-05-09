<?php

declare(strict_types=1);

namespace Switon\Testing\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Switon\Eventing\EventDispatcherContext;
use Switon\Eventing\EventDispatcherInterface;
use Switon\Testing\EventDispatcher;

class EventDispatcherTest extends TestCase
{
    public function testDispatchCollectsEventAndForwardsByDefault(): void
    {
        $delegate = new DelegateDispatcherStub();
        $dispatcher = new EventDispatcher($delegate);
        $event = new \stdClass();
        $returnedEvent = new \stdClass();
        $delegate->returnEvent = $returnedEvent;

        $returned = $dispatcher->dispatch($event);

        $this->assertSame($returnedEvent, $returned);
        $this->assertSame([$event], $dispatcher->dispatchedEvents);
        $this->assertSame([$event], $delegate->dispatchedEvents);
    }

    public function testDispatchCanBeCollectedWithoutForwarding(): void
    {
        $delegate = new DelegateDispatcherStub();
        $dispatcher = new EventDispatcher($delegate);
        $dispatcher->setDispatchEvents(false);
        $event = new \stdClass();

        $dispatcher->dispatch($event);

        $this->assertSame([$event], $dispatcher->dispatchedEvents);
        $this->assertSame([], $delegate->dispatchedEvents);
        $this->assertFalse($dispatcher->isDispatchingEvents());
    }

    public function testHelperMethodsExposeRecordedEvents(): void
    {
        $delegate = new DelegateDispatcherStub();
        $dispatcher = new EventDispatcher($delegate);
        $eventA = new EventA();
        $eventB = new EventB();

        $dispatcher->dispatch($eventA);
        $dispatcher->dispatch($eventB);

        $this->assertTrue($dispatcher->hasDispatched(EventA::class));
        $this->assertFalse($dispatcher->hasDispatched(\RuntimeException::class));
        $this->assertSame($eventB, $dispatcher->getLastEvent());
        $this->assertSame([$eventA], $dispatcher->getEventsByClass(EventA::class));

        $dispatcher->clear();
        $this->assertSame([], $dispatcher->dispatchedEvents);
        $this->assertNull($dispatcher->getLastEvent());
    }

    public function testGetContextAndDelegateAreForwarded(): void
    {
        $delegate = new DelegateDispatcherStub();
        $dispatcher = new EventDispatcher($delegate);

        $this->assertSame($delegate, $dispatcher->getDelegate());
        $this->assertSame($delegate->context, $dispatcher->getContext());
    }
}

class DelegateDispatcherStub implements EventDispatcherInterface
{
    /** @var object[] */
    public array $dispatchedEvents = [];
    public EventDispatcherContext $context;
    public ?object $returnEvent = null;

    public function __construct()
    {
        $this->context = new EventDispatcherContext();
    }

    public function dispatch(object $event): object
    {
        $this->dispatchedEvents[] = $event;
        return $this->returnEvent ?? $event;
    }

    public function getContext(): EventDispatcherContext
    {
        return $this->context;
    }
}

class EventA
{
}

class EventB
{
}
