<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Recruitment\Domain\Write\Aggregate\Root\JobApplication\JobApplication;
use App\Recruitment\Domain\Write\Aggregate\Root\Position\Position;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\Candidate;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\CvText;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\Email;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\JobApplicationId;
use App\Recruitment\Domain\Write\Aggregate\Value\Position\PositionId;
use App\Recruitment\Domain\Write\Aggregate\Value\Position\Requirement;
use App\Recruitment\Domain\Write\Aggregate\Value\Position\RequirementLevel;

/**
 * Ready-made aggregates for application and infrastructure tests, which care about
 * the use case and not about building a valid candidate.
 */
final class JobApplicationMother
{
    public static function position(?PositionId $id = null): Position
    {
        return Position::create(
            $id ?? PositionId::generate(),
            'Backend Engineer',
            'Build the hiring platform.',
            'Remote',
            [new Requirement('PHP', RequirementLevel::Must), new Requirement('MongoDB', RequirementLevel::Nice)],
        );
    }

    /**
     * A submitted application whose events have already been pulled.
     */
    public static function received(
        ?PositionId $positionId = null,
        string $fullName = 'Ada Lovelace',
        string $email = 'ada@example.com',
        \DateTimeImmutable $appliedAt = new \DateTimeImmutable('2026-10-21 10:00:00'),
    ): JobApplication {
        $jobApplication = JobApplication::submit(
            JobApplicationId::generate(),
            $positionId ?? PositionId::generate(),
            Candidate::create($fullName, Email::fromString($email), '+34 600 000 000'),
            'Available from November',
            CvText::fromString('Backend developer with PHP, Symfony and MongoDB.'),
            true,
            $appliedAt,
        );
        $jobApplication->pullDomainEvents();

        return $jobApplication;
    }

    public static function enriching(?PositionId $positionId = null): JobApplication
    {
        $jobApplication = self::received($positionId);
        $jobApplication->startEnrichment(new \DateTimeImmutable('2026-10-21 10:00:01'));
        $jobApplication->pullDomainEvents();

        return $jobApplication;
    }
}
