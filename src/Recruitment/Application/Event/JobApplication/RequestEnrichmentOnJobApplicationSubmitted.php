<?php

declare(strict_types=1);

namespace App\Recruitment\Application\Event\JobApplication;

use App\Recruitment\Application\Command\JobApplication\EnrichJobApplication\EnrichJobApplicationCommand;
use App\Recruitment\Domain\Write\Event\JobApplication\JobApplicationWasSubmitted;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Turns the fact (an application was submitted) into an intent (enrich it), which has
 * its own queue and retry policy and can be re-sent on its own.
 */
#[AsMessageHandler(bus: 'event.bus')]
final class RequestEnrichmentOnJobApplicationSubmitted
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(JobApplicationWasSubmitted $event): void
    {
        $this->commandBus->dispatch(new EnrichJobApplicationCommand($event->jobApplicationId));
    }
}
