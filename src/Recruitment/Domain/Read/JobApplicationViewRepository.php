<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\Read;

interface JobApplicationViewRepository
{
    /**
     * Inserts or replaces the whole view, unless the stored one is already as new
     * (same or higher version): applying it twice, or out of order, is harmless.
     */
    public function save(JobApplicationView $view): void;

    /**
     * Replaces the view whatever its version, for rebuilding the read model.
     */
    public function overwrite(JobApplicationView $view): void;

    public function findById(string $id): ?JobApplicationView;

    /**
     * Newest first (by appliedAt), filtered by status and position, searched by name or email.
     */
    public function search(JobApplicationCriteria $criteria): JobApplicationViewPage;
}
