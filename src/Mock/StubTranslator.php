<?php

declare(strict_types=1);

namespace Switon\Testing\Mock;

use Switon\Core\TranslatorInterface;

/**
 * Minimal {@see TranslatorInterface} for tests that do not exercise i18n loading.
 *
 * Returns translation keys unchanged and reports no catalog hits.
 */
final class StubTranslator implements TranslatorInterface
{
    public function translate(string $id, array $bind = [], bool $useICU = false): string
    {
        return $id;
    }

    public function has(string $id): bool
    {
        return false;
    }
}
