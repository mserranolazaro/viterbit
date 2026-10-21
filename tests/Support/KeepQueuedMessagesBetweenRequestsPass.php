<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Symfony empties in-memory transports between two requests of the same test client.
 * A real broker keeps messages until a worker takes them, so functional tests do too:
 * submit, look at the "pending" page, then run the worker.
 */
final class KeepQueuedMessagesBetweenRequestsPass implements CompilerPassInterface
{
    /** The factory resets every in-memory transport it created; so does each transport. */
    private const array SERVICES = [
        'messenger.transport.in_memory.factory',
        'messenger.transport.events',
        'messenger.transport.enrichment',
        'messenger.transport.failed',
    ];

    public function process(ContainerBuilder $container): void
    {
        foreach (self::SERVICES as $id) {
            if ($container->hasDefinition($id)) {
                $container->getDefinition($id)->clearTag('kernel.reset');
            }
        }
    }
}
