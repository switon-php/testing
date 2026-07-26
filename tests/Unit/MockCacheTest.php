<?php

declare(strict_types=1);

namespace Switon\Testing\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Switon\Testing\Mock\MockCache;

class MockCacheTest extends TestCase
{
    public function testGetReturnsDefaultWhenKeyMissing(): void
    {
        $cache = new MockCache();

        self::assertSame('fallback', $cache->get('nope', 'fallback'));
    }

    public function testSetGetHasDeleteAndClear(): void
    {
        $cache = new MockCache();

        self::assertTrue($cache->set('k', 'v'));
        self::assertTrue($cache->has('k'));
        self::assertSame('v', $cache->get('k'));

        self::assertTrue($cache->delete('k'));
        self::assertFalse($cache->has('k'));

        $cache->set('a', 1);
        self::assertTrue($cache->clear());
        self::assertFalse($cache->has('a'));
    }

    public function testGetMultipleAndSetMultiple(): void
    {
        $cache = new MockCache();
        $cache->setMultiple(['x' => 1, 'y' => 2]);

        $out = $cache->getMultiple(['x', 'y', 'z'], 'none');

        self::assertSame(1, $out['x']);
        self::assertSame(2, $out['y']);
        self::assertSame('none', $out['z']);
    }

    public function testDeleteMultiple(): void
    {
        $cache = new MockCache();
        $cache->set('a', 1);
        $cache->set('b', 2);

        self::assertTrue($cache->deleteMultiple(['a', 'b']));

        self::assertFalse($cache->has('a'));
        self::assertFalse($cache->has('b'));
    }
}
