<?php

declare(strict_types=1);

namespace App\Tests\Integration\Recruitment\Infrastructure\Messaging;

use App\Recruitment\Application\Command\JobApplication\EnrichJobApplication\EnrichJobApplicationCommand;
use App\Recruitment\Application\Command\JobApplication\ReconcileJobApplications\ReconcileJobApplicationsCommand;
use App\Recruitment\Application\Command\JobApplication\SubmitJobApplication\SubmitJobApplicationCommand;
use App\Recruitment\Domain\Read\JobApplicationViewRepository;
use App\Recruitment\Domain\Write\Aggregate\Value\Position\PositionId;
use App\Recruitment\Domain\Write\Repository\JobApplicationRepository;
use App\Recruitment\Domain\Write\Event\JobApplication\JobApplicationWasSubmitted;
use App\Recruitment\Infrastructure\Ai\SimulatedLlmClient;
use App\Recruitment\Infrastructure\Console\SeedPositionsCommand;
use App\Tests\IntegrationTestCase;
use App\Tests\Support\JobApplicationMother;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * The whole asynchronous path through the real buses, transports, worker, retries and MongoDB.
 */
final class EnrichmentFlowTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::seedPositions();
    }

    public function testSubmittingQueuesTheEventAndTheWorkerEnrichesTheApplication(): void
    {
        $id = $this->submit("Backend developer\n7 years with PHP, Symfony and MongoDB. Some Docker.");

        // Nothing is processed synchronously: the event waits in the queue and there is no view yet.
        $queued = self::transport('events')->getSent();
        self::assertCount(1, $queued);
        self::assertInstanceOf(JobApplicationWasSubmitted::class, $queued[0]->getMessage());
        self::assertNull(self::service(JobApplicationViewRepository::class)->findById($id));

        self::runWorker();

        $view = self::service(JobApplicationViewRepository::class)->findById($id);
        self::assertNotNull($view);
        self::assertSame('enriched', $view->status);
        self::assertSame('Senior Backend Engineer', $view->positionTitle);
        // Must: PHP, Symfony, MongoDB (3 × 3) + nice: Docker (1) out of 9 + 3 → 10 / 12.
        self::assertSame(83, $view->score);
        self::assertSame('cv_analysis.v1', $view->promptVersion);
        self::assertStringContainsString('Missing: RabbitMQ, DDD.', (string) $view->summary);
        self::assertCount(1, self::transport('enrichment')->getSent());
    }

    public function testTheApplicationIsMarkedAsFailedOnceTheRetriesAreExhausted(): void
    {
        $id = $this->submit('PHP developer ' . SimulatedLlmClient::FAILURE_MARKER);

        self::runWorker();

        $view = self::service(JobApplicationViewRepository::class)->findById($id);
        self::assertSame('failed', $view?->status);
        self::assertStringStartsWith(
            'Enrichment failed after 3 attempt(s): The LLM answer does not match the schema',
            (string) $view->failureReason,
        );
        self::assertNull($view->score);
        // 1 attempt + 2 retries, then the message is kept in the failure transport.
        self::assertCount(3, self::transport('enrichment')->getSent());
        self::assertCount(1, self::transport('failed')->getSent());
    }

    public function testTheReconcilerRecoversAnApplicationWhoseEventsWereLost(): void
    {
        // Saved, but its events never reached the broker (e.g. RabbitMQ was down).
        $lost = JobApplicationMother::received(PositionId::fromString(SeedPositionsCommand::BACKEND_ENGINEER_ID));
        self::service(JobApplicationRepository::class)->save($lost);
        $id = $lost->id()->value();
        self::assertNull($this->statusOf($id));

        self::service(MessageBusInterface::class)->dispatch(new ReconcileJobApplicationsCommand());

        // The recruiter can see it right away; the enrichment it never got is queued again.
        self::assertSame('received', $this->statusOf($id));
        self::runWorker();
        self::assertSame('enriched', $this->statusOf($id));
    }

    public function testTheReconcilerLeavesRecentAndFinishedApplicationsAlone(): void
    {
        $this->submit('PHP, Symfony and MongoDB.');
        self::runWorker();
        $this->submit('Recent and still queued.', 'grace@example.com');
        $queued = count(self::transport('enrichment')->getSent());

        self::service(MessageBusInterface::class)->dispatch(new ReconcileJobApplicationsCommand());

        self::assertCount($queued, self::transport('enrichment')->getSent());
    }

    public function testARedeliveredEnrichmentChangesNothing(): void
    {
        $id = $this->submit('PHP, Symfony and MongoDB.');
        self::runWorker();
        $before = self::service(JobApplicationViewRepository::class)->findById($id);

        self::service(MessageBusInterface::class)->dispatch(new EnrichJobApplicationCommand($id));
        self::runWorker();

        self::assertEquals($before, self::service(JobApplicationViewRepository::class)->findById($id));
    }

    private function statusOf(string $id): ?string
    {
        return self::service(JobApplicationViewRepository::class)->findById($id)?->status;
    }

    private function submit(string $cv, string $email = 'ada@example.com'): string
    {
        $envelope = self::service(MessageBusInterface::class)->dispatch(new SubmitJobApplicationCommand(
            SeedPositionsCommand::BACKEND_ENGINEER_ID,
            'Ada Lovelace',
            $email,
            null,
            null,
            $cv,
            true,
        ));

        /** @var string */
        return $envelope->last(HandledStamp::class)?->getResult();
    }
}
