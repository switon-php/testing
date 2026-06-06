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

    public function testLoadFromComposerJsonGlobsSkipsInvalidEntriesAndNormalizesExtra(): void
    {
        $sandbox = sys_get_temp_dir() . '/switon-testing-extra-globs-' . bin2hex(random_bytes(4));
        $packagesDir = $sandbox . '/packages';
        mkdir($packagesDir . '/valid', 0777, true);
        mkdir($packagesDir . '/string-extra', 0777, true);
        mkdir($packagesDir . '/missing-name', 0777, true);
        mkdir($packagesDir . '/invalid-json', 0777, true);

        file_put_contents(
            $packagesDir . '/valid/composer.json',
            (string)json_encode([
                'name' => 'acme/valid',
                'extra' => ['switon' => ['providers' => ['Acme\\Valid\\ServiceProvider']]],
            ], JSON_THROW_ON_ERROR)
        );
        file_put_contents(
            $packagesDir . '/string-extra/composer.json',
            (string)json_encode([
                'name' => 'acme/string-extra',
                'extra' => 'not-an-array',
            ], JSON_THROW_ON_ERROR)
        );
        file_put_contents(
            $packagesDir . '/missing-name/composer.json',
            (string)json_encode([
                'extra' => ['switon' => ['providers' => ['Acme\\MissingName\\ServiceProvider']]],
            ], JSON_THROW_ON_ERROR)
        );
        file_put_contents($packagesDir . '/invalid-json/composer.json', '{');

        try {
            $extra = new TestableComposerExtra();
            $data = $extra->loadFromComposerJsonGlobsPublic([$packagesDir . '/*/composer.json']);

            self::assertCount(2, $data);
            self::assertSame(['switon' => ['providers' => ['Acme\\Valid\\ServiceProvider']]], $data['acme/valid']);
            self::assertSame([], $data['acme/string-extra']);
            self::assertArrayNotHasKey('acme/missing-name', $data);
        } finally {
            @unlink($packagesDir . '/valid/composer.json');
            @unlink($packagesDir . '/string-extra/composer.json');
            @unlink($packagesDir . '/missing-name/composer.json');
            @unlink($packagesDir . '/invalid-json/composer.json');
            @rmdir($packagesDir . '/valid');
            @rmdir($packagesDir . '/string-extra');
            @rmdir($packagesDir . '/missing-name');
            @rmdir($packagesDir . '/invalid-json');
            @rmdir($packagesDir);
            @rmdir($sandbox . '/packages');
            @rmdir($sandbox);
        }
    }

    public function testLoadFromInstalledVersionsReturnsArray(): void
    {
        $extra = new TestableComposerExtra();

        self::assertIsArray($extra->loadFromInstalledVersionsPublic());
    }

    public function testAllFallsBackToMonorepoPackageDiscoveryWhenCacheFileMissing(): void
    {
        $cacheFile = sys_get_temp_dir() . '/composer-extra-missing-' . bin2hex(random_bytes(4)) . '.json';
        $extra = new ComposerExtra($cacheFile);

        $all = $extra->all();

        $this->assertTrue(is_array($all));
        $this->assertNotSame([], $all);
        $this->assertNotSame([], $extra->collect('switon.providers'));
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
            $this->assertContains(
                'Acme\\SplitFixture\\ServiceProvider',
                $extra->collect('switon.providers')
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

    /**
     * @param list<string> $globPaths
     *
     * @return array<string, array<string, mixed>>
     */
    public function loadFromComposerJsonGlobsPublic(array $globPaths): array
    {
        return $this->loadFromComposerJsonGlobs($globPaths);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function loadFromInstalledVersionsPublic(): array
    {
        return $this->loadFromInstalledVersions();
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
