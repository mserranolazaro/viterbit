<?php

declare(strict_types=1);

namespace App\Tests\Unit\SharedKernel\Domain\Value;

use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\JobApplicationId;
use App\Recruitment\Domain\Write\Aggregate\Value\Position\PositionId;
use PHPUnit\Framework\TestCase;

final class UuidTest extends TestCase
{
    public function testItGeneratesTimeOrderedIds(): void
    {
        $first = JobApplicationId::generate();
        usleep(2000);
        $second = JobApplicationId::generate();

        self::assertLessThan(0, strcmp($first->value(), $second->value()));
    }

    public function testItRejectsAnInvalidUuid(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        JobApplicationId::fromString('not-a-uuid');
    }

    public function testIdsOfDifferentAggregatesAreNeverEqual(): void
    {
        $value = JobApplicationId::generate()->value();

        $sameIdUpperCased = JobApplicationId::fromString(strtoupper($value));

        self::assertTrue(JobApplicationId::fromString($value)->equals($sameIdUpperCased));
        self::assertFalse(JobApplicationId::fromString($value)->equals(PositionId::fromString($value)));
    }
}
