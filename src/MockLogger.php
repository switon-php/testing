<?php

declare(strict_types=1);

namespace Switon\Testing;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Stringable;

/**
 * In-memory PSR-3 logger for assertions in tests.
 *
 * Use when tests need to verify logged levels, messages, and context without external appenders.
 *
 * @see \Psr\Log\LoggerInterface
 * @see \Switon\Testing\Container\Container
 */
class MockLogger implements LoggerInterface
{
    /**
     * @var array<array{level: string, message: string, context: array}> Collected log entries
     */
    public array $logs = [];

    public function emergency(string|Stringable $message, array $context = []): void
    {
        $this->logs[] = ['level' => LogLevel::EMERGENCY, 'message' => (string)$message, 'context' => $context];
    }

    public function alert(string|Stringable $message, array $context = []): void
    {
        $this->logs[] = ['level' => LogLevel::ALERT, 'message' => (string)$message, 'context' => $context];
    }

    public function critical(string|Stringable $message, array $context = []): void
    {
        $this->logs[] = ['level' => LogLevel::CRITICAL, 'message' => (string)$message, 'context' => $context];
    }

    public function error(string|Stringable $message, array $context = []): void
    {
        $this->logs[] = ['level' => LogLevel::ERROR, 'message' => (string)$message, 'context' => $context];
    }

    public function warning(string|Stringable $message, array $context = []): void
    {
        $this->logs[] = ['level' => LogLevel::WARNING, 'message' => (string)$message, 'context' => $context];
    }

    public function notice(string|Stringable $message, array $context = []): void
    {
        $this->logs[] = ['level' => LogLevel::NOTICE, 'message' => (string)$message, 'context' => $context];
    }

    public function info(string|Stringable $message, array $context = []): void
    {
        $this->logs[] = ['level' => LogLevel::INFO, 'message' => (string)$message, 'context' => $context];
    }

    public function debug(string|Stringable $message, array $context = []): void
    {
        $this->logs[] = ['level' => LogLevel::DEBUG, 'message' => (string)$message, 'context' => $context];
    }

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->logs[] = ['level' => (string)$level, 'message' => (string)$message, 'context' => $context];
    }

    /**
     * Clears all collected logs.
     */
    public function clear(): void
    {
        $this->logs = [];
    }

    /**
     * Whether a matching level/message entry exists.
     */
    public function hasLog(string $level, string $message): bool
    {
        foreach ($this->logs as $log) {
            if ($log['level'] === $level && str_contains($log['message'], $message)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns the last collected log entry.
     *
     * @return array{level: string, message: string, context: array}|null
     */
    public function getLastLog(): ?array
    {
        return $this->logs[count($this->logs) - 1] ?? null;
    }

    /**
     * Returns all collected log entries for one level.
     *
     * @return array<array{level: string, message: string, context: array}>
     */
    public function getLogsByLevel(string $level): array
    {
        $logs = [];
        foreach ($this->logs as $log) {
            if ($log['level'] === $level) {
                $logs[] = $log;
            }
        }
        return $logs;
    }
}
