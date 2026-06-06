<?php

declare(strict_types=1);

namespace Switon\Testing\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Switon\Testing\MockListenerProvider;
use stdClass;

final class MockListenerProviderTest extends TestCase
{
    public function testGetListenersIsEmpty(): void
    {
        $provider = new MockListenerProvider();

        self::assertSame([], $provider->getListeners());
    }

    public function testGetListenersForEventAndWildcardReturnEmptyPrioritizedBuckets(): void
    {
        $provider = new MockListenerProvider();

        self::assertSame([], iterator_to_array($provider->getListenersForEvent(new stdClass())));
        self::assertSame([], iterator_to_array($provider->getListenersForWildcard()));
        self::assertSame(
            $provider->getListenersForEvent(new stdClass()),
            $provider->getListenersForWildcard()
        );
    }

    public function testOnAndRegisterAreNoOps(): void
    {
        $provider = new MockListenerProvider();

        $provider->on('SomeEvent', static function (): void {
        });
        $provider->register(static function (): void {
        });

        self::assertSame([], $provider->getListeners());
    }
}
