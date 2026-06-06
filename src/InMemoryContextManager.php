<?php

declare(strict_types=1);

namespace Switon\Testing;

use ReflectionClass;
use ReflectionNamedType;
use Switon\Core\ContextAware;
use Switon\Core\ContextManagerInterface;
use stdClass;

/**
 * In-memory context manager for tests.
 *
 * Use when tests need stable per-object context storage without depending on the runtime context package.
 *
 * Guidance: context instances are cached per object identity and cleared by resetContexts().
 *
 * @see \Switon\Core\ContextManagerInterface
 * @see \Switon\Testing\Container
 */
final class InMemoryContextManager implements ContextManagerInterface
{
    /** @var array<int, object> */
    protected array $contexts = [];

    public function getContext(ContextAware $object, int $cid = 0): mixed
    {
        $objectId = spl_object_id($object);

        if (isset($this->contexts[$objectId])) {
            return $this->contexts[$objectId];
        }

        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod('getContext');
        $returnType = $method->getReturnType();

        if (!$returnType instanceof ReflectionNamedType || $returnType->isBuiltin()) {
            return $this->contexts[$objectId] = new stdClass();
        }

        $contextClass = $returnType->getName();

        return $this->contexts[$objectId] = new $contextClass();
    }

    /**
     * Clears per-object context cache (mirrors {@see \Switon\Context\ContextManager::resetContexts()}).
     *
     * Used by long-lived server adapters after each request in tests.
     */
    public function resetContexts(): void
    {
        $this->contexts = [];
    }
}
