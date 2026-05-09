<?php

declare(strict_types=1);

namespace Switon\Testing;

use Switon\ComposerExtra\ComposerExtra as CoreComposerExtra;
use Switon\Core\Exception\RuntimeException;
use function dirname;
use function file_exists;
use function glob;
use function is_array;
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

        $repoRoot = $this->detectRepoRoot(__DIR__);
        $globPath = $repoRoot . '/packages/*/composer.json';

        $data = [];
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

        if ($data === []) {
            RuntimeException::raise('Failed to discover composer extra: no packages found at {path}', ['path' => $globPath]);
        }

        return $data;
    }

    protected function detectRepoRoot(string $startDir): string
    {
        $dir = realpath($startDir) ?: $startDir;

        while (true) {
            if (is_dir($dir . '/packages')) {
                return $dir;
            }

            $parent = dirname($dir);
            if ($parent === $dir) {
                // best effort: fall back to going up from packages/testing/src
                return dirname(__DIR__, 3);
            }
            $dir = $parent;
        }
    }
}
