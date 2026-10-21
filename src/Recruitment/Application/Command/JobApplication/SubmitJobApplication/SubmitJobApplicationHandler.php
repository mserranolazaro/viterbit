<?php

declare(strict_types=1);

namespace App\Recruitment\Application\Command\JobApplication\SubmitJobApplication;

use App\Recruitment\Domain\Write\Aggregate\Root\JobApplication\JobApplication;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\Candidate;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\CvText;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\Email;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\JobApplicationId;
use App\Recruitment\Domain\Write\Aggregate\Value\Position\PositionId;
use App\Recruitment\Domain\Write\Exception\Position\PositionNotFoundException;
use App\Recruitment\Domain\Write\Repository\JobApplicationRepository;
use App\Recruitment\Domain\Write\Repository\PositionRepository;
use App\SharedKernel\Domain\Event\DomainEventPublisher;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class SubmitJobApplicationHandler
{
    public function __construct(
        private readonly JobApplicationRepository $jobApplicationRepository,
        private readonly PositionRepository $positionRepository,
        private readonly DomainEventPublisher $eventPublisher,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @return string The id of the new job application
     */
    public function __invoke(SubmitJobApplicationCommand $command): string
    {
        $positionId = PositionId::fromString($command->positionId);
        if ($this->positionRepository->findById($positionId) === null) {
            throw PositionNotFoundException::withId($positionId);
        }

        $jobApplicationId = JobApplicationId::generate();
        $jobApplication = JobApplication::submit(
            $jobApplicationId,
            $positionId,
            Candidate::create($command->fullName, Email::fromString($command->email), $command->phone),
            $command->notes,
            CvText::fromString($command->cvText),
            $this->clock->now(),
        );

        $this->jobApplicationRepository->save($jobApplication);
        $this->eventPublisher->publish(...$jobApplication->pullDomainEvents());

        return $jobApplicationId->value();
    }
}
