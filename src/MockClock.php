<?php

declare(strict_types=1);

namespace Switon\Testing;

use Switon\Core\ClockInterface;
use function time;

/**
 * Mutable clock for deterministic time control in tests.
 *
 * Use when code depends on `ClockInterface` and tests must advance or set time explicitly.
 *
 * @see \Switon\Core\ClockInterface
 * @see \Switon\Testing\Container\Container
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
     * Advances current time by the given number of seconds.
     */
    public function advance(float $seconds): self
    {
        $this->currentTime += $seconds;
        return $this;
    }

    /**
     * Sets current time to a specific timestamp.
     */
    public function setTime(float|int $timestamp): self
    {
        $this->currentTime = (float)$timestamp;
        return $this;
    }
}
