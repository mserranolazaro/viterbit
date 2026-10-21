<?php

declare(strict_types=1);

namespace App\Recruitment\Infrastructure\Http\Controller\Position;

use App\Recruitment\Application\Query\Position\ListPositions\ListPositionsQuery;
use App\SharedKernel\Infrastructure\Http\UiController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/positions', name: 'position_list', methods: ['GET'])]
final class ListPositionsController extends UiController
{
    public function __invoke(): Response
    {
        return $this->render('positions/index.html.twig', [
            'positions' => $this->query(new ListPositionsQuery()),
        ]);
    }
}
