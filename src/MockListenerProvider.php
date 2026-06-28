<?php

declare(strict_types=1);

namespace Switon\Testing;

use Switon\Eventing\ListenerProviderInterface;
use Switon\Eventing\PrioritizedListeners;

/**
 * No-op listener provider for tests.
 *
 * Use when code requires `ListenerProviderInterface` but tests do not need registered listeners.
 *
 * Guidance: registration calls are intentionally ignored; every lookup returns an empty listener bucket.
 *
 * @see \Switon\Eventing\ListenerProviderInterface
 * @see \Switon\Testing\Container
 */
class MockListenerProvider implements ListenerProviderInterface
{
    /** @var PrioritizedListeners Shared empty bucket (same pattern as {@see \Switon\Eventing\ListenerProvider}). */
    protected PrioritizedListeners $emptyListeners;

    public function __construct()
    {
        $this->emptyListeners = new PrioritizedListeners();
    }

    public function on(?object $sender, callable $handler, int $priority = 0): void
    {
        // No-op: ignore listener registration
    }

    public function register(string|object $listener): void
    {
        // No-op: ignore listener registration
    }

    public function getListeners(): array
    {
        return [];
    }

    public function getListenersForEvent(object $event): PrioritizedListeners
    {
        return $this->emptyListeners;
    }

    public function getListenersForWildcard(): PrioritizedListeners
    {
        return $this->emptyListeners;
    }

    public function getListenersForSender(object $event): PrioritizedListeners
    {
        return $this->emptyListeners;
    }

    public function getObservabilityListenerRegistry(): array
    {
        return [];
    }

    public function getBusinessListenerRegistry(): array
    {
        return [];
    }
}
