<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\Write\Aggregate\Value\JobApplication;

/**
 * How well the CV fits the position, from 0 (no fit) to 100 (perfect fit).
 */
final readonly class Score
{
    public const int MIN = 0;
    public const int MAX = 100;

    private function __construct(private int $value)
    {
    }

    public static function fromInt(int $value): self
    {
        if ($value < self::MIN || $value > self::MAX) {
            throw new \InvalidArgumentException(
                sprintf('A score must be between %d and %d, got %d.', self::MIN, self::MAX, $value),
            );
        }

        return new self($value);
    }

    public function value(): int
    {
        return $this->value;
    }
}
