<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\Write\Exception\JobApplication;

use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\Email;
use App\Recruitment\Domain\Write\Aggregate\Value\Position\PositionId;

/**
 * One application per candidate (email) and position: a double click, a refresh or a retry
 * after an error must not create a second one.
 */
final class JobApplicationAlreadyExistsException extends \DomainException
{
    public static function forCandidate(Email $email, PositionId $positionId): self
    {
        return new self(sprintf(
            'An application from "%s" to position "%s" already exists.',
            $email->value(),
            $positionId->value(),
        ));
    }
}
