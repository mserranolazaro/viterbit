<?php

declare(strict_types=1);

namespace App\Recruitment\Application\Query\Position\GetPosition;

use App\Recruitment\Domain\Write\Aggregate\Value\Position\PositionId;
use App\Recruitment\Domain\Write\Aggregate\Value\Position\Requirement;
use App\Recruitment\Domain\Write\Repository\PositionRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler(bus: 'query.bus')]
final class GetPositionHandler
{
    public function __construct(
        private readonly PositionRepository $positionRepository,
    ) {
    }

    public function __invoke(GetPositionQuery $query): ?PositionDetailResponse
    {
        if (!Uuid::isValid($query->id)) {
            return null;
        }

        $position = $this->positionRepository->findById(PositionId::fromString($query->id));
        if ($position === null) {
            return null;
        }

        return new PositionDetailResponse(
            $position->id()->value(),
            $position->title(),
            $position->description(),
            $position->location(),
            array_map(
                static fn (Requirement $requirement): array => [
                    'skill' => $requirement->skill,
                    'level' => $requirement->level->value,
                ],
                $position->requirements(),
            ),
        );
    }
}
