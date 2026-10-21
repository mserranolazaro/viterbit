<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;

final class KernelBootTest extends KernelTestCase
{
    public function testItBootsWithTheThreeBuses(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        self::assertInstanceOf(MessageBusInterface::class, $container->get('command.bus'));
        self::assertInstanceOf(MessageBusInterface::class, $container->get('query.bus'));
        self::assertInstanceOf(MessageBusInterface::class, $container->get('event.bus'));
    }
}
