<?php

declare(strict_types=1);

namespace App\Tests\Integration\Recruitment\Infrastructure\Persistence\Mongo;

use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\CvAnalysis;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\JobApplicationId;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\JobApplicationStatus;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\Score;
use App\Recruitment\Domain\Write\Repository\JobApplicationRepository;
use App\Tests\IntegrationTestCase;
use App\Tests\Support\JobApplicationMother;

final class MongoJobApplicationRepositoryTest extends IntegrationTestCase
{
    public function testItRoundTripsAReceivedApplication(): void
    {
        $repository = self::service(JobApplicationRepository::class);
        $jobApplication = JobApplicationMother::received();

        $repository->save($jobApplication);
        $found = $repository->findById($jobApplication->id());

        self::assertNotNull($found);
        self::assertTrue($found->id()->equals($jobApplication->id()));
        self::assertTrue($found->positionId()->equals($jobApplication->positionId()));
        self::assertSame('Ada Lovelace', $found->candidate()->fullName());
        self::assertSame('ada@example.com', $found->candidate()->email()->value());
        self::assertSame('+34 600 000 000', $found->candidate()->phone());
        self::assertSame('Available from November', $found->notes());
        self::assertSame($jobApplication->cvText()->value(), $found->cvText()->value());
        self::assertSame(JobApplicationStatus::Received, $found->status());
        self::assertSame(
            $jobApplication->appliedAt()->format('Y-m-d H:i:s.v'),
            $found->appliedAt()->format('Y-m-d H:i:s.v'),
        );
        self::assertNull($found->enrichment());
        self::assertSame([], $found->pullDomainEvents());
    }

    public function testItReplacesTheDocumentWhenTheApplicationChanges(): void
    {
        $repository = self::service(JobApplicationRepository::class);
        $jobApplication = JobApplicationMother::enriching();
        $repository->save($jobApplication);

        $jobApplication->completeEnrichment(
            new CvAnalysis('Senior PHP developer.', Score::fromInt(91), 'cv_analysis.v1'),
            new \DateTimeImmutable('2026-10-21 10:00:07.123'),
        );
        $repository->save($jobApplication);
        $found = $repository->findById($jobApplication->id());

        self::assertSame(JobApplicationStatus::Enriched, $found?->status());
        self::assertSame(91, $found->enrichment()?->score->value());
        self::assertSame('cv_analysis.v1', $found->enrichment()->promptVersion);
        self::assertSame('2026-10-21 10:00:07.123', $found->enrichment()->enrichedAt->format('Y-m-d H:i:s.v'));
        self::assertCount(1, iterator_to_array($repository->all()));
    }

    public function testItReturnsNullForAnUnknownId(): void
    {
        self::assertNull(self::service(JobApplicationRepository::class)->findById(JobApplicationId::generate()));
    }
}
