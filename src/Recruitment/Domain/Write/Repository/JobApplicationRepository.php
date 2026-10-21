<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\Write\Repository;

use App\Recruitment\Domain\Write\Aggregate\Root\JobApplication\JobApplication;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\Email;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\JobApplicationId;
use App\Recruitment\Domain\Write\Aggregate\Value\Position\PositionId;
use App\Recruitment\Domain\Write\Exception\JobApplication\JobApplicationAlreadyExistsException;

interface JobApplicationRepository
{
    /**
     * @throws JobApplicationAlreadyExistsException if a new application duplicates an existing
     *                                              one (same position and email)
     */
    public function save(JobApplication $jobApplication): void;

    public function existsForCandidate(PositionId $positionId, Email $email): bool;

    public function findById(JobApplicationId $id): ?JobApplication;

    /**
     * @return iterable<JobApplication>
     */
    public function all(): iterable;
}
