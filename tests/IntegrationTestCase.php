<?php

declare(strict_types=1);

namespace App\Tests;

use MongoDB\Database;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Worker;

/**
 * Boots the kernel against a real MongoDB (database "ats_test", emptied before each test).
 * Asynchronous messages wait in in-memory transports until runWorker() consumes them.
 */
abstract class IntegrationTestCase extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
        self::service(Database::class)->drop();
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    protected static function service(string $id): object
    {
        /** @var T */
        return self::getContainer()->get($id);
    }

    /**
     * Runs a real Messenger worker over the asynchronous transports until they are empty,
     * with the configured retries and failure listeners, as the worker container would.
     */
    protected static function runWorker(): void
    {
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
                ['events' => self::transport('events'), 'enrichment' => self::transport('enrichment')],
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
