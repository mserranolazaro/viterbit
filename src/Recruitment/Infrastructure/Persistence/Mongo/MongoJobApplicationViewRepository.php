<?php

declare(strict_types=1);

namespace App\Recruitment\Infrastructure\Persistence\Mongo;

use App\Recruitment\Domain\Read\JobApplicationCriteria;
use App\Recruitment\Domain\Read\JobApplicationView;
use App\Recruitment\Domain\Read\JobApplicationViewPage;
use App\Recruitment\Domain\Read\JobApplicationViewRepository;
use App\SharedKernel\Infrastructure\Mongo\MongoDates;
use MongoDB\BSON\Regex;
use MongoDB\Collection;
use MongoDB\Database;

final class MongoJobApplicationViewRepository implements JobApplicationViewRepository
{
    public const string COLLECTION = 'job_application_views';

    /** Newest first; the id (UUID v7, time-ordered) breaks ties within the same millisecond. */
    private const array NEWEST_FIRST = ['appliedAt' => -1, '_id' => -1];

    private Collection $collection;

    public function __construct(Database $database)
    {
        $this->collection = $database->selectCollection(self::COLLECTION);
    }

    /**
     * Indexes for the list page: the default ordering, and each filter followed by it.
     */
    public function createIndexes(): void
    {
        $this->collection->createIndexes([
            ['key' => self::NEWEST_FIRST],
            ['key' => ['status' => 1] + self::NEWEST_FIRST],
            ['key' => ['positionId' => 1] + self::NEWEST_FIRST],
        ]);
    }

    public function save(JobApplicationView $view): void
    {
        $this->collection->replaceOne(['_id' => $view->id], $this->toDocument($view), ['upsert' => true]);
    }

    public function findById(string $id): ?JobApplicationView
    {
        /** @var array<string, mixed>|null $document */
        $document = $this->collection->findOne(['_id' => $id]);

        return $document === null ? null : $this->toView($document);
    }

    public function search(JobApplicationCriteria $criteria): JobApplicationViewPage
    {
        $filter = [];
        if ($criteria->status !== null) {
            $filter['status'] = $criteria->status;
        }

        if ($criteria->positionId !== null) {
            $filter['positionId'] = $criteria->positionId;
        }

        if ($criteria->search !== null) {
            // Escaped, so the user's text is matched literally and cannot inject a regex.
            $filter['searchText'] = new Regex(preg_quote(mb_strtolower($criteria->search), '/'));
        }

        $items = [];
        $cursor = $this->collection->find($filter, [
            'sort' => self::NEWEST_FIRST,
            'skip' => $criteria->offset(),
            'limit' => $criteria->perPage,
        ]);
        /** @var array<string, mixed> $document */
        foreach ($cursor as $document) {
            $items[] = $this->toView($document);
        }

        return new JobApplicationViewPage(
            $items,
            $this->collection->countDocuments($filter),
            $criteria->page,
            $criteria->perPage,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toDocument(JobApplicationView $view): array
    {
        return [
            '_id' => $view->id,
            'positionId' => $view->positionId,
            'positionTitle' => $view->positionTitle,
            'candidateName' => $view->candidateName,
            'email' => $view->email,
            'phone' => $view->phone,
            'notes' => $view->notes,
            'cvText' => $view->cvText,
            'status' => $view->status,
            'score' => $view->score,
            'summary' => $view->summary,
            'promptVersion' => $view->promptVersion,
            'failureReason' => $view->failureReason,
            'appliedAt' => MongoDates::toBson($view->appliedAt),
            'updatedAt' => MongoDates::toBson($view->updatedAt),
            'enrichedAt' => $view->enrichedAt === null ? null : MongoDates::toBson($view->enrichedAt),
            'searchText' => $view->searchText,
        ];
    }

    /**
     * @param array<string, mixed> $document
     */
    private function toView(array $document): JobApplicationView
    {
        return new JobApplicationView(
            id: $document['_id'],
            positionId: $document['positionId'],
            positionTitle: $document['positionTitle'],
            candidateName: $document['candidateName'],
            email: $document['email'],
            phone: $document['phone'],
            notes: $document['notes'],
            cvText: $document['cvText'],
            status: $document['status'],
            score: $document['score'],
            summary: $document['summary'],
            promptVersion: $document['promptVersion'],
            failureReason: $document['failureReason'],
            appliedAt: MongoDates::fromBson($document['appliedAt']),
            updatedAt: MongoDates::fromBson($document['updatedAt']),
            enrichedAt: MongoDates::fromNullableBson($document['enrichedAt']),
        );
    }
}
