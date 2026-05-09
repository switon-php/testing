<?php

declare(strict_types=1);

namespace Switon\Testing\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Switon\Testing\MockEventDispatcher;

class MockEventDispatcherTest extends TestCase
{
    public function testDispatchCollectsEventsAndReturnsSameObject(): void
    {
        $dispatcher = new MockEventDispatcher();
        $event = new \stdClass();

        $returned = $dispatcher->dispatch($event);

        $this->assertSame($event, $returned);
        $this->assertSame([$event], $dispatcher->dispatchedEvents);
    }

    public function testHelperMethodsWorkWithCollectedEvents(): void
    {
        $dispatcher = new MockEventDispatcher();
        $eventA = new MockEventA();
        $eventB = new MockEventB();

        $dispatcher->dispatch($eventA);
        $dispatcher->dispatch($eventB);

        $this->assertTrue($dispatcher->hasDispatched(MockEventA::class));
        $this->assertFalse($dispatcher->hasDispatched(\RuntimeException::class));
        $this->assertSame($eventB, $dispatcher->getLastEvent());
        $this->assertSame([$eventA], $dispatcher->getEventsByClass(MockEventA::class));
    }

    public function testClearResetsCollectedEvents(): void
    {
        $dispatcher = new MockEventDispatcher();
        $dispatcher->dispatch(new \stdClass());

        $dispatcher->clear();

        $this->assertSame([], $dispatcher->dispatchedEvents);
        $this->assertNull($dispatcher->getLastEvent());
    }

    public function testGetContextReturnsSameInstanceAcrossCalls(): void
    {
        $dispatcher = new MockEventDispatcher();

        $first = $dispatcher->getContext();
        $second = $dispatcher->getContext();

        $this->assertSame($first, $second);
    }
}

class MockEventA
{
}

class MockEventB
{
}
