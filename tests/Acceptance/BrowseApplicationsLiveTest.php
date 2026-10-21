<?php

declare(strict_types=1);

namespace App\Tests\Acceptance;

use App\Recruitment\Infrastructure\Console\SeedPositionsCommand;
use App\Tests\AcceptanceTestCase;

/**
 * Acceptance criteria: the list is newest first and supports real-time filtering and search.
 */
final class BrowseApplicationsLiveTest extends AcceptanceTestCase
{
    public function testARecruiterSearchesAndFiltersWithoutReloading(): void
    {
        $first = 'Grace ' . $this->runId;
        $second = 'Alan ' . $this->runId;
        $this->apply($first, 'grace', 'PHP and Symfony');
        $this->waitForText('.page-header', 'Enriched');
        $design = '/positions/' . SeedPositionsCommand::PRODUCT_DESIGNER_ID;
        $this->apply($second, 'alan', 'Figma and prototyping', $design);
        $this->waitForText('.page-header', 'Enriched');

        $this->browser->request('GET', '/applications');
        $this->browser->executeScript('window.__sameDocument = true;');

        // Typing filters as you go, newest first; the URL follows so it can be shared.
        $this->browser->getCrawler()->filter('#q')->sendKeys($this->runId);
        $this->waitForLiveResults('q=' . $this->runId);
        self::assertSame('2 applications', $this->resultCount());
        self::assertSame([$second, $first], $this->listedNames());

        // Filtering by position narrows it down in place.
        $this->browser->executeScript(sprintf(<<<'JS'
            const select = document.querySelector('#position');
            select.value = '%s';
            select.dispatchEvent(new Event('input', {bubbles: true}));
            JS, SeedPositionsCommand::PRODUCT_DESIGNER_ID));
        $this->waitForLiveResults('position=' . SeedPositionsCommand::PRODUCT_DESIGNER_ID);
        self::assertSame('1 application', $this->resultCount());
        self::assertSame([$second], $this->listedNames());
        self::assertTrue($this->browser->executeScript('return window.__sameDocument === true;'));

        // The score column is there, and the status.
        $row = $this->browser->getCrawler()->filter('#results .entry')->first();
        self::assertMatchesRegularExpression('#^\d+\s*/100$#', $row->filter('.score')->text());
        self::assertStringContainsString('Enriched', $row->text());
    }

    public function testAListThatCannotRefreshSaysSoAndRecoversOnRetry(): void
    {
        $this->browser->request('GET', '/applications');
        $this->browser->executeScript(<<<'JS'
            window.__fetch = window.fetch;
            window.fetch = () => Promise.reject(new TypeError('Failed to fetch'));
            const status = document.querySelector('#status');
            status.value = 'failed';
            status.dispatchEvent(new Event('input', {bubbles: true}));
            JS);

        $this->waitForText('[data-live-error]', 'Could not update this section');

        $this->browser->executeScript('window.fetch = window.__fetch;');
        $this->browser->getCrawler()->filter('[data-live-retry]')->click();

        // However fast the retry is, the message must end up gone and the request finished.
        $this->waitUntil(
            '!document.querySelector("[data-live-error]") && !document.querySelector("#results[aria-busy]")',
        );
        self::assertStringContainsString('status=failed', $this->browser->getCurrentURL());
    }

    private function resultCount(): string
    {
        return $this->browser->getCrawler()->filter('#results [data-live-summary]')->text();
    }
}
