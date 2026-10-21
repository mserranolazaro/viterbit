<?php

declare(strict_types=1);

namespace App\Tests\Integration\Recruitment\Infrastructure\Console;

use App\Recruitment\Domain\Write\Repository\JobApplicationRepository;
use App\Recruitment\Domain\Write\Repository\PositionRepository;
use App\Recruitment\Infrastructure\Persistence\Mongo\MongoJobApplicationRepository;
use App\Tests\IntegrationTestCase;
use App\Tests\Support\JobApplicationMother;
use MongoDB\Database;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The commands "make init" and "make demo" run: they must be safe to run again.
 */
final class MaintenanceCommandsTest extends IntegrationTestCase
{
    public function testSeedingTwiceKeepsFourPositions(): void
    {
        $this->console('app:positions:seed');
        $tester = $this->console('app:positions:seed');

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('4 positions loaded', $tester->getDisplay());
        self::assertCount(4, self::service(PositionRepository::class)->all());
    }

    public function testSetupCreatesTheIndexesAndCanRunAgain(): void
    {
        $this->console('app:mongo:setup')->assertCommandIsSuccessful();
        $this->console('app:mongo:setup')->assertCommandIsSuccessful();

        $indexes = [];
        $collection = self::service(Database::class)->selectCollection(MongoJobApplicationRepository::COLLECTION);
        foreach ($collection->listIndexes() as $index) {
            $indexes[] = $index->getName();
        }
        self::assertContains('one_application_per_candidate', $indexes);
    }

    public function testSetupExplainsWhyItCannotAddTheUniqueIndexOverDuplicates(): void
    {
        // Two applications from the same candidate to the same position, written before the rule.
        $first = JobApplicationMother::received();
        self::service(JobApplicationRepository::class)->save($first);
        self::service(JobApplicationRepository::class)->save(JobApplicationMother::received($first->positionId()));

        $tester = $this->console('app:mongo:setup');

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('applied more than once to the same position', $tester->getDisplay());
    }

    public function testTheDemoCanBeRunTwice(): void
    {
        $this->console('app:positions:seed');

        $first = $this->console('app:demo:applications');
        $second = $this->console('app:demo:applications');

        $first->assertCommandIsSuccessful();
        self::assertStringContainsString('7 applications submitted', $first->getDisplay());
        $second->assertCommandIsSuccessful();
        self::assertStringContainsString('0 applications submitted', $second->getDisplay());
        self::assertCount(7, iterator_to_array(self::service(JobApplicationRepository::class)->all(), false));
    }

    private function console(string $command): CommandTester
    {
        self::assertNotNull(self::$kernel);
        $tester = new CommandTester(new Application(self::$kernel)->find($command));
        $tester->execute([]);

        return $tester;
    }
}
