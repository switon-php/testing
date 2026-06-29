<?php

declare(strict_types=1);

namespace Switon\Testing;

use Switon\Core\ClockInterface;

use function round;
use function time;

/**
 * Mutable clock for deterministic time control in tests.
 *
 * Use when code depends on `ClockInterface` and tests must advance or set time explicitly.
 *
 * Road-signs:
 * - time()/microtime()/now()/elapsed() for production-style reads
 * - advance() and setTime() for test control
 *
 * @see \Switon\Core\ClockInterface
 * @see \Switon\Testing\Container
 */
class MockClock implements ClockInterface
{
    /**
     * Current mock time in seconds with microseconds (float).
     */
    protected float $currentTime;

    /**
     * @param float|int $initialTime Initial unix timestamp; uses current system time when `0`
     */
    public function __construct(float|int $initialTime = 0)
    {
        $this->currentTime = $initialTime ?: (float)time();
    }

    /**
     * {@inheritDoc}
     */
    public function time(): int
    {
        return (int)$this->currentTime;
    }

    /**
     * {@inheritDoc}
     */
    public function microtime(): float
    {
        return $this->currentTime;
    }

    /**
     * {@inheritDoc}
     */
    public function now(int $precision = 3): float
    {
        return round($this->microtime(), $precision);
    }

    /**
     * {@inheritDoc}
     */
    public function elapsed(float $startedAt, int $precision = 3): float
    {
        return round($this->microtime() - $startedAt, $precision);
    }

    /**
     * Advances current time by the given number of seconds.
     */
    public function advance(float $seconds): static
    {
        $this->currentTime += $seconds;
        return $this;
    }

    /**
     * Sets current time to a specific timestamp.
     */
    public function setTime(float|int $timestamp): static
    {
        $this->currentTime = (float)$timestamp;
        return $this;
    }

    /**
     * {@inheritDoc}
     *
     * Advances virtual time without blocking.
     */
    public function sleep(float $seconds): void
    {
        $this->advance($seconds);
    }
}
