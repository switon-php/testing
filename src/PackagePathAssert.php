<?php

declare(strict_types=1);

namespace Switon\Testing;

use PHPUnit\Framework\Assert;
use ReflectionClass;

/**
 * Compare resolved filesystem paths against package layout for both monorepo and split repos.
 */
final class PackagePathAssert
{
    /**
     * Assert a resolved path equals `{package root}/{relative}` where package root is derived from a class under `src/`.
     *
     * @param class-string $providerClass Typically the package {@see \Switon\Core\ServiceProviderInterface} implementation.
     * @param non-empty-string $relativeFromPackageRoot Use forward slashes, e.g. `resources`, `src/Templates`.
     */
    public static function assertSameAsPackagePath(string $providerClass, string $resolvedPath, string $relativeFromPackageRoot): void
    {
        $base = dirname((new ReflectionClass($providerClass))->getFileName(), 2);
        $expected = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeFromPackageRoot);
        $expectedPath = realpath($expected) ?: $expected;
        $actualPath = realpath($resolvedPath) ?: $resolvedPath;
        Assert::assertSame($expectedPath, $actualPath);
    }
}
