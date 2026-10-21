<?php

declare(strict_types=1);

namespace App\Tests\Integration\Recruitment\Infrastructure\Console;

use App\Recruitment\Domain\Read\JobApplicationViewRepository;
use App\Recruitment\Domain\Write\Repository\JobApplicationRepository;
use App\Recruitment\Domain\Write\Repository\PositionRepository;
use App\Tests\IntegrationTestCase;
use App\Tests\Support\JobApplicationMother;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class RebuildJobApplicationViewsCommandTest extends IntegrationTestCase
{
    public function testItRegeneratesTheReadModelFromTheWriteModel(): void
    {
        $position = JobApplicationMother::position();
        self::service(PositionRepository::class)->save($position);
        $jobApplication = JobApplicationMother::received($position->id());
        self::service(JobApplicationRepository::class)->save($jobApplication);

        self::assertNotNull(self::$kernel);
        $tester = new CommandTester(new Application(self::$kernel)->find('app:views:rebuild'));
        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('1 job application views rebuilt', $tester->getDisplay());
        $view = self::service(JobApplicationViewRepository::class)->findById($jobApplication->id()->value());
        self::assertSame('received', $view?->status);
        self::assertSame('Backend Engineer', $view->positionTitle);
    }
}
