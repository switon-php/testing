<?php

declare(strict_types=1);

namespace Switon\Testing\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Switon\Core\ContextAware;
use Switon\Testing\InMemoryContextManager;
use stdClass;

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

    public function testGetContextUsesStdClassWhenReturnTypeIsUnion(): void
    {
        $manager = new InMemoryContextManager();
        $component = new InMemoryContextManagerUnionReturnComponent();

        $ctx = $manager->getContext($component);

        $this->assertInstanceOf(stdClass::class, $ctx);
        $this->assertSame($ctx, $manager->getContext($component));
    }

    public function testResetContextsClearsCachedContexts(): void
    {
        $manager = new InMemoryContextManager();
        $component = new InMemoryContextManagerTestComponent();

        $original = $manager->getContext($component);
        $manager->resetContexts();
        $fresh = $manager->getContext($component);

        $this->assertNotSame($original, $fresh);
        $this->assertInstanceOf(InMemoryContextManagerTestContext::class, $fresh);
    }
}

class InMemoryContextManagerTestComponent implements ContextAware
{
    public function getContext(): InMemoryContextManagerTestContext
    {
        return new InMemoryContextManagerTestContext();
    }
}

class InMemoryContextManagerTestContext
{
}

class InMemoryContextManagerUnionReturnComponent implements ContextAware
{
    public function getContext(): InMemoryContextManagerTestContext|stdClass
    {
        return new InMemoryContextManagerTestContext();
    }
}
