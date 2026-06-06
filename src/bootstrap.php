<?php

declare(strict_types=1);

/**
 * Unified bootstrap file for all Switon Framework package tests.
 * Used via symlink: packages/<package>/tests/bootstrap.php -> ../../testing/src/bootstrap.php
 *
 * Use when package tests need one shared bootstrap for monorepo and split-package installs.
 *
 * @see \Switon\Core\Runtime Test runtime boundary
 */

// Set coroutine disabled for tests by default
use Switon\Core\Runtime;

Runtime::setCoroutineEnabled(false);

/**
 * Register package test autoloads that should not leak into published metadata.
 *
 * Loads package `autoload.files`, `autoload-dev.psr-4`, and the optional GMP override helper.
 */
function switon_register_package_test_autoloads(string $packageRoot): void
{
    $packageComposerJson = $packageRoot . '/composer.json';
    if (!file_exists($packageComposerJson)) {
        return;
    }

    $composerConfig = json_decode(file_get_contents($packageComposerJson), true);
    if (!is_array($composerConfig)) {
        return;
    }

    if (isset($composerConfig['autoload']['files'])) {
        foreach ($composerConfig['autoload']['files'] as $file) {
            $fullPath = $packageRoot . '/' . $file;
            if (file_exists($fullPath)) {
                require_once $fullPath;
            }
        }
    }

    if (isset($composerConfig['autoload-dev']['psr-4'])) {
        foreach ($composerConfig['autoload-dev']['psr-4'] as $namespace => $path) {
            $fullPath = $packageRoot . '/' . $path;
            if (is_dir($fullPath)) {
                spl_autoload_register(static function ($class) use ($namespace, $fullPath) {
                    $prefix = rtrim($namespace, '\\');
                    if (str_starts_with($class, $prefix)) {
                        $relativeClass = substr($class, strlen($prefix));
                        $file = $fullPath . str_replace('\\', '/', ltrim($relativeClass, '\\')) . '.php';
                        if (file_exists($file)) {
                            require $file;
                        }
                    }
                });
            }
        }
    }

    $gmpOverride = $packageRoot . '/tests/gmp-function-override.php';
    if (file_exists($gmpOverride)) {
        require_once $gmpOverride;
    }
}

$packagesDir = dirname(__DIR__, 2);
$isMonorepo = basename($packagesDir) === 'packages';

if ($isMonorepo) {
    $repoRoot = dirname($packagesDir);
    $repoAutoload = $repoRoot . '/vendor/autoload.php';
    if (file_exists($repoAutoload)) {
        require_once $repoAutoload;
    } else {
        throw new RuntimeException(
            'Composer autoloader not found in repo-root: ' . $repoAutoload . '. ' .
            'Please run "composer install" in the repository root directory.'
        );
    }
    // Get package directory from PHPUnit's --configuration argument
    // Load package's autoload-dev (for test classes)
    if (isset($_SERVER['argv'])) {
        $argv = $_SERVER['argv'];
        $configIndex = array_search('--configuration', $argv, true);
        if (!is_int($configIndex)) {
            $configIndex = array_search('-c', $argv, true);
        }
        if (is_int($configIndex) && isset($argv[$configIndex + 1])) {
            $configArg = (string)$argv[$configIndex + 1];
            $configPath = realpath($configArg) ?: realpath(getcwd() . '/' . $configArg);
            if ($configPath !== false) {
                $packageRoot = dirname($configPath, 2);
                // Load package autoloader if present (package dev deps)
                $packageAutoload = $packageRoot . '/vendor/autoload.php';
                if (file_exists($packageAutoload)) {
                    require_once $packageAutoload;
                }
                switon_register_package_test_autoloads($packageRoot);
            }
        }
    }
} else {
    $packageRoot = dirname(__DIR__);
    $packageAutoload = $packageRoot . '/vendor/autoload.php';
    if (file_exists($packageAutoload)) {
        require_once $packageAutoload;
    } else {
        throw new RuntimeException(
            'Composer autoloader not found in package root: ' . $packageAutoload . '. ' .
            'Please run "composer install" in the package root directory.'
        );
    }

    $autoloadPackageRoot = $packageRoot;
    if (isset($_SERVER['argv'])) {
        $argv = $_SERVER['argv'];
        $configIndex = array_search('--configuration', $argv, true);
        if (!is_int($configIndex)) {
            $configIndex = array_search('-c', $argv, true);
        }
        if (is_int($configIndex) && isset($argv[$configIndex + 1])) {
            $configArg = (string)$argv[$configIndex + 1];
            $configPath = realpath($configArg) ?: realpath(getcwd() . '/' . $configArg);
            if ($configPath !== false) {
                $autoloadPackageRoot = dirname($configPath, 2);
            }
        }
    }

    if ($autoloadPackageRoot !== $packageRoot) {
        $targetAutoload = $autoloadPackageRoot . '/vendor/autoload.php';
        if (file_exists($targetAutoload)) {
            require_once $targetAutoload;
        }
    }

    switon_register_package_test_autoloads($autoloadPackageRoot);
}
