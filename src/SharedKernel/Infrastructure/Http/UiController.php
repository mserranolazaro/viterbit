<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Http;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Base for the HTML controllers: they only translate HTTP into commands and queries.
 */
abstract class UiController extends AbstractController
{
    private MessageBusInterface $commandBus;
    private MessageBusInterface $queryBus;

    #[Required]
    public function setBuses(MessageBusInterface $commandBus, MessageBusInterface $queryBus): void
    {
        $this->commandBus = $commandBus;
        $this->queryBus = $queryBus;
    }

    protected function command(object $command): mixed
    {
        return $this->handle($this->commandBus, $command);
    }

    protected function query(object $query): mixed
    {
        return $this->handle($this->queryBus, $query);
    }

    /**
     * Requests made by the page's own JavaScript, which only want the part that changes.
     */
    protected function isFragmentRequest(Request $request): bool
    {
        return $request->headers->get('X-Requested-With') === 'fetch';
    }

    /**
     * The same URL answers with a full page or a fragment, so caches must tell them apart.
     */
    protected function varyOnFragment(Response $response): Response
    {
        $response->setVary('X-Requested-With', false);

        return $response;
    }

    private function handle(MessageBusInterface $bus, object $message): mixed
    {
        try {
            return $bus->dispatch($message)->last(HandledStamp::class)?->getResult();
        } catch (HandlerFailedException $e) {
            // Rethrow what the handler threw (a domain exception), not Messenger's wrapper.
            $wrapped = $e->getWrappedExceptions();

            throw reset($wrapped) ?: $e;
        }
    }
}
