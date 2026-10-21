<?php

declare(strict_types=1);

namespace App\Recruitment\Application\Command\JobApplication\MarkEnrichmentAsFailed;

use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\JobApplicationId;
use App\Recruitment\Domain\Write\Repository\JobApplicationRepository;
use App\SharedKernel\Domain\Event\DomainEventPublisher;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Dispatched once the enrichment has run out of retries.
 */
#[AsMessageHandler(bus: 'command.bus')]
final class MarkEnrichmentAsFailedHandler
{
    public function __construct(
        private readonly JobApplicationRepository $jobApplicationRepository,
        private readonly DomainEventPublisher $eventPublisher,
        private readonly ClockInterface $clock,
    ) {
    }

    public function __invoke(MarkEnrichmentAsFailedCommand $command): void
    {
        $jobApplication = $this->jobApplicationRepository->findById(
            JobApplicationId::fromString($command->jobApplicationId),
        );

        if ($jobApplication === null || $jobApplication->status()->isFinal()) {
            return;
        }

        $jobApplication->failEnrichment($command->reason, $this->clock->now());
        $this->jobApplicationRepository->save($jobApplication);
        $this->eventPublisher->publish(...$jobApplication->pullDomainEvents());
    }
}
