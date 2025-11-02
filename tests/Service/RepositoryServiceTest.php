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

    public function testCreateRepositoryWithNotificationSettings()
    {
        $data = [
            'name' => 'repo-with-notif',
            'url' => 'https://github.com/test/repo',
            'notification_settings' => [
                'emails' => ['test@example.com'],
                'slack_channels' => ['#general'],
                'webhooks' => ['https://webhook.site/test']
            ]
        ];

        // Expect persist called twice: once for repo, once for notification
        $this->em->expects($this->exactly(2))->method('persist');
        $this->em->expects($this->once())->method('flush');

        $result = $this->service->createRepository($data);

        $this->assertInstanceOf(RepoEntity::class, $result);
        $this->assertEquals('repo-with-notif', $result->getName());
    }

    public function testListRepositoriesReturnsPaginatedResults()
    {
        $repo1 = new RepoEntity();
        $repo1->setName('repo1');
        
        $repo2 = new RepoEntity();
        $repo2->setName('repo2');

        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $query = $this->getMockBuilder(\Doctrine\ORM\Query::class)
            ->disableOriginalConstructor()
            ->getMock();
        
        $this->em->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $queryBuilder->expects($this->once())->method('select')->willReturnSelf();
        $queryBuilder->expects($this->once())->method('from')->willReturnSelf();
        $queryBuilder->expects($this->once())->method('orderBy')->willReturnSelf();
        $queryBuilder->expects($this->once())->method('setFirstResult')->willReturnSelf();
        $queryBuilder->expects($this->once())->method('setMaxResults')->willReturnSelf();
        $queryBuilder->expects($this->once())->method('getQuery')->willReturn($query);
        
        $query->expects($this->once())->method('getResult')->willReturn([$repo1, $repo2]);
        
        $this->repoRepo->expects($this->once())->method('count')->willReturn(2);

        $result = $this->service->listRepositories(1, 20);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('meta', $result);
        $this->assertCount(2, $result['data']);
        $this->assertEquals(1, $result['meta']['page']);
        $this->assertEquals(20, $result['meta']['limit']);
        $this->assertEquals(2, $result['meta']['total']);
    }

    public function testUpdateRepositoryReturnsNullWhenNotFound()
    {
        $this->repoRepo->expects($this->once())->method('find')->with('missing-id')->willReturn(null);

        $result = $this->service->updateRepository('missing-id', ['name' => 'new-name']);

        $this->assertNull($result);
    }

    public function testDeleteRepositoryReturnsFalseWhenNotFound()
    {
        $this->repoRepo->expects($this->once())->method('find')->with('missing-id')->willReturn(null);

        $result = $this->service->deleteRepository('missing-id');

        $this->assertFalse($result);
    }

    public function testGetNotificationSettingsReturnsNullWhenRepoNotFound()
    {
        $this->repoRepo->expects($this->once())->method('find')->with('missing-id')->willReturn(null);

        $result = $this->service->getNotificationSettings('missing-id');

        $this->assertNull($result);
    }

    public function testGetNotificationSettingsReturnsSettingsWhenFound()
    {
        $repo = new RepoEntity();
        $repo->setName('test-repo');

        $notif = new NotificationSetting();
        $notif->setRepository($repo);
        $notif->setEmails(['test@example.com']);

        $this->repoRepo->expects($this->once())->method('find')->with($repo->getId())->willReturn($repo);
        $this->notifRepo->expects($this->once())->method('findOneBy')->with(['repository' => $repo])->willReturn($notif);

        $result = $this->service->getNotificationSettings($repo->getId());

        $this->assertInstanceOf(NotificationSetting::class, $result);
        $this->assertEquals(['test@example.com'], $result->getEmails());
    }

    public function testReplaceNotificationSettingsThrowsExceptionWhenRepoNotFound()
    {
        $this->repoRepo->expects($this->once())->method('find')->with('missing-id')->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Repository not found');

        $this->service->replaceNotificationSettings('missing-id', ['emails' => ['test@example.com']]);
    }

    public function testReplaceNotificationSettingsUpdatesExisting()
    {
        $repo = new RepoEntity();
        $repo->setName('test-repo');

        $existing = new NotificationSetting();
        $existing->setRepository($repo);
        $existing->setEmails(['old@example.com']);

        $this->repoRepo->expects($this->once())->method('find')->with($repo->getId())->willReturn($repo);
        $this->notifRepo->expects($this->once())->method('findOneBy')->with(['repository' => $repo])->willReturn($existing);
        $this->em->expects($this->once())->method('flush');

        $result = $this->service->replaceNotificationSettings($repo->getId(), [
            'emails' => ['new@example.com'],
            'slack_channels' => ['#new-channel']
        ]);

        $this->assertInstanceOf(NotificationSetting::class, $result);
        $this->assertEquals(['new@example.com'], $result->getEmails());
        $this->assertEquals(['#new-channel'], $result->getSlackChannels());
    }

    public function testPatchNotificationSettingsThrowsExceptionWhenRepoNotFound()
    {
        $this->repoRepo->expects($this->once())->method('find')->with('missing-id')->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Repository not found');

        $this->service->patchNotificationSettings('missing-id', ['emails' => ['test@example.com']]);
    }

    public function testPatchNotificationSettingsCreatesNewWhenNotExists()
    {
        $repo = new RepoEntity();
        $repo->setName('test-repo');

        // The service will call find() twice: once in patchNotificationSettings and once in replaceNotificationSettings
        $this->repoRepo->expects($this->exactly(2))->method('find')->with($repo->getId())->willReturn($repo);
        // findOneBy will be called twice as well
        $this->notifRepo->expects($this->exactly(2))->method('findOneBy')->with(['repository' => $repo])->willReturn(null);
        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $result = $this->service->patchNotificationSettings($repo->getId(), ['emails' => ['new@example.com']]);

        $this->assertInstanceOf(NotificationSetting::class, $result);
    }

    /**
     * Test that creating repository with duplicate name is rejected
     */
    public function testCreateRepositoryRejectsDuplicateName()
    {
        $existingRepo = new RepoEntity();
        $existingRepo->setName('duplicate-repo');
        $existingRepo->setUrl('https://github.com/existing/repo');

        $this->repoRepo->expects($this->once())
            ->method('findOneBy')
            ->with(['name' => 'duplicate-repo'])
            ->willReturn($existingRepo);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Repository with name "duplicate-repo" already exists');

        $this->service->createRepository([
            'name' => 'duplicate-repo',
            'url' => 'https://github.com/new/repo',
            'default_branch' => 'main'
        ]);
    }

    /**
     * Test that invalid email format in notification settings is rejected
     */
    public function testNotificationSettingsRejectsInvalidEmail()
    {
        $repo = new RepoEntity();
        $repo->setName('test-repo');

        $this->repoRepo->expects($this->once())
            ->method('find')
            ->with($repo->getId())
            ->willReturn($repo);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email format');

        $this->service->replaceNotificationSettings($repo->getId(), [
            'emails' => ['valid@example.com', 'invalid-email-format']
        ]);
    }

    /**
     * Test that repository URL format is validated
     */
    public function testCreateRepositoryValidatesUrlFormat()
    {
        $this->repoRepo->expects($this->once())
            ->method('findOneBy')
            ->with(['name' => 'test-repo'])
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid repository URL format');

        $this->service->createRepository([
            'name' => 'test-repo',
            'url' => 'not-a-valid-url',
            'default_branch' => 'main'
        ]);
    }

    /**
     * Test that empty notification email list is rejected
     */
    public function testNotificationSettingsRejectsEmptyEmailArray()
    {
        $repo = new RepoEntity();
        $repo->setName('test-repo');

        $this->repoRepo->expects($this->once())
            ->method('find')
            ->with($repo->getId())
            ->willReturn($repo);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one notification email is required');

        $this->service->replaceNotificationSettings($repo->getId(), [
            'emails' => []
        ]);
    }
}
