<?php

declare(strict_types=1);

namespace App\Tests\Unit\Recruitment\Application\Command\JobApplication\MarkEnrichmentAsFailed;

use App\Recruitment\Application\Command\JobApplication\MarkEnrichmentAsFailed\MarkEnrichmentAsFailedCommand;
use App\Recruitment\Application\Command\JobApplication\MarkEnrichmentAsFailed\MarkEnrichmentAsFailedHandler;
use App\Recruitment\Domain\Write\Aggregate\Root\JobApplication\JobApplication;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\JobApplicationStatus;
use App\Recruitment\Domain\Write\Event\JobApplication\JobApplicationEnrichmentHasFailed;
use App\Recruitment\Domain\Write\Repository\JobApplicationRepository;
use App\Tests\Support\JobApplicationMother;
use App\Tests\Support\SpyDomainEventPublisher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class MarkEnrichmentAsFailedHandlerTest extends TestCase
{
    public function testItMarksTheEnrichmentAsFailedWithTheReason(): void
    {
        $jobApplication = JobApplicationMother::enriching();
        $publisher = new SpyDomainEventPublisher();
        $repository = $this->repositoryReturning($jobApplication, expectedSaves: 1);

        new MarkEnrichmentAsFailedHandler($repository, $publisher, new MockClock())(
            new MarkEnrichmentAsFailedCommand($jobApplication->id()->value(), 'Gave up after 3 attempts'),
        );

        self::assertSame(JobApplicationStatus::Failed, $jobApplication->status());
        self::assertSame('Gave up after 3 attempts', $jobApplication->failureReason());
        self::assertSame([JobApplicationEnrichmentHasFailed::class], $publisher->publishedClasses());
    }

    public function testItDoesNotOverwriteAFinalStatus(): void
    {
        $jobApplication = JobApplicationMother::enriching();
        $jobApplication->failEnrichment('First failure', new \DateTimeImmutable());
        $jobApplication->pullDomainEvents();
        $publisher = new SpyDomainEventPublisher();

        new MarkEnrichmentAsFailedHandler($this->repositoryReturning($jobApplication, 0), $publisher, new MockClock())(
            new MarkEnrichmentAsFailedCommand($jobApplication->id()->value(), 'Second failure'),
        );

        self::assertSame('First failure', $jobApplication->failureReason());
        self::assertSame([], $publisher->published);
    }

    private function repositoryReturning(JobApplication $jobApplication, int $expectedSaves): JobApplicationRepository
    {
        $repository = $this->createMock(JobApplicationRepository::class);
        $repository->method('findById')->willReturn($jobApplication);
        $repository->expects(self::exactly($expectedSaves))->method('save');

        return $repository;
    }
}
