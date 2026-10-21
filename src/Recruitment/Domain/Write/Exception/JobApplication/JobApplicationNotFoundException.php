<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\Write\Exception\JobApplication;

use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\JobApplicationId;

final class JobApplicationNotFoundException extends \DomainException
{
    public static function withId(JobApplicationId $id): self
    {
        return new self(sprintf('Job application "%s" not found.', $id->value()));
    }
}
