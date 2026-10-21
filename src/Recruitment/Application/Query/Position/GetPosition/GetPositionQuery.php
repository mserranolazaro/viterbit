<?php

declare(strict_types=1);

namespace App\Recruitment\Application\Query\Position\GetPosition;

final class GetPositionQuery
{
    public function __construct(
        public readonly string $id,
    ) {
    }
}
