<?php

declare(strict_types=1);

namespace App\Tests;

use App\Tests\Support\InteractsWithTheApp;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Boots the kernel against a real MongoDB, emptied before each test.
 */
abstract class IntegrationTestCase extends KernelTestCase
{
    use InteractsWithTheApp;

    protected function setUp(): void
    {
        self::bootKernel();
        self::resetDatabase();
    }
}
