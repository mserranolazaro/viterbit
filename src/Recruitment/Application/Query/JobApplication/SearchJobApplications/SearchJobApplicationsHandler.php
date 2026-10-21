<?php

declare(strict_types=1);

namespace App\Recruitment\Application\Query\JobApplication\SearchJobApplications;

use App\Recruitment\Domain\Read\JobApplicationCriteria;
use App\Recruitment\Domain\Read\JobApplicationViewPage;
use App\Recruitment\Domain\Read\JobApplicationViewRepository;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\JobApplicationStatus;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * Reads straight from the read model: views are already shaped for the list page,
 * so there is no response DTO copying them field by field.
 */
#[AsMessageHandler(bus: 'query.bus')]
final class SearchJobApplicationsHandler
{
    public function __construct(
        private readonly JobApplicationViewRepository $viewRepository,
    ) {
    }

    public function __invoke(SearchJobApplicationsQuery $query): JobApplicationViewPage
    {
        $status = $query->status === null ? null : JobApplicationStatus::tryFrom($query->status);
        $positionId = $query->positionId !== null && Uuid::isValid($query->positionId)
            ? strtolower($query->positionId)
            : null;

        return $this->viewRepository->search(new JobApplicationCriteria(
            status: $status?->value,
            positionId: $positionId,
            search: $query->search,
            page: $query->page,
        ));
    }
}
