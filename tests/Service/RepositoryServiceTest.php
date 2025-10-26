<?php

namespace App\Tests\Service;

use App\Entity\Repository as RepoEntity;
use App\Entity\NotificationSetting;
use App\Service\RepositoryService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

class RepositoryServiceTest extends TestCase
{
    private $em;
    private $repoRepo;
    private $notifRepo;
    private RepositoryService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->repoRepo = $this->createMock(EntityRepository::class);
        $this->notifRepo = $this->createMock(EntityRepository::class);

        // Configure EM->getRepository to return our mocked repositories
        $this->em->method('getRepository')
            ->willReturnCallback(function ($class) {
                if ($class === RepoEntity::class) return $this->repoRepo;
                if ($class === NotificationSetting::class) return $this->notifRepo;
                return null;
            });

        $this->service = new RepositoryService($this->em);
    }

    public function testCreateRepositoryPersists()
    {
        $data = ['name' => 'my-repo', 'url' => 'https://x', 'default_branch' => 'main'];

        // Expect persist called once for the repository
        $this->em->expects($this->once())->method('persist')
            ->with($this->isInstanceOf(RepoEntity::class));

        $this->em->expects($this->once())->method('flush');

        $result = $this->service->createRepository($data);

        $this->assertInstanceOf(RepoEntity::class, $result);
        $this->assertEquals('my-repo', $result->getName());
        $this->assertEquals('https://x', $result->getUrl());
        $this->assertEquals('main', $result->getDefaultBranch());
    }

    public function testGetRepositoryReturnsNullWhenMissing()
    {
        $this->repoRepo->expects($this->once())->method('find')->with('nonexistent')->willReturn(null);
        $res = $this->service->getRepository('nonexistent');
        $this->assertNull($res);
    }

    public function testUpdateRepositoryPatches()
    {
        $repo = new RepoEntity();
        $repo->setName('name1');
        $repo->setUrl('https://a');
        $repo->setDefaultBranch('main');
        
        $this->repoRepo->expects($this->once())->method('find')->with($repo->getId())->willReturn($repo);

        // EM::flush expected
        $this->em->expects($this->once())->method('flush');

        $patched = $this->service->updateRepository($repo->getId(), ['name' => 'newname', 'url' => 'https://b']);
        $this->assertNotNull($patched);
        $this->assertInstanceOf(RepoEntity::class, $patched);
        $this->assertEquals('newname', $patched->getName());
        $this->assertEquals('https://b', $patched->getUrl());
    }

    public function testDeleteRepositoryRemoves()
    {
        $repo = new RepoEntity();
        $repo->setName('delme');
        
        $this->repoRepo->expects($this->once())->method('find')->with($repo->getId())->willReturn($repo);

        $this->em->expects($this->once())->method('remove')->with($repo);
        $this->em->expects($this->once())->method('flush');

        $ok = $this->service->deleteRepository($repo->getId());
        $this->assertTrue($ok);
    }

    public function testReplaceNotificationSettingsCreatesWhenAbsent()
    {
        $repo = new RepoEntity();
        $repo->setName('nrepo');
        
        $this->repoRepo->expects($this->once())->method('find')->with($repo->getId())->willReturn($repo);

        $this->notifRepo->expects($this->once())->method('findOneBy')->with(['repository' => $repo])->willReturn(null);

        $this->em->expects($this->once())->method('persist')->with($this->isInstanceOf(NotificationSetting::class));
        $this->em->expects($this->once())->method('flush');

        $settings = ['emails' => ['a@b.com'], 'slack_channels' => ['#x']];
        $result = $this->service->replaceNotificationSettings($repo->getId(), $settings);

        $this->assertInstanceOf(NotificationSetting::class, $result);
        $this->assertEquals(['a@b.com'], $result->getEmails());
    }

    public function testPatchNotificationSettingsUpdatesExisting()
    {
        $repo = new RepoEntity();
        $repo->setName('nrepo2');
        
        $this->repoRepo->expects($this->once())->method('find')->with($repo->getId())->willReturn($repo);

        $existing = new NotificationSetting();
        $existing->setRepository($repo);
        $existing->setEmails(['old@x.com']);
        $existing->setSlackChannels(['#old']);
        
        $this->notifRepo->expects($this->once())->method('findOneBy')->with(['repository' => $repo])->willReturn($existing);

        $this->em->expects($this->once())->method('flush');

        $res = $this->service->patchNotificationSettings($repo->getId(), ['emails' => ['new@x.com']]);
        $this->assertInstanceOf(NotificationSetting::class, $res);
        $this->assertEquals(['new@x.com'], $res->getEmails());
    }
}
