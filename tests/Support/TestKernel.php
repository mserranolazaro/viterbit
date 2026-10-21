<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Kernel;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The application kernel plus test-only container tweaks (see KERNEL_CLASS in .env.test).
 */
final class TestKernel extends Kernel
{
    protected function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new KeepQueuedMessagesBetweenRequestsPass());
    }
}
