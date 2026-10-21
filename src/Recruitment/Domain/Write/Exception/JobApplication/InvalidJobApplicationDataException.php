<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\Write\Exception\JobApplication;

/**
 * Candidate input that breaks a domain invariant. The HTTP layer validates first and
 * shows friendly messages; this is the last line of defence.
 */
final class InvalidJobApplicationDataException extends \DomainException
{
    public static function emptyFullName(): self
    {
        return new self('The candidate full name cannot be empty.');
    }

    public static function fullNameTooLong(int $max): self
    {
        return new self(sprintf('The candidate full name cannot exceed %d characters.', $max));
    }

    public static function invalidEmail(string $email): self
    {
        return new self(sprintf('"%s" is not a valid email address.', $email));
    }

    public static function invalidPhone(string $phone): self
    {
        return new self(sprintf('"%s" is not a valid phone number.', $phone));
    }

    public static function emptyCv(): self
    {
        return new self('The CV text cannot be empty.');
    }

    public static function cvTooLong(int $max): self
    {
        return new self(sprintf('The CV text cannot exceed %d characters.', $max));
    }

    public static function privacyConsentRequired(): self
    {
        return new self('The candidate must accept the privacy notice to apply.');
    }

    public static function notesTooLong(int $max): self
    {
        return new self(sprintf('The notes cannot exceed %d characters.', $max));
    }
}
