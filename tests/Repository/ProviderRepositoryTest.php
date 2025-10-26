<?php

namespace App\Tests\Repository;

use App\Entity\Provider;
use App\Repository\ProviderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ProviderRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private ProviderRepository $repository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();

        $this->entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();

        $this->repository = $this->entityManager->getRepository(Provider::class);
    }

    protected function tearDown(): void
    {
        // Clean up test data
        $providers = $this->repository->findAll();
        foreach ($providers as $provider) {
            $this->entityManager->remove($provider);
        }
        $this->entityManager->flush();

        parent::tearDown();
        $this->entityManager->close();
    }

    public function testCanCreateAndFindProvider(): void
    {
        $provider = new Provider();
        $provider->setName('Snyk');
        $provider->setCode('sca_snyk');
        $provider->setConfig(['api_url' => 'https://api.snyk.io']);

        $this->entityManager->persist($provider);
        $this->entityManager->flush();

        $foundProvider = $this->repository->find($provider->getId());

        $this->assertNotNull($foundProvider);
        $this->assertEquals('Snyk', $foundProvider->getName());
        $this->assertEquals('sca_snyk', $foundProvider->getCode());
        $this->assertNotNull($foundProvider->getCreatedAt());
    }

    public function testCanFindProviderByName(): void
    {
        $provider = new Provider();
        $provider->setName('GitHub Advanced Security');
        $provider->setCode('sca_github');

        $this->entityManager->persist($provider);
        $this->entityManager->flush();

        $result = $this->repository->findOneBy(['name' => 'GitHub Advanced Security']);

        $this->assertNotNull($result);
        $this->assertEquals('GitHub Advanced Security', $result->getName());
    }

    public function testCanFindEnabledProviders(): void
    {
        $provider1 = new Provider();
        $provider1->setName('Snyk');
        $provider1->setCode('sca_snyk');

        $provider2 = new Provider();
        $provider2->setName('Checkmarx');
        $provider2->setCode('sca_checkmarx');

        $this->entityManager->persist($provider1);
        $this->entityManager->persist($provider2);
        $this->entityManager->flush();

        $providers = $this->repository->findAll();

        $this->assertCount(2, $providers);
    }

    public function testCanUpdateProvider(): void
    {
        $provider = new Provider();
        $provider->setName('Original Name');
        $provider->setCode('sca_original');

        $this->entityManager->persist($provider);
        $this->entityManager->flush();

        $provider->setName('Updated Name');
        $this->entityManager->flush();

        $updatedProvider = $this->repository->find($provider->getId());

        $this->assertEquals('Updated Name', $updatedProvider->getName());
        $this->assertNotNull($updatedProvider->getUpdatedAt());
    }

    public function testCanDeleteProvider(): void
    {
        $provider = new Provider();
        $provider->setName('To Be Deleted');
        $provider->setCode('sca_deleted');

        $this->entityManager->persist($provider);
        $this->entityManager->flush();

        $providerId = $provider->getId();

        $this->entityManager->remove($provider);
        $this->entityManager->flush();

        $deletedProvider = $this->repository->find($providerId);

        $this->assertNull($deletedProvider);
    }
}
