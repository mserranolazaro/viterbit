<?php

declare(strict_types=1);

namespace App\Recruitment\Application\Query\JobApplication\GetJobApplication;

final class GetJobApplicationQuery
{
    public function __construct(
        public readonly string $id,
    ) {
    }
}
