<?php

declare(strict_types=1);

namespace App\Tests\Functional\Recruitment;

use App\Recruitment\Application\Command\JobApplication\SubmitJobApplication\SubmitJobApplicationCommand;
use App\Recruitment\Infrastructure\Console\SeedPositionsCommand;
use App\Tests\FunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * The recruiter's flow: list newest first, filter and search in place, open the detail.
 */
final class BrowseApplicationsTest extends FunctionalTestCase
{
    private const string ADA_CV = 'PHP, Symfony, MongoDB, RabbitMQ, Docker and DDD.';
    private const string LINUS_CV = 'C and Git. Some Docker and PHP.';
    private const string SARA_CV = 'CSS and accessibility.';

    public function testItShowsAnEmptyStateBeforeAnyoneApplies(): void
    {
        $this->client->request('GET', '/applications');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#results', 'No applications yet');
    }

    public function testItListsApplicationsNewestFirstWithTheirScore(): void
    {
        $this->submitSampleApplications();
        self::runWorker();

        $crawler = $this->client->request('GET', '/applications');

        self::assertResponseIsSuccessful();
        self::assertSame(['Sara Soueidan', 'Linus Torvalds', 'Ada Lovelace'], $this->names($crawler->html()));
        self::assertSelectorTextContains('#results .card__title', '3 applications');
        // Sara: CSS (3) + Accessibility (1) of 12 → 33. Linus: PHP (3) + Docker (1) of 12 → 33.
        self::assertSame(
            ['33/100', '33/100', '100/100'],
            $crawler->filter('#results .score')->each(static fn ($node): string => $node->text()),
        );
    }

    public function testRowsWaitingForTheAiAskThePageToKeepRefreshing(): void
    {
        $this->submitSampleApplications();
        // Only the projections run: the applications are listed, the enrichment is still queued.
        self::runWorker('events');

        $this->client->request('GET', '/applications');

        self::assertSelectorExists('#results [data-pending]');
        self::assertSelectorTextContains('#results', 'Received');
    }

    public function testTheFragmentContainsOnlyTheFilteredResults(): void
    {
        $this->submitSampleApplications();
        self::runWorker();

        $html = $this->fragment('/applications?position=' . SeedPositionsCommand::BACKEND_ENGINEER_ID);

        self::assertStringNotContainsString('<html', $html);
        self::assertSame(['Linus Torvalds', 'Ada Lovelace'], $this->names($html));
        self::assertSame('X-Requested-With', $this->client->getResponse()->headers->get('Vary'));
    }

    public function testItFiltersByStatusAndSearchesByNameOrEmail(): void
    {
        $this->submitSampleApplications();
        self::runWorker();

        self::assertSame(['Linus Torvalds'], $this->names($this->fragment('/applications?q=LINUS')));
        self::assertSame(['Sara Soueidan'], $this->names($this->fragment('/applications?q=example.net')));
        self::assertSame([], $this->names($this->fragment('/applications?status=failed')));
        self::assertStringContainsString('No matches', $this->fragment('/applications?status=failed'));
        self::assertCount(3, $this->names($this->fragment('/applications?status=enriched')));
    }

    public function testFiltersComeBackFilledWhenTheUrlIsShared(): void
    {
        $crawler = $this->client->request('GET', '/applications?q=ada&status=enriched');

        self::assertSame('ada', $crawler->filter('#q')->attr('value'));
        self::assertSame('enriched', $crawler->filter('#status option[selected]')->attr('value'));
    }

    public function testBrowsingApplicationsDoesNotStartASession(): void
    {
        $this->submitSampleApplications();
        self::runWorker();
        $this->client->getCookieJar()->clear();

        $crawler = $this->client->request('GET', '/applications');
        self::assertFalse($this->sessionWasStarted());
        $this->client->click($crawler->filter('a.entry')->link());
        self::assertResponseIsSuccessful();
        self::assertFalse($this->sessionWasStarted());

        // Control: the application form does need a session (CSRF token), and the check sees it.
        $this->client->request('GET', '/positions/' . SeedPositionsCommand::BACKEND_ENGINEER_ID);
        self::assertTrue($this->sessionWasStarted());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unreadableQueries(): iterable
    {
        yield 'page is not a number' => ['?page=abc'];
        yield 'page overflows an integer' => ['?page=99999999999999999999'];
        yield 'page is negative' => ['?page=-3'];
        yield 'filters sent as arrays' => ['?q[]=ada&status[]=enriched&position[a]=x&page[]=2'];
        yield 'operator injection' => ['?q[$ne]=1'];
    }

    #[DataProvider('unreadableQueries')]
    public function testAnUnreadableQueryStillShowsTheFirstPage(string $query): void
    {
        $this->submitSampleApplications();
        self::runWorker();

        $this->client->request('GET', '/applications' . $query);

        self::assertResponseIsSuccessful();
        self::assertCount(3, $this->names((string) $this->client->getResponse()->getContent()));
    }

    public function testAPagePastTheEndSaysSoAndLinksBackToTheFirstOne(): void
    {
        $this->submitSampleApplications();
        self::runWorker();

        $crawler = $this->client->request('GET', '/applications?status=enriched&page=50');

        self::assertSelectorTextContains('#results', 'Nothing on this page');
        self::assertSelectorTextContains('#results', 'There are 3 matching applications');
        self::assertSame('/applications?status=enriched', $crawler->filter('#results a[data-live-link]')->attr('href'));
    }

    public function testPaginationKeepsTheFilters(): void
    {
        $bus = self::service(MessageBusInterface::class);
        for ($i = 1; $i <= 22; ++$i) {
            $bus->dispatch(new SubmitJobApplicationCommand(
                SeedPositionsCommand::DATA_ANALYST_ID,
                sprintf('Analyst %02d', $i),
                sprintf('analyst%02d@example.com', $i),
                null,
                null,
                'SQL',
                true,
            ));
        }
        self::runWorker();

        $crawler = $this->client->request('GET', '/applications?q=analyst&status=enriched');
        self::assertSelectorTextContains('.pagination', 'Page 1 of 2');
        $next = (string) $crawler->filter('.pagination a[rel="next"]')->attr('href');
        self::assertSame('/applications?q=analyst&status=enriched&page=2', $next);

        $this->client->request('GET', $next);
        self::assertCount(2, $this->names((string) $this->client->getResponse()->getContent()));
    }

    public function testAnUnknownApplicationIsNotFound(): void
    {
        $this->client->request('GET', '/applications/019a0a20-0000-7000-8000-0000000000ff');

        self::assertResponseStatusCodeSame(404);
    }

    private function submitSampleApplications(): void
    {
        $bus = self::service(MessageBusInterface::class);
        $clock = new MockClock('2026-10-21 09:00:00');
        Clock::set($clock);

        try {
            $candidates = [
                [SeedPositionsCommand::BACKEND_ENGINEER_ID, 'Ada Lovelace', 'ada@example.com', self::ADA_CV],
                [SeedPositionsCommand::BACKEND_ENGINEER_ID, 'Linus Torvalds', 'linus@example.org', self::LINUS_CV],
                [SeedPositionsCommand::FRONTEND_ENGINEER_ID, 'Sara Soueidan', 'sara@example.net', self::SARA_CV],
            ];
            foreach ($candidates as [$positionId, $name, $email, $cv]) {
                $bus->dispatch(new SubmitJobApplicationCommand($positionId, $name, $email, null, null, $cv, true));
                $clock->modify('+1 hour');
            }
        } finally {
            Clock::set(new NativeClock());
        }
    }

    /**
     * Symfony marks every response that used the session as non-cacheable (must-revalidate).
     */
    private function sessionWasStarted(): bool
    {
        return str_contains((string) $this->client->getResponse()->headers->get('Cache-Control'), 'must-revalidate');
    }

    /**
     * @return string[]
     */
    private function names(string $html): array
    {
        preg_match_all('#<span class="entry__title">([^<]+)</span>#', $html, $matches);

        return array_map('trim', $matches[1]);
    }
}
