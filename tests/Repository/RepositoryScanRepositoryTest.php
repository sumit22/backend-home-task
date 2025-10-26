<?php

namespace App\Tests\Repository;

use App\Entity\Provider;
use App\Entity\Repository;
use App\Entity\RepositoryScan;
use App\Repository\RepositoryScanRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class RepositoryScanRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private RepositoryScanRepository $repository;
    private Provider $testProvider;
    private Repository $testRepository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();

        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();

        $this->repository = $this->entityManager->getRepository(RepositoryScan::class);

        // Create test provider
        $this->testProvider = new Provider();
        $this->testProvider->setName('Test Provider');
        $this->testProvider->setCode('sca_test');
        $this->entityManager->persist($this->testProvider);

        // Create test repository
        $this->testRepository = new Repository();
        $this->testRepository->setProvider($this->testProvider);
        $this->testRepository->setName('test/repo');
        $this->testRepository->setFullPath('/path/to/test/repo');
        $this->testRepository->setDefaultBranch('main');
        $this->entityManager->persist($this->testRepository);

        $this->entityManager->flush();
    }

    protected function tearDown(): void
    {
        // Clean up test data
        $scans = $this->repository->findAll();
        foreach ($scans as $scan) {
            $this->entityManager->remove($scan);
        }
        $this->entityManager->flush();

        if ($this->testRepository) {
            $this->entityManager->remove($this->testRepository);
        }
        if ($this->testProvider) {
            $this->entityManager->remove($this->testProvider);
        }
        $this->entityManager->flush();

        parent::tearDown();
        $this->entityManager->close();
    }

    public function testCanCreateRepositoryScan(): void
    {
        $scan = new RepositoryScan();
        $scan->setRepository($this->testRepository);
        $scan->setCommitSha('abc123def456');
        $scan->setBranch('main');
        $scan->setStatus('pending');
        $scan->setStartedAt(new \DateTime());

        $this->entityManager->persist($scan);
        $this->entityManager->flush();

        $foundScan = $this->repository->find($scan->getId());

        $this->assertNotNull($foundScan);
        $this->assertEquals('abc123def456', $foundScan->getCommitSha());
        $this->assertEquals('main', $foundScan->getBranch());
        $this->assertEquals('pending', $foundScan->getStatus());
    }

    public function testCanFindScansByStatus(): void
    {
        $pendingScan = new RepositoryScan();
        $pendingScan->setRepository($this->testRepository);
        $pendingScan->setStatus('pending');
        $pendingScan->setBranch('main');

        $completedScan = new RepositoryScan();
        $completedScan->setRepository($this->testRepository);
        $completedScan->setStatus('completed');
        $completedScan->setBranch('main');

        $this->entityManager->persist($pendingScan);
        $this->entityManager->persist($completedScan);
        $this->entityManager->flush();

        $pendingScans = $this->repository->findBy(['status' => 'pending']);
        $completedScans = $this->repository->findBy(['status' => 'completed']);

        $this->assertCount(1, $pendingScans);
        $this->assertCount(1, $completedScans);
        $this->assertEquals('pending', $pendingScans[0]->getStatus());
        $this->assertEquals('completed', $completedScans[0]->getStatus());
    }

    public function testCanFindScansByRepository(): void
    {
        $scan1 = new RepositoryScan();
        $scan1->setRepository($this->testRepository);
        $scan1->setStatus('completed');
        $scan1->setBranch('main');

        $scan2 = new RepositoryScan();
        $scan2->setRepository($this->testRepository);
        $scan2->setStatus('completed');
        $scan2->setBranch('develop');

        $this->entityManager->persist($scan1);
        $this->entityManager->persist($scan2);
        $this->entityManager->flush();

        $scans = $this->repository->findBy(['repository' => $this->testRepository]);

        $this->assertCount(2, $scans);
    }

    public function testCanUpdateScanStatus(): void
    {
        $scan = new RepositoryScan();
        $scan->setRepository($this->testRepository);
        $scan->setStatus('pending');
        $scan->setBranch('main');
        $scan->setStartedAt(new \DateTime());

        $this->entityManager->persist($scan);
        $this->entityManager->flush();

        $scan->setStatus('completed');
        $scan->setFinishedAt(new \DateTime());
        $this->entityManager->flush();

        $updatedScan = $this->repository->find($scan->getId());

        $this->assertEquals('completed', $updatedScan->getStatus());
        $this->assertNotNull($updatedScan->getFinishedAt());
        $this->assertNotNull($updatedScan->getUpdatedAt());
    }

    public function testCanStoreScanMetadata(): void
    {
        $metadata = [
            'duration_seconds' => 45,
            'files_scanned' => 150,
            'lines_of_code' => 12000
        ];

        $scan = new RepositoryScan();
        $scan->setRepository($this->testRepository);
        $scan->setStatus('completed');
        $scan->setBranch('main');
        $scan->setMetadata($metadata);

        $this->entityManager->persist($scan);
        $this->entityManager->flush();

        $foundScan = $this->repository->find($scan->getId());

        $this->assertEquals($metadata, $foundScan->getMetadata());
        $this->assertEquals(45, $foundScan->getMetadata()['duration_seconds']);
    }
}
