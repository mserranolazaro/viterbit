<?php

declare(strict_types=1);

namespace App\Recruitment\Infrastructure\Persistence\Mongo;

use App\Recruitment\Domain\Write\Aggregate\Root\JobApplication\JobApplication;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\Candidate;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\CvText;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\Email;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\Enrichment;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\JobApplicationId;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\JobApplicationStatus;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\Score;
use App\Recruitment\Domain\Write\Aggregate\Value\Position\PositionId;
use App\Recruitment\Domain\Write\Exception\JobApplication\JobApplicationAlreadyExistsException;
use App\Recruitment\Domain\Write\Repository\JobApplicationRepository;
use App\SharedKernel\Infrastructure\Mongo\MongoErrors;
use App\SharedKernel\Infrastructure\Mongo\MongoDates;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use MongoDB\Driver\Exception\BulkWriteException;
use MongoDB\Database;

/**
 * Write model: one document per aggregate, replaced as a whole on every save.
 * The mapping is explicit so the domain carries no persistence code.
 */
final class MongoJobApplicationRepository implements JobApplicationRepository
{
    public const string COLLECTION = 'job_applications';
    private const string ONE_APPLICATION_PER_CANDIDATE = 'one_application_per_candidate';

    private Collection $collection;

    public function __construct(Database $database)
    {
        $this->collection = $database->selectCollection(self::COLLECTION);
    }

    /**
     * The unique index backs up the duplicate check in the use case when two submissions race.
     */
    public function createIndexes(): void
    {
        $this->collection->createIndex(
            ['positionId' => 1, 'candidate.email' => 1],
            ['unique' => true, 'name' => self::ONE_APPLICATION_PER_CANDIDATE],
        );
    }

    public function save(JobApplication $jobApplication): void
    {
        try {
            $this->collection->replaceOne(
                ['_id' => $jobApplication->id()->value()],
                $this->toDocument($jobApplication),
                ['upsert' => true],
            );
        } catch (BulkWriteException $e) {
            if (!MongoErrors::isDuplicateKey($e)) {
                throw $e;
            }

            throw JobApplicationAlreadyExistsException::forCandidate(
                $jobApplication->candidate()->email(),
                $jobApplication->positionId(),
            );
        }
    }

    public function existsForCandidate(PositionId $positionId, Email $email): bool
    {
        return $this->collection->countDocuments(
            ['positionId' => $positionId->value(), 'candidate.email' => $email->value()],
            ['limit' => 1],
        ) > 0;
    }

    public function findById(JobApplicationId $id): ?JobApplication
    {
        /** @var array<string, mixed>|null $document */
        $document = $this->collection->findOne(['_id' => $id->value()]);

        return $document === null ? null : $this->toJobApplication($document);
    }

    public function all(): iterable
    {
        /** @var array<string, mixed> $document */
        foreach ($this->collection->find([], ['sort' => ['appliedAt' => 1]]) as $document) {
            yield $this->toJobApplication($document);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function toDocument(JobApplication $jobApplication): array
    {
        $candidate = $jobApplication->candidate();
        $enrichment = $jobApplication->enrichment();

        return [
            '_id' => $jobApplication->id()->value(),
            'positionId' => $jobApplication->positionId()->value(),
            'candidate' => [
                'fullName' => $candidate->fullName(),
                'email' => $candidate->email()->value(),
                'phone' => $candidate->phone(),
            ],
            'notes' => $jobApplication->notes(),
            'cvText' => $jobApplication->cvText()->value(),
            'status' => $jobApplication->status()->value,
            'appliedAt' => MongoDates::toBson($jobApplication->appliedAt()),
            'updatedAt' => MongoDates::toBson($jobApplication->updatedAt()),
            'enrichment' => $enrichment === null ? null : [
                'summary' => $enrichment->summary,
                'score' => $enrichment->score->value(),
                'promptVersion' => $enrichment->promptVersion,
                'enrichedAt' => MongoDates::toBson($enrichment->enrichedAt),
                'matchedSkills' => $enrichment->matchedSkills,
                'missingSkills' => $enrichment->missingSkills,
            ],
            'failureReason' => $jobApplication->failureReason(),
            'version' => $jobApplication->version(),
        ];
    }

    /**
     * @param array<string, mixed> $document
     */
    private function toJobApplication(array $document): JobApplication
    {
        /** @var array{fullName: string, email: string, phone: ?string} $candidate */
        $candidate = $document['candidate'];
        /** @var array{summary: string, score: int, promptVersion: string, enrichedAt: UTCDateTime, matchedSkills?: string[], missingSkills?: string[]}|null $enrichment */
        $enrichment = $document['enrichment'];

        return JobApplication::reconstitute(
            JobApplicationId::fromString($document['_id']),
            PositionId::fromString($document['positionId']),
            Candidate::create($candidate['fullName'], Email::fromString($candidate['email']), $candidate['phone']),
            $document['notes'],
            CvText::fromString($document['cvText']),
            JobApplicationStatus::from($document['status']),
            MongoDates::fromBson($document['appliedAt']),
            MongoDates::fromBson($document['updatedAt']),
            $enrichment === null ? null : new Enrichment(
                $enrichment['summary'],
                Score::fromInt($enrichment['score']),
                $enrichment['promptVersion'],
                MongoDates::fromBson($enrichment['enrichedAt']),
                $enrichment['matchedSkills'] ?? [],
                $enrichment['missingSkills'] ?? [],
            ),
            $document['failureReason'],
            // Documents written before versioning existed are read as their first version.
            $document['version'] ?? 1,
        );
    }
}
