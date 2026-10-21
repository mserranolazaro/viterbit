<?php

declare(strict_types=1);

namespace App\Recruitment\Application\Query\Position\ListPositions;

final class PositionResponse
{
    /**
     * @param string[] $mustHaveSkills
     */
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $location,
        public readonly array $mustHaveSkills,
    ) {
    }
}
