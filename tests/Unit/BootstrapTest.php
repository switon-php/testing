<?php

declare(strict_types=1);

namespace Switon\Testing\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Switon\Core\Runtime;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

use function bin2hex;
use function class_exists;
use function file_put_contents;
use function is_array;
use function is_dir;
use function json_encode;
use function mkdir;
use function random_bytes;
use function sys_get_temp_dir;

final class BootstrapTest extends TestCase
{
    public function testBootstrapRegistersPackageAutoloadsFromMonorepoLayout(): void
    {
        $bootstrap = dirname(__DIR__, 2) . '/src/bootstrap.php';
        $sandbox = sys_get_temp_dir() . '/switon-testing-bootstrap-' . bin2hex(random_bytes(4));
        $packageRoot = $sandbox . '/packages/fixture';
        $configPath = $packageRoot . '/tests/phpunit.xml.dist';
        $autoloadFile = $packageRoot . '/bootstrap-files/autoloaded.php';
        $autoloadClassFile = $packageRoot . '/tests/autoload-dev/BootstrappedClass.php';
        $gmpOverride = $packageRoot . '/tests/gmp-function-override.php';

        $originalArgv = $_SERVER['argv'] ?? null;

        try {
            mkdir($packageRoot . '/bootstrap-files', 0777, true);
            mkdir($packageRoot . '/tests/autoload-dev', 0777, true);
            mkdir($packageRoot . '/vendor', 0777, true);

            file_put_contents(
                $packageRoot . '/composer.json',
                (string)json_encode([
                    'name' => 'acme/fixture',
                    'autoload' => [
                        'files' => ['bootstrap-files/autoloaded.php'],
                    ],
                    'autoload-dev' => [
                        'psr-4' => [
                            'Fixture\\Tests\\' => 'tests/autoload-dev/',
                        ],
                    ],
                ], JSON_THROW_ON_ERROR)
            );
            file_put_contents($packageRoot . '/vendor/autoload.php', "<?php\n");
            file_put_contents($configPath, "<?php\n");
            file_put_contents(
                $autoloadFile,
                "<?php\n\$GLOBALS['switon_bootstrap_autoload_files'][] = __FILE__;\n"
            );
            file_put_contents(
                $autoloadClassFile,
                "<?php\nnamespace Fixture\\Tests;\nfinal class BootstrappedClass {}\n"
            );
            file_put_contents(
                $gmpOverride,
                "<?php\n\$GLOBALS['switon_bootstrap_gmp_override'][] = __FILE__;\n"
            );

            $_SERVER['argv'] = ['phpunit', '--configuration', $configPath];

            include $bootstrap;

            self::assertFalse(Runtime::isCoroutineEnabled());
            self::assertTrue(is_array($GLOBALS['switon_bootstrap_autoload_files'] ?? null));
            self::assertSame([realpath($autoloadFile)], array_map('realpath', $GLOBALS['switon_bootstrap_autoload_files']));
            self::assertSame([realpath($gmpOverride)], array_map('realpath', $GLOBALS['switon_bootstrap_gmp_override']));
            self::assertTrue(class_exists('Fixture\\Tests\\BootstrappedClass'));

            switon_register_package_test_autoloads($sandbox . '/missing-package');
            mkdir($sandbox . '/invalid-package', 0777, true);
            file_put_contents($sandbox . '/invalid-package/composer.json', '{');
            switon_register_package_test_autoloads($sandbox . '/invalid-package');
        } finally {
            if ($originalArgv === null) {
                unset($_SERVER['argv']);
            } else {
                $_SERVER['argv'] = $originalArgv;
            }

            $this->removeTree($sandbox);
        }
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($path);
    }
}
