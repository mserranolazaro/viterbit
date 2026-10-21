<?php

declare(strict_types=1);

namespace App\Recruitment\Infrastructure\Http\Controller\JobApplication;

use App\Recruitment\Application\Query\JobApplication\GetJobApplication\GetJobApplicationQuery;
use App\SharedKernel\Infrastructure\Http\UiController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/applications/{id}', name: 'job_application_show', methods: ['GET'])]
final class ShowJobApplicationController extends UiController
{
    public function __invoke(string $id, Request $request): Response
    {
        $application = $this->query(new GetJobApplicationQuery($id));
        // Only look at the session if the visitor already has one: reading it would start one
        // (and set a cookie) for every recruiter opening an application.
        $session = $request->hasPreviousSession() ? $request->getSession() : null;
        $justSubmitted = $session?->get(SubmitJobApplicationController::JUST_SUBMITTED);

        if ($application === null) {
            // Right after submitting, the view may not be projected yet (eventual consistency):
            // the candidate who just applied gets a "being filed" state; anyone else a 404.
            if ($justSubmitted !== strtolower($id)) {
                throw $this->createNotFoundException('Application not found.');
            }
        } elseif ($justSubmitted === $application->id) {
            $session?->remove(SubmitJobApplicationController::JUST_SUBMITTED);
        }

        $template = $this->isFragmentRequest($request)
            ? 'applications/_detail.html.twig'
            : 'applications/show.html.twig';

        return $this->varyOnFragment($this->render($template, ['application' => $application]));
    }
}
