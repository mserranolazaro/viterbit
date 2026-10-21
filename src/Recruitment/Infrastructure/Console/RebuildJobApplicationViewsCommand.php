<?php

declare(strict_types=1);

namespace App\Recruitment\Infrastructure\Console;

use App\Recruitment\Application\Service\JobApplicationViewProjector;
use App\Recruitment\Domain\Read\JobApplicationViewRepository;
use App\Recruitment\Domain\Write\Repository\JobApplicationRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Regenerates every view from the write model, e.g. after changing how views are built.
 * Uses the same projector as the event subscriber, so the result is identical.
 */
#[AsCommand(name: 'app:views:rebuild', description: 'Rebuilds the job application read model from the write model')]
final class RebuildJobApplicationViewsCommand
{
    public function __construct(
        private readonly JobApplicationRepository $jobApplicationRepository,
        private readonly JobApplicationViewRepository $viewRepository,
        private readonly JobApplicationViewProjector $projector,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $count = 0;
        foreach ($this->jobApplicationRepository->all() as $jobApplication) {
            $this->viewRepository->save($this->projector->viewOf($jobApplication));
            ++$count;
        }

        $io->success(sprintf('%d job application views rebuilt.', $count));

        return Command::SUCCESS;
    }
}
