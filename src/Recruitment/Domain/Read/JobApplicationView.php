<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\Read;

/**
 * Read model of a job application, shaped for the list and detail pages: flat,
 * with the position title denormalised and a lower-cased search field.
 */
final readonly class JobApplicationView
{
    public string $searchText;

    /**
     * @param string[] $matchedSkills
     * @param string[] $missingSkills
     */
    public function __construct(
        public string $id,
        public string $positionId,
        public string $positionTitle,
        public string $candidateName,
        public string $email,
        public ?string $phone,
        public ?string $notes,
        public string $cvText,
        public string $status,
        public ?int $score,
        public ?string $summary,
        public ?string $promptVersion,
        public ?string $failureReason,
        public \DateTimeImmutable $appliedAt,
        public \DateTimeImmutable $updatedAt,
        public ?\DateTimeImmutable $enrichedAt,
        public int $version,
        public array $matchedSkills = [],
        public array $missingSkills = [],
    ) {
        $this->searchText = mb_strtolower($candidateName . ' ' . $email);
    }
}
