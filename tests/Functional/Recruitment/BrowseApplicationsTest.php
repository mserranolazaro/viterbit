<?php

declare(strict_types=1);

namespace App\Tests\Functional\Recruitment;

use App\Recruitment\Application\Command\JobApplication\SubmitJobApplication\SubmitJobApplicationCommand;
use App\Recruitment\Infrastructure\Console\SeedPositionsCommand;
use App\Tests\FunctionalTestCase;
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
     * @return string[]
     */
    private function names(string $html): array
    {
        preg_match_all('#<span class="entry__title">([^<]+)</span>#', $html, $matches);

        return array_map('trim', $matches[1]);
    }
}
