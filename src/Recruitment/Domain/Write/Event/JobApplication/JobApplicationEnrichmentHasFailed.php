<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\Write\Event\JobApplication;

use App\SharedKernel\Domain\Event\DomainEvent;

final readonly class JobApplicationEnrichmentHasFailed implements DomainEvent
{
    public function __construct(
        public string $jobApplicationId,
        public string $reason,
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
