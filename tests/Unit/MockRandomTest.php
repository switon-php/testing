<?php

declare(strict_types=1);

namespace Switon\Testing\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Switon\Testing\MockRandom;
use function ord;
use function preg_match;
use function strlen;

class MockRandomTest extends TestCase
{
    public function testBytesUsesConfiguredSequenceAndWrapsAround(): void
    {
        $random = new MockRandom([65, 66]); // A, B

        $bytes = $random->bytes(5);

        $this->assertSame(5, strlen($bytes));
        $this->assertSame([65, 66, 65, 66, 65], [
            ord($bytes[0]),
            ord($bytes[1]),
            ord($bytes[2]),
            ord($bytes[3]),
            ord($bytes[4]),
        ]);
    }

    public function testIntReturnsValueWithinRange(): void
    {
        $random = new MockRandom(null, 1234);

        for ($i = 0; $i < 20; $i++) {
            $value = $random->int(10, 20);
            $this->assertGreaterThanOrEqual(10, $value);
            $this->assertLessThanOrEqual(20, $value);
        }
    }

    public function testUuidMatchesRfc4122V4Shape(): void
    {
        $random = new MockRandom(null, 1234);

        $uuid = $random->uuid();

        $this->assertSame(1, preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid));
    }

    public function testCharsHonorsBaseCharacterSet(): void
    {
        $random = new MockRandom(null, 1234);

        $base10 = $random->chars(32, 10);
        $base36 = $random->chars(32, 36);
        $base62 = $random->chars(32, 62);

        $this->assertSame(1, preg_match('/^[0-9]{32}$/', $base10));
        $this->assertSame(1, preg_match('/^[0-9a-z]{32}$/', $base36));
        $this->assertSame(1, preg_match('/^[0-9a-zA-Z]{32}$/', $base62));
    }

    public function testSeededInstancesDoNotShareGlobalRandomState(): void
    {
        $first = new MockRandom(null, 1234);
        $expected1 = $first->bytes(8);
        $expected2 = $first->bytes(8);

        $second = new MockRandom(null, 1234);
        $this->assertSame($expected1, $second->bytes(8));
        $this->assertSame($expected2, $second->bytes(8));
    }
}
