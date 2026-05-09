<?php

declare(strict_types=1);

namespace Switon\Testing;

use Switon\Eventing\EventDispatcherContext;
use Switon\Eventing\EventDispatcherInterface;

/**
 * No-op event dispatcher that only records dispatched events.
 *
 * Use when tests must assert emitted events and intentionally skip listener execution.
 *
 * @see \Switon\Testing\EventDispatcher
 * @see \Switon\Testing\Container\Container
 */
class MockEventDispatcher implements EventDispatcherInterface
{
    protected EventDispatcherContext $context;
    /**
     * @var object[] Dispatched events
     */
    public array $dispatchedEvents = [];

    public function dispatch(object $event): object
    {
        $this->dispatchedEvents[] = $event;
        return $event;
    }

    /**
     * {@inheritDoc}
     */
    public function getContext(): EventDispatcherContext
    {
        return $this->context ??= new EventDispatcherContext();
    }

    /**
     * Clears all collected events.
     */
    public function clear(): void
    {
        $this->dispatchedEvents = [];
    }

    /**
     * Whether at least one event of the given class was dispatched.
     */
    public function hasDispatched(string $eventClass): bool
    {
        foreach ($this->dispatchedEvents as $event) {
            if ($event instanceof $eventClass) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns the last dispatched event.
     */
    public function getLastEvent(): ?object
    {
        return $this->dispatchedEvents[count($this->dispatchedEvents) - 1] ?? null;
    }

    /**
     * Returns all dispatched events matching the given class.
     *
     * @return object[]
     */
    public function getEventsByClass(string $eventClass): array
    {
        $events = [];
        foreach ($this->dispatchedEvents as $event) {
            if ($event instanceof $eventClass) {
                $events[] = $event;
            }
        }
        return $events;
    }
}
