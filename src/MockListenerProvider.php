<?php

declare(strict_types=1);

namespace Switon\Testing;

use Switon\Eventing\ListenerProviderInterface;

/**
 * No-op listener provider for tests.
 *
 * Use when code requires `ListenerProviderInterface` but tests do not need registered listeners.
 *
 * @see \Switon\Eventing\ListenerProviderInterface
 * @see \Switon\Testing\Container\Container
 */
class MockListenerProvider implements ListenerProviderInterface
{
    public function on(string $event, callable $handler, int $priority = 0): void
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

    public function getListenersForEvent(object $event): iterable
    {
        // Return empty iterable: no listeners registered
        return [];
    }

    public function getListenersForWildcard(): iterable
    {
        // Return empty iterable: no wildcard listeners registered
        return [];
    }
}
