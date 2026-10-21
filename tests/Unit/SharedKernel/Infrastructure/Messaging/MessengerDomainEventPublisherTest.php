<?php

declare(strict_types=1);

namespace App\Tests\Unit\SharedKernel\Infrastructure\Messaging;

use App\Recruitment\Domain\Write\Event\JobApplication\JobApplicationEnrichmentWasStarted;
use App\Recruitment\Domain\Write\Event\JobApplication\JobApplicationWasSubmitted;
use App\SharedKernel\Infrastructure\Messaging\MessengerDomainEventPublisher;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\MessageBusInterface;

final class MessengerDomainEventPublisherTest extends TestCase
{
    public function testItDispatchesEveryEventOnTheEventBus(): void
    {
        $dispatched = [];
        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(static function (object $event) use (&$dispatched): Envelope {
            $dispatched[] = $event;

            return new Envelope($event);
        });

        $submitted = new JobApplicationWasSubmitted('app-1', 'position-1', new \DateTimeImmutable());
        $started = new JobApplicationEnrichmentWasStarted('app-1', new \DateTimeImmutable());
        $publisher = new MessengerDomainEventPublisher($bus, $this->createStub(LoggerInterface::class));
        $publisher->publish($submitted, $started);

        self::assertSame([$submitted, $started], $dispatched);
    }

    public function testABrokerOutageIsLoggedInsteadOfFailingTheSavedChange(): void
    {
        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willThrowException(new TransportException('Could not connect to the AMQP server.'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('error')
            ->with('Domain event not published, the reconciler will recover it.', self::arrayHasKey('aggregateId'));

        new MessengerDomainEventPublisher($bus, $logger)->publish(
            new JobApplicationWasSubmitted('app-1', 'position-1', new \DateTimeImmutable()),
            new JobApplicationEnrichmentWasStarted('app-1', new \DateTimeImmutable()),
        );
    }
}
