<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Http\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Browser-side hardening on every response. Set in the application rather than in Caddy
 * because the CSP needs the per-request nonce, and so the functional tests can assert it.
 */
#[AsEventListener]
final class SecurityHeadersListener
{
    public function __construct(
        private readonly CspNonce $nonce,
        #[Autowire('%kernel.debug%')]
        private readonly bool $debug,
    ) {
    }

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $headers = $event->getResponse()->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'same-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // Symfony's debug error page relies on inline scripts; real pages never do.
        if ($this->debug && $event->getResponse()->getStatusCode() >= 500) {
            return;
        }

        $headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            // Only our files and inline scripts carrying this request's nonce (the importmap).
            sprintf("script-src 'self' 'nonce-%s'", $this->nonce->get()),
            // Inline style attributes set CSS variables (e.g. the score ring).
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]));
    }
}
