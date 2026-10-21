<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\Write\Aggregate\Value\JobApplication;

use App\Recruitment\Domain\Write\Exception\JobApplication\InvalidJobApplicationDataException;
use App\SharedKernel\Domain\Value\Text;

final readonly class Candidate
{
    public const int FULL_NAME_MAX_LENGTH = 120;
    /** Shared with the HTTP form validation, so both layers accept exactly the same numbers. */
    public const string PHONE_PATTERN = '/^\+?[0-9 ()\-]{6,20}$/';

    private function __construct(
        private string $fullName,
        private Email $email,
        private ?string $phone,
    ) {
    }

    public static function create(string $fullName, Email $email, ?string $phone): self
    {
        $fullName = Text::singleLine($fullName);
        if ($fullName === '') {
            throw InvalidJobApplicationDataException::emptyFullName();
        }

        if (mb_strlen($fullName) > self::FULL_NAME_MAX_LENGTH) {
            throw InvalidJobApplicationDataException::fullNameTooLong(self::FULL_NAME_MAX_LENGTH);
        }

        $phone = $phone === null ? null : Text::singleLine($phone);
        if ($phone === '') {
            $phone = null;
        }

        if ($phone !== null && preg_match(self::PHONE_PATTERN, $phone) !== 1) {
            throw InvalidJobApplicationDataException::invalidPhone($phone);
        }

        return new self($fullName, $email, $phone);
    }

    public function fullName(): string
    {
        return $this->fullName;
    }

    public function email(): Email
    {
        return $this->email;
    }

    public function phone(): ?string
    {
        return $this->phone;
    }
}
