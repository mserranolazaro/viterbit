<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\Write\Service;

/**
 * The analyzer could not produce a valid result (provider error, malformed or
 * schema-invalid output). Considered transient: the enrichment is retried.
 */
final class CvAnalysisFailedException extends \RuntimeException
{
    public static function because(string $reason, ?\Throwable $previous = null): self
    {
        return new self($reason, 0, $previous);
    }
}
