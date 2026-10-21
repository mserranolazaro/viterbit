<?php

declare(strict_types=1);

namespace App\Tests;

use App\Recruitment\Infrastructure\Console\SeedPositionsCommand;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use MongoDB\Client as MongoClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Crawler as DomCrawler;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\DomCrawler\Crawler;
use Symfony\Component\Process\Process;

/**
 * Acceptance tests: a real Chrome (the "selenium" service) drives the running application
 * through Caddy, with the real workers, RabbitMQ and MongoDB behind it. They check the
 * exercise's acceptance criteria the way a user experiences them, JavaScript included.
 *
 * Every test works with its own candidates (a unique run id in names and emails) and removes
 * them afterwards, so the suite can run against a stack that already has data.
 */
abstract class AcceptanceTestCase extends TestCase
{
    protected const string BACKEND_POSITION = '/positions/' . SeedPositionsCommand::BACKEND_ENGINEER_ID;
    private const string EMAIL_DOMAIN = 'acceptance.example';
    private const int TIMEOUT_SECONDS = 30;

    protected Client $browser;
    protected string $runId;

    public static function setUpBeforeClass(): void
    {
        // The container may be up before Chrome is: wait until Selenium accepts sessions.
        $status = self::seleniumUrl() . '/status';
        for ($attempt = 0; $attempt < 60; ++$attempt) {
            $body = @file_get_contents($status);
            $ready = is_string($body) ? (json_decode($body, true)['value']['ready'] ?? false) : false;
            if ($ready === true) {
                return;
            }
            usleep(500_000);
        }

        self::fail(sprintf('Selenium is not ready at %s. Run "make acceptance".', $status));
    }

    protected function setUp(): void
    {
        $this->runId = bin2hex(random_bytes(4));
        // Every test submits a few applications from the same IP: start below the rate limit.
        new Process(['bin/console', 'cache:pool:clear', 'rate_limiter.cache', '--env=dev'])->mustRun();

        $this->browser = Client::createSeleniumClient(
            self::seleniumUrl(),
            DesiredCapabilities::chrome(),
            (string) ($_SERVER['ACCEPTANCE_BASE_URL'] ?? 'http://caddy'),
        );
    }

    protected function tearDown(): void
    {
        $this->browser->quit();
        $this->database()->selectCollection('job_applications')
            ->deleteMany(['candidate.email' => ['$regex' => '@' . preg_quote(self::EMAIL_DOMAIN) . '$']]);
        $this->database()->selectCollection('job_application_views')
            ->deleteMany(['email' => ['$regex' => '@' . preg_quote(self::EMAIL_DOMAIN) . '$']]);
    }

    protected function email(string $who): string
    {
        return sprintf('%s.%s@%s', $who, $this->runId, self::EMAIL_DOMAIN);
    }

    /**
     * Fills and sends the application form the way a candidate does.
     */
    protected function apply(
        string $name,
        string $who,
        string $cv,
        string $position = self::BACKEND_POSITION,
    ): DomCrawler {
        $crawler = $this->browser->request('GET', $position);
        $form = $crawler->selectButton('Submit application')->form([
            'fullName' => $name,
            'email' => $this->email($who),
            'cvText' => $cv,
        ]);
        $consent = $form['privacyConsent'];
        self::assertInstanceOf(ChoiceFormField::class, $consent);
        $consent->tick();

        return $this->browser->submit($form);
    }

    protected function waitForText(string $selector, string $text): Crawler
    {
        return $this->browser->waitForElementToContain($selector, $text, self::TIMEOUT_SECONDS);
    }

    /**
     * Waits for a condition evaluated in the page (a JavaScript expression), so tests wait for
     * the state they need rather than for something that may already be true by coincidence.
     */
    protected function waitUntil(string $expression): void
    {
        $this->browser->wait(self::TIMEOUT_SECONDS)->until(
            fn (): bool => (bool) $this->browser->executeScript('return Boolean(' . $expression . ');'),
        );
    }

    /**
     * After typing or picking a filter: the URL carries it and the results request has finished.
     * Waiting on the visible count alone is not enough: it can already match before filtering.
     */
    protected function waitForLiveResults(string $expectedInUrl): void
    {
        $this->waitUntil(sprintf(
            'location.href.includes(%s) && !document.querySelector("#results[aria-busy]")',
            json_encode($expectedInUrl, JSON_THROW_ON_ERROR),
        ));
    }

    /**
     * @return string[] Candidate names currently listed
     */
    protected function listedNames(): array
    {
        return $this->browser->getCrawler()->filter('#results .entry__title')->each(
            static fn (Crawler $title): string => $title->text(),
        );
    }

    private static function seleniumUrl(): string
    {
        return (string) ($_SERVER['ACCEPTANCE_SELENIUM_URL'] ?? 'http://selenium:4444');
    }

    private function database(): \MongoDB\Database
    {
        return new MongoClient((string) ($_SERVER['MONGODB_URL'] ?? 'mongodb://mongo:27017'))
            ->getDatabase((string) ($_SERVER['ACCEPTANCE_MONGODB_DB'] ?? 'ats'));
    }
}
