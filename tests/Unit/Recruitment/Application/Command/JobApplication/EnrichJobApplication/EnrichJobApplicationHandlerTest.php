<?php

declare(strict_types=1);

namespace App\Tests\Unit\Recruitment\Application\Command\JobApplication\EnrichJobApplication;

use App\Recruitment\Application\Command\JobApplication\EnrichJobApplication\EnrichJobApplicationCommand;
use App\Recruitment\Application\Command\JobApplication\EnrichJobApplication\EnrichJobApplicationHandler;
use App\Recruitment\Domain\Write\Aggregate\Root\JobApplication\JobApplication;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\CvAnalysis;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\JobApplicationId;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\JobApplicationStatus;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\Score;
use App\Recruitment\Domain\Write\Event\JobApplication\JobApplicationEnrichmentHasFailed;
use App\Recruitment\Domain\Write\Event\JobApplication\JobApplicationEnrichmentWasStarted;
use App\Recruitment\Domain\Write\Event\JobApplication\JobApplicationWasEnriched;
use App\Recruitment\Domain\Write\Repository\JobApplicationRepository;
use App\Recruitment\Domain\Write\Repository\PositionRepository;
use App\Recruitment\Domain\Write\Service\CvAnalysisFailedException;
use App\Recruitment\Domain\Write\Service\CvAnalyzer;
use App\Tests\Support\JobApplicationMother;
use App\Tests\Support\SpyDomainEventPublisher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

final class EnrichJobApplicationHandlerTest extends TestCase
{
    private SpyDomainEventPublisher $publisher;
    /** @var JobApplicationStatus[] */
    private array $savedStatuses = [];

    protected function setUp(): void
    {
        $this->publisher = new SpyDomainEventPublisher();
    }

    public function testItStartsAndCompletesTheEnrichment(): void
    {
        $jobApplication = JobApplicationMother::received();
        $analyzer = $this->createMock(CvAnalyzer::class);
        $analyzer->expects(self::once())->method('analyze')
            ->willReturn(new CvAnalysis('Senior PHP developer.', Score::fromInt(75), 'cv_analysis.v1'));

        $this->handler($jobApplication, $analyzer)(new EnrichJobApplicationCommand($jobApplication->id()->value()));

        self::assertSame(JobApplicationStatus::Enriched, $jobApplication->status());
        self::assertSame(75, $jobApplication->enrichment()?->score->value());
        self::assertSame([JobApplicationStatus::Enriching, JobApplicationStatus::Enriched], $this->savedStatuses);
        self::assertSame(
            [JobApplicationEnrichmentWasStarted::class, JobApplicationWasEnriched::class],
            $this->publisher->publishedClasses(),
        );
    }

    public function testItLeavesTheApplicationEnrichingWhenTheAnalysisFailsSoItCanBeRetried(): void
    {
        $jobApplication = JobApplicationMother::received();
        $analyzer = $this->createStub(CvAnalyzer::class);
        $analyzer->method('analyze')->willThrowException(CvAnalysisFailedException::because('Malformed output'));

        try {
            $this->handler($jobApplication, $analyzer)(new EnrichJobApplicationCommand($jobApplication->id()->value()));
            self::fail('Expected the analysis failure to propagate.');
        } catch (CvAnalysisFailedException) {
            self::assertSame(JobApplicationStatus::Enriching, $jobApplication->status());
            self::assertSame([JobApplicationEnrichmentWasStarted::class], $this->publisher->publishedClasses());
        }
    }

    public function testARetryDoesNotStartTheEnrichmentTwice(): void
    {
        $jobApplication = JobApplicationMother::enriching();
        $analyzer = $this->createStub(CvAnalyzer::class);
        $analyzer->method('analyze')->willReturn(new CvAnalysis('Summary.', Score::fromInt(40), 'cv_analysis.v1'));

        $this->handler($jobApplication, $analyzer)(new EnrichJobApplicationCommand($jobApplication->id()->value()));

        self::assertSame([JobApplicationWasEnriched::class], $this->publisher->publishedClasses());
    }

    public function testItIgnoresAnApplicationThatIsAlreadyEnriched(): void
    {
        $jobApplication = JobApplicationMother::enriching();
        $jobApplication->completeEnrichment(
            new CvAnalysis('Summary.', Score::fromInt(40), 'cv_analysis.v1'),
            new \DateTimeImmutable(),
        );
        $jobApplication->pullDomainEvents();
        $analyzer = $this->createMock(CvAnalyzer::class);
        $analyzer->expects(self::never())->method('analyze');

        $this->handler($jobApplication, $analyzer)(new EnrichJobApplicationCommand($jobApplication->id()->value()));

        self::assertSame([], $this->savedStatuses);
        self::assertSame([], $this->publisher->published);
    }

    public function testItFailsTheApplicationWhenItsPositionIsGone(): void
    {
        $jobApplication = JobApplicationMother::received();
        $analyzer = $this->createMock(CvAnalyzer::class);
        $analyzer->expects(self::never())->method('analyze');

        $this->handler($jobApplication, $analyzer, positionExists: false)(
            new EnrichJobApplicationCommand($jobApplication->id()->value()),
        );

        self::assertSame(JobApplicationStatus::Failed, $jobApplication->status());
        self::assertSame([JobApplicationEnrichmentHasFailed::class], $this->publisher->publishedClasses());
    }

    public function testItGivesUpWithoutRetryingWhenTheApplicationDoesNotExist(): void
    {
        $repository = $this->createStub(JobApplicationRepository::class);
        $repository->method('findById')->willReturn(null);
        $handler = new EnrichJobApplicationHandler(
            $repository,
            $this->createStub(PositionRepository::class),
            $this->createStub(CvAnalyzer::class),
            $this->publisher,
            new MockClock(),
        );

        $this->expectException(UnrecoverableMessageHandlingException::class);

        $handler(new EnrichJobApplicationCommand(JobApplicationId::generate()->value()));
    }

    private function handler(
        JobApplication $jobApplication,
        CvAnalyzer $analyzer,
        bool $positionExists = true,
    ): EnrichJobApplicationHandler {
        $repository = $this->createStub(JobApplicationRepository::class);
        $repository->method('findById')->willReturn($jobApplication);
        $repository->method('save')->willReturnCallback(function (JobApplication $saved): void {
            $this->savedStatuses[] = $saved->status();
        });

        $positions = $this->createStub(PositionRepository::class);
        $positions->method('findById')->willReturn(
            $positionExists ? JobApplicationMother::position($jobApplication->positionId()) : null,
        );

        return new EnrichJobApplicationHandler(
            $repository,
            $positions,
            $analyzer,
            $this->publisher,
            new MockClock('2026-10-21 10:00:05'),
        );
    }
}
