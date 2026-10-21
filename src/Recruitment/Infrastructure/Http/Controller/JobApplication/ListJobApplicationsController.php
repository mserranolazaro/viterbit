<?php

declare(strict_types=1);

namespace App\Recruitment\Infrastructure\Http\Controller\JobApplication;

use App\Recruitment\Application\Query\JobApplication\SearchJobApplications\SearchJobApplicationsQuery;
use App\Recruitment\Application\Query\Position\ListPositions\ListPositionsQuery;
use App\SharedKernel\Infrastructure\Http\UiController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/applications', name: 'job_application_list', methods: ['GET'])]
final class ListJobApplicationsController extends UiController
{
    public function __invoke(Request $request): Response
    {
        $filters = [
            'q' => $request->query->getString('q'),
            'status' => $request->query->getString('status'),
            'position' => $request->query->getString('position'),
        ];

        $page = $this->query(new SearchJobApplicationsQuery(
            status: $filters['status'] ?: null,
            positionId: $filters['position'] ?: null,
            search: $filters['q'] ?: null,
            page: max(1, $request->query->getInt('page', 1)),
        ));

        if ($this->isFragmentRequest($request)) {
            return $this->varyOnFragment($this->render('applications/_results.html.twig', ['page' => $page]));
        }

        return $this->varyOnFragment($this->render('applications/index.html.twig', [
            'page' => $page,
            'filters' => $filters,
            'positions' => $this->query(new ListPositionsQuery()),
        ]));
    }
}
