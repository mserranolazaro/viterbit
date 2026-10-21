<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\Read;

interface JobApplicationViewRepository
{
    /**
     * Inserts or replaces the whole view, so applying it twice is harmless.
     */
    public function save(JobApplicationView $view): void;

    public function findById(string $id): ?JobApplicationView;

    /**
     * Newest first (by appliedAt), filtered by status and position, searched by name or email.
     */
    public function search(JobApplicationCriteria $criteria): JobApplicationViewPage;
}
