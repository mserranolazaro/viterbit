<?php

declare(strict_types=1);

namespace App\Recruitment\Infrastructure\Http\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/', name: 'home', methods: ['GET'])]
final class HomeController extends AbstractController
{
    public function __invoke(): RedirectResponse
    {
        return $this->redirectToRoute('position_list');
    }
}
