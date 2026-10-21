<?php

declare(strict_types=1);

namespace App\Tests\Unit\Recruitment\Domain\Write\Aggregate\Value\JobApplication;

use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\Candidate;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\Email;
use App\Recruitment\Domain\Write\Exception\JobApplication\InvalidJobApplicationDataException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CandidateTest extends TestCase
{
    public function testItNormalisesTheNameAndPhone(): void
    {
        $candidate = Candidate::create("  Ada   \n Lovelace ", Email::fromString(' Ada@Example.COM '), '  ');

        self::assertSame('Ada Lovelace', $candidate->fullName());
        self::assertSame('ada@example.com', $candidate->email()->value());
        self::assertNull($candidate->phone());
    }

    public function testItAcceptsAnInternationalPhone(): void
    {
        $candidate = Candidate::create('Ada Lovelace', Email::fromString('ada@example.com'), '+34 (600) 000-000');

        self::assertSame('+34 (600) 000-000', $candidate->phone());
    }

    /**
     * @return iterable<string, array{string, ?string}>
     */
    public static function invalidCandidates(): iterable
    {
        yield 'empty name' => ['   ', null];
        yield 'name made of invisible characters' => ["\u{200B}\u{200B}", null];
        yield 'name too long' => [str_repeat('a', Candidate::FULL_NAME_MAX_LENGTH + 1), null];
        yield 'phone with letters' => ['Ada Lovelace', 'call me'];
        yield 'phone too short' => ['Ada Lovelace', '123'];
    }

    #[DataProvider('invalidCandidates')]
    public function testItRejectsInvalidData(string $fullName, ?string $phone): void
    {
        $this->expectException(InvalidJobApplicationDataException::class);

        Candidate::create($fullName, Email::fromString('ada@example.com'), $phone);
    }

    public function testItRejectsAnInvalidEmail(): void
    {
        $this->expectException(InvalidJobApplicationDataException::class);

        Email::fromString('not-an-email');
    }
}
