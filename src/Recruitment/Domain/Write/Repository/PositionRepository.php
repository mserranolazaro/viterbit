<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\Write\Repository;

use App\Recruitment\Domain\Write\Aggregate\Root\Position\Position;
use App\Recruitment\Domain\Write\Aggregate\Value\Position\PositionId;

interface PositionRepository
{
    public function save(Position $position): void;

    public function findById(PositionId $id): ?Position;

    /**
     * @return Position[] Ordered by title.
     */
    public function all(): array;
}
