<?php

declare(strict_types=1);

namespace App\Recruitment\Infrastructure\Scheduler;

use App\Recruitment\Application\Command\JobApplication\ReconcileJobApplications\ReconcileJobApplicationsCommand;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * Recurring jobs, run by worker-events (it consumes the "scheduler_maintenance" transport).
 */
#[AsSchedule('maintenance')]
final class MaintenanceSchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return new Schedule()->add(
            RecurringMessage::every('1 minute', new ReconcileJobApplicationsCommand()),
        );
    }
}
