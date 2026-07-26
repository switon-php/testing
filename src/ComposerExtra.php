<?php

declare(strict_types=1);

namespace Switon\Testing;

use Composer\InstalledVersions;
use Switon\ComposerExtra\ComposerExtra as CoreComposerExtra;
use Switon\Core\Exception\RuntimeException;

use function dirname;
use function file_exists;
use function glob;
use function is_array;
use function is_dir;
use function is_file;
use function is_string;

/**
 * Test-friendly Composer extra loader with layout-agnostic discovery.
 *
 * Plugin cache (`vendor/switon/composer-extra.json`) may be missing or stale. Tests still need
 * provider metadata whether packages live in a workspace (`packages/*`), under `vendor/`, or as a
 * split package repo (root `composer.json`).
 *
 * Road-signs:
 * - loadCache() merges: local composer.json manifests → InstalledVersions → plugin cache
 * - packageComposerGlobPaths() unions workspace / vendor / split-root paths (no mono-only branch)
 * - detectRepoRoot() finds the nearest install root
 *
 * @see \Switon\ComposerExtra\ComposerExtraInterface
 * @see \Switon\Testing\Container
 */
class ComposerExtra extends CoreComposerExtra
{
    /**
     * @return array<string, array<string, mixed>>
     */
    protected function loadCache(): array
    {
        $data = [];
        if (file_exists($this->cacheFile)) {
            /** @var array<string, array<string, mixed>> $data */
            $data = parent::loadCache();
        }

        $installed = $this->loadFromInstalledVersions();
        $repoRoot = $this->detectRepoRoot(__DIR__);
        $manifestData = $this->loadFromComposerJsonGlobs($this->packageComposerGlobPaths($repoRoot));

        // Local manifests win, then installed packages, then plugin cache (fill gaps only).
        $data = $manifestData + $installed + $data;

        if ($data === []) {
            RuntimeException::raise('Failed to discover composer extra: no packages found under {path}', [
                'path' => $repoRoot,
            ]);
        }

        return $this->filterPackagesWithMissingInstallPath($data);
    }

    /**
     * Drop Composer metadata for path packages that are still listed in lock/installed data but absent on disk.
     *
     * @param array<string, array<string, mixed>> $data
     *
     * @return array<string, array<string, mixed>>
     */
    protected function filterPackagesWithMissingInstallPath(array $data): array
    {
        if (!class_exists(InstalledVersions::class)) {
            return $data;
        }

        $present = [];
        foreach ($data as $packageName => $extra) {
            if (!is_string($packageName) || $packageName === '') {
                continue;
            }

            if (InstalledVersions::isInstalled($packageName)) {
                $installPath = InstalledVersions::getInstallPath($packageName);
                if ($installPath === null || !is_dir($installPath)) {
                    continue;
                }
            }

            $present[$packageName] = $extra;
        }

        return $present;
    }

    /**
     * Composer.json locations for the current install layout (union, not exclusive).
     *
     * @return list<string>
     */
    protected function packageComposerGlobPaths(string $repoRoot): array
    {
        $paths = [];
        if (is_dir($repoRoot . '/packages')) {
            $paths[] = $repoRoot . '/packages/*/composer.json';
        }
        if (is_dir($repoRoot . '/vendor')) {
            $paths[] = $repoRoot . '/vendor/*/*/composer.json';
        }
        // Split package under test: the package's own composer.json at repo root.
        if (is_file($repoRoot . '/composer.json')) {
            $paths[] = $repoRoot . '/composer.json';
        }

        return $paths;
    }

    /**
     * @param list<string> $globPaths
     *
     * @return array<string, array<string, mixed>>
     */
    protected function loadFromComposerJsonGlobs(array $globPaths): array
    {
        $data = [];
        foreach ($globPaths as $globPath) {
            foreach (glob($globPath) ?: [] as $composerJson) {
                // Unreadable entries are skipped by design; suppress the E_WARNING so callers
                // can distinguish "file missing/unreadable" from a real failure.
                $json = @file_get_contents($composerJson);
                if ($json === false) {
                    continue;
                }

                $parsed = json_decode($json, true);
                if (!is_array($parsed)) {
                    continue;
                }

                $name = $parsed['name'] ?? null;
                if (!is_string($name) || $name === '') {
                    continue;
                }

                $extra = $parsed['extra'] ?? [];
                if (!is_array($extra)) {
                    $extra = [];
                }

                $data[$name] = $extra;
            }
        }

        return $data;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function loadFromInstalledVersions(): array
    {
        if (!class_exists(InstalledVersions::class)) {
            return [];
        }

        $extra = [];
        foreach (InstalledVersions::getAllRawData() as $sourceData) {
            if (!isset($sourceData['versions']) || !is_array($sourceData['versions'])) {
                continue;
            }

            foreach ($sourceData['versions'] as $packageName => $packageInfo) {
                if (!is_array($packageInfo)) {
                    continue;
                }

                $packageExtra = $packageInfo['extra'] ?? null;
                if (!is_array($packageExtra) || $packageExtra === []) {
                    continue;
                }

                $extra[$packageName] = $packageExtra;
            }
        }

        return $extra;
    }

    protected function detectRepoRoot(string $startDir): string
    {
        $dir = realpath($startDir) ?: $startDir;
        $nearestComposerRoot = null;
        $nearestVendorProject = null;

        while (true) {
            // Workspace root (multi-package tree).
            if (is_dir($dir . '/packages')) {
                return $dir;
            }
            // Split / app project root: remember, but keep walking in case a workspace is above.
            if ($nearestVendorProject === null
                && is_dir($dir . '/vendor')
                && is_file($dir . '/composer.json')
            ) {
                $nearestVendorProject = $dir;
            }
            if (is_file($dir . '/composer.json')) {
                $nearestComposerRoot = $dir;
            }

            $parent = dirname($dir);
            if ($parent === $dir) {
                return $nearestVendorProject ?? $nearestComposerRoot ?? dirname(__DIR__);
            }
            $dir = $parent;
        }
    }
}
