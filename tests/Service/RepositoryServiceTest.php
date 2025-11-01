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

    public function testCreateRepositoryThrowsExceptionWhenNameMissing()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Repository name is required');
        
        $this->service->createRepository([]);
    }

    public function testCreateRepositoryThrowsExceptionWhenNameEmpty()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Repository name is required');
        
        $this->service->createRepository(['name' => '']);
    }

    public function testCreateRepositoryWithNotificationSettings()
    {
        $this->em->expects($this->exactly(2))->method('persist');
        $this->em->expects($this->once())->method('flush');

        $data = [
            'name' => 'repo-with-notif',
            'url' => 'https://github.com/test/repo',
            'default_branch' => 'develop',
            'notification_settings' => [
                'emails' => ['dev@example.com', 'qa@example.com'],
                'slack_channels' => ['#alerts', '#team'],
                'webhooks' => ['https://webhook.site/test']
            ]
        ];

        $result = $this->service->createRepository($data);
        
        $this->assertInstanceOf(RepoEntity::class, $result);
        $this->assertEquals('repo-with-notif', $result->getName());
        $this->assertEquals('https://github.com/test/repo', $result->getUrl());
        $this->assertEquals('develop', $result->getDefaultBranch());
    }

    public function testListRepositoriesWithPagination()
    {
        $repo1 = new RepoEntity();
        $repo1->setName('repo1');
        $repo2 = new RepoEntity();
        $repo2->setName('repo2');

        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->expects($this->once())->method('getResult')->willReturn([$repo1, $repo2]);

        $qb = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $qb->expects($this->once())->method('select')->with('r')->willReturnSelf();
        $qb->expects($this->once())->method('from')->with(RepoEntity::class, 'r')->willReturnSelf();
        $qb->expects($this->once())->method('orderBy')->with('r.createdAt', 'DESC')->willReturnSelf();
        $qb->expects($this->once())->method('setFirstResult')->with(20)->willReturnSelf();
        $qb->expects($this->once())->method('setMaxResults')->with(10)->willReturnSelf();
        $qb->expects($this->once())->method('getQuery')->willReturn($query);

        $this->em->expects($this->once())->method('createQueryBuilder')->willReturn($qb);
        $this->repoRepo->expects($this->once())->method('count')->with([])->willReturn(25);

        $result = $this->service->listRepositories(3, 10);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('meta', $result);
        $this->assertCount(2, $result['data']);
        $this->assertEquals(3, $result['meta']['page']);
        $this->assertEquals(10, $result['meta']['limit']);
        $this->assertEquals(25, $result['meta']['total']);
    }

    public function testListRepositoriesWithInvalidPageDefaults()
    {
        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->expects($this->once())->method('getResult')->willReturn([]);

        $qb = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $qb->expects($this->once())->method('select')->willReturnSelf();
        $qb->expects($this->once())->method('from')->willReturnSelf();
        $qb->expects($this->once())->method('orderBy')->willReturnSelf();
        $qb->expects($this->once())->method('setFirstResult')->with(0)->willReturnSelf();
        $qb->expects($this->once())->method('setMaxResults')->with(1)->willReturnSelf();
        $qb->expects($this->once())->method('getQuery')->willReturn($query);

        $this->em->expects($this->once())->method('createQueryBuilder')->willReturn($qb);
        $this->repoRepo->expects($this->once())->method('count')->willReturn(0);

        $result = $this->service->listRepositories(-5, -10);

        $this->assertEquals(1, $result['meta']['page']);
        $this->assertEquals(1, $result['meta']['limit']);
    }

    public function testUpdateRepositoryReturnsNullWhenNotFound()
    {
        $this->repoRepo->expects($this->once())->method('find')->with('invalid-id')->willReturn(null);
        
        $result = $this->service->updateRepository('invalid-id', ['name' => 'new-name']);
        
        $this->assertNull($result);
    }

    public function testUpdateRepositoryWithNullValues()
    {
        $repo = new RepoEntity();
        $repo->setName('original');
        $repo->setUrl('https://original.com');
        $repo->setDefaultBranch('main');
        
        $this->repoRepo->expects($this->once())->method('find')->willReturn($repo);
        $this->em->expects($this->once())->method('flush');

        $result = $this->service->updateRepository($repo->getId(), [
            'url' => null,
            'default_branch' => null
        ]);

        $this->assertNotNull($result);
        $this->assertNull($result->getUrl());
        $this->assertNull($result->getDefaultBranch());
    }

    public function testDeleteRepositoryReturnsFalseWhenNotFound()
    {
        $this->repoRepo->expects($this->once())->method('find')->with('nonexistent')->willReturn(null);
        
        $result = $this->service->deleteRepository('nonexistent');
        
        $this->assertFalse($result);
    }

    public function testGetNotificationSettingsReturnsNullWhenRepoNotFound()
    {
        $this->repoRepo->expects($this->once())->method('find')->with('invalid')->willReturn(null);
        
        $result = $this->service->getNotificationSettings('invalid');
        
        $this->assertNull($result);
    }

    public function testGetNotificationSettingsReturnsSettings()
    {
        $repo = new RepoEntity();
        $repo->setName('test-repo');
        
        $notif = new NotificationSetting();
        $notif->setRepository($repo);
        $notif->setEmails(['test@example.com']);
        
        $this->repoRepo->expects($this->once())->method('find')->willReturn($repo);
        $this->notifRepo->expects($this->once())->method('findOneBy')->with(['repository' => $repo])->willReturn($notif);
        
        $result = $this->service->getNotificationSettings($repo->getId());
        
        $this->assertInstanceOf(NotificationSetting::class, $result);
        $this->assertEquals(['test@example.com'], $result->getEmails());
    }

    public function testReplaceNotificationSettingsThrowsExceptionWhenRepoNotFound()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Repository not found');
        
        $this->repoRepo->expects($this->once())->method('find')->with('invalid')->willReturn(null);
        
        $this->service->replaceNotificationSettings('invalid', ['emails' => ['test@example.com']]);
    }

    public function testReplaceNotificationSettingsUpdatesExisting()
    {
        $repo = new RepoEntity();
        $repo->setName('existing-repo');
        
        $existing = new NotificationSetting();
        $existing->setRepository($repo);
        $existing->setEmails(['old@example.com']);
        $existing->setSlackChannels(['#old']);
        
        $this->repoRepo->expects($this->once())->method('find')->willReturn($repo);
        $this->notifRepo->expects($this->once())->method('findOneBy')->with(['repository' => $repo])->willReturn($existing);
        $this->em->expects($this->once())->method('flush');

        $result = $this->service->replaceNotificationSettings($repo->getId(), [
            'emails' => ['new@example.com'],
            'slack_channels' => ['#new'],
            'webhooks' => ['https://webhook.example.com']
        ]);

        $this->assertInstanceOf(NotificationSetting::class, $result);
        $this->assertEquals(['new@example.com'], $result->getEmails());
        $this->assertEquals(['#new'], $result->getSlackChannels());
        $this->assertEquals(['https://webhook.example.com'], $result->getWebhooks());
    }

    public function testPatchNotificationSettingsThrowsExceptionWhenRepoNotFound()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Repository not found');
        
        $this->repoRepo->expects($this->once())->method('find')->with('invalid')->willReturn(null);
        
        $this->service->patchNotificationSettings('invalid', ['emails' => ['test@example.com']]);
    }

    public function testPatchNotificationSettingsCreatesWhenAbsent()
    {
        $repo = new RepoEntity();
        $repo->setName('new-repo');
        
        $this->repoRepo->expects($this->exactly(2))->method('find')->willReturn($repo);
        $this->notifRepo->expects($this->exactly(2))->method('findOneBy')->with(['repository' => $repo])->willReturn(null);
        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $result = $this->service->patchNotificationSettings($repo->getId(), [
            'emails' => ['new@example.com'],
            'slack_channels' => ['#new']
        ]);

        $this->assertInstanceOf(NotificationSetting::class, $result);
    }

    public function testPatchNotificationSettingsWithWebhooks()
    {
        $repo = new RepoEntity();
        $repo->setName('webhook-repo');
        
        $existing = new NotificationSetting();
        $existing->setRepository($repo);
        $existing->setEmails(['old@example.com']);
        
        $this->repoRepo->expects($this->once())->method('find')->willReturn($repo);
        $this->notifRepo->expects($this->once())->method('findOneBy')->with(['repository' => $repo])->willReturn($existing);
        $this->em->expects($this->once())->method('flush');

        $result = $this->service->patchNotificationSettings($repo->getId(), [
            'webhooks' => ['https://webhook1.example.com', 'https://webhook2.example.com']
        ]);

        $this->assertInstanceOf(NotificationSetting::class, $result);
        $this->assertEquals(['https://webhook1.example.com', 'https://webhook2.example.com'], $result->getWebhooks());
        $this->assertEquals(['old@example.com'], $result->getEmails());
    }
}
