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
        // Shared or hand-edited URLs must still show a list: anything unreadable is ignored.
        $filters = [
            'q' => self::text($request, 'q'),
            'status' => self::text($request, 'status'),
            'position' => self::text($request, 'position'),
        ];
        $pageNumber = filter_var(self::text($request, 'page'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        $page = $this->query(new SearchJobApplicationsQuery(
            status: $filters['status'] ?: null,
            positionId: $filters['position'] ?: null,
            search: $filters['q'] ?: null,
            page: $pageNumber === false ? 1 : $pageNumber,
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

    private static function text(Request $request, string $name): string
    {
        $value = $request->query->all()[$name] ?? '';

        return is_string($value) ? $value : '';
    }
}
