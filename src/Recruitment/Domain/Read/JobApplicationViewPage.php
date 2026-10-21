<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\Read;

final readonly class JobApplicationViewPage
{
    /**
     * @param JobApplicationView[] $items Newest first.
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
    ) {
    }

    public function totalPages(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }
}
