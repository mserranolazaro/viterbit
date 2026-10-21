<?php

declare(strict_types=1);

namespace App\Recruitment\Infrastructure\Console;

use App\Recruitment\Infrastructure\Persistence\Mongo\MongoJobApplicationRepository;
use App\Recruitment\Infrastructure\Persistence\Mongo\MongoJobApplicationViewRepository;
use MongoDB\Driver\Exception\CommandException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:mongo:setup', description: 'Creates the MongoDB indexes (idempotent)')]
final class SetupMongoCommand
{
    private const int DUPLICATE_KEY = 11000;

    public function __construct(
        private readonly MongoJobApplicationRepository $jobApplicationRepository,
        private readonly MongoJobApplicationViewRepository $viewRepository,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        try {
            $this->jobApplicationRepository->createIndexes();
        } catch (CommandException $e) {
            if ($e->getCode() !== self::DUPLICATE_KEY) {
                throw $e;
            }

            // Data written before the rule existed: deciding which duplicate to keep is a human call.
            $io->error([
                'Some candidates applied more than once to the same position, so the unique index',
                'cannot be created. Remove the duplicates and run this command again.',
                $e->getMessage(),
            ]);

            return Command::FAILURE;
        }

        $this->viewRepository->createIndexes();
        $io->success('MongoDB indexes are in place.');

        return Command::SUCCESS;
    }
}
