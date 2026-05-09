<?php

declare(strict_types=1);

namespace Switon\Testing;

use Switon\Core\RandomInterface;
use function chr;
use function ord;
use function sprintf;

/**
 * Deterministic random source for reproducible tests.
 *
 * Use when code depends on `RandomInterface` and test runs must produce stable bytes, ints, uuids, or chars.
 *
 * @see \Switon\Core\RandomInterface
 * @see \Switon\Testing\Container\Container
 */
class MockRandom implements RandomInterface
{
    protected const DEFAULT_SEED = 1;

    /**
     * Fixed byte sequence (if provided).
     *
     * @var array<int>|null
     */
    protected ?array $byteSequence = null;

    /**
     * Current position in byte sequence.
     */
    protected int $position = 0;

    /** Instance-local pseudo-random state. */
    protected int $state;

    /**
     * Create mock random generator.
     *
     * @param array<int>|null $byteSequence Fixed byte sequence (null = use seed)
     * @param int|null $seed Random seed for pseudo-random generation
     */
    public function __construct(?array $byteSequence = null, ?int $seed = null)
    {
        $this->byteSequence = $byteSequence;
        $this->state = $seed ?? self::DEFAULT_SEED;
    }

    /**
     * {@inheritDoc}
     */
    public function bytes(int $length): string
    {
        if ($this->byteSequence !== null) {
            $result = '';
            for ($i = 0; $i < $length; $i++) {
                $result .= chr($this->byteSequence[$this->position % count($this->byteSequence)]);
                $this->position++;
            }
            return $result;
        }

        // Use instance-local pseudo-random state.
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= chr($this->nextInt(0, 255));
        }
        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function int(int $min, int $max): int
    {
        return $this->nextInt($min, $max);
    }

    /**
     * {@inheritDoc}
     */
    public function uuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            $this->nextInt(0, 0xffff),
            $this->nextInt(0, 0xffff),
            $this->nextInt(0, 0xffff),
            $this->nextInt(0, 0x0fff) | 0x4000,
            $this->nextInt(0, 0x3fff) | 0x8000,
            $this->nextInt(0, 0xffff),
            $this->nextInt(0, 0xffff),
            $this->nextInt(0, 0xffff)
        );
    }

    /**
     * {@inheritDoc}
     */
    public function chars(int $length, int $base = 62): string
    {
        $str = '';
        for ($i = 0; $i < $length; $i++) {
            $r = $this->nextInt(0, $base - 1);
            if ($r < 10) {
                $str .= chr(ord('0') + $r);
            } elseif ($r < 36) {
                $str .= chr(ord('a') + $r - 10);
            } else {
                $str .= chr(ord('A') + $r - 36);
            }
        }
        return $str;
    }

    protected function nextInt(int $min, int $max): int
    {
        if ($min === $max) {
            return $min;
        }

        $this->state = (int)(($this->state * 1664525 + 1013904223) & 0xffffffff);
        $range = $max - $min + 1;

        return $min + ($this->state % $range);
    }
}
