<?php

declare(strict_types=1);

namespace App\Recruitment\Infrastructure\Console;

use App\Recruitment\Infrastructure\Persistence\Mongo\MongoJobApplicationViewRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:mongo:setup', description: 'Creates the MongoDB indexes (idempotent)')]
final class SetupMongoCommand
{
    public function __construct(
        private readonly MongoJobApplicationViewRepository $viewRepository,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $this->viewRepository->createIndexes();
        $io->success('MongoDB indexes are in place.');

        return Command::SUCCESS;
    }
}
