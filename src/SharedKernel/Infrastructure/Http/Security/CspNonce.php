<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Http\Security;

use Symfony\Contracts\Service\ResetInterface;

/**
 * One random nonce per request: the page's own inline scripts (the importmap) carry it,
 * so the Content-Security-Policy can forbid every other inline script.
 */
final class CspNonce implements ResetInterface
{
    private ?string $nonce = null;

    public function get(): string
    {
        return $this->nonce ??= base64_encode(random_bytes(18));
    }

    public function isUsed(): bool
    {
        return $this->nonce !== null;
    }

    public function reset(): void
    {
        $this->nonce = null;
    }
}
