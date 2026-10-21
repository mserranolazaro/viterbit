<?php

declare(strict_types=1);

namespace App\Tests\Functional\Recruitment;

use App\Recruitment\Infrastructure\Console\SeedPositionsCommand;
use App\Tests\FunctionalTestCase;
use PHPUnit\Framework\Attributes\TestWith;

final class PositionPagesTest extends FunctionalTestCase
{
    public function testTheHomePageLeadsToTheOpenPositions(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseRedirects('/positions');
    }

    public function testItListsTheOpenPositions(): void
    {
        $crawler = $this->client->request('GET', '/positions');

        self::assertResponseIsSuccessful();
        self::assertSame(
            ['Data Analyst', 'Frontend Engineer', 'Product Designer', 'Senior Backend Engineer'],
            $crawler->filter('main h2')->each(static fn ($node): string => $node->text()),
        );
    }

    public function testThePositionPageShowsTheJobAndTheApplicationForm(): void
    {
        $crawler = $this->client->request('GET', '/positions/' . SeedPositionsCommand::BACKEND_ENGINEER_ID);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Senior Backend Engineer');
        self::assertSelectorTextContains('main', 'RabbitMQ');
        self::assertCount(1, $crawler->filter('textarea[name="cvText"]'));
        self::assertCount(0, $crawler->filter('input[type="file"]'));
    }

    #[TestWith(['019a0a10-0000-7000-8000-0000000000ff'])]
    #[TestWith(['not-a-uuid'])]
    public function testAnUnknownPositionIsNotFound(string $id): void
    {
        $this->client->request('GET', '/positions/' . $id);

        self::assertResponseStatusCodeSame(404);
    }
}
