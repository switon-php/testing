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
            $all = $extra->all();
            $this->assertSame(['switon' => ['providers' => ['A\\Provider']]], $extra->get('vendor/pkg'));
            $this->assertTrue($extra->has('vendor/pkg'));
            $this->assertArrayHasKey('vendor/pkg', $all);
            // Local composer.json discovery may add more packages on top of the cache.
            $this->assertGreaterThanOrEqual(1, count($all));
        } finally {
            @unlink($cacheFile);
        }
    }

    public function testAllBackfillsLocalComposerJsonOverStaleCache(): void
    {
        $cacheFile = sys_get_temp_dir() . '/composer-extra-stale-' . bin2hex(random_bytes(4)) . '.json';
        file_put_contents($cacheFile, (string)json_encode([
            'vendor/old' => ['switon' => ['providers' => ['Old\\Provider']]],
        ], JSON_THROW_ON_ERROR));

        try {
            $extra = new ComposerExtra($cacheFile);
            $all = $extra->all();
            $this->assertArrayHasKey('vendor/old', $all);
        } finally {
            @unlink($cacheFile);
        }
    }

    public function testCollectIgnoresPackagesWhoseInstallPathIsMissing(): void
    {
        $extra = new ComposerExtra();
        $providers = $extra->collect('switon.providers');

        $this->assertNotContains('Switon\\Agency\\ServiceProvider', $providers);
        $this->assertNotContains('Switon\\Tui\\ServiceProvider', $providers);
        $this->assertContains('Switon\\Http\\ServiceProvider', $providers);
    }

    public function testLoadFromComposerJsonGlobsSkipsUnreadableComposerJson(): void
    {
        $sandbox = sys_get_temp_dir() . '/switon-testing-unreadable-' . bin2hex(random_bytes(4));
        $packagesDir = $sandbox . '/packages';
        mkdir($packagesDir . '/unreadable', 0777, true);
        $composerJson = $packagesDir . '/unreadable/composer.json';
        file_put_contents($composerJson, '{"name":"acme/unreadable","extra":{}}');
        chmod($composerJson, 0o000);

        try {
            if (!is_readable($composerJson)) {
                $extra = new TestableComposerExtra();
                $data = $extra->loadFromComposerJsonGlobsPublic([$packagesDir . '/*/composer.json']);
                $this->assertSame([], $data);
            } else {
                $this->markTestSkipped('Cannot create unreadable composer.json on this filesystem');
            }
        } finally {
            @chmod($composerJson, 0o644);
            @unlink($composerJson);
            @rmdir($packagesDir . '/unreadable');
            @rmdir($packagesDir);
            @rmdir($sandbox);
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

    public function testAllFallsBackToPackageDiscoveryWhenCacheFileMissing(): void
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

    public function testDetectRepoRootFallsBackWhenFilesystemRootHasNoComposerManifest(): void
    {
        $extra = new TestableComposerExtra();
        $this->assertSame(dirname(__DIR__, 2), $extra->detectRepoRootPublic('/'));
    }

    public function testFilterPackagesWithMissingInstallPathSkipsEmptyPackageNames(): void
    {
        $extra = new TestableComposerExtra();

        $filtered = $extra->filterPackagesWithMissingInstallPathPublic([
            '' => ['switon' => ['providers' => []]],
            'valid/pkg' => ['switon' => ['providers' => ['Valid\\Provider']]],
        ]);

        $this->assertArrayHasKey('valid/pkg', $filtered);
        $this->assertArrayNotHasKey('', $filtered);
    }

    public function testFilterPackagesWithMissingInstallPathSkipsAbsentInstallDirectories(): void
    {
        if (!class_exists(\Composer\InstalledVersions::class)) {
            $this->markTestSkipped('Composer InstalledVersions is not available');
        }

        $packageName = 'switon/testing';
        if (!\Composer\InstalledVersions::isInstalled($packageName)) {
            $this->markTestSkipped('switon/testing is not installed in this workspace');
        }

        $installPath = \Composer\InstalledVersions::getInstallPath($packageName);
        $hiddenPath = dirname($installPath) . '/.hidden-' . bin2hex(random_bytes(4));
        $this->assertTrue(rename($installPath, $hiddenPath));

        try {
            $extra = new TestableComposerExtra();
            $filtered = $extra->filterPackagesWithMissingInstallPathPublic([
                $packageName => ['switon' => ['providers' => []]],
            ]);
            $this->assertArrayNotHasKey($packageName, $filtered);
        } finally {
            $this->assertTrue(rename($hiddenPath, $installPath));
        }
    }

    public function testPackageComposerGlobPathsIncludesVendorDirectory(): void
    {
        $extra = new TestableComposerExtra();
        $root = $extra->detectRepoRootPublic(__DIR__);
        $paths = $extra->packageComposerGlobPathsPublic($root);

        $this->assertContains($root . '/vendor/*/*/composer.json', $paths);
    }

    public function testPackageComposerGlobPathsIncludesRootComposerWhenPackagesDirMissing(): void
    {
        $sandbox = sys_get_temp_dir() . '/switon-testing-globs-' . bin2hex(random_bytes(4));
        mkdir($sandbox, 0777, true);
        file_put_contents($sandbox . '/composer.json', (string)json_encode(['name' => 'acme/alone'], JSON_THROW_ON_ERROR));

        try {
            $extra = new TestableComposerExtra();
            $paths = $extra->packageComposerGlobPathsPublic($sandbox);

            $this->assertContains($sandbox . '/composer.json', $paths);
            $this->assertNotContains($sandbox . '/packages/*/composer.json', $paths);
        } finally {
            @unlink($sandbox . '/composer.json');
            @rmdir($sandbox);
        }
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

    /**
     * @param array<string, array<string, mixed>> $data
     *
     * @return array<string, array<string, mixed>>
     */
    public function filterPackagesWithMissingInstallPathPublic(array $data): array
    {
        return $this->filterPackagesWithMissingInstallPath($data);
    }

    /**
     * @return list<string>
     */
    public function packageComposerGlobPathsPublic(string $repoRoot): array
    {
        return $this->packageComposerGlobPaths($repoRoot);
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
