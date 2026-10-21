<?php

declare(strict_types=1);

namespace App\Recruitment\Infrastructure\Console;

use App\Recruitment\Application\Command\JobApplication\SubmitJobApplication\SubmitJobApplicationCommand;
use App\Recruitment\Infrastructure\Ai\SimulatedLlmClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Submits a handful of sample applications through the real use case, so the demo shows
 * the asynchronous enrichment happening (including one that fails on purpose).
 */
#[AsCommand(name: 'app:demo:applications', description: 'Submits sample applications through the normal flow')]
final class SubmitDemoApplicationsCommand
{
    private const array CANDIDATES = [
        [
            SeedPositionsCommand::BACKEND_ENGINEER_ID, 'Ada Lovelace', 'ada@example.com',
            "Senior backend engineer\n8 years with PHP and Symfony, MongoDB in production, RabbitMQ for async jobs.",
        ],
        [
            SeedPositionsCommand::BACKEND_ENGINEER_ID, 'Linus Torvalds', 'linus@example.org',
            "Systems programmer\nC, Git, Linux kernel. Some Docker. 30 years of experience.",
        ],
        [
            SeedPositionsCommand::FRONTEND_ENGINEER_ID, 'Sara Soueidan', 'sara@example.net',
            "Frontend engineer\nCSS, JavaScript, TypeScript and accessibility audits. Design systems with Tailwind.",
        ],
        [
            SeedPositionsCommand::FRONTEND_ENGINEER_ID, 'Dan Abramov', 'dan@example.com',
            "UI engineer\nReact and JavaScript for 9 years. Writes about TypeScript.",
        ],
        [
            SeedPositionsCommand::PRODUCT_DESIGNER_ID, 'Julie Zhuo', 'julie@example.com',
            "Product designer\nFigma, prototyping and user research. Built a design system used by 40 teams.",
        ],
        [
            SeedPositionsCommand::DATA_ANALYST_ID, 'Hadley Wickham', 'hadley@example.org',
            "Data scientist\nR and SQL daily, statistics background, dashboards in Looker. 12 years of experience.",
        ],
        [
            SeedPositionsCommand::DATA_ANALYST_ID, 'Broken Pipeline', 'broken@example.com',
            'Python and SQL analyst ' . SimulatedLlmClient::FAILURE_MARKER,
        ],
    ];

    public function __construct(
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        foreach (self::CANDIDATES as [$positionId, $name, $email, $cv]) {
            $this->commandBus->dispatch(new SubmitJobApplicationCommand($positionId, $name, $email, null, null, $cv));
        }

        $io->success(sprintf('%d applications submitted; the worker is enriching them now.', count(self::CANDIDATES)));

        return Command::SUCCESS;
    }
}
