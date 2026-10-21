<?php

declare(strict_types=1);

namespace App\Tests\Unit\Recruitment\Application\Query\Position;

use App\Recruitment\Application\Query\Position\GetPosition\GetPositionHandler;
use App\Recruitment\Application\Query\Position\GetPosition\GetPositionQuery;
use App\Recruitment\Application\Query\Position\ListPositions\ListPositionsHandler;
use App\Recruitment\Application\Query\Position\ListPositions\ListPositionsQuery;
use App\Recruitment\Domain\Write\Repository\PositionRepository;
use App\Tests\Support\JobApplicationMother;
use PHPUnit\Framework\TestCase;

final class PositionQueriesTest extends TestCase
{
    public function testItListsPositionsWithTheirMustHaveSkills(): void
    {
        $position = JobApplicationMother::position();
        $repository = $this->createStub(PositionRepository::class);
        $repository->method('all')->willReturn([$position]);

        $responses = new ListPositionsHandler($repository)(new ListPositionsQuery());

        self::assertCount(1, $responses);
        self::assertSame($position->id()->value(), $responses[0]->id);
        self::assertSame('Backend Engineer', $responses[0]->title);
        self::assertSame(['PHP'], $responses[0]->mustHaveSkills);
    }

    public function testItGetsAPositionWithAllItsRequirements(): void
    {
        $position = JobApplicationMother::position();
        $repository = $this->createStub(PositionRepository::class);
        $repository->method('findById')->willReturn($position);

        $response = new GetPositionHandler($repository)(new GetPositionQuery($position->id()->value()));

        self::assertNotNull($response);
        self::assertSame('Build the hiring platform.', $response->description);
        self::assertSame(
            [['skill' => 'PHP', 'level' => 'must'], ['skill' => 'MongoDB', 'level' => 'nice']],
            $response->requirements,
        );
    }

    public function testItReturnsNothingForAMalformedId(): void
    {
        $repository = $this->createMock(PositionRepository::class);
        $repository->expects(self::never())->method('findById');

        self::assertNull(new GetPositionHandler($repository)(new GetPositionQuery('not-a-uuid')));
    }
}
