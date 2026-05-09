<?php

declare(strict_types=1);

namespace Switon\Testing\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Switon\Testing\ComposerExtra;
use function bin2hex;
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
        $this->assertNotSame([], $extra->getClasses('switon.providers'));
    }

    public function testDetectRepoRootFindsRootContainingPackages(): void
    {
        $extra = new TestableComposerExtra();
        $root = $extra->detectRepoRootPublic(__DIR__);

        $this->assertTrue(
            is_dir($root . '/packages') || is_file($root . '/composer.json')
        );
    }

    public function testAllFallsBackToVendorComposerDiscoveryInSplitRepo(): void
    {
        $sandbox = sys_get_temp_dir() . '/switon-testing-extra-' . bin2hex(random_bytes(4));
        $vendorPackageDir = $sandbox . '/vendor/acme/split-fixture';
        $vendorComposer = $vendorPackageDir . '/composer.json';
        $projectComposer = $sandbox . '/composer.json';

        mkdir($vendorPackageDir, 0777, true);
        file_put_contents(
            $vendorComposer,
            (string)json_encode([
                'name' => 'acme/split-fixture',
                'extra' => [
                    'switon' => [
                        'providers' => ['Acme\\SplitFixture\\ServiceProvider'],
                    ],
                ],
            ], JSON_THROW_ON_ERROR)
        );
        file_put_contents($projectComposer, (string)json_encode(['name' => 'switon/testing'], JSON_THROW_ON_ERROR));

        try {
            $cacheFile = $sandbox . '/missing-composer-extra.json';
            $extra = new SplitRepoComposerExtraFixture($cacheFile, $sandbox);
            $all = $extra->all();
            $this->assertArrayHasKey('acme/split-fixture', $all);
            $this->assertSame(
                ['Acme\\SplitFixture\\ServiceProvider'],
                $extra->getClasses('switon.providers', 'acme/split-fixture')
            );
        } finally {
            @unlink($vendorComposer);
            @unlink($projectComposer);
            @rmdir($vendorPackageDir);
            @rmdir($sandbox . '/vendor/acme');
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

class SplitRepoComposerExtraFixture extends ComposerExtra
{
    public function __construct(string $cacheFile, protected string $repoRoot)
    {
        parent::__construct($cacheFile);
    }

    protected function detectRepoRoot(string $startDir): string
    {
        return $this->repoRoot;
    }
}
