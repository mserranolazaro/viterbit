<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Recruitment\Domain\Write\Repository\PositionRepository;
use App\Recruitment\Infrastructure\Console\SeedPositionsCommand;
use MongoDB\Database;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Worker;

/**
 * Shared by integration and functional tests: a real MongoDB (database "ats_test")
 * and a real Messenger worker over the in-memory transports.
 */
trait InteractsWithTheApp
{
    protected static function resetDatabase(): void
    {
        self::service(Database::class)->drop();
    }

    protected static function seedPositions(): void
    {
        foreach (SeedPositionsCommand::positions() as $position) {
            self::service(PositionRepository::class)->save($position);
        }
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $type
     *
     * @return T
     */
    protected static function service(string $type, ?string $id = null): object
    {
        /** @var T */
        return self::getContainer()->get($id ?? $type);
    }

    /**
     * Runs a real Messenger worker over the asynchronous transports until they are empty,
     * with the configured retries and failure listeners, as the worker container would.
     */
    protected static function runWorker(string ...$transports): void
    {
        $transports = $transports ?: ['events', 'enrichment'];
        $container = self::getContainer();
        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $container->get('event_dispatcher');
        /** @var MessageBusInterface $bus */
        $bus = $container->get('messenger.routable_message_bus');

        $stopWhenIdle = static function (WorkerRunningEvent $event): void {
            if ($event->isWorkerIdle()) {
                $event->getWorker()->stop();
            }
        };
        $dispatcher->addListener(WorkerRunningEvent::class, $stopWhenIdle);

        try {
            new Worker(
                array_combine($transports, array_map(self::transport(...), $transports)),
                $bus,
                $dispatcher,
            )->run(['sleep' => 0]);
        } finally {
            $dispatcher->removeListener(WorkerRunningEvent::class, $stopWhenIdle);
        }
    }

    protected static function transport(string $name): InMemoryTransport
    {
        /** @var InMemoryTransport */
        return self::getContainer()->get('messenger.transport.' . $name);
    }
}
