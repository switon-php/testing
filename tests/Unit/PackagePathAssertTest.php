<?php

declare(strict_types=1);

namespace Switon\Testing\Tests\Unit;

use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use Switon\Testing\PackagePathAssert;
use ReflectionClass;

class PackagePathAssertTest extends TestCase
{
    public function testAssertSameAsPackagePathPassesWhenResolvedMatchesRelative(): void
    {
        $base = dirname((new ReflectionClass(\Switon\Testing\MockClock::class))->getFileName(), 2);
        $resolved = $base . DIRECTORY_SEPARATOR . 'src';

        PackagePathAssert::assertSameAsPackagePath(\Switon\Testing\MockClock::class, $resolved, 'src');
    }

    public function testAssertSameAsPackagePathFailsWhenPathsDiffer(): void
    {
        $this->expectException(ExpectationFailedException::class);

        PackagePathAssert::assertSameAsPackagePath(
            \Switon\Testing\MockClock::class,
            sys_get_temp_dir(),
            'src'
        );
    }
}
