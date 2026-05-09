<?php

declare(strict_types=1);

namespace Switon\Testing;

use Switon\Eventing\EventDispatcherContext;
use Switon\Eventing\EventDispatcherInterface;

/**
 * Test event dispatcher that records events and delegates dispatching.
 *
 * Use when tests must assert emitted events while optionally preserving real listener execution.
 *
 * @see \Switon\Testing\Container\Container
 * @see \Switon\Testing\TestCase
 * @see \Switon\Testing\MockEventDispatcher
 */
class EventDispatcher implements EventDispatcherInterface
{
    protected EventDispatcherInterface $delegate;

    /**
     * @var object[] Collected dispatched events
     */
    public array $dispatchedEvents = [];

    /**
     * Whether to dispatch events to listeners.
     *
     * If false, events are only collected but not dispatched.
     */
    protected bool $dispatchEvents = true;

    /**
     * @param EventDispatcherInterface $delegate Event dispatcher to wrap
     */
    public function __construct(EventDispatcherInterface $delegate)
    {
        $this->delegate = $delegate;
    }

    /**
     * {@inheritDoc}
     */
    public function dispatch(object $event): object
    {
        // Always collect events for verification
        $this->dispatchedEvents[] = $event;

        // Optionally dispatch to real implementation
        if ($this->dispatchEvents) {
            return $this->delegate->dispatch($event);
        }

        // Return the event (PSR-14 allows returning the event object)
        return $event;
    }

    /**
     * {@inheritDoc}
     */
    public function getContext(): EventDispatcherContext
    {
        // Always forward context access to preserve context tracking
        return $this->delegate->getContext();
    }

    /**
     * Returns the wrapped event dispatcher.
     */
    public function getDelegate(): EventDispatcherInterface
    {
        return $this->delegate;
    }

    /**
     * Enables or disables listener dispatching.
     */
    public function setDispatchEvents(bool $enable): self
    {
        $this->dispatchEvents = $enable;
        return $this;
    }

    /**
     * Whether listener dispatching is enabled.
     */
    public function isDispatchingEvents(): bool
    {
        return $this->dispatchEvents;
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
