<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\Write\Aggregate\Value\JobApplication;

use App\Recruitment\Domain\Write\Exception\JobApplication\InvalidJobApplicationDataException;

/**
 * The CV as pasted by the candidate. Kept verbatim (only trimmed) because recruiters
 * read the original in the detail view.
 */
final readonly class CvText
{
    public const int MAX_LENGTH = 20000;

    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $value = trim($value);
        if ($value === '') {
            throw InvalidJobApplicationDataException::emptyCv();
        }

        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw InvalidJobApplicationDataException::cvTooLong(self::MAX_LENGTH);
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }
}
