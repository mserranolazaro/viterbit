<?php

declare(strict_types=1);

namespace App\Recruitment\Infrastructure\Http\Exception;

use App\Recruitment\Domain\Write\Exception\JobApplication\JobApplicationNotFoundException;
use App\Recruitment\Domain\Write\Exception\Position\PositionNotFoundException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Maps domain exceptions that reach the HTTP layer to status codes, in one table.
 * Validation errors never get here: controllers re-render the form with them.
 */
#[AsEventListener]
final class RecruitmentExceptionListener
{
    private const array STATUS_BY_EXCEPTION = [
        PositionNotFoundException::class => 404,
        JobApplicationNotFoundException::class => 404,
    ];

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        foreach (self::STATUS_BY_EXCEPTION as $class => $status) {
            if ($exception instanceof $class) {
                $event->setThrowable(new HttpException($status, $exception->getMessage(), $exception));

                return;
            }
        }
    }
}
