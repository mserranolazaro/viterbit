<?php

declare(strict_types=1);

namespace App\SharedKernel\Domain\Event;

interface DomainEventPublisher
{
    public function publish(DomainEvent ...$events): void;
}
