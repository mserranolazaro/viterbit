<?php

declare(strict_types=1);

namespace App\Recruitment\Application\Command\JobApplication\SubmitJobApplication;

final class SubmitJobApplicationCommand
{
    public function __construct(
        public readonly string $positionId,
        public readonly string $fullName,
        public readonly string $email,
        public readonly ?string $phone,
        public readonly ?string $notes,
        public readonly string $cvText,
    ) {
    }
}
