<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Twig;

use App\SharedKernel\Infrastructure\Http\Security\CspNonce;
use Twig\Attribute\AsTwigFunction;

final class CspExtension
{
    public function __construct(
        private readonly CspNonce $nonce,
    ) {
    }

    #[AsTwigFunction('csp_nonce')]
    public function nonce(): string
    {
        return $this->nonce->get();
    }
}
