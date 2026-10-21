<?php

declare(strict_types=1);

namespace App\SharedKernel\Domain\Event;

interface DomainEvent
{
    public function aggregateId(): string;

    public function occurredOn(): \DateTimeImmutable;
}
