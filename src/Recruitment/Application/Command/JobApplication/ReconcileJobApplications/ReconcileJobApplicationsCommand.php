<?php

declare(strict_types=1);

namespace App\Recruitment\Application\Command\JobApplication\ReconcileJobApplications;

/**
 * Sent every minute by the scheduler (see MaintenanceSchedule).
 */
final class ReconcileJobApplicationsCommand
{
    /** Enrichment normally takes seconds, retries included; past this, something was lost. */
    public const string STALE_AFTER = '2 minutes';

    /** Each run is bounded, so a large backlog is worked through over several minutes. */
    public const int BATCH_SIZE = 100;
}
