<?php

declare(strict_types=1);

namespace App\Recruitment\Application\Service;

use App\Recruitment\Domain\Read\JobApplicationView;
use App\Recruitment\Domain\Read\JobApplicationViewRepository;
use App\Recruitment\Domain\Write\Aggregate\Root\JobApplication\JobApplication;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\JobApplicationId;
use App\Recruitment\Domain\Write\Repository\JobApplicationRepository;
use App\Recruitment\Domain\Write\Repository\PositionRepository;

/**
 * Builds the read model from the current state of the write model. Used by the event
 * subscriber and by the rebuild command, so both always produce the same view.
 */
final class JobApplicationViewProjector
{
    private const string UNKNOWN_POSITION = 'Unknown position';

    public function __construct(
        private readonly JobApplicationRepository $jobApplicationRepository,
        private readonly PositionRepository $positionRepository,
        private readonly JobApplicationViewRepository $viewRepository,
    ) {
    }

    public function project(JobApplicationId $id): void
    {
        $jobApplication = $this->jobApplicationRepository->findById($id);
        if ($jobApplication === null) {
            return;
        }

        $this->viewRepository->save($this->viewOf($jobApplication));
    }

    public function viewOf(JobApplication $jobApplication): JobApplicationView
    {
        $position = $this->positionRepository->findById($jobApplication->positionId());
        $candidate = $jobApplication->candidate();
        $enrichment = $jobApplication->enrichment();

        return new JobApplicationView(
            id: $jobApplication->id()->value(),
            positionId: $jobApplication->positionId()->value(),
            positionTitle: $position?->title() ?? self::UNKNOWN_POSITION,
            candidateName: $candidate->fullName(),
            email: $candidate->email()->value(),
            phone: $candidate->phone(),
            notes: $jobApplication->notes(),
            cvText: $jobApplication->cvText()->value(),
            status: $jobApplication->status()->value,
            score: $enrichment?->score->value(),
            summary: $enrichment?->summary,
            promptVersion: $enrichment?->promptVersion,
            failureReason: $jobApplication->failureReason(),
            appliedAt: $jobApplication->appliedAt(),
            updatedAt: $jobApplication->updatedAt(),
            enrichedAt: $enrichment?->enrichedAt,
            version: $jobApplication->version(),
            matchedSkills: $enrichment->matchedSkills ?? [],
            missingSkills: $enrichment->missingSkills ?? [],
        );
    }
}
