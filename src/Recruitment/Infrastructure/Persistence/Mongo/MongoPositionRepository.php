<?php

declare(strict_types=1);

namespace App\Recruitment\Infrastructure\Persistence\Mongo;

use App\Recruitment\Domain\Write\Aggregate\Root\Position\Position;
use App\Recruitment\Domain\Write\Aggregate\Value\Position\PositionId;
use App\Recruitment\Domain\Write\Aggregate\Value\Position\Requirement;
use App\Recruitment\Domain\Write\Aggregate\Value\Position\RequirementLevel;
use App\Recruitment\Domain\Write\Repository\PositionRepository;
use MongoDB\Collection;
use MongoDB\Database;

final class MongoPositionRepository implements PositionRepository
{
    public const string COLLECTION = 'positions';

    private Collection $collection;

    public function __construct(Database $database)
    {
        $this->collection = $database->selectCollection(self::COLLECTION);
    }

    public function save(Position $position): void
    {
        $this->collection->replaceOne(
            ['_id' => $position->id()->value()],
            $this->toDocument($position),
            ['upsert' => true],
        );
    }

    public function findById(PositionId $id): ?Position
    {
        /** @var array<string, mixed>|null $document */
        $document = $this->collection->findOne(['_id' => $id->value()]);

        return $document === null ? null : $this->toPosition($document);
    }

    public function all(): array
    {
        $positions = [];
        /** @var array<string, mixed> $document */
        foreach ($this->collection->find([], ['sort' => ['title' => 1]]) as $document) {
            $positions[] = $this->toPosition($document);
        }

        return $positions;
    }

    /**
     * @return array<string, mixed>
     */
    private function toDocument(Position $position): array
    {
        return [
            '_id' => $position->id()->value(),
            'title' => $position->title(),
            'description' => $position->description(),
            'location' => $position->location(),
            'requirements' => array_map(
                static fn (Requirement $requirement): array => [
                    'skill' => $requirement->skill,
                    'level' => $requirement->level->value,
                ],
                $position->requirements(),
            ),
        ];
    }

    /**
     * @param array<string, mixed> $document
     */
    private function toPosition(array $document): Position
    {
        /** @var array<array{skill: string, level: string}> $requirements */
        $requirements = $document['requirements'];

        return Position::create(
            PositionId::fromString($document['_id']),
            $document['title'],
            $document['description'],
            $document['location'],
            array_map(
                static fn (array $requirement): Requirement => new Requirement(
                    $requirement['skill'],
                    RequirementLevel::from($requirement['level']),
                ),
                $requirements,
            ),
        );
    }
}
