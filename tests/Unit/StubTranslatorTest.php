<?php

declare(strict_types=1);

namespace Switon\Testing\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Switon\Testing\Mock\StubTranslator;

class StubTranslatorTest extends TestCase
{
    public function testTranslateReturnsKeyUnchanged(): void
    {
        $t = new StubTranslator();

        self::assertSame('app.greeting', $t->translate('app.greeting', ['name' => 'x'], true));
    }

    public function testHasAlwaysReportsFalse(): void
    {
        $t = new StubTranslator();

        self::assertFalse($t->has('any.id'));
    }
}
