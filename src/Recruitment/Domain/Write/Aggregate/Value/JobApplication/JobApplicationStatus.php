<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\Write\Aggregate\Value\JobApplication;

/**
 * Lifecycle of the AI enrichment: received → enriching → enriched | failed.
 * The allowed transitions live here, in one place.
 */
enum JobApplicationStatus: string
{
    case Received = 'received';
    case Enriching = 'enriching';
    case Enriched = 'enriched';
    case Failed = 'failed';

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::Received => [self::Enriching, self::Failed],
            self::Enriching => [self::Enriched, self::Failed],
            self::Enriched, self::Failed => [],
        }, true);
    }

    public function isFinal(): bool
    {
        return $this === self::Enriched || $this === self::Failed;
    }
}
