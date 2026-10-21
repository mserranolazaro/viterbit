<?php

declare(strict_types=1);

namespace App\Recruitment\Infrastructure\Http\Controller\JobApplication;

use App\Recruitment\Application\Command\JobApplication\SubmitJobApplication\SubmitJobApplicationCommand;
use App\Recruitment\Application\Query\Position\GetPosition\GetPositionQuery;
use App\Recruitment\Application\Query\Position\GetPosition\PositionDetailResponse;
use App\Recruitment\Domain\Write\Exception\JobApplication\InvalidJobApplicationDataException;
use App\Recruitment\Infrastructure\Http\Request\SubmitJobApplicationRequest;
use App\SharedKernel\Infrastructure\Http\UiController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/positions/{id}/applications', name: 'job_application_submit', methods: ['POST'])]
final class SubmitJobApplicationController extends UiController
{
    /** Lets the detail page tell "not projected yet" from "does not exist". */
    public const string JUST_SUBMITTED = 'just_submitted_application';

    public function __invoke(string $id, Request $request, ValidatorInterface $validator): Response
    {
        /** @var PositionDetailResponse|null $position */
        $position = $this->query(new GetPositionQuery($id))
            ?? throw $this->createNotFoundException('Position not found.');
        $form = SubmitJobApplicationRequest::fromRequest($request);

        if (!$this->isCsrfTokenValid('submit_application', $request->request->getString('_token'))) {
            return $this->formWithErrors($position, $form, [
                'form' => 'Your session expired. Please submit the form again.',
            ]);
        }

        $errors = [];
        /** @var ConstraintViolationInterface $violation */
        foreach ($validator->validate($form) as $violation) {
            $errors[$violation->getPropertyPath()] ??= (string) $violation->getMessage();
        }

        if ($errors !== []) {
            return $this->formWithErrors($position, $form, $errors);
        }

        try {
            /** @var string $jobApplicationId */
            $jobApplicationId = $this->command(new SubmitJobApplicationCommand(
                $position->id,
                $form->fullName,
                $form->email,
                $form->phone,
                $form->notes,
                $form->cvText,
            ));
        } catch (InvalidJobApplicationDataException $e) {
            return $this->formWithErrors($position, $form, ['form' => $e->getMessage()]);
        }

        $request->getSession()->set(self::JUST_SUBMITTED, $jobApplicationId);
        $this->addFlash('success', 'Application received. The AI is screening the CV now.');

        // 303: the browser follows with a GET, so a refresh never resubmits the form.
        return $this->redirectToRoute('job_application_show', ['id' => $jobApplicationId], Response::HTTP_SEE_OTHER);
    }

    /**
     * @param array<string, string> $errors
     */
    private function formWithErrors(
        PositionDetailResponse $position,
        SubmitJobApplicationRequest $form,
        array $errors,
    ): Response {
        return $this->render(
            'positions/show.html.twig',
            ['position' => $position, 'values' => $form->values(), 'errors' => $errors],
            new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY),
        );
    }
}
