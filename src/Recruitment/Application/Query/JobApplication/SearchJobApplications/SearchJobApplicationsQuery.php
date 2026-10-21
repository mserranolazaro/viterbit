<?php

declare(strict_types=1);

namespace App\Recruitment\Application\Query\JobApplication\SearchJobApplications;

/**
 * Raw filters as they come from the request; the handler ignores values it does not understand.
 */
final class SearchJobApplicationsQuery
{
    public function __construct(
        public readonly ?string $status = null,
        public readonly ?string $positionId = null,
        public readonly ?string $search = null,
        public readonly int $page = 1,
    ) {
    }
}
