<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\SharedKernel\Domain\Event\DomainEvent;
use App\SharedKernel\Domain\Event\DomainEventPublisher;

/**
 * Records published events so tests can assert on what and in which order.
 */
final class SpyDomainEventPublisher implements DomainEventPublisher
{
    /** @var DomainEvent[] */
    public array $published = [];

    public function publish(DomainEvent ...$events): void
    {
        array_push($this->published, ...$events);
    }

    /**
     * @return array<class-string<DomainEvent>>
     */
    public function publishedClasses(): array
    {
        return array_map(static fn (DomainEvent $event): string => $event::class, $this->published);
    }
}
