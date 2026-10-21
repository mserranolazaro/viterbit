<?php

declare(strict_types=1);

namespace App\Tests\Unit\Recruitment\Infrastructure\Scheduler;

use App\Recruitment\Application\Command\JobApplication\ReconcileJobApplications\ReconcileJobApplicationsCommand;
use App\Recruitment\Infrastructure\Scheduler\MaintenanceSchedule;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Generator\MessageContext;

final class MaintenanceScheduleTest extends TestCase
{
    public function testItRunsTheReconcilerEveryMinute(): void
    {
        $messages = new MaintenanceSchedule()->getSchedule()->getRecurringMessages();
        self::assertCount(1, $messages);

        $trigger = $messages[0]->getTrigger();
        $now = new \DateTimeImmutable('2026-10-21 12:00:00');
        self::assertEquals($now->modify('+1 minute'), $trigger->getNextRunDate($now));

        $context = new MessageContext('maintenance', 'id', $trigger, $now);
        $produced = iterator_to_array($messages[0]->getMessages($context), false);
        self::assertInstanceOf(ReconcileJobApplicationsCommand::class, $produced[0]);
    }
}
