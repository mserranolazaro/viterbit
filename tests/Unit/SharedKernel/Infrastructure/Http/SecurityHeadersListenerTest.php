<?php

declare(strict_types=1);

namespace App\Tests\Unit\SharedKernel\Infrastructure\Http;

use App\SharedKernel\Infrastructure\Http\Security\CspNonce;
use App\SharedKernel\Infrastructure\Http\Security\SecurityHeadersListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class SecurityHeadersListenerTest extends TestCase
{
    public function testSubRequestsAreLeftToTheMainResponse(): void
    {
        $response = $this->handle(new Response(), debug: false, type: HttpKernelInterface::SUB_REQUEST);

        self::assertFalse($response->headers->has('Content-Security-Policy'));
        self::assertFalse($response->headers->has('X-Frame-Options'));
    }

    public function testTheDebugErrorPageKeepsItsInlineScripts(): void
    {
        $response = $this->handle(new Response(status: 500), debug: true);

        self::assertSame('DENY', $response->headers->get('X-Frame-Options'));
        self::assertFalse($response->headers->has('Content-Security-Policy'));
    }

    public function testErrorPagesInProductionAreStillLockedDown(): void
    {
        $response = $this->handle(new Response(status: 500), debug: false);

        $policy = (string) $response->headers->get('Content-Security-Policy');
        self::assertStringContainsString("default-src 'self'", $policy);
    }

    public function testTheNonceIsStableWithinARequestAndRenewedAfter(): void
    {
        $nonce = new CspNonce();
        $first = $nonce->get();
        self::assertSame($first, $nonce->get());

        $nonce->reset();

        self::assertNotSame($first, $nonce->get());
    }

    private function handle(Response $response, bool $debug, int $type = HttpKernelInterface::MAIN_REQUEST): Response
    {
        $event = new ResponseEvent($this->createStub(HttpKernelInterface::class), new Request(), $type, $response);
        new SecurityHeadersListener(new CspNonce(), $debug)($event);

        return $event->getResponse();
    }
}
