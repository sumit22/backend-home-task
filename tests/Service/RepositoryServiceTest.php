<?php

namespace App\Tests\Service;

use App\Entity\Repository as RepoEntity;
use App\Entity\NotificationSetting;
use App\Service\RepositoryService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
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
        $this->repoRepo = $this->createMock(ObjectRepository::class);
        $this->notifRepo = $this->createMock(ObjectRepository::class);

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
        $data = ['name' => 'my-repo', 'full_path' => 'https://x', 'default_branch' => 'main'];

        // Expect persist called once for the repository
        $this->em->expects($this->once())->method('persist')
            ->with($this->isInstanceOf(RepoEntity::class));

        $this->em->expects($this->once())->method('flush');

        $result = $this->service->createRepository($data);

        $this->assertIsArray($result);
        $this->assertEquals('my-repo', $result['name']);
        $this->assertEquals('https://x', $result['full_path']);
    }

    public function testGetRepositoryReturnsNullWhenMissing()
    {
        $this->repoRepo->expects($this->once())->method('find')->with('nonexistent')->willReturn(null);
        $res = $this->service->getRepository('nonexistent');
        $this->assertNull($res);
    }

    public function testUpdateRepositoryPatches()
    {
        $repo = new RepoEntity('name1', 'https://a', 'main');
        $this->repoRepo->expects($this->once())->method('find')->with($repo->getId())->willReturn($repo);

        // EM::flush expected
        $this->em->expects($this->once())->method('flush');

        $patched = $this->service->updateRepository($repo->getId(), ['name' => 'newname', 'url' => 'https://b']);
        $this->assertNotNull($patched);
        $this->assertEquals('newname', $patched['name']);
        $this->assertEquals('https://b', $patched['url']);
    }

    public function testDeleteRepositoryRemoves()
    {
        $repo = new RepoEntity('delme', null, null);
        $this->repoRepo->expects($this->once())->method('find')->with($repo->getId())->willReturn($repo);

        $this->em->expects($this->once())->method('remove')->with($repo);
        $this->em->expects($this->once())->method('flush');

        $ok = $this->service->deleteRepository($repo->getId());
        $this->assertTrue($ok);
    }

    public function testReplaceNotificationSettingsCreatesWhenAbsent()
    {
        $repo = new RepoEntity('nrepo', null, null);
        $this->repoRepo->expects($this->once())->method('find')->with($repo->getId())->willReturn($repo);

        $this->notifRepo->expects($this->once())->method('findOneBy')->with(['repository' => $repo])->willReturn(null);

        $this->em->expects($this->once())->method('persist')->with($this->isInstanceOf(NotificationSetting::class));
        $this->em->expects($this->once())->method('flush');

        $settings = ['emails' => ['a@b.com'], 'slack_channels' => ['#x']];
        $result = $this->service->replaceNotificationSettings($repo->getId(), $settings);

        $this->assertIsArray($result);
        $this->assertEquals(['a@b.com'], $result['emails']);
    }

    public function testPatchNotificationSettingsUpdatesExisting()
    {
        $repo = new RepoEntity('nrepo2', null, null);
        $this->repoRepo->expects($this->once())->method('find')->with($repo->getId())->willReturn($repo);

        $existing = new NotificationSetting($repo, ['old@x.com'], ['#old'], []);
        $this->notifRepo->expects($this->once())->method('findOneBy')->with(['repository' => $repo])->willReturn($existing);

        $this->em->expects($this->once())->method('flush');

        $res = $this->service->patchNotificationSettings($repo->getId(), ['emails' => ['new@x.com']]);
        $this->assertEquals(['new@x.com'], $res['emails']);
    }
}
