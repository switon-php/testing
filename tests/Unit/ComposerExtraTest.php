<?php

declare(strict_types=1);

namespace Switon\Testing\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Switon\Testing\ComposerExtra;
use function bin2hex;
use function chdir;
use function file_put_contents;
use function getcwd;
use function is_array;
use function is_dir;
use function is_file;
use function json_encode;
use function mkdir;
use function random_bytes;
use function rmdir;
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

        $this->assertTrue(
            is_dir($root . '/packages') || is_file($root . '/testing/composer.json')
        );
    }

    public function testAllFallsBackToVendorComposerDiscoveryInSplitRepo(): void
    {
        $sandbox = sys_get_temp_dir() . '/switon-testing-extra-' . bin2hex(random_bytes(4));
        $vendorPackageDir = $sandbox . '/vendor/switon/core';
        $vendorComposer = $vendorPackageDir . '/composer.json';
        $projectComposer = $sandbox . '/composer.json';

        mkdir($vendorPackageDir, 0777, true);
        file_put_contents(
            $vendorComposer,
            (string)json_encode([
                'name' => 'switon/core',
                'extra' => [
                    'switon' => [
                        'providers' => ['Switon\\Core\\ServiceProvider'],
                    ],
                ],
            ], JSON_THROW_ON_ERROR)
        );
        file_put_contents($projectComposer, (string)json_encode(['name' => 'switon/testing'], JSON_THROW_ON_ERROR));

        $cwd = getcwd();
        if ($cwd !== false) {
            chdir($sandbox);
        }

        try {
            $cacheFile = $sandbox . '/missing-composer-extra.json';
            $extra = new ComposerExtra($cacheFile);
            $all = $extra->all();
            $this->assertArrayHasKey('switon/core', $all);
            $this->assertTrue(is_array($all['switon/core']));
        } finally {
            if ($cwd !== false) {
                chdir($cwd);
            }

            @unlink($vendorComposer);
            @unlink($projectComposer);
            @rmdir($vendorPackageDir);
            @rmdir($sandbox . '/vendor/switon');
            @rmdir($sandbox . '/vendor');
            @rmdir($sandbox);
        }
    }
}

class TestableComposerExtra extends ComposerExtra
{
    public function detectRepoRootPublic(string $startDir): string
    {
        return $this->detectRepoRoot($startDir);
    }
}
