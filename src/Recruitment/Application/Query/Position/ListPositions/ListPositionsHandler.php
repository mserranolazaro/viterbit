<?php

declare(strict_types=1);

namespace App\Recruitment\Application\Query\Position\ListPositions;

use App\Recruitment\Domain\Write\Aggregate\Root\Position\Position;
use App\Recruitment\Domain\Write\Aggregate\Value\Position\Requirement;
use App\Recruitment\Domain\Write\Repository\PositionRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class ListPositionsHandler
{
    public function __construct(
        private readonly PositionRepository $positionRepository,
    ) {
    }

    /**
     * @return PositionResponse[]
     */
    public function __invoke(ListPositionsQuery $query): array
    {
        return array_map(
            static fn (Position $position): PositionResponse => new PositionResponse(
                $position->id()->value(),
                $position->title(),
                $position->location(),
                array_values(array_map(
                    static fn (Requirement $requirement): string => $requirement->skill,
                    array_filter($position->requirements(), static fn (Requirement $r): bool => $r->isMandatory()),
                )),
            ),
            $this->positionRepository->all(),
        );
    }
}
