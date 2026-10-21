<?php

declare(strict_types=1);

namespace App\Recruitment\Application\Query\Position\GetPosition;

final class PositionDetailResponse
{
    /**
     * @param array<array{skill: string, level: string}> $requirements
     */
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $description,
        public readonly string $location,
        public readonly array $requirements,
    ) {
    }
}
