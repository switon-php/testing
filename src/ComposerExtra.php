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
use function is_string;

/**
 * Test-friendly Composer extra loader with monorepo fallback.
 *
 * Use when package tests run without `vendor/switon/composer-extra.json` and must discover
 * provider metadata directly from package `composer.json` files.
 *
 * @see \Switon\ComposerExtra\ComposerExtraInterface
 * @see \Switon\Testing\Container\Container
 */
class ComposerExtra extends CoreComposerExtra
{
    /**
     * @return array<string, array<string, mixed>>
     */
    protected function loadCache(): array
    {
        // Prefer the real cache when available.
        if (file_exists($this->cacheFile)) {
            /** @var array<string, array<string, mixed>> $data */
            $data = parent::loadCache();
            return $data;
        }

        $data = $this->loadFromInstalledVersions();

        $repoRoot = $this->detectRepoRoot(__DIR__);
        $globPaths = $this->packageComposerGlobPaths($repoRoot);
        $manifestData = $this->loadFromComposerJsonGlobs($globPaths);
        if ($manifestData !== []) {
            // Keep InstalledVersions as fast source, but backfill missing entries from composer manifests.
            $data = $manifestData + $data;
        }

        if ($data === []) {
            RuntimeException::raise('Failed to discover composer extra: no packages found under {path}', [
                'path' => $repoRoot,
            ]);
        }

        return $data;
    }

    /**
     * @return list<string>
     */
    protected function packageComposerGlobPaths(string $repoRoot): array
    {
        $packagesDir = $repoRoot . '/packages';
        if (is_dir($packagesDir)) {
            return [$packagesDir . '/*/composer.json'];
        }

        return [
            $repoRoot . '/vendor/*/*/composer.json',
            $repoRoot . '/*/composer.json',
        ];
    }

    /**
     * @param list<string> $globPaths
     * @return array<string, array<string, mixed>>
     */
    protected function loadFromComposerJsonGlobs(array $globPaths): array
    {
        $data = [];
        foreach ($globPaths as $globPath) {
            foreach (glob($globPath) ?: [] as $composerJson) {
                $json = file_get_contents($composerJson);
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

        while (true) {
            if (is_dir($dir . '/packages')) {
                return $dir;
            }
            if (file_exists($dir . '/composer.json')) {
                $nearestComposerRoot = $dir;
            }

            $parent = dirname($dir);
            if ($parent === $dir) {
                return $nearestComposerRoot ?? dirname(__DIR__);
            }
            $dir = $parent;
        }
    }
}
