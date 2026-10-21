<?php

declare(strict_types=1);

namespace App\Tests\Unit\Recruitment\Application\Command\JobApplication\ReconcileJobApplications;

use App\Recruitment\Application\Command\JobApplication\EnrichJobApplication\EnrichJobApplicationCommand;
use App\Recruitment\Application\Command\JobApplication\ReconcileJobApplications\ReconcileJobApplicationsCommand;
use App\Recruitment\Application\Command\JobApplication\ReconcileJobApplications\ReconcileJobApplicationsHandler;
use App\Recruitment\Application\Service\JobApplicationViewProjector;
use App\Recruitment\Domain\Read\JobApplicationView;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\JobApplicationId;
use App\Recruitment\Domain\Read\JobApplicationViewRepository;
use App\Recruitment\Domain\Write\Repository\JobApplicationRepository;
use App\Recruitment\Domain\Write\Repository\PositionRepository;
use App\Tests\Support\JobApplicationMother;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class ReconcileJobApplicationsHandlerTest extends TestCase
{
    public function testItRefreshesTheViewAndRequestsTheEnrichmentOfEveryStuckApplication(): void
    {
        $stuck = [JobApplicationMother::received(), JobApplicationMother::enriching()];
        $before = null;
        $limit = null;
        $marked = [];
        $applications = $this->createStub(JobApplicationRepository::class);
        $applications->method('findUnfinishedSince')->willReturnCallback(
            static function (\DateTimeImmutable $since, int $batch) use (&$before, &$limit, $stuck): array {
                $before = $since;
                $limit = $batch;

                return $stuck;
            },
        );
        $applications->method('findById')->willReturnOnConsecutiveCalls(...$stuck);
        $applications->method('markRedriven')->willReturnCallback(
            static function (JobApplicationId $id, \DateTimeImmutable $at) use (&$marked): void {
                $marked[] = $id->value() . '@' . $at->format('H:i');
            },
        );

        $projected = [];
        $views = $this->createStub(JobApplicationViewRepository::class);
        $views->method('save')->willReturnCallback(static function (JobApplicationView $view) use (&$projected): void {
            $projected[] = $view->id;
        });

        $requested = [];
        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(static function (object $command) use (&$requested): Envelope {
            self::assertInstanceOf(EnrichJobApplicationCommand::class, $command);
            $requested[] = $command->jobApplicationId;

            return new Envelope($command);
        });

        $count = new ReconcileJobApplicationsHandler(
            $applications,
            new JobApplicationViewProjector($applications, $this->createStub(PositionRepository::class), $views),
            $bus,
            new MockClock('2026-10-21 12:00:00'),
            $this->createStub(LoggerInterface::class),
        )(new ReconcileJobApplicationsCommand());

        $ids = array_map(static fn ($jobApplication): string => $jobApplication->id()->value(), $stuck);
        self::assertSame(2, $count);
        self::assertSame($ids, $projected);
        self::assertSame($ids, $requested);
        self::assertSame('2026-10-21 11:58:00', $before?->format('Y-m-d H:i:s'));
        self::assertSame(ReconcileJobApplicationsCommand::BATCH_SIZE, $limit);
        self::assertSame(array_map(static fn (string $id): string => $id . '@12:00', $ids), $marked);
    }

    public function testItDoesNothingWhenNothingIsStuck(): void
    {
        $applications = $this->createStub(JobApplicationRepository::class);
        $applications->method('findUnfinishedSince')->willReturn([]);
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');

        $count = new ReconcileJobApplicationsHandler(
            $applications,
            new JobApplicationViewProjector(
                $applications,
                $this->createStub(PositionRepository::class),
                $this->createStub(JobApplicationViewRepository::class),
            ),
            $bus,
            new MockClock(),
            $logger,
        )(new ReconcileJobApplicationsCommand());

        self::assertSame(0, $count);
    }
}
