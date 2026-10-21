<?php

declare(strict_types=1);

namespace App\Tests\Functional\Recruitment;

use App\Recruitment\Domain\Write\Aggregate\Value\JobApplication\CvText;
use App\Recruitment\Infrastructure\Ai\SimulatedLlmClient;
use App\Recruitment\Infrastructure\Console\SeedPositionsCommand;
use App\Tests\FunctionalTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;

/**
 * The candidate's flow: fill the form, land on the application, watch it get enriched.
 */
final class ApplyToAPositionTest extends FunctionalTestCase
{
    private const string POSITION = '/positions/' . SeedPositionsCommand::BACKEND_ENGINEER_ID;
    private const array VALID = [
        'fullName' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'cvText' => 'PHP developer',
    ];

    public function testSubmittingCreatesAReceivedApplicationThatTheWorkerEnriches(): void
    {
        $this->apply([
            'fullName' => 'Grace Hopper',
            'email' => 'grace@example.com',
            'phone' => '+34 600 123 456',
            'notes' => 'Available in November',
            'cvText' => "Staff engineer\n10 years of PHP, Symfony and MongoDB. RabbitMQ and Docker daily.",
        ]);

        // Redirected (303) to the new application, which the read model has not caught up with yet.
        self::assertResponseStatusCodeSame(303);
        $detailUrl = (string) $this->client->getResponse()->headers->get('Location');
        self::assertMatchesRegularExpression('#^/applications/[0-9a-f-]{36}$#', $detailUrl);

        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.toasts', 'Application received');
        self::assertSelectorExists('[data-pending]');

        self::assertCount(1, self::transport('events')->getSent());
        self::runWorker();

        $crawler = $this->client->request('GET', $detailUrl);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Grace Hopper');
        self::assertSelectorTextContains('.page-header', 'Enriched');
        self::assertSame('92', $crawler->filter('.ring__value')->text());
        self::assertSelectorTextContains('main', 'Meets 3 of 3 must-have requirements');
        self::assertSelectorTextContains('main', 'cv_analysis.v1');
        $met = ['PHP', 'Symfony', 'MongoDB', 'RabbitMQ', 'Docker'];
        self::assertSame($met, $this->chips($crawler, 'Requirements met'));
        self::assertSame(['DDD'], $this->chips($crawler, 'Requirements missing'));
        self::assertSelectorTextContains('.cv-text', '10 years of PHP, Symfony and MongoDB.');
        self::assertSelectorTextContains('main', 'Available in November');
        self::assertSelectorNotExists('[data-pending]');
    }

    public function testInvalidDataIsShownBackWithoutCreatingAnything(): void
    {
        $crawler = $this->apply(['fullName' => '  ', 'email' => 'not-an-email', 'cvText' => 'My CV']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('Tell us your full name.', $crawler->filter('#fullName-error')->text());
        self::assertSame('This does not look like a valid email.', $crawler->filter('#email-error')->text());
        self::assertSame('true', $crawler->filter('#email')->attr('aria-invalid'));
        self::assertSame('not-an-email', $crawler->filter('#email')->attr('value'));
        self::assertSame('My CV', $crawler->filter('#cvText')->text());
        self::assertCount(0, self::transport('events')->getSent());
    }

    public function testAForgedRequestIsRejected(): void
    {
        $this->client->request('POST', self::POSITION . '/applications', [
            '_token' => 'forged',
            'fullName' => 'Mallory',
            'email' => 'mallory@example.com',
            'cvText' => 'CV',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[role="alert"]', 'Your session expired');
        self::assertCount(0, self::transport('events')->getSent());
    }

    public function testApplyingToAnUnknownPositionIsNotFound(): void
    {
        $this->client->request('POST', '/positions/019a0a10-0000-7000-8000-0000000000ff/applications');

        self::assertResponseStatusCodeSame(404);
    }

    public function testAFailedEnrichmentIsExplainedOnTheDetailPage(): void
    {
        $this->apply([
            'fullName' => 'Broken Pipeline',
            'email' => 'broken@example.com',
            'cvText' => 'PHP developer ' . SimulatedLlmClient::FAILURE_MARKER,
        ]);
        $detailUrl = (string) $this->client->getResponse()->headers->get('Location');

        self::runWorker();
        $this->client->request('GET', $detailUrl);

        self::assertSelectorTextContains('.page-header', 'Failed');
        self::assertSelectorTextContains('[role="alert"]', 'This CV could not be screened automatically.');
        self::assertSelectorTextContains('[role="alert"] details', 'Enrichment failed after 3 attempt(s)');
    }

    public function testAnInvisibleNameIsTreatedAsMissing(): void
    {
        $crawler = $this->apply(['fullName' => "\u{200B}\u{200B}"] + self::VALID);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('Tell us your full name.', $crawler->filter('#fullName-error')->text());
    }

    public function testACvAtTheLimitIsAcceptedWhateverLineBreaksTheBrowserSends(): void
    {
        // The textarea allows 20,000 characters counting each line break once; browsers send \r\n.
        $cv = rtrim(str_repeat(str_repeat('a', 99) . "\n", 200)) . str_repeat('b', 1);
        self::assertSame(CvText::MAX_LENGTH, mb_strlen($cv));

        $this->apply(['cvText' => str_replace("\n", "\r\n", $cv)] + self::VALID);

        self::assertResponseStatusCodeSame(303);
    }

    public function testFieldsSentAsArraysAreTreatedAsMissing(): void
    {
        $crawler = $this->client->request('GET', self::POSITION);
        $token = $crawler->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', self::POSITION . '/applications', [
            '_token' => $token,
            'fullName' => ['Ada'],
            'email' => ['$ne' => ''],
            'cvText' => 'PHP',
            'privacyConsent' => ['1'],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('#fullName-error', 'Tell us your full name.');
        self::assertSelectorTextContains('#email-error', 'We need an email to contact you.');
        self::assertSelectorTextContains('#privacyConsent-error', 'Please accept the privacy notice to apply.');
    }

    public function testItCannotApplyWithoutAcceptingThePrivacyNotice(): void
    {
        $crawler = $this->apply(self::VALID, consent: false);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(
            'Please accept the privacy notice to apply.',
            $crawler->filter('#privacyConsent-error')->text(),
        );
        self::assertCount(0, self::transport('events')->getSent());
    }

    public function testItLimitsHowManyApplicationsOneClientCanSend(): void
    {
        for ($i = 1; $i <= 5; ++$i) {
            $this->apply(self::VALID);
            self::assertResponseStatusCodeSame(303, sprintf('Application %d should be accepted.', $i));
        }

        $this->apply(self::VALID);

        self::assertResponseStatusCodeSame(429);
        self::assertSelectorTextContains('[role="alert"]', 'Too many applications from your network');
        self::assertCount(5, self::transport('events')->getSent());
    }

    /**
     * Skill chips in a group, without the text of their (decorative) icon.
     *
     * @return string[]
     */
    private function chips(Crawler $crawler, string $group): array
    {
        return $crawler->filter(sprintf('[aria-label="%s"] .chip', $group))->each(
            static function (Crawler $chip): string {
                $icon = $chip->filter('.material-symbols-rounded')->text();

                return trim(str_replace($icon, '', $chip->text()));
            },
        );
    }

    /**
     * Fills and submits the real form, CSRF token included.
     *
     * @param array<string, string> $values
     */
    private function apply(array $values, bool $consent = true): Crawler
    {
        $crawler = $this->client->request('GET', self::POSITION);
        $form = $crawler->selectButton('Submit application')->form($values);
        $checkbox = $form['privacyConsent'];
        if ($consent && $checkbox instanceof ChoiceFormField) {
            $checkbox->tick();
        }

        return $this->client->submit($form);
    }
}
