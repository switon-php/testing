<?php

declare(strict_types=1);

namespace Switon\Testing\Mock;

use Stringable;
use Switon\Core\ConsoleInterface;

/**
 * In-memory console implementation for CLI test assertions.
 *
 * Use when command tests need to capture output without writing to real stdout/stderr.
 *
 * @see \Switon\Core\ConsoleInterface
 * @see \Switon\Testing\Container\Container
 */
class MockConsole implements ConsoleInterface
{
    /**
     * Captured output lines
     *
     * @var array<string>
     */
    protected array $output = [];

    /**
     * Get all captured output lines.
     *
     * @return array<string>
     */
    public function getOutput(): array
    {
        return $this->output;
    }

    /**
     * Clear all captured output.
     */
    public function clearOutput(): void
    {
        $this->output = [];
    }

    /**
     * {@inheritDoc}
     */
    public function isSupportColor(): bool
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function colorize(string $text, int $options = 0, int $width = 0): string
    {
        return $text;
    }

    /**
     * {@inheritDoc}
     */
    public function write(string|Stringable $message, array $context = [], int $options = 0): void
    {
        // Don't add to output array, just ignore (for inline writes)
    }

    /**
     * {@inheritDoc}
     */
    public function writeLn(string|Stringable $message = '', array $context = [], int $options = 0): void
    {
        $this->output[] = (string)$message;
    }

    /**
     * {@inheritDoc}
     */
    public function debug(string|Stringable $message = '', array $context = [], int $options = 0): void
    {
        $this->writeLn($message, $context, $options);
    }

    /**
     * {@inheritDoc}
     */
    public function info(string|Stringable $message, array $context = []): void
    {
        $this->writeLn($message, $context);
    }

    /**
     * {@inheritDoc}
     */
    public function warning(string|Stringable $message, array $context = []): void
    {
        $this->writeLn($message, $context);
    }

    /**
     * {@inheritDoc}
     */
    public function success(string|Stringable $message, array $context = []): void
    {
        $this->writeLn($message, $context);
    }

    /**
     * {@inheritDoc}
     */
    public function error(string|Stringable $message, array $context = [], int $code = 1): int
    {
        $this->writeLn($message, $context);
        return $code;
    }

    /**
     * {@inheritDoc}
     */
    public function progress(string|Stringable $message, mixed $value = null): void
    {
        $this->writeLn($message);
    }

    /**
     * {@inheritDoc}
     */
    public function read(): string
    {
        return '';
    }

    /**
     * {@inheritDoc}
     */
    public function ask(string $message): string
    {
        $this->writeLn($message);
        return '';
    }

    /**
     * {@inheritDoc}
     */
    public function confirm(string $message, bool $default = true): bool
    {
        $this->writeLn($message);
        return $default;
    }

    /**
     * {@inheritDoc}
     */
    public function choice(string $message, array $options, string|int|null $default = null): string|int
    {
        $this->writeLn($message);
        if ($default !== null) {
            $keys = array_keys($options);
            $isIndexed = array_keys($keys) === $keys;
            return $isIndexed ? $options[$default] : $default;
        }
        $keys = array_keys($options);
        $isIndexed = array_keys($keys) === $keys;
        return $isIndexed ? $options[0] : $keys[0];
    }

    /**
     * {@inheritDoc}
     */
    public function secret(string $message): string
    {
        $this->writeLn($message);
        return '';
    }

    /**
     * {@inheritDoc}
     */
    public function block(string|array $messages, ?string $type = null, ?string $prefix = null, bool $padding = true): void
    {
        $messages = is_array($messages) ? $messages : [$messages];
        foreach ($messages as $message) {
            $this->writeLn(($prefix ?? '') . ' ' . $message);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function section(string $message): void
    {
        $this->writeLn($message);
    }

    /**
     * {@inheritDoc}
     */
    public function note(string $message): void
    {
        $this->writeLn('[NOTE] ' . $message);
    }

    /**
     * {@inheritDoc}
     */
    public function caution(string $message): void
    {
        $this->writeLn('[CAUTION] ' . $message);
    }

    /**
     * {@inheritDoc}
     */
    public function listing(array $items): void
    {
        foreach ($items as $item) {
            $this->writeLn(' - ' . $item);
        }
    }

    /** {@inheritDoc} */
    public function table(array $headers, array $rows, int $minWidth = 8, bool $withRowNumber = true): void
    {
        if ($withRowNumber && $rows !== []) {
            $headers = $headers !== [] ? array_merge(['#'], $headers) : ['#'];
            $rows = array_map(fn(array $row, int $i): array => array_merge([$i + 1], $row), $rows, array_keys($rows));
        }
        $cols = empty($rows) ? count($headers) : max(count($headers), max(array_map('count', $rows)));
        if ($cols === 0) {
            return;
        }
        $cellDisplay = static function (mixed $v): string {
            return $v === null ? '-' : (string)$v;
        };
        $widths = [];
        for ($i = 0; $i < $cols; $i++) {
            $w = $minWidth;
            if ($headers !== []) {
                $w = max($w, strlen($cellDisplay($headers[$i] ?? null)));
            }
            foreach ($rows as $row) {
                $w = max($w, strlen($cellDisplay($row[$i] ?? null)));
            }
            $widths[$i] = $w;
        }
        $padLeft = static fn(string $s, int $w) => str_pad($s, $w);
        $padRight = static fn(string $s, int $w) => str_pad($s, $w, ' ', STR_PAD_LEFT);
        if ($headers !== []) {
            $headerCells = [];
            for ($i = 0; $i < $cols; $i++) {
                $s = $cellDisplay($headers[$i] ?? null);
                $headerCells[] = $i === 0 ? $padRight($s, $widths[$i]) : $padLeft($s, $widths[$i]);
            }
            $this->writeLn(implode('  ', $headerCells));
        }
        foreach ($rows as $row) {
            $cells = [];
            for ($i = 0; $i < $cols; $i++) {
                $s = $cellDisplay($row[$i] ?? null);
                $cells[] = $i === 0 ? $padRight($s, $widths[$i]) : $padLeft($s, $widths[$i]);
            }
            $this->writeLn(implode('  ', $cells));
        }
    }

    /**
     * {@inheritDoc}
     */
    public function newLine(int $count = 1): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->writeLn();
        }
    }

    /**
     * {@inheritDoc}
     */
    public function line(string $message = ''): void
    {
        $this->writeLn($message);
    }

    /**
     * {@inheritDoc}
     */
    public function sampleColorizer(): void
    {
        // No-op for testing
    }
}
