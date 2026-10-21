<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\Read;

final readonly class JobApplicationCriteria
{
    public const int DEFAULT_PER_PAGE = 20;

    public ?string $search;
    public int $page;

    public function __construct(
        public ?string $status = null,
        public ?string $positionId = null,
        ?string $search = null,
        int $page = 1,
        public int $perPage = self::DEFAULT_PER_PAGE,
    ) {
        $search = $search === null ? null : trim($search);
        $this->search = $search === '' ? null : $search;
        $this->page = max(1, $page);
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }
}
