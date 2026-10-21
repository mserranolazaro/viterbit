<?php

declare(strict_types=1);

namespace App\Tests\Unit\Recruitment\Infrastructure\Http;

use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\JobApplicationId;
use App\Recruitment\Domain\Write\Aggregate\Value\Position\PositionId;
use App\Recruitment\Domain\Write\Exception\JobApplication\JobApplicationNotFoundException;
use App\Recruitment\Domain\Write\Exception\Position\PositionNotFoundException;
use App\Recruitment\Infrastructure\Http\Exception\RecruitmentExceptionListener;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class RecruitmentExceptionListenerTest extends TestCase
{
    /**
     * @return iterable<string, array{\Throwable}>
     */
    public static function notFound(): iterable
    {
        // E.g. a position removed between showing the form and submitting it.
        yield 'position' => [PositionNotFoundException::withId(PositionId::generate())];
        yield 'job application' => [JobApplicationNotFoundException::withId(JobApplicationId::generate())];
    }

    #[DataProvider('notFound')]
    public function testDomainNotFoundBecomesA404(\Throwable $exception): void
    {
        $event = $this->eventFor($exception);

        new RecruitmentExceptionListener()($event);

        $mapped = $event->getThrowable();
        self::assertInstanceOf(HttpExceptionInterface::class, $mapped);
        self::assertSame(404, $mapped->getStatusCode());
        self::assertSame($exception, $mapped->getPrevious());
    }

    public function testOtherExceptionsAreLeftAlone(): void
    {
        $exception = new \LogicException('Bug');
        $event = $this->eventFor($exception);

        new RecruitmentExceptionListener()($event);

        self::assertSame($exception, $event->getThrowable());
    }

    private function eventFor(\Throwable $exception): ExceptionEvent
    {
        return new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            $exception,
        );
    }
}
