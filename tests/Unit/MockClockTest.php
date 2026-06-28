<?php

declare(strict_types=1);

namespace Switon\Testing\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Switon\Testing\MockClock;

use function time;

class MockClockTest extends TestCase
{
    public function testConstructorWithExplicitTimeSetsClockState(): void
    {
        $clock = new MockClock(1700000000.75);

        $this->assertSame(1700000000, $clock->time());
        $this->assertSame(1700000000.75, $clock->microtime());
    }

    public function testAdvanceMovesClockForward(): void
    {
        $clock = new MockClock(1000.0);

        $clock->advance(1.25);

        $this->assertSame(1001, $clock->time());
        $this->assertSame(1001.25, $clock->microtime());
    }

    public function testElapsedReturnsRoundedDeltaSinceStartedAt(): void
    {
        $clock = new MockClock(1000.0);
        $startedAt = $clock->now();

        $clock->advance(1.2345);

        $this->assertSame(1.235, $clock->elapsed($startedAt));
        $this->assertSame(1.23, $clock->elapsed($startedAt, 2));
    }

    public function testNowReturnsRoundedCurrentTimestamp(): void
    {
        $clock = new MockClock(1000.1234);

        $this->assertSame(1000.123, $clock->now());
        $this->assertSame(1000.12, $clock->now(2));
    }

    public function testSetTimeOverridesCurrentClockValue(): void
    {
        $clock = new MockClock(1000.0);
        $clock->advance(10);

        $clock->setTime(42.5);

        $this->assertSame(42, $clock->time());
        $this->assertSame(42.5, $clock->microtime());
    }

    public function testConstructorWithoutArgumentStartsNearSystemTime(): void
    {
        $before = time();
        $clock = new MockClock();
        $after = time();

        $this->assertGreaterThanOrEqual($before, $clock->time());
        $this->assertLessThanOrEqual($after + 1, $clock->time());
    }
}
