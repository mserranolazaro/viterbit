<?php

declare(strict_types=1);

namespace App\Recruitment\Application\Command\JobApplication\MarkEnrichmentAsFailed;

final class MarkEnrichmentAsFailedCommand
{
    public function __construct(
        public readonly string $jobApplicationId,
        public readonly string $reason,
    ) {
    }
}
