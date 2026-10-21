<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\Write\Aggregate\Value\JobApplication;

/**
 * What the CV analyzer returns. The prompt version travels with the result so every
 * stored score can be traced back to the prompt that produced it.
 */
final readonly class CvAnalysis
{
    public string $summary;

    public function __construct(string $summary, public Score $score, public string $promptVersion)
    {
        $summary = trim($summary);
        if ($summary === '') {
            throw new \InvalidArgumentException('A CV analysis needs a summary.');
        }

        $this->summary = $summary;
    }
}
