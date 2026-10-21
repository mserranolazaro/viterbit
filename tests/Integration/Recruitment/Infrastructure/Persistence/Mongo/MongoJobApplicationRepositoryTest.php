<?php

declare(strict_types=1);

namespace App\Tests\Integration\Recruitment\Infrastructure\Persistence\Mongo;

use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\CvAnalysis;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\JobApplicationId;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\JobApplicationStatus;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\Score;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\Email;
use App\Recruitment\Domain\Write\Aggregate\Value\Position\PositionId;
use App\Recruitment\Domain\Write\Exception\JobApplication\JobApplicationAlreadyExistsException;
use App\Recruitment\Domain\Write\Repository\JobApplicationRepository;
use App\Recruitment\Infrastructure\Persistence\Mongo\MongoJobApplicationRepository;
use App\Tests\IntegrationTestCase;
use App\Tests\Support\JobApplicationMother;
use MongoDB\Database;

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
            new CvAnalysis('Senior PHP developer.', Score::fromInt(91), 'cv_analysis.v1', ['PHP'], ['DDD']),
            new \DateTimeImmutable('2026-10-21 10:00:07.123'),
        );
        $repository->save($jobApplication);
        $found = $repository->findById($jobApplication->id());

        self::assertSame(JobApplicationStatus::Enriched, $found?->status());
        self::assertSame(91, $found->enrichment()?->score->value());
        self::assertSame('cv_analysis.v1', $found->enrichment()->promptVersion);
        self::assertSame(['PHP'], $found->enrichment()->matchedSkills);
        self::assertSame(['DDD'], $found->enrichment()->missingSkills);
        self::assertSame('2026-10-21 10:00:07.123', $found->enrichment()->enrichedAt->format('Y-m-d H:i:s.v'));
        self::assertCount(1, iterator_to_array($repository->all()));
    }

    public function testItReadsDocumentsWrittenBeforeVersioningAsTheFirstVersion(): void
    {
        $repository = self::service(JobApplicationRepository::class);
        $jobApplication = JobApplicationMother::received();
        $repository->save($jobApplication);
        self::service(Database::class)->selectCollection(MongoJobApplicationRepository::COLLECTION)
            ->updateOne(['_id' => $jobApplication->id()->value()], ['$unset' => ['version' => '']]);

        self::assertSame(1, $repository->findById($jobApplication->id())?->version());
    }

    public function testItKnowsWhetherACandidateAlreadyAppliedToAPosition(): void
    {
        $repository = self::service(JobApplicationRepository::class);
        $jobApplication = JobApplicationMother::received();
        $repository->save($jobApplication);

        $position = $jobApplication->positionId();
        $ada = Email::fromString('ada@example.com');

        self::assertTrue($repository->existsForCandidate($position, $ada));
        self::assertFalse($repository->existsForCandidate($position, Email::fromString('grace@example.com')));
        self::assertFalse($repository->existsForCandidate(PositionId::generate(), $ada));
    }

    public function testTheUniqueIndexStopsADuplicateThatRacedPastTheCheck(): void
    {
        self::service(MongoJobApplicationRepository::class)->createIndexes();
        $repository = self::service(JobApplicationRepository::class);
        $first = JobApplicationMother::received();
        $repository->save($first);

        $this->expectException(JobApplicationAlreadyExistsException::class);

        $repository->save(JobApplicationMother::received($first->positionId()));
    }

    public function testItFindsOnlyApplicationsLeftUnfinishedForTooLong(): void
    {
        $repository = self::service(JobApplicationRepository::class);
        $oldReceived = JobApplicationMother::received(appliedAt: new \DateTimeImmutable('2026-10-21 09:00'));
        $oldEnriching = JobApplicationMother::enriching();
        $recent = JobApplicationMother::received(
            email: 'grace@example.com',
            appliedAt: new \DateTimeImmutable('2026-10-21 11:59'),
        );
        $finished = JobApplicationMother::enriching();
        $finished->failEnrichment('Gave up', new \DateTimeImmutable('2026-10-21 09:00'));
        foreach ([$oldReceived, $oldEnriching, $recent, $finished] as $jobApplication) {
            $repository->save($jobApplication);
        }

        $found = iterator_to_array($repository->findUnfinishedSince(new \DateTimeImmutable('2026-10-21 11:58')), false);

        self::assertEqualsCanonicalizing(
            [$oldReceived->id()->value(), $oldEnriching->id()->value()],
            array_map(static fn ($jobApplication): string => $jobApplication->id()->value(), $found),
        );
    }

    public function testItReturnsNullForAnUnknownId(): void
    {
        self::assertNull(self::service(JobApplicationRepository::class)->findById(JobApplicationId::generate()));
    }
}
