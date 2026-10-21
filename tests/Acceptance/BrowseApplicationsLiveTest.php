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
        $this->waitForText('#results [data-live-summary]', '2 applications');
        self::assertSame([$second, $first], $this->listedNames());
        self::assertStringContainsString('q=' . $this->runId, $this->browser->getCurrentURL());

        // Filtering by position narrows it down in place.
        $this->browser->executeScript(sprintf(<<<'JS'
            const select = document.querySelector('#position');
            select.value = '%s';
            select.dispatchEvent(new Event('input', {bubbles: true}));
            JS, SeedPositionsCommand::PRODUCT_DESIGNER_ID));
        $this->waitForText('#results [data-live-summary]', '1 application');
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
        $this->browser->waitForStaleness('[data-live-error]', 30);
        self::assertCount(0, $this->browser->getCrawler()->filter('[data-live-error]'));
    }
}
