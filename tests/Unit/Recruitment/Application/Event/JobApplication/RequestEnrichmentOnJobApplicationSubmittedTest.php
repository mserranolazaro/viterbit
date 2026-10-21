<?php

declare(strict_types=1);

namespace App\Tests\Unit\Recruitment\Application\Event\JobApplication;

use App\Recruitment\Application\Command\JobApplication\EnrichJobApplication\EnrichJobApplicationCommand;
use App\Recruitment\Application\Event\JobApplication\RequestEnrichmentOnJobApplicationSubmitted;
use App\Recruitment\Domain\Write\Event\JobApplication\JobApplicationWasSubmitted;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class RequestEnrichmentOnJobApplicationSubmittedTest extends TestCase
{
    public function testItAsksForTheEnrichmentOfTheSubmittedApplication(): void
    {
        $commandBus = $this->createMock(MessageBusInterface::class);
        $commandBus->expects(self::once())->method('dispatch')
            ->with(self::callback(
                static fn (EnrichJobApplicationCommand $command): bool => $command->jobApplicationId === 'app-id',
            ))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        new RequestEnrichmentOnJobApplicationSubmitted($commandBus)(
            new JobApplicationWasSubmitted('app-id', 'position-id', new \DateTimeImmutable()),
        );
    }
}
