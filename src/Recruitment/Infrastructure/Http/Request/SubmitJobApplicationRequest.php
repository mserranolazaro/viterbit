<?php

declare(strict_types=1);

namespace App\Recruitment\Infrastructure\Http\Request;

use App\Recruitment\Domain\Write\Aggregate\Root\JobApplication\JobApplication;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\Candidate;
use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\CvText;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The application form as submitted. Validation here gives friendly, per-field messages;
 * the domain enforces the same rules again as invariants.
 */
final class SubmitJobApplicationRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Tell us your full name.', normalizer: 'trim')]
        #[Assert\Length(max: Candidate::FULL_NAME_MAX_LENGTH, maxMessage: 'Keep it under {{ limit }} characters.')]
        public readonly string $fullName,
        #[Assert\NotBlank(message: 'We need an email to contact you.', normalizer: 'trim')]
        #[Assert\Email(message: 'This does not look like a valid email.')]
        public readonly string $email,
        #[Assert\Regex(pattern: '/^\+?[0-9 ()\-]{6,20}$/', message: 'Use digits, spaces and an optional leading +.')]
        public readonly string $phone,
        #[Assert\Length(
            max: JobApplication::NOTES_MAX_LENGTH,
            maxMessage: 'Keep the notes under {{ limit }} characters.',
        )]
        public readonly string $notes,
        #[Assert\NotBlank(message: 'Paste your CV as text.', normalizer: 'trim')]
        #[Assert\Length(max: CvText::MAX_LENGTH, maxMessage: 'The CV cannot exceed {{ limit }} characters.')]
        public readonly string $cvText,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $field = static fn (string $name): string => trim($request->request->getString($name));

        return new self($field('fullName'), $field('email'), $field('phone'), $field('notes'), $field('cvText'));
    }

    /**
     * @return array<string, string>
     */
    public function values(): array
    {
        return get_object_vars($this);
    }
}
