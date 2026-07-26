<?php

declare(strict_types=1);

namespace Switon\Testing\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Switon\Core\Exception\RuntimeException;
use Switon\Testing\ComposerExtra;

use function bin2hex;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;

class TestingComposerExtraEdgeTest extends TestCase
{
    public function testAllRaisesWhenDiscoveryFindsNoPackages(): void
    {
        $root = sys_get_temp_dir() . '/switon-extra-empty-' . bin2hex(random_bytes(4));
        mkdir($root, 0777, true);
        $cache = $root . '/missing-cache.json';

        $extra = new class ($cache, $root) extends ComposerExtra {
            public function __construct(string $cacheFile, protected string $root)
            {
                parent::__construct($cacheFile);
            }

            protected function detectRepoRoot(string $startDir): string
            {
                return $this->root;
            }

            protected function loadFromComposerJsonGlobs(array $globPaths): array
            {
                return [];
            }

            protected function loadFromInstalledVersions(): array
            {
                return [];
            }
        };

        try {
            $this->expectException(RuntimeException::class);
            $extra->all();
        } finally {
            @rmdir($root);
        }
    }
}
