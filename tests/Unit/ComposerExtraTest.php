<?php

declare(strict_types=1);

namespace Switon\Testing\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Switon\Testing\ComposerExtra;
use function bin2hex;
use function dirname;
use function file_put_contents;
use function is_array;
use function is_dir;
use function json_encode;
use function random_bytes;
use function sys_get_temp_dir;
use function unlink;

class ComposerExtraTest extends TestCase
{
    public function testAllUsesProvidedCacheFileWhenItExists(): void
    {
        $cacheFile = sys_get_temp_dir() . '/composer-extra-' . bin2hex(random_bytes(4)) . '.json';
        $expected = [
            'vendor/pkg' => ['switon' => ['providers' => ['A\\Provider']]],
        ];
        file_put_contents($cacheFile, (string)json_encode($expected, JSON_THROW_ON_ERROR));

        try {
            $extra = new ComposerExtra($cacheFile);
            $this->assertSame($expected, $extra->all());
            $this->assertSame(['switon' => ['providers' => ['A\\Provider']]], $extra->get('vendor/pkg'));
            $this->assertTrue($extra->has('vendor/pkg'));
        } finally {
            @unlink($cacheFile);
        }
    }

    public function testAllFallsBackToMonorepoPackageDiscoveryWhenCacheFileMissing(): void
    {
        $cacheFile = sys_get_temp_dir() . '/composer-extra-missing-' . bin2hex(random_bytes(4)) . '.json';
        $extra = new ComposerExtra($cacheFile);

        $all = $extra->all();

        $this->assertTrue(is_array($all));
        $this->assertNotSame([], $all);
        $this->assertArrayHasKey('switon/testing', $all);
    }

    public function testDetectRepoRootFindsRootContainingPackages(): void
    {
        $extra = new TestableComposerExtra();
        $root = $extra->detectRepoRootPublic(__DIR__);

        $this->assertTrue(is_dir($root . '/packages'));
        $this->assertSame(dirname(__DIR__, 4), $root);
    }
}

class TestableComposerExtra extends ComposerExtra
{
    public function detectRepoRootPublic(string $startDir): string
    {
        return $this->detectRepoRoot($startDir);
    }
}
