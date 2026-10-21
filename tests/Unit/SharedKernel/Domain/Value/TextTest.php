<?php

declare(strict_types=1);

namespace App\Tests\Unit\SharedKernel\Domain\Value;

use App\SharedKernel\Domain\Value\Text;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TextTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function singleLines(): iterable
    {
        yield 'surrounding and repeated whitespace' => ["  Ada \t\n  Lovelace  ", 'Ada Lovelace'];
        yield 'zero-width and direction marks' => ["\u{200B}Ada\u{200E} \u{FEFF}Lovelace\u{200B}", 'Ada Lovelace'];
        yield 'only invisible characters' => ["\u{200B}\u{200B}\u{2060}", ''];
        yield 'control characters' => ["Ada\x00\x1b[31m Lovelace", 'Ada [31m Lovelace'];
        yield 'non-breaking and other spaces' => ["Ada\u{00A0}\u{2003}Lovelace", 'Ada Lovelace'];
        yield 'accents, emoji and other scripts are kept' => ['Zoë 😀 مرحبا', 'Zoë 😀 مرحبا'];
        yield 'invalid UTF-8 never breaks it' => ["Ada \xff Lovelace", 'Ada ? Lovelace'];
    }

    #[DataProvider('singleLines')]
    public function testItNormalisesSingleLineText(string $input, string $expected): void
    {
        self::assertSame($expected, Text::singleLine($input));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function multiLines(): iterable
    {
        yield 'Windows and old Mac line breaks' => ["PHP\r\nSymfony\rMongoDB", "PHP\nSymfony\nMongoDB"];
        yield 'tabs and indentation are kept' => ["Skills:\n\t- PHP\n    - Go", "Skills:\n\t- PHP\n    - Go"];
        yield 'control characters are removed' => ["PHP\x01 dev\x1b[0m\x7f", 'PHP dev[0m'];
        yield 'surrounding blank lines are trimmed' => ["\n\n  CV  \n\n", 'CV'];
    }

    #[DataProvider('multiLines')]
    public function testItNormalisesMultiLineText(string $input, string $expected): void
    {
        self::assertSame($expected, Text::multiLine($input));
    }
}
