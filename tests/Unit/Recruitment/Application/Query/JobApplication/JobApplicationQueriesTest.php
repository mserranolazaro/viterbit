<?php

declare(strict_types=1);

namespace App\Tests\Unit\Recruitment\Application\Query\JobApplication;

use App\Recruitment\Application\Query\JobApplication\GetJobApplication\GetJobApplicationHandler;
use App\Recruitment\Application\Query\JobApplication\GetJobApplication\GetJobApplicationQuery;
use App\Recruitment\Application\Query\JobApplication\SearchJobApplications\SearchJobApplicationsHandler;
use App\Recruitment\Application\Query\JobApplication\SearchJobApplications\SearchJobApplicationsQuery;
use App\Recruitment\Domain\Read\JobApplicationCriteria;
use App\Recruitment\Domain\Read\JobApplicationViewPage;
use App\Recruitment\Domain\Read\JobApplicationViewRepository;
use PHPUnit\Framework\TestCase;

final class JobApplicationQueriesTest extends TestCase
{
    private const string POSITION_ID = '019a0000-0000-7000-8000-000000000001';

    public function testItTranslatesTheFiltersIntoCriteria(): void
    {
        $criteria = $this->searchWith(
            new SearchJobApplicationsQuery('enriched', strtoupper(self::POSITION_ID), '  ada ', 2),
        );

        self::assertSame('enriched', $criteria->status);
        self::assertSame(self::POSITION_ID, $criteria->positionId);
        self::assertSame('ada', $criteria->search);
        self::assertSame(2, $criteria->page);
    }

    public function testItIgnoresFiltersItDoesNotUnderstand(): void
    {
        $criteria = $this->searchWith(new SearchJobApplicationsQuery('hired', 'not-a-uuid', '   ', -3));

        self::assertNull($criteria->status);
        self::assertNull($criteria->positionId);
        self::assertNull($criteria->search);
        self::assertSame(1, $criteria->page);
    }

    public function testItReturnsNothingForAMalformedId(): void
    {
        $repository = $this->createMock(JobApplicationViewRepository::class);
        $repository->expects(self::never())->method('findById');

        self::assertNull(new GetJobApplicationHandler($repository)(new GetJobApplicationQuery('../etc/passwd')));
    }

    private function searchWith(SearchJobApplicationsQuery $query): JobApplicationCriteria
    {
        $received = null;
        $repository = $this->createMock(JobApplicationViewRepository::class);
        $repository->expects(self::once())->method('search')
            ->willReturnCallback(
                static function (JobApplicationCriteria $criteria) use (&$received): JobApplicationViewPage {
                    $received = $criteria;

                    return new JobApplicationViewPage([], 0, $criteria->page, $criteria->perPage);
                },
            );

        new SearchJobApplicationsHandler($repository)($query);
        self::assertInstanceOf(JobApplicationCriteria::class, $received);

        return $received;
    }
}
