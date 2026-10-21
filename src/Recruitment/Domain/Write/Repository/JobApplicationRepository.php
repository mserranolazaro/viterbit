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

    /**
     * Applications still received or enriching whose last change, and last re-drive, are older
     * than $before: an enrichment that should have finished by now. Oldest first, at most $limit.
     *
     * @return iterable<JobApplication>
     */
    public function findUnfinishedSince(\DateTimeImmutable $before, int $limit): iterable;

    /**
     * Records that the reconciler re-drove it, so it waits a whole window before the next one.
     * Forgotten as soon as the application changes (the next save replaces the document).
     */
    public function markRedriven(JobApplicationId $id, \DateTimeImmutable $at): void;
}
