<?php

declare(strict_types=1);

namespace App\Tests\Functional\SharedKernel;

use App\Tests\FunctionalTestCase;

final class SecurityHeadersTest extends FunctionalTestCase
{
    public function testPagesAreServedWithHardeningHeaders(): void
    {
        $this->client->request('GET', '/positions');

        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertResponseHeaderSame('X-Frame-Options', 'DENY');
        self::assertResponseHeaderSame('Referrer-Policy', 'same-origin');
    }

    public function testOnlyInlineScriptsCarryingTheRequestNonceAreAllowed(): void
    {
        $crawler = $this->client->request('GET', '/positions');

        $policy = (string) $this->client->getResponse()->headers->get('Content-Security-Policy');
        self::assertMatchesRegularExpression("/script-src 'self' 'nonce-([A-Za-z0-9+\\/=]+)'/", $policy);
        self::assertStringContainsString("frame-ancestors 'none'", $policy);
        self::assertSame(1, preg_match("/'nonce-([^']+)'/", $policy, $match));

        $nonces = $crawler->filter('script')->each(static fn ($script): ?string => $script->attr('nonce'));
        self::assertNotEmpty($nonces);
        self::assertSame([$match[1] ?? null], array_values(array_unique($nonces)));
    }

    public function testTheImportmapHasNoDataModulesThePolicyWouldBlock(): void
    {
        $crawler = $this->client->request('GET', '/positions');

        // Importing CSS from JS turns into a data: module, refused by script-src, and the JS never runs.
        self::assertStringNotContainsString('data:', $crawler->filter('script[type="importmap"]')->text());
        self::assertCount(1, $crawler->filter('link[rel="stylesheet"]'));
    }

    public function testEveryRequestGetsADifferentNonce(): void
    {
        $this->client->request('GET', '/positions');
        $first = $this->client->getResponse()->headers->get('Content-Security-Policy');
        $this->client->request('GET', '/positions');

        self::assertNotSame($first, $this->client->getResponse()->headers->get('Content-Security-Policy'));
    }
}
