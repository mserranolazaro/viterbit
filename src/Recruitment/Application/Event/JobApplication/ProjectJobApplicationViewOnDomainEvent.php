<?php

declare(strict_types=1);

namespace App\Recruitment\Application\Event\JobApplication;

use App\Recruitment\Application\Service\JobApplicationViewProjector;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\JobApplicationId;
use App\Recruitment\Domain\Write\Event\JobApplication\JobApplicationEnrichmentHasFailed;
use App\Recruitment\Domain\Write\Event\JobApplication\JobApplicationEnrichmentWasStarted;
use App\Recruitment\Domain\Write\Event\JobApplication\JobApplicationWasEnriched;
use App\Recruitment\Domain\Write\Event\JobApplication\JobApplicationWasSubmitted;
use App\SharedKernel\Domain\Event\DomainEvent;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Every job application event refreshes its view. The event only says which one changed.
 */
#[AsMessageHandler(bus: 'event.bus', handles: JobApplicationWasSubmitted::class)]
#[AsMessageHandler(bus: 'event.bus', handles: JobApplicationEnrichmentWasStarted::class)]
#[AsMessageHandler(bus: 'event.bus', handles: JobApplicationWasEnriched::class)]
#[AsMessageHandler(bus: 'event.bus', handles: JobApplicationEnrichmentHasFailed::class)]
final class ProjectJobApplicationViewOnDomainEvent
{
    public function __construct(
        private readonly JobApplicationViewProjector $projector,
    ) {
    }

    public function __invoke(DomainEvent $event): void
    {
        $this->projector->project(JobApplicationId::fromString($event->aggregateId()));
    }
}
