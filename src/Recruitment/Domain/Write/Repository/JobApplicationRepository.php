<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\Write\Repository;

use App\Recruitment\Domain\Write\Aggregate\Root\JobApplication\JobApplication;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\JobApplicationId;

interface JobApplicationRepository
{
    public function save(JobApplication $jobApplication): void;

    public function findById(JobApplicationId $id): ?JobApplication;

    /**
     * @return iterable<JobApplication>
     */
    public function all(): iterable;
}
