<?php

declare(strict_types=1);

namespace App\Recruitment\Application\Query\JobApplication\GetJobApplication;

use App\Recruitment\Domain\Read\JobApplicationView;
use App\Recruitment\Domain\Read\JobApplicationViewRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler(bus: 'query.bus')]
final class GetJobApplicationHandler
{
    public function __construct(
        private readonly JobApplicationViewRepository $viewRepository,
    ) {
    }

    public function __invoke(GetJobApplicationQuery $query): ?JobApplicationView
    {
        if (!Uuid::isValid($query->id)) {
            return null;
        }

        return $this->viewRepository->findById(strtolower($query->id));
    }
}
