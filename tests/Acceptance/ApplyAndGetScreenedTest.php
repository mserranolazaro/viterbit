<?php

declare(strict_types=1);

namespace App\Tests\Acceptance;

use App\Recruitment\Infrastructure\Ai\SimulatedLlmClient;
use App\Tests\AcceptanceTestCase;

/**
 * Acceptance criteria: submitting creates a record with appliedAt and the default status, and
 * the asynchronous enrichment adds a summary and a score, visible in the detail.
 */
final class ApplyAndGetScreenedTest extends AcceptanceTestCase
{
    public function testACandidateAppliesAndSeesTheScreeningArriveLive(): void
    {
        $this->apply('Ada Lovelace', 'ada', "Backend engineer\n8 years of PHP, Symfony and MongoDB. Some Docker.");

        self::assertMatchesRegularExpression('#/applications/[0-9a-f-]{36}$#', $this->browser->getCurrentURL());
        $toast = $this->browser->getCrawler()->filter('.toasts')->text();
        self::assertStringContainsString('Application received', $toast);
        $this->browser->executeScript('window.__sameDocument = "still the same page";');

        // The worker enriches it; the page refreshes its own fragment until the score is there.
        $this->waitForText('.page-header', 'Enriched');
        $crawler = $this->browser->getCrawler();
        self::assertSame('still the same page', $this->browser->executeScript('return window.__sameDocument;'));
        self::assertSame('Ada Lovelace', $crawler->filter('h1')->text());
        self::assertSame('83', $crawler->filter('.ring__value')->text());
        self::assertStringContainsString(
            'Meets 3 of 3 must-have requirements',
            $crawler->filter('section[aria-labelledby="screening"]')->text(),
        );
        self::assertStringContainsString('PHP', $crawler->filter('[aria-label="Requirements met"]')->text());
        $timeline = $crawler->filter('section[aria-labelledby="timeline"]')->text();
        self::assertStringContainsStringIgnoringCase('Applied', $timeline);
        self::assertStringContainsString('Ada Lovelace', $this->browser->getTitle());
    }

    public function testAScreeningThatKeepsFailingIsFlaggedForManualReview(): void
    {
        $this->apply('Broken Pipeline', 'broken', 'PHP developer ' . SimulatedLlmClient::FAILURE_MARKER);

        // Three attempts (now, +1 s, +3 s), then the application is marked as failed.
        $this->waitForText('.page-header', 'Failed');
        self::assertStringContainsString(
            'This CV could not be screened automatically.',
            $this->browser->getCrawler()->filter('[role="alert"]')->text(),
        );
    }

    public function testInvalidDataComesBackWithTheCursorOnTheFirstField(): void
    {
        $crawler = $this->browser->request('GET', self::BACKEND_POSITION);
        $this->browser->submit($crawler->selectButton('Submit application')->form());

        $crawler = $this->waitForText('#fullName-error', 'Tell us your full name.');
        self::assertSame('fullName', $this->browser->executeScript('return document.activeElement.id;'));
        $consentError = $crawler->filter('#privacyConsent-error')->text();
        self::assertSame('Please accept the privacy notice to apply.', $consentError);
    }

    public function testADoubleClickOnSubmitSendsOneApplication(): void
    {
        $crawler = $this->browser->request('GET', self::BACKEND_POSITION);
        $form = $crawler->selectButton('Submit application')->form([
            'fullName' => 'Double Click ' . $this->runId,
            'email' => $this->email('double'),
            'cvText' => 'PHP',
        ]);
        $this->browser->executeScript(<<<'JS'
            document.querySelector('#privacyConsent').checked = true;
            const button = document.querySelector('button[type="submit"]');
            button.click();
            button.click();
            JS);
        unset($form);

        $this->browser->waitForElementToContain('.page-header', 'Double Click', 30);
        $this->browser->request('GET', '/applications?q=' . urlencode($this->email('double')));

        self::assertSame(['Double Click ' . $this->runId], $this->listedNames());
    }
}
