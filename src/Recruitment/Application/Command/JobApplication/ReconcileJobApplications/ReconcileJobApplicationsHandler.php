<?php

declare(strict_types=1);

namespace App\Recruitment\Application\Command\JobApplication\ReconcileJobApplications;

use App\Recruitment\Application\Command\JobApplication\EnrichJobApplication\EnrichJobApplicationCommand;
use App\Recruitment\Application\Service\JobApplicationViewProjector;
use App\Recruitment\Domain\Write\Repository\JobApplicationRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Safety net for lost messages (broker down when an event was published, a message dropped):
 * every application that should have been enriched by now gets its view refreshed and its
 * enrichment requested again. Both steps are idempotent, so running it twice is harmless.
 */
#[AsMessageHandler(bus: 'command.bus')]
final class ReconcileJobApplicationsHandler
{
    public function __construct(
        private readonly JobApplicationRepository $jobApplicationRepository,
        private readonly JobApplicationViewProjector $projector,
        private readonly MessageBusInterface $commandBus,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return int How many applications were re-driven
     */
    public function __invoke(ReconcileJobApplicationsCommand $command): int
    {
        $before = $this->clock->now()->modify('-' . ReconcileJobApplicationsCommand::STALE_AFTER);
        $count = 0;

        foreach ($this->jobApplicationRepository->findUnfinishedSince($before) as $jobApplication) {
            $this->projector->project($jobApplication->id());
            $this->commandBus->dispatch(new EnrichJobApplicationCommand($jobApplication->id()->value()));
            ++$count;
        }

        if ($count > 0) {
            $this->logger->warning('Re-drove unfinished job applications.', ['count' => $count]);
        }

        return $count;
    }
}
