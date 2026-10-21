<?php

declare(strict_types=1);

namespace App\Recruitment\Application\Command\JobApplication\EnrichJobApplication;

/**
 * Routed to its own asynchronous transport, with its own retry policy.
 */
final class EnrichJobApplicationCommand
{
    public function __construct(
        public readonly string $jobApplicationId,
    ) {
    }
}
