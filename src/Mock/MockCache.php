<?php

declare(strict_types=1);

namespace Switon\Testing\Mock;

use DateInterval;
use Psr\SimpleCache\CacheInterface;

/**
 * In-memory PSR-16 cache implementation for tests.
 *
 * Use when code depends on `CacheInterface` and tests do not require TTL expiration behavior.
 *
 * @see \Psr\SimpleCache\CacheInterface
 * @see \Switon\Testing\Container\Container
 */
class MockCache implements CacheInterface
{
    /** @var array<string, mixed> In-memory cache storage */
    protected array $storage = [];

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->storage[$key] ?? $default;
    }

    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        $this->storage[$key] = $value;
        return true;
    }

    public function delete(string $key): bool
    {
        if (isset($this->storage[$key])) {
            unset($this->storage[$key]);
        }
        return true;
    }

    public function clear(): bool
    {
        $this->storage = [];
        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }
        return $result;
    }

    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }
        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }
        return true;
    }

    public function has(string $key): bool
    {
        return isset($this->storage[$key]);
    }
}
