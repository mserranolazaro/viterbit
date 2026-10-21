<?php

declare(strict_types=1);

namespace App\Recruitment\Application\Command\JobApplication\EnrichJobApplication;

use App\Recruitment\Domain\Write\Aggregate\Root\JobApplication\JobApplication;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\JobApplicationId;
use App\Recruitment\Domain\Write\Exception\JobApplication\JobApplicationNotFoundException;
use App\Recruitment\Domain\Write\Repository\JobApplicationRepository;
use App\Recruitment\Domain\Write\Repository\PositionRepository;
use App\Recruitment\Domain\Write\Service\CvAnalysisFailedException;
use App\Recruitment\Domain\Write\Service\CvAnalyzer;
use App\SharedKernel\Domain\Event\DomainEventPublisher;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler(bus: 'command.bus')]
final class EnrichJobApplicationHandler
{
    public function __construct(
        private readonly JobApplicationRepository $jobApplicationRepository,
        private readonly PositionRepository $positionRepository,
        private readonly CvAnalyzer $cvAnalyzer,
        private readonly DomainEventPublisher $eventPublisher,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @throws CvAnalysisFailedException so Messenger retries; after the last attempt the
     *                                   application is marked as failed (see MarkEnrichmentAsFailed)
     */
    public function __invoke(EnrichJobApplicationCommand $command): void
    {
        $id = JobApplicationId::fromString($command->jobApplicationId);
        $jobApplication = $this->jobApplicationRepository->findById($id);
        if ($jobApplication === null) {
            // Retrying cannot make it appear: give up straight away.
            throw new UnrecoverableMessageHandlingException(
                'Nothing to enrich.',
                previous: JobApplicationNotFoundException::withId($id),
            );
        }

        // Delivery is at least once: an already enriched or failed application is left alone.
        if ($jobApplication->status()->isFinal()) {
            return;
        }

        $position = $this->positionRepository->findById($jobApplication->positionId());
        if ($position === null) {
            $jobApplication->failEnrichment('The position no longer exists.', $this->clock->now());
            $this->saveAndPublish($jobApplication);

            return;
        }

        // Saved before calling the AI so the UI shows "enriching" while the analysis runs.
        $jobApplication->startEnrichment($this->clock->now());
        $this->saveAndPublish($jobApplication);

        $analysis = $this->cvAnalyzer->analyze($jobApplication->cvText(), $position);

        $jobApplication->completeEnrichment($analysis, $this->clock->now());
        $this->saveAndPublish($jobApplication);
    }

    private function saveAndPublish(JobApplication $jobApplication): void
    {
        $this->jobApplicationRepository->save($jobApplication);
        $this->eventPublisher->publish(...$jobApplication->pullDomainEvents());
    }
}
