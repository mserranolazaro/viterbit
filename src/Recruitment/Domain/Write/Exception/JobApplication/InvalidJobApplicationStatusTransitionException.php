<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\Write\Exception\JobApplication;

use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\JobApplicationStatus;

final class InvalidJobApplicationStatusTransitionException extends \DomainException
{
    public static function from(JobApplicationStatus $current, JobApplicationStatus $next): self
    {
        return new self(sprintf('A job application cannot go from "%s" to "%s".', $current->value, $next->value));
    }
}
