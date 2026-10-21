<?php

declare(strict_types=1);

namespace App\Tests\Unit\Recruitment\Domain\Write\Aggregate\Value\JobApplication;

use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\CvText;
use App\Recruitment\Domain\Write\Exception\JobApplication\InvalidJobApplicationDataException;
use PHPUnit\Framework\TestCase;

final class CvTextTest extends TestCase
{
    public function testItKeepsTheTextVerbatimExceptSurroundingWhitespace(): void
    {
        $cv = CvText::fromString("\n  Experience:\n  - PHP\n");

        self::assertSame("Experience:\n  - PHP", $cv->value());
    }

    public function testItCountsALineBreakAsOneCharacterLikeTheBrowserDoes(): void
    {
        // 20,000 characters as the textarea counts them, sent with \r\n line breaks.
        $cv = CvText::fromString(str_repeat(str_repeat('a', 99) . "\r\n", 200));

        self::assertSame(19999, mb_strlen($cv->value()));
        self::assertStringNotContainsString("\r", $cv->value());
    }

    public function testItRejectsAnEmptyCv(): void
    {
        $this->expectException(InvalidJobApplicationDataException::class);

        CvText::fromString(" \n\t ");
    }

    public function testItAcceptsTheMaximumLengthCountingCharactersNotBytes(): void
    {
        $cv = CvText::fromString(str_repeat('ñ', CvText::MAX_LENGTH));

        self::assertSame(CvText::MAX_LENGTH, mb_strlen($cv->value()));
    }

    public function testItRejectsACvThatIsTooLong(): void
    {
        $this->expectException(InvalidJobApplicationDataException::class);

        CvText::fromString(str_repeat('a', CvText::MAX_LENGTH + 1));
    }
}
