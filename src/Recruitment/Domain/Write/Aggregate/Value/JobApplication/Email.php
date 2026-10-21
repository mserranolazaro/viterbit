<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\Write\Aggregate\Value\JobApplication;

use App\Recruitment\Domain\Write\Exception\JobApplication\InvalidJobApplicationDataException;

final readonly class Email
{
    private function __construct(private string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $value = strtolower(trim($value));
        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw InvalidJobApplicationDataException::invalidEmail($value);
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }
}
