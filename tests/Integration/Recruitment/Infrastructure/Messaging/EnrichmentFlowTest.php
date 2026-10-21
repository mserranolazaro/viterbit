<?php

declare(strict_types=1);

namespace App\Tests\Integration\Recruitment\Infrastructure\Messaging;

use App\Recruitment\Application\Command\JobApplication\EnrichJobApplication\EnrichJobApplicationCommand;
use App\Recruitment\Application\Command\JobApplication\SubmitJobApplication\SubmitJobApplicationCommand;
use App\Recruitment\Domain\Read\JobApplicationViewRepository;
use App\Recruitment\Domain\Write\Event\JobApplication\JobApplicationWasSubmitted;
use App\Recruitment\Domain\Write\Repository\PositionRepository;
use App\Recruitment\Infrastructure\Ai\SimulatedLlmClient;
use App\Recruitment\Infrastructure\Console\SeedPositionsCommand;
use App\Tests\IntegrationTestCase;
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
        foreach (SeedPositionsCommand::positions() as $position) {
            self::service(PositionRepository::class)->save($position);
        }
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

    public function testARedeliveredEnrichmentChangesNothing(): void
    {
        $id = $this->submit('PHP, Symfony and MongoDB.');
        self::runWorker();
        $before = self::service(JobApplicationViewRepository::class)->findById($id);

        self::service(MessageBusInterface::class)->dispatch(new EnrichJobApplicationCommand($id));
        self::runWorker();

        self::assertEquals($before, self::service(JobApplicationViewRepository::class)->findById($id));
    }

    private function submit(string $cv): string
    {
        $envelope = self::service(MessageBusInterface::class)->dispatch(new SubmitJobApplicationCommand(
            SeedPositionsCommand::BACKEND_ENGINEER_ID,
            'Ada Lovelace',
            'ada@example.com',
            null,
            null,
            $cv,
        ));

        /** @var string */
        return $envelope->last(HandledStamp::class)?->getResult();
    }
}
