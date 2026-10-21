<?php

declare(strict_types=1);

namespace App\Recruitment\Infrastructure\Messaging;

use App\Recruitment\Application\Command\JobApplication\EnrichJobApplication\EnrichJobApplicationCommand;
use App\Recruitment\Application\Command\JobApplication\MarkEnrichmentAsFailed\MarkEnrichmentAsFailedCommand;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

/**
 * Only the worker knows when an enrichment has run out of retries: at that point
 * the application is marked as failed, so it does not stay "enriching" forever.
 */
#[AsEventListener]
final class MarkEnrichmentAsFailedOnFinalFailure
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(WorkerMessageFailedEvent $event): void
    {
        $message = $event->getEnvelope()->getMessage();
        if (!$message instanceof EnrichJobApplicationCommand || $event->willRetry()) {
            return;
        }

        $attempts = ($event->getEnvelope()->last(RedeliveryStamp::class)?->getRetryCount() ?? 0) + 1;

        $this->commandBus->dispatch(new MarkEnrichmentAsFailedCommand(
            $message->jobApplicationId,
            sprintf('Enrichment failed after %d attempt(s): %s', $attempts, $this->reasonOf($event->getThrowable())),
        ));
    }

    private function reasonOf(\Throwable $throwable): string
    {
        if ($throwable instanceof HandlerFailedException) {
            $wrapped = $throwable->getWrappedExceptions();
            $throwable = reset($wrapped) ?: $throwable;
        }

        return $throwable->getMessage();
    }
}
