<?php

declare(strict_types=1);

namespace App\Recruitment\Infrastructure\Console;

use App\Recruitment\Domain\Write\Aggregate\Root\Position\Position;
use App\Recruitment\Domain\Write\Aggregate\Value\Position\PositionId;
use App\Recruitment\Domain\Write\Aggregate\Value\Position\Requirement;
use App\Recruitment\Domain\Write\Aggregate\Value\Position\RequirementLevel;
use App\Recruitment\Domain\Write\Repository\PositionRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Loads the sample positions. Ids are fixed, so running it again updates instead of duplicating.
 */
#[AsCommand(name: 'app:positions:seed', description: 'Loads the sample job positions (idempotent)')]
final class SeedPositionsCommand
{
    public const string BACKEND_ENGINEER_ID = '019a0a10-0000-7000-8000-000000000001';
    public const string FRONTEND_ENGINEER_ID = '019a0a10-0000-7000-8000-000000000002';
    public const string PRODUCT_DESIGNER_ID = '019a0a10-0000-7000-8000-000000000003';
    public const string DATA_ANALYST_ID = '019a0a10-0000-7000-8000-000000000004';

    public function __construct(
        private readonly PositionRepository $positionRepository,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $positions = self::positions();
        foreach ($positions as $position) {
            $this->positionRepository->save($position);
        }

        $io->success(sprintf('%d positions loaded.', count($positions)));

        return Command::SUCCESS;
    }

    /**
     * @return Position[]
     */
    public static function positions(): array
    {
        return [
            Position::create(
                PositionId::fromString(self::BACKEND_ENGINEER_ID),
                'Senior Backend Engineer',
                "Design and build the services behind our hiring platform: event-driven, well tested and easy "
                . "to change.\n\n"
                . 'You will own features end to end, from the domain model to the queue that processes them.',
                'Valencia · Hybrid',
                self::requirements(['PHP', 'Symfony', 'MongoDB'], ['RabbitMQ', 'Docker', 'DDD']),
            ),
            Position::create(
                PositionId::fromString(self::FRONTEND_ENGINEER_ID),
                'Frontend Engineer',
                "Craft fast, accessible interfaces that recruiters use all day.\n\n"
                . 'You will work closely with design and backend to ship complete features.',
                'Remote · Spain',
                self::requirements(['JavaScript', 'TypeScript', 'CSS'], ['React', 'Tailwind', 'Accessibility']),
            ),
            Position::create(
                PositionId::fromString(self::PRODUCT_DESIGNER_ID),
                'Product Designer',
                "Turn recruiters' daily problems into simple, delightful flows.\n\n"
                . 'You will research, prototype and test, and keep our design system coherent.',
                'Valencia · Hybrid',
                self::requirements(['Figma', 'User research', 'Prototyping'], ['Design systems', 'HTML', 'CSS']),
            ),
            Position::create(
                PositionId::fromString(self::DATA_ANALYST_ID),
                'Data Analyst',
                "Help customers understand their hiring funnels with clear metrics.\n\n"
                . 'You will model data, build dashboards and run experiments with product.',
                'Remote · Europe',
                self::requirements(['SQL', 'Python', 'Statistics'], ['dbt', 'Looker', 'A/B testing']),
            ),
        ];
    }

    /**
     * @param string[] $must
     * @param string[] $nice
     *
     * @return Requirement[]
     */
    private static function requirements(array $must, array $nice): array
    {
        $toRequirements = static fn (array $skills, RequirementLevel $level): array => array_map(
            static fn (string $skill): Requirement => new Requirement($skill, $level),
            $skills,
        );

        return [...$toRequirements($must, RequirementLevel::Must), ...$toRequirements($nice, RequirementLevel::Nice)];
    }
}
