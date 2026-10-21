<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\Write\Aggregate\Root\Position;

use App\Recruitment\Domain\Write\Aggregate\Value\Position\PositionId;
use App\Recruitment\Domain\Write\Aggregate\Value\Position\Requirement;
use App\SharedKernel\Domain\Aggregate\AggregateRoot;

/**
 * A job opening candidates apply to. Master data: loaded by the seed command and
 * read-only in this PoC, so it records no events.
 */
final class Position extends AggregateRoot
{
    /**
     * @param Requirement[] $requirements
     */
    private function __construct(
        private readonly PositionId $id,
        private readonly string $title,
        private readonly string $description,
        private readonly string $location,
        private readonly array $requirements,
    ) {
    }

    /**
     * @param Requirement[] $requirements
     */
    public static function create(
        PositionId $id,
        string $title,
        string $description,
        string $location,
        array $requirements,
    ): self {
        if (trim($title) === '') {
            throw new \InvalidArgumentException('A position needs a title.');
        }

        if ($requirements === []) {
            throw new \InvalidArgumentException('A position needs at least one requirement to score against.');
        }

        return new self($id, trim($title), trim($description), trim($location), array_values($requirements));
    }

    public function id(): PositionId
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function location(): string
    {
        return $this->location;
    }

    /**
     * @return Requirement[]
     */
    public function requirements(): array
    {
        return $this->requirements;
    }
}
