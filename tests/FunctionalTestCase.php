<?php

declare(strict_types=1);

namespace App\Tests;

use App\Tests\Support\InteractsWithTheApp;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Drives the app through HTTP, like a browser, against a real MongoDB with the sample
 * positions loaded. The kernel is not rebooted between requests, so queued messages
 * survive until runWorker() consumes them.
 */
abstract class FunctionalTestCase extends WebTestCase
{
    use InteractsWithTheApp;

    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();
        self::resetDatabase();
        self::seedPositions();
        // Rate-limit counters survive between tests otherwise (the pool is on disk).
        self::service(CacheItemPoolInterface::class, 'rate_limiter.cache')->clear();
    }

    protected function fragment(string $uri): string
    {
        $this->client->request('GET', $uri, server: ['HTTP_X_REQUESTED_WITH' => 'fetch']);

        return (string) $this->client->getResponse()->getContent();
    }
}
