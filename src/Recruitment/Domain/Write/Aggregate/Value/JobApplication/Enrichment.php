<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\Write\Aggregate\Value\JobApplication;

final readonly class Enrichment
{
    /**
     * @param string[] $matchedSkills
     * @param string[] $missingSkills
     */
    public function __construct(
        public string $summary,
        public Score $score,
        public string $promptVersion,
        public \DateTimeImmutable $enrichedAt,
        public array $matchedSkills = [],
        public array $missingSkills = [],
    ) {
    }

    public static function fromAnalysis(CvAnalysis $analysis, \DateTimeImmutable $enrichedAt): self
    {
        return new self(
            $analysis->summary,
            $analysis->score,
            $analysis->promptVersion,
            $enrichedAt,
            $analysis->matchedSkills,
            $analysis->missingSkills,
        );
    }
}
