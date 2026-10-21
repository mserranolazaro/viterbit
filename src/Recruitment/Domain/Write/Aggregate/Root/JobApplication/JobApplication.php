<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\Write\Aggregate\Root\JobApplication;

use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\Candidate;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\CvAnalysis;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\CvText;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\Enrichment;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\JobApplicationId;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\JobApplicationStatus;
use App\Recruitment\Domain\Write\Aggregate\Value\Position\PositionId;
use App\Recruitment\Domain\Write\Event\JobApplication\JobApplicationEnrichmentHasFailed;
use App\Recruitment\Domain\Write\Event\JobApplication\JobApplicationEnrichmentWasStarted;
use App\Recruitment\Domain\Write\Event\JobApplication\JobApplicationWasEnriched;
use App\Recruitment\Domain\Write\Event\JobApplication\JobApplicationWasSubmitted;
use App\Recruitment\Domain\Write\Exception\JobApplication\InvalidJobApplicationDataException;
use App\Recruitment\Domain\Write\Exception\JobApplication\InvalidJobApplicationStatusTransitionException;
use App\SharedKernel\Domain\Aggregate\AggregateRoot;

/**
 * A candidate's application to a position, and the state of its AI enrichment.
 * Named JobApplication (not Application) so it never reads like the Application layer.
 */
final class JobApplication extends AggregateRoot
{
    public const int NOTES_MAX_LENGTH = 2000;

    private function __construct(
        private readonly JobApplicationId $id,
        private readonly PositionId $positionId,
        private readonly Candidate $candidate,
        private readonly ?string $notes,
        private readonly CvText $cvText,
        private JobApplicationStatus $status,
        private readonly \DateTimeImmutable $appliedAt,
        private \DateTimeImmutable $updatedAt,
        private ?Enrichment $enrichment,
        private ?string $failureReason,
        private int $version,
    ) {
    }

    /**
     * Personal data (the CV above all) is only processed with the candidate's consent, so an
     * application cannot exist without it, whatever the entry point. appliedAt records when it was given.
     */
    public static function submit(
        JobApplicationId $id,
        PositionId $positionId,
        Candidate $candidate,
        ?string $notes,
        CvText $cvText,
        bool $privacyConsentGiven,
        \DateTimeImmutable $now,
    ): self {
        if (!$privacyConsentGiven) {
            throw InvalidJobApplicationDataException::privacyConsentRequired();
        }

        $notes = $notes === null ? null : trim($notes);
        if ($notes !== null && mb_strlen($notes) > self::NOTES_MAX_LENGTH) {
            throw InvalidJobApplicationDataException::notesTooLong(self::NOTES_MAX_LENGTH);
        }

        $jobApplication = new self(
            $id,
            $positionId,
            $candidate,
            $notes === '' ? null : $notes,
            $cvText,
            JobApplicationStatus::Received,
            $now,
            $now,
            null,
            null,
            1,
        );

        $jobApplication->record(new JobApplicationWasSubmitted($id->value(), $positionId->value(), $now));

        return $jobApplication;
    }

    /**
     * Rebuilds an existing application from storage. Records no events: nothing happened.
     */
    public static function reconstitute(
        JobApplicationId $id,
        PositionId $positionId,
        Candidate $candidate,
        ?string $notes,
        CvText $cvText,
        JobApplicationStatus $status,
        \DateTimeImmutable $appliedAt,
        \DateTimeImmutable $updatedAt,
        ?Enrichment $enrichment,
        ?string $failureReason,
        int $version,
    ): self {
        return new self(
            $id,
            $positionId,
            $candidate,
            $notes,
            $cvText,
            $status,
            $appliedAt,
            $updatedAt,
            $enrichment,
            $failureReason,
            $version,
        );
    }

    public function startEnrichment(\DateTimeImmutable $now): void
    {
        // A redelivered or retried message finds it already enriching: nothing to do.
        if ($this->status === JobApplicationStatus::Enriching) {
            return;
        }

        $this->transitionTo(JobApplicationStatus::Enriching, $now);
        $this->record(new JobApplicationEnrichmentWasStarted($this->id->value(), $now));
    }

    public function completeEnrichment(CvAnalysis $analysis, \DateTimeImmutable $now): void
    {
        $this->transitionTo(JobApplicationStatus::Enriched, $now);
        $this->enrichment = Enrichment::fromAnalysis($analysis, $now);
        $this->record(new JobApplicationWasEnriched($this->id->value(), $analysis->score->value(), $now));
    }

    public function failEnrichment(string $reason, \DateTimeImmutable $now): void
    {
        $this->transitionTo(JobApplicationStatus::Failed, $now);
        $this->failureReason = $reason;
        $this->record(new JobApplicationEnrichmentHasFailed($this->id->value(), $reason, $now));
    }

    private function transitionTo(JobApplicationStatus $next, \DateTimeImmutable $now): void
    {
        if (!$this->status->canTransitionTo($next)) {
            throw InvalidJobApplicationStatusTransitionException::from($this->status, $next);
        }

        $this->status = $next;
        $this->updatedAt = $now;
        ++$this->version;
    }

    public function id(): JobApplicationId
    {
        return $this->id;
    }

    public function positionId(): PositionId
    {
        return $this->positionId;
    }

    public function candidate(): Candidate
    {
        return $this->candidate;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }

    public function cvText(): CvText
    {
        return $this->cvText;
    }

    public function status(): JobApplicationStatus
    {
        return $this->status;
    }

    public function appliedAt(): \DateTimeImmutable
    {
        return $this->appliedAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function enrichment(): ?Enrichment
    {
        return $this->enrichment;
    }

    public function failureReason(): ?string
    {
        return $this->failureReason;
    }

    /**
     * Grows by one on every change, so readers can tell which of two states is newer
     * (the projection uses it to never overwrite a view with an older one).
     */
    public function version(): int
    {
        return $this->version;
    }
}
