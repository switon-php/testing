<?php

declare(strict_types=1);

namespace Switon\Testing\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Switon\Core\ContextAware;
use Switon\Testing\InMemoryContextManager;

class InMemoryContextManagerTest extends TestCase
{
    public function testGetContextReturnsSameInstanceForSameObject(): void
    {
        $manager = new InMemoryContextManager();
        $component = new InMemoryContextManagerTestComponent();

        $first = $manager->getContext($component);
        $second = $manager->getContext($component);

        $this->assertSame($first, $second);
        $this->assertInstanceOf(InMemoryContextManagerTestContext::class, $first);
    }

    public function testGetContextReturnsDifferentInstancesForDifferentObjects(): void
    {
        $manager = new InMemoryContextManager();

        $first = $manager->getContext(new InMemoryContextManagerTestComponent());
        $second = $manager->getContext(new InMemoryContextManagerTestComponent());

        $this->assertNotSame($first, $second);
        $this->assertInstanceOf(InMemoryContextManagerTestContext::class, $first);
        $this->assertInstanceOf(InMemoryContextManagerTestContext::class, $second);
    }
}

final class InMemoryContextManagerTestComponent implements ContextAware
{
    public function getContext(): InMemoryContextManagerTestContext
    {
        return new InMemoryContextManagerTestContext();
    }
}

final class InMemoryContextManagerTestContext
{
}
