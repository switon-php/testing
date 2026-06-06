<?php

declare(strict_types=1);

namespace Switon\Testing\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Switon\Testing\Mock\MockConsole;

final class MockConsoleTest extends TestCase
{
    public function testWriteLnAndLevelHelpersCaptureLines(): void
    {
        $console = new MockConsole();

        $console->info('one');
        $console->warning('two');
        $console->success('s');
        $console->error('three');

        self::assertSame(['one', 'two', 's', 'three'], $console->getOutput());
    }

    public function testInlineAndInteractiveHelpersKeepOutputInMemory(): void
    {
        $console = new MockConsole();

        $console->write('inline');
        self::assertSame('', $console->read());
        $console->debug('debug');
        $console->progress('progress', 50);
        self::assertSame('', $console->ask('ask?'));
        self::assertTrue($console->confirm('confirm?', true));
        self::assertFalse($console->confirm('confirm?', false));
        self::assertSame('', $console->secret('secret?'));
        $console->section('section');
        $console->sampleColorizer();

        self::assertSame(['debug', 'progress', 'ask?', 'confirm?', 'confirm?', 'secret?', 'section'], $console->getOutput());
    }

    public function testErrorReturnsProvidedExitCode(): void
    {
        $console = new MockConsole();

        self::assertSame(7, $console->error('fail', [], 7));
    }

    public function testClearOutputEmptiesCapturedLines(): void
    {
        $console = new MockConsole();
        $console->line('x');
        $console->clearOutput();

        self::assertSame([], $console->getOutput());
    }

    public function testIsSupportColorIsFalseAndColorizeIsPassthrough(): void
    {
        $console = new MockConsole();

        self::assertFalse($console->isSupportColor());
        self::assertSame('plain', $console->colorize('plain'));
    }

    public function testChoiceWithDefaultSelectsOption(): void
    {
        $console = new MockConsole();

        self::assertSame('a', $console->choice('pick', ['a', 'b', 'c']));
        self::assertSame('first', $console->choice('pick', ['first' => 'one', 'second' => 'two']));
        self::assertSame('b', $console->choice('pick', ['a', 'b', 'c'], 1));
        self::assertSame('second', $console->choice('pick', ['first' => 'one', 'second' => 'two'], 'second'));
        self::assertSame('first', $console->choice('pick assoc', ['first' => 'one', 'second' => 'two']));

        self::assertSame(['pick', 'pick', 'pick', 'pick', 'pick assoc'], $console->getOutput());
    }

    public function testTableRendersHeaderAndRows(): void
    {
        $console = new MockConsole();

        $console->table(['Name', 'Qty'], [['apple', 3]]);

        $lines = $console->getOutput();
        self::assertGreaterThanOrEqual(2, count($lines));
        self::assertStringContainsString('Name', $lines[0]);
        self::assertStringContainsString('apple', $lines[1]);
    }

    public function testTableHandlesNullCellsWithoutRowNumbers(): void
    {
        $console = new MockConsole();

        $console->table(['Name', 'Qty'], [['apple', null]], 0, false);

        $lines = $console->getOutput();
        self::assertCount(2, $lines);
        self::assertStringContainsString('Name', $lines[0]);
        self::assertStringContainsString('apple', $lines[1]);
        self::assertStringContainsString('-', $lines[1]);
    }

    public function testTableWithEmptyHeadersAndRowsProducesNoOutput(): void
    {
        $console = new MockConsole();
        $console->table([], []);

        self::assertSame([], $console->getOutput());
    }

    public function testWriteAndNewLineAppendExpectedBlankLines(): void
    {
        $console = new MockConsole();

        $console->write('inline');
        $console->newLine(2);
        $console->line('tail');

        self::assertSame(['', '', 'tail'], $console->getOutput());
    }

    public function testBlockSupportsStringAndArrayMessagesWithPrefix(): void
    {
        $console = new MockConsole();

        $console->block('single', null, '>>');
        $console->block(['alpha', 'beta']);

        self::assertSame(['>> single', ' alpha', ' beta'], $console->getOutput());
    }

    public function testListingNoteAndCautionPrefixLines(): void
    {
        $console = new MockConsole();

        $console->listing(['a']);
        $console->note('n');
        $console->caution('c');

        $lines = $console->getOutput();
        self::assertStringStartsWith(' - ', $lines[0]);
        self::assertStringContainsString('[NOTE]', $lines[1]);
        self::assertStringContainsString('[CAUTION]', $lines[2]);
    }
}
