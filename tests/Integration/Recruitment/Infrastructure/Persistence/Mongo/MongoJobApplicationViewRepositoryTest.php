<?php

declare(strict_types=1);

namespace App\Tests\Integration\Recruitment\Infrastructure\Persistence\Mongo;

use App\Recruitment\Domain\Read\JobApplicationCriteria;
use App\Recruitment\Domain\Read\JobApplicationView;
use App\Recruitment\Domain\Read\JobApplicationViewRepository;
use App\Recruitment\Infrastructure\Persistence\Mongo\MongoJobApplicationViewRepository;
use App\Tests\IntegrationTestCase;

final class MongoJobApplicationViewRepositoryTest extends IntegrationTestCase
{
    private const string BACKEND = '019a0a10-0000-7000-8000-000000000001';
    private const string DESIGN = '019a0a10-0000-7000-8000-000000000003';
    private const string ID_PREFIX = '019a0a20-0000-7000-8000-00000000000';

    private JobApplicationViewRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = self::service(JobApplicationViewRepository::class);
        self::service(MongoJobApplicationViewRepository::class)->createIndexes();

        $applications = [
            ['1', 'Ada Lovelace', 'ada@example.com', self::BACKEND, 'enriched', '2026-10-01 09:00'],
            ['2', 'Grace Hopper', 'grace@navy.mil', self::BACKEND, 'received', '2026-10-03 09:00'],
            ['3', 'Alan Turing', 'alan@bletchley.uk', self::DESIGN, 'enriched', '2026-10-02 09:00'],
            ['4', 'Ana Ñúñez', 'ana@example.com', self::DESIGN, 'failed', '2026-10-04 09:00'],
        ];
        foreach ($applications as $application) {
            $this->repository->save($this->view(...$application));
        }
    }

    public function testItListsNewestFirst(): void
    {
        self::assertSame(
            ['Ana Ñúñez', 'Grace Hopper', 'Alan Turing', 'Ada Lovelace'],
            $this->namesFor(new JobApplicationCriteria()),
        );
    }

    public function testItFiltersByStatusAndPosition(): void
    {
        $enriched = new JobApplicationCriteria(status: 'enriched');
        $backend = new JobApplicationCriteria(positionId: self::BACKEND);
        $enrichedForBackend = new JobApplicationCriteria(status: 'enriched', positionId: self::BACKEND);

        self::assertSame(['Alan Turing', 'Ada Lovelace'], $this->namesFor($enriched));
        self::assertSame(['Grace Hopper', 'Ada Lovelace'], $this->namesFor($backend));
        self::assertSame(['Ada Lovelace'], $this->namesFor($enrichedForBackend));
    }

    public function testItSearchesByNameOrEmailIgnoringCase(): void
    {
        self::assertSame(['Grace Hopper'], $this->namesFor(new JobApplicationCriteria(search: 'HOPPER')));
        self::assertSame(['Alan Turing'], $this->namesFor(new JobApplicationCriteria(search: 'bletchley')));
        self::assertSame(['Ana Ñúñez'], $this->namesFor(new JobApplicationCriteria(search: 'ÑÚÑ')));
        self::assertSame(
            ['Ana Ñúñez', 'Ada Lovelace'],
            $this->namesFor(new JobApplicationCriteria(search: '@example.com')),
        );
    }

    public function testItTreatsTheSearchAsLiteralText(): void
    {
        self::assertSame([], $this->namesFor(new JobApplicationCriteria(search: '.*')));
    }

    public function testItPaginatesAndCountsTheWholeResult(): void
    {
        $criteria = new JobApplicationCriteria(page: 2, perPage: 3);
        $page = $this->repository->search($criteria);

        self::assertSame(4, $page->total);
        self::assertSame(2, $page->totalPages());
        self::assertSame(['Ada Lovelace'], $this->namesFor($criteria));
    }

    public function testItRoundTripsAView(): void
    {
        $view = $this->repository->findById(self::ID_PREFIX . '1');

        self::assertNotNull($view);
        self::assertSame('Backend Engineer', $view->positionTitle);
        self::assertSame(80, $view->score);
        self::assertSame('2026-10-01 09:00', $view->appliedAt->format('Y-m-d H:i'));
        self::assertSame('2026-10-01 09:05', $view->enrichedAt?->format('Y-m-d H:i'));
    }

    public function testAStaleViewNeverOverwritesANewerOne(): void
    {
        $id = '019a0a20-0000-7000-8000-00000000000f';
        $newer = $this->graceView('enriched', version: 3);
        $older = $this->graceView('enriching', version: 2);

        // A slower projection of an earlier state arrives after the latest one…
        $this->repository->save($newer);
        $this->repository->save($older);
        $this->repository->save($newer);

        // …and is ignored: the view keeps the newest state, and replaying the same one is harmless.
        self::assertSame('enriched', $this->repository->findById($id)?->status);
        self::assertSame(3, $this->repository->findById($id)->version);
    }

    public function testOverwritingIgnoresTheVersionForRebuilds(): void
    {
        $id = '019a0a20-0000-7000-8000-00000000000f';
        $this->repository->save($this->graceView('enriched', version: 3, name: 'Grace'));

        $this->repository->overwrite($this->graceView('enriched', version: 3));

        self::assertSame('Grace Hopper', $this->repository->findById($id)?->candidateName);
    }

    private function graceView(string $status, int $version, string $name = 'Grace Hopper'): JobApplicationView
    {
        return $this->view('f', $name, 'grace@navy.mil', self::BACKEND, $status, '2026-10-05 09:00', $version);
    }

    /**
     * @return string[]
     */
    private function namesFor(JobApplicationCriteria $criteria): array
    {
        return array_map(
            static fn (JobApplicationView $view): string => $view->candidateName,
            $this->repository->search($criteria)->items,
        );
    }

    private function view(
        string $idSuffix,
        string $name,
        string $email,
        string $positionId,
        string $status,
        string $appliedAt,
        int $version = 1,
    ): JobApplicationView {
        $id = self::ID_PREFIX . $idSuffix;
        $applied = new \DateTimeImmutable($appliedAt);
        $enriched = $status === 'enriched';

        return new JobApplicationView(
            id: $id,
            positionId: $positionId,
            positionTitle: 'Backend Engineer',
            candidateName: $name,
            email: $email,
            phone: null,
            notes: null,
            cvText: 'CV',
            status: $status,
            score: $enriched ? 80 : null,
            summary: $enriched ? 'Summary.' : null,
            promptVersion: $enriched ? 'cv_analysis.v1' : null,
            failureReason: $status === 'failed' ? 'Gave up' : null,
            appliedAt: $applied,
            updatedAt: $applied,
            enrichedAt: $enriched ? $applied->modify('+5 minutes') : null,
            version: $version,
        );
    }
}
