<?php

declare(strict_types=1);

namespace App\Tests\Unit\Recruitment\Domain\Write\Aggregate\Value\JobApplication;

use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\JobApplicationStatus as Status;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class JobApplicationStatusTest extends TestCase
{
    /**
     * @return iterable<string, array{Status, Status, bool}>
     */
    public static function transitions(): iterable
    {
        foreach (Status::cases() as $from) {
            foreach (Status::cases() as $to) {
                $allowed = in_array([$from, $to], [
                    [Status::Received, Status::Enriching],
                    [Status::Received, Status::Failed],
                    [Status::Enriching, Status::Enriched],
                    [Status::Enriching, Status::Failed],
                ], true);

                yield sprintf('%s → %s', $from->value, $to->value) => [$from, $to, $allowed];
            }
        }
    }

    #[DataProvider('transitions')]
    public function testItOnlyAllowsTheDocumentedTransitions(Status $from, Status $to, bool $allowed): void
    {
        self::assertSame($allowed, $from->canTransitionTo($to));
    }

    public function testItKnowsWhichStatusesAreFinal(): void
    {
        self::assertFalse(Status::Received->isFinal());
        self::assertFalse(Status::Enriching->isFinal());
        self::assertTrue(Status::Enriched->isFinal());
        self::assertTrue(Status::Failed->isFinal());
    }
}
