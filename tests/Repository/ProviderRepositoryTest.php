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
        $provider->setType('security_scanner');
        $provider->setEnabled(true);
        $provider->setConfig(['api_url' => 'https://api.snyk.io']);

        $this->entityManager->persist($provider);
        $this->entityManager->flush();

        $foundProvider = $this->repository->find($provider->getId());

        $this->assertNotNull($foundProvider);
        $this->assertEquals('Snyk', $foundProvider->getName());
        $this->assertEquals('security_scanner', $foundProvider->getType());
        $this->assertTrue($foundProvider->isEnabled());
        $this->assertNotNull($foundProvider->getCreatedAt());
    }

    public function testCanFindProviderByName(): void
    {
        $provider = new Provider();
        $provider->setName('GitHub Advanced Security');
        $provider->setType('security_scanner');
        $provider->setEnabled(true);

        $this->entityManager->persist($provider);
        $this->entityManager->flush();

        $result = $this->repository->findOneBy(['name' => 'GitHub Advanced Security']);

        $this->assertNotNull($result);
        $this->assertEquals('GitHub Advanced Security', $result->getName());
    }

    public function testCanFindEnabledProviders(): void
    {
        $enabledProvider = new Provider();
        $enabledProvider->setName('Snyk');
        $enabledProvider->setType('security_scanner');
        $enabledProvider->setEnabled(true);

        $disabledProvider = new Provider();
        $disabledProvider->setName('Checkmarx');
        $disabledProvider->setType('security_scanner');
        $disabledProvider->setEnabled(false);

        $this->entityManager->persist($enabledProvider);
        $this->entityManager->persist($disabledProvider);
        $this->entityManager->flush();

        $enabledProviders = $this->repository->findBy(['enabled' => true]);

        $this->assertCount(1, $enabledProviders);
        $this->assertEquals('Snyk', $enabledProviders[0]->getName());
    }

    public function testCanUpdateProvider(): void
    {
        $provider = new Provider();
        $provider->setName('Original Name');
        $provider->setType('security_scanner');
        $provider->setEnabled(true);

        $this->entityManager->persist($provider);
        $this->entityManager->flush();

        $provider->setName('Updated Name');
        $provider->setEnabled(false);
        $this->entityManager->flush();

        $updatedProvider = $this->repository->find($provider->getId());

        $this->assertEquals('Updated Name', $updatedProvider->getName());
        $this->assertFalse($updatedProvider->isEnabled());
        $this->assertNotNull($updatedProvider->getUpdatedAt());
    }

    public function testCanDeleteProvider(): void
    {
        $provider = new Provider();
        $provider->setName('To Be Deleted');
        $provider->setType('security_scanner');
        $provider->setEnabled(true);

        $this->entityManager->persist($provider);
        $this->entityManager->flush();

        $providerId = $provider->getId();

        $this->entityManager->remove($provider);
        $this->entityManager->flush();

        $deletedProvider = $this->repository->find($providerId);

        $this->assertNull($deletedProvider);
    }
}
