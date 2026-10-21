<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\Write\Event\JobApplication;

use App\SharedKernel\Domain\Event\DomainEvent;

/**
 * Events carry ids and the facts that changed, not the whole aggregate: subscribers
 * that need more (the read-model projection) load the current state.
 */
final readonly class JobApplicationWasSubmitted implements DomainEvent
{
    public function __construct(
        public string $jobApplicationId,
        public string $positionId,
        private \DateTimeImmutable $occurredOn,
    ) {
    }

    public function aggregateId(): string
    {
        return $this->jobApplicationId;
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
