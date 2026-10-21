<?php

declare(strict_types=1);

namespace App\Recruitment\Infrastructure\Http\Controller\Position;

use App\Recruitment\Application\Query\Position\GetPosition\GetPositionQuery;
use App\SharedKernel\Infrastructure\Http\UiController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/positions/{id}', name: 'position_show', methods: ['GET'])]
final class ShowPositionController extends UiController
{
    public function __invoke(string $id): Response
    {
        $position = $this->query(new GetPositionQuery($id))
            ?? throw $this->createNotFoundException('Position not found.');

        return $this->render('positions/show.html.twig', ['position' => $position]);
    }
}
