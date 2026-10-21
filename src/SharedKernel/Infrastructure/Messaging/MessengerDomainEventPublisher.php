<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Messaging;

use App\SharedKernel\Domain\Event\DomainEvent;
use App\SharedKernel\Domain\Event\DomainEventPublisher;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Domain events go to the event bus, which routes them to the asynchronous
 * "events" transport (see config/packages/messenger.yaml).
 */
final class MessengerDomainEventPublisher implements DomainEventPublisher
{
    public function __construct(
        private readonly MessageBusInterface $eventBus,
    ) {
    }

    public function publish(DomainEvent ...$events): void
    {
        foreach ($events as $event) {
            $this->eventBus->dispatch($event);
        }
    }
}
