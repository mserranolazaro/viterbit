<?php

declare(strict_types=1);

namespace App\Tests\Unit\Recruitment\Application\Service;

use App\Recruitment\Application\Service\JobApplicationViewProjector;
use App\Recruitment\Domain\Read\JobApplicationView;
use App\Recruitment\Domain\Read\JobApplicationViewRepository;
use App\Recruitment\Domain\Write\Aggregate\Root\JobApplication\JobApplication;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\CvAnalysis;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\JobApplicationId;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\Score;
use App\Recruitment\Domain\Write\Repository\JobApplicationRepository;
use App\Recruitment\Domain\Write\Repository\PositionRepository;
use App\Tests\Support\JobApplicationMother;
use PHPUnit\Framework\TestCase;

final class JobApplicationViewProjectorTest extends TestCase
{
    public function testItProjectsTheCurrentStateOfAnEnrichedApplication(): void
    {
        $jobApplication = JobApplicationMother::enriching();
        $enrichedAt = new \DateTimeImmutable('2026-10-21 10:00:09');
        $jobApplication->completeEnrichment(
            new CvAnalysis('Senior PHP developer.', Score::fromInt(88), 'cv_analysis.v1'),
            $enrichedAt,
        );

        $view = $this->project($jobApplication, positionExists: true);

        self::assertSame($jobApplication->id()->value(), $view->id);
        self::assertSame('Backend Engineer', $view->positionTitle);
        self::assertSame('Ada Lovelace', $view->candidateName);
        self::assertSame('ada@example.com', $view->email);
        self::assertSame('+34 600 000 000', $view->phone);
        self::assertSame('Available from November', $view->notes);
        self::assertSame('enriched', $view->status);
        self::assertSame(88, $view->score);
        self::assertSame('Senior PHP developer.', $view->summary);
        self::assertSame('cv_analysis.v1', $view->promptVersion);
        self::assertEquals($enrichedAt, $view->enrichedAt);
        self::assertEquals($jobApplication->appliedAt(), $view->appliedAt);
        self::assertSame('ada lovelace ada@example.com', $view->searchText);
        self::assertSame(3, $view->version);
    }

    public function testItProjectsAReceivedApplicationWithoutEnrichment(): void
    {
        $view = $this->project(JobApplicationMother::received(), positionExists: false);

        self::assertSame('received', $view->status);
        self::assertSame('Unknown position', $view->positionTitle);
        self::assertNull($view->score);
        self::assertNull($view->summary);
        self::assertNull($view->enrichedAt);
    }

    public function testItSkipsAnApplicationThatNoLongerExists(): void
    {
        $applications = $this->createStub(JobApplicationRepository::class);
        $applications->method('findById')->willReturn(null);
        $views = $this->createMock(JobApplicationViewRepository::class);
        $views->expects(self::never())->method('save');

        new JobApplicationViewProjector($applications, $this->createStub(PositionRepository::class), $views)
            ->project(JobApplicationId::generate());
    }

    private function project(JobApplication $jobApplication, bool $positionExists): JobApplicationView
    {
        $applications = $this->createStub(JobApplicationRepository::class);
        $applications->method('findById')->willReturn($jobApplication);
        $positions = $this->createStub(PositionRepository::class);
        $positions->method('findById')->willReturn(
            $positionExists ? JobApplicationMother::position($jobApplication->positionId()) : null,
        );

        $saved = null;
        $views = $this->createMock(JobApplicationViewRepository::class);
        $views->expects(self::once())->method('save')
            ->willReturnCallback(static function (JobApplicationView $view) use (&$saved): void {
                $saved = $view;
            });

        new JobApplicationViewProjector($applications, $positions, $views)->project($jobApplication->id());
        self::assertInstanceOf(JobApplicationView::class, $saved);

        return $saved;
    }
}
