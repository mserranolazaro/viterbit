<?php

declare(strict_types=1);

namespace App\Tests\Integration\Recruitment\Infrastructure\Persistence\Mongo;

use App\Recruitment\Domain\Write\Aggregate\Value\Position\PositionId;
use App\Recruitment\Domain\Write\Aggregate\Value\Position\RequirementLevel;
use App\Recruitment\Domain\Write\Repository\PositionRepository;
use App\Recruitment\Infrastructure\Console\SeedPositionsCommand;
use App\Tests\IntegrationTestCase;

final class MongoPositionRepositoryTest extends IntegrationTestCase
{
    public function testItRoundTripsPositionsAndListsThemByTitle(): void
    {
        $repository = self::service(PositionRepository::class);
        foreach (SeedPositionsCommand::positions() as $position) {
            $repository->save($position);
        }

        $titles = array_map(static fn ($position): string => $position->title(), $repository->all());
        $backend = $repository->findById(PositionId::fromString(SeedPositionsCommand::BACKEND_ENGINEER_ID));

        self::assertSame(['Data Analyst', 'Frontend Engineer', 'Product Designer', 'Senior Backend Engineer'], $titles);
        self::assertNotNull($backend);
        self::assertSame('PHP', $backend->requirements()[0]->skill);
        self::assertSame(RequirementLevel::Must, $backend->requirements()[0]->level);
        self::assertSame(RequirementLevel::Nice, $backend->requirements()[5]->level);
    }

    public function testSavingTheSamePositionTwiceDoesNotDuplicateIt(): void
    {
        $repository = self::service(PositionRepository::class);
        $position = SeedPositionsCommand::positions()[0];

        $repository->save($position);
        $repository->save($position);

        self::assertCount(1, $repository->all());
    }
}
