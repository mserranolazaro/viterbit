<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Messaging;

use App\SharedKernel\Domain\Event\DomainEvent;
use App\SharedKernel\Domain\Event\DomainEventPublisher;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Domain events go to the event bus, which routes them to the asynchronous
 * "events" transport (see config/packages/messenger.yaml).
 *
 * The state is already saved when events are published. If the broker is unreachable, failing
 * the request would only hide a saved change from the user (and invite a duplicate retry), so
 * the failure is logged and the reconciler re-drives what the lost events would have triggered
 * (ADR-011).
 */
final class MessengerDomainEventPublisher implements DomainEventPublisher
{
    public function __construct(
        private readonly MessageBusInterface $eventBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function publish(DomainEvent ...$events): void
    {
        foreach ($events as $event) {
            try {
                $this->eventBus->dispatch($event);
            } catch (TransportException $e) {
                $this->logger->error('Domain event not published, the reconciler will recover it.', [
                    'event' => $event::class,
                    'aggregateId' => $event->aggregateId(),
                    'exception' => $e,
                ]);
            }
        }
    }
}
