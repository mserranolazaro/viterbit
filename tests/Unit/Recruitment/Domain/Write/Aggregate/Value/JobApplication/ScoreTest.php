<?php

declare(strict_types=1);

namespace App\Tests\Unit\Recruitment\Domain\Write\Aggregate\Value\JobApplication;

use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\Score;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class ScoreTest extends TestCase
{
    #[TestWith([0])]
    #[TestWith([100])]
    public function testItAcceptsTheBounds(int $value): void
    {
        self::assertSame($value, Score::fromInt($value)->value());
    }

    #[TestWith([-1])]
    #[TestWith([101])]
    public function testItRejectsValuesOutOfRange(int $value): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Score::fromInt($value);
    }
}
