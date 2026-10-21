<?php

declare(strict_types=1);

namespace App\Tests\Unit\Recruitment\Domain\Write\Aggregate\Root\JobApplication;

use App\Recruitment\Domain\Write\Aggregate\Root\JobApplication\JobApplication;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\Candidate;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\CvAnalysis;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\CvText;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\Email;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\JobApplicationId;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\JobApplicationStatus;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\Score;
use App\Recruitment\Domain\Write\Aggregate\Value\Position\PositionId;
use App\Recruitment\Domain\Write\Event\JobApplication\JobApplicationEnrichmentHasFailed;
use App\Recruitment\Domain\Write\Event\JobApplication\JobApplicationEnrichmentWasStarted;
use App\Recruitment\Domain\Write\Event\JobApplication\JobApplicationWasEnriched;
use App\Recruitment\Domain\Write\Event\JobApplication\JobApplicationWasSubmitted;
use App\Recruitment\Domain\Write\Exception\JobApplication\InvalidJobApplicationDataException;
use App\Recruitment\Domain\Write\Exception\JobApplication\InvalidJobApplicationStatusTransitionException;
use PHPUnit\Framework\TestCase;

final class JobApplicationTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-10-21 10:00:00');
    }

    public function testItIsReceivedWhenSubmitted(): void
    {
        $id = JobApplicationId::generate();
        $positionId = PositionId::generate();

        $jobApplication = JobApplication::submit(
            $id,
            $positionId,
            Candidate::create('Ada Lovelace', Email::fromString('ada@example.com'), null),
            '  Available from November  ',
            CvText::fromString('Backend developer with PHP and Symfony.'),
            $this->now,
        );

        self::assertSame(JobApplicationStatus::Received, $jobApplication->status());
        self::assertEquals($this->now, $jobApplication->appliedAt());
        self::assertEquals($this->now, $jobApplication->updatedAt());
        self::assertSame('Available from November', $jobApplication->notes());
        self::assertNull($jobApplication->enrichment());

        $events = $jobApplication->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(JobApplicationWasSubmitted::class, $events[0]);
        self::assertSame($id->value(), $events[0]->aggregateId());
        self::assertSame($positionId->value(), $events[0]->positionId);
        self::assertEquals($this->now, $events[0]->occurredOn());
    }

    public function testItStoresEmptyNotesAsNull(): void
    {
        self::assertNull($this->submittedApplication(notes: '   ')->notes());
    }

    public function testItRejectsNotesThatAreTooLong(): void
    {
        $this->expectException(InvalidJobApplicationDataException::class);

        $this->submittedApplication(notes: str_repeat('a', JobApplication::NOTES_MAX_LENGTH + 1));
    }

    public function testItStartsTheEnrichment(): void
    {
        $jobApplication = $this->submittedApplication();
        $jobApplication->pullDomainEvents();
        $later = $this->now->modify('+1 second');

        $jobApplication->startEnrichment($later);

        self::assertSame(JobApplicationStatus::Enriching, $jobApplication->status());
        self::assertEquals($later, $jobApplication->updatedAt());
        $events = $jobApplication->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(JobApplicationEnrichmentWasStarted::class, $events[0]);
    }

    public function testItIgnoresStartingAnEnrichmentAlreadyInProgress(): void
    {
        $jobApplication = $this->enrichingApplication();

        $jobApplication->startEnrichment($this->now->modify('+5 seconds'));

        self::assertSame(JobApplicationStatus::Enriching, $jobApplication->status());
        self::assertSame([], $jobApplication->pullDomainEvents());
    }

    public function testItCompletesTheEnrichmentWithTheAnalysis(): void
    {
        $jobApplication = $this->enrichingApplication();
        $later = $this->now->modify('+3 seconds');

        $jobApplication->completeEnrichment(
            new CvAnalysis('Senior PHP developer.', Score::fromInt(82), 'cv_analysis.v1'),
            $later,
        );

        self::assertSame(JobApplicationStatus::Enriched, $jobApplication->status());
        $enrichment = $jobApplication->enrichment();
        self::assertNotNull($enrichment);
        self::assertSame('Senior PHP developer.', $enrichment->summary);
        self::assertSame(82, $enrichment->score->value());
        self::assertSame('cv_analysis.v1', $enrichment->promptVersion);
        self::assertEquals($later, $enrichment->enrichedAt);

        $events = $jobApplication->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(JobApplicationWasEnriched::class, $events[0]);
        self::assertSame(82, $events[0]->score);
    }

    public function testItCannotCompleteAnEnrichmentThatWasNotStarted(): void
    {
        $this->expectException(InvalidJobApplicationStatusTransitionException::class);

        $this->submittedApplication()->completeEnrichment(
            new CvAnalysis('Summary.', Score::fromInt(50), 'cv_analysis.v1'),
            $this->now,
        );
    }

    public function testItFailsTheEnrichmentWithAReason(): void
    {
        $jobApplication = $this->enrichingApplication();

        $jobApplication->failEnrichment('LLM output did not match the schema', $this->now->modify('+9 seconds'));

        self::assertSame(JobApplicationStatus::Failed, $jobApplication->status());
        self::assertSame('LLM output did not match the schema', $jobApplication->failureReason());
        $events = $jobApplication->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(JobApplicationEnrichmentHasFailed::class, $events[0]);
        self::assertSame('LLM output did not match the schema', $events[0]->reason);
    }

    public function testItCanFailBeforeTheEnrichmentStarts(): void
    {
        $jobApplication = $this->submittedApplication();

        $jobApplication->failEnrichment('Position no longer exists', $this->now);

        self::assertSame(JobApplicationStatus::Failed, $jobApplication->status());
    }

    public function testItCannotLeaveAFinalStatus(): void
    {
        $jobApplication = $this->enrichingApplication();
        $jobApplication->completeEnrichment(
            new CvAnalysis('Summary.', Score::fromInt(50), 'cv_analysis.v1'),
            $this->now,
        );

        $this->expectException(InvalidJobApplicationStatusTransitionException::class);

        $jobApplication->failEnrichment('Too late', $this->now);
    }

    public function testItRecordsNoEventsWhenReconstituted(): void
    {
        $jobApplication = JobApplication::reconstitute(
            JobApplicationId::generate(),
            PositionId::generate(),
            Candidate::create('Ada Lovelace', Email::fromString('ada@example.com'), null),
            null,
            CvText::fromString('CV'),
            JobApplicationStatus::Enriching,
            $this->now,
            $this->now,
            null,
            null,
        );

        self::assertSame(JobApplicationStatus::Enriching, $jobApplication->status());
        self::assertSame([], $jobApplication->pullDomainEvents());
    }

    private function submittedApplication(?string $notes = null): JobApplication
    {
        return JobApplication::submit(
            JobApplicationId::generate(),
            PositionId::generate(),
            Candidate::create('Ada Lovelace', Email::fromString('ada@example.com'), '+34 600 000 000'),
            $notes,
            CvText::fromString('Backend developer with PHP and Symfony.'),
            $this->now,
        );
    }

    private function enrichingApplication(): JobApplication
    {
        $jobApplication = $this->submittedApplication();
        $jobApplication->startEnrichment($this->now);
        $jobApplication->pullDomainEvents();

        return $jobApplication;
    }
}
