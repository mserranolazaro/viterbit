<?php

declare(strict_types=1);

namespace App\Tests\Unit\Recruitment\Application\Command\JobApplication\SubmitJobApplication;

use App\Recruitment\Application\Command\JobApplication\SubmitJobApplication\SubmitJobApplicationCommand;
use App\Recruitment\Application\Command\JobApplication\SubmitJobApplication\SubmitJobApplicationHandler;
use App\Recruitment\Domain\Write\Aggregate\Root\JobApplication\JobApplication;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\JobApplicationStatus;
use App\Recruitment\Domain\Write\Aggregate\Value\Position\PositionId;
use App\Recruitment\Domain\Write\Event\JobApplication\JobApplicationWasSubmitted;
use App\Recruitment\Domain\Write\Exception\JobApplication\InvalidJobApplicationDataException;
use App\Recruitment\Domain\Write\Exception\Position\PositionNotFoundException;
use App\Recruitment\Domain\Write\Repository\JobApplicationRepository;
use App\Recruitment\Domain\Write\Repository\PositionRepository;
use App\Tests\Support\JobApplicationMother;
use App\Tests\Support\SpyDomainEventPublisher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class SubmitJobApplicationHandlerTest extends TestCase
{
    private PositionId $positionId;
    private SpyDomainEventPublisher $publisher;
    private MockClock $clock;

    protected function setUp(): void
    {
        $this->positionId = PositionId::generate();
        $this->publisher = new SpyDomainEventPublisher();
        $this->clock = new MockClock('2026-10-21 10:00:00');
    }

    public function testItSavesAReceivedApplicationAndPublishesTheSubmission(): void
    {
        $saved = null;
        $repository = $this->createMock(JobApplicationRepository::class);
        $repository->expects(self::once())->method('save')
            ->willReturnCallback(static function (JobApplication $jobApplication) use (&$saved): void {
                $saved = $jobApplication;
            });

        $id = $this->handler($repository)(new SubmitJobApplicationCommand(
            $this->positionId->value(),
            'Ada Lovelace',
            'ada@example.com',
            null,
            null,
            'Backend developer with PHP.',
        ));

        self::assertInstanceOf(JobApplication::class, $saved);
        self::assertSame($id, $saved->id()->value());
        self::assertSame(JobApplicationStatus::Received, $saved->status());
        self::assertEquals($this->clock->now(), $saved->appliedAt());
        self::assertSame([JobApplicationWasSubmitted::class], $this->publisher->publishedClasses());
        self::assertSame($id, $this->publisher->published[0]->aggregateId());
    }

    public function testItRejectsAnApplicationToAnUnknownPosition(): void
    {
        $repository = $this->createMock(JobApplicationRepository::class);
        $repository->expects(self::never())->method('save');

        $this->expectException(PositionNotFoundException::class);

        $this->handler($repository, positionExists: false)(new SubmitJobApplicationCommand(
            $this->positionId->value(),
            'Ada Lovelace',
            'ada@example.com',
            null,
            null,
            'CV',
        ));
    }

    public function testItSavesNothingWhenTheCandidateDataIsInvalid(): void
    {
        $repository = $this->createMock(JobApplicationRepository::class);
        $repository->expects(self::never())->method('save');

        try {
            $this->handler($repository)(new SubmitJobApplicationCommand(
                $this->positionId->value(),
                'Ada Lovelace',
                'not-an-email',
                null,
                null,
                'CV',
            ));
            self::fail('Expected the email to be rejected.');
        } catch (InvalidJobApplicationDataException) {
            self::assertSame([], $this->publisher->published);
        }
    }

    private function handler(
        JobApplicationRepository $repository,
        bool $positionExists = true,
    ): SubmitJobApplicationHandler {
        $positions = $this->createStub(PositionRepository::class);
        $positions->method('findById')->willReturn(
            $positionExists ? JobApplicationMother::position($this->positionId) : null,
        );

        return new SubmitJobApplicationHandler($repository, $positions, $this->publisher, $this->clock);
    }
}
