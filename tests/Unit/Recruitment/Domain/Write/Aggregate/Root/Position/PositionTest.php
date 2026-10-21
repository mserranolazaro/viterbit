<?php

declare(strict_types=1);

namespace App\Tests\Unit\Recruitment\Domain\Write\Aggregate\Root\Position;

use App\Recruitment\Domain\Write\Aggregate\Root\Position\Position;
use App\Recruitment\Domain\Write\Aggregate\Value\Position\PositionId;
use App\Recruitment\Domain\Write\Aggregate\Value\Position\Requirement;
use App\Recruitment\Domain\Write\Aggregate\Value\Position\RequirementLevel;
use PHPUnit\Framework\TestCase;

final class PositionTest extends TestCase
{
    public function testItIsCreatedWithItsRequirements(): void
    {
        $position = Position::create(
            PositionId::generate(),
            ' Backend Engineer ',
            'Build the ATS.',
            'Remote',
            [new Requirement('PHP', RequirementLevel::Must), new Requirement('MongoDB', RequirementLevel::Nice)],
        );

        self::assertSame('Backend Engineer', $position->title());
        self::assertCount(2, $position->requirements());
        self::assertTrue($position->requirements()[0]->isMandatory());
        self::assertFalse($position->requirements()[1]->isMandatory());
    }

    public function testItNeedsRequirementsToScoreAgainst(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Position::create(PositionId::generate(), 'Backend Engineer', '', 'Remote', []);
    }
}
