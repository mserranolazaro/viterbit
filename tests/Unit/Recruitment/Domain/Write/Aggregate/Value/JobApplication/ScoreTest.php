<?php

declare(strict_types=1);

namespace App\Tests\Unit\Recruitment\Domain\Write\Aggregate\Value\JobApplication;

use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\CvAnalysis;
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

    public function testAnAnalysisNeedsASummary(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CvAnalysis("  \n ", Score::fromInt(50), 'cv_analysis.v1');
    }

    public function testAnAnalysisKeepsTheSkillsInOrder(): void
    {
        $analysis = new CvAnalysis(' Fits. ', Score::fromInt(50), 'cv_analysis.v1', [2 => 'PHP', 5 => 'Go'], ['DDD']);

        self::assertSame('Fits.', $analysis->summary);
        self::assertSame(['PHP', 'Go'], $analysis->matchedSkills);
        self::assertSame(['DDD'], $analysis->missingSkills);
    }

    #[TestWith([-1])]
    #[TestWith([101])]
    public function testItRejectsValuesOutOfRange(int $value): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Score::fromInt($value);
    }
}
