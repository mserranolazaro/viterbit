<?php

declare(strict_types=1);

namespace App\SharedKernel\Domain\Aggregate;

use App\SharedKernel\Domain\Event\DomainEvent;

abstract class AggregateRoot
{
    /** @var DomainEvent[] */
    private array $domainEvents = [];

    protected function record(DomainEvent $event): void
    {
        $this->domainEvents[] = $event;
    }

    /**
     * Returns the events recorded since the last call and forgets them,
     * so they are published exactly once per change.
     *
     * @return DomainEvent[]
     */
    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }
}
