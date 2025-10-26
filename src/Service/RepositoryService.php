<?php
// src/Service/RepositoryService.php
namespace App\Service;

use App\Entity\Repository;
use App\Entity\NotificationSetting;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use App\Contracts\Service\RepositoryServiceInterface;

class RepositoryService implements RepositoryServiceInterface
{
    private EntityManagerInterface $em;
    /** @var ObjectRepository<Repository> */
    private ObjectRepository $repoRepo;
    /** @var ObjectRepository<NotificationSetting> */
    private ObjectRepository $notifRepo;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
        $this->repoRepo = $em->getRepository(Repository::class);
        $this->notifRepo = $em->getRepository(NotificationSetting::class);
    }

    public function createRepository(array $data): Repository
    {
        $name = $data['name'] ?? null;
        if (empty($name)) {
            throw new \InvalidArgumentException('Repository name is required');
        }
        $url = $data['url'] ?? null;
        $defaultBranch = $data['default_branch'] ?? null;
        $settings = $data['settings'] ?? null;

        $repo = new Repository($name, $url, $defaultBranch, $settings);
        $this->em->persist($repo);

        // optional notification settings in create payload
        if (!empty($data['notification_settings']) && is_array($data['notification_settings'])) {
            $ns = $data['notification_settings'];
            $notif = new NotificationSetting(
                $repo,
                $ns['emails'] ?? null,
                $ns['slack_channels'] ?? null,
                $ns['webhooks'] ?? null
            );
            $this->em->persist($notif);
        }

        $this->em->flush();

        return $repo;
    }

    public function getRepository(string $id): ?Repository
    {
        return $this->repoRepo->find($id) ?? throw new \InvalidArgumentException('Repository not found');
    }

    public function listRepositories(int $page = 1, int $limit = 20): array
    {
        $page = max(1, $page);
        $limit = max(1, min(100, $limit)); // Cap at 100 items per page
        $offset = ($page - 1) * $limit;

        $qb = $this->em->createQueryBuilder();
        $qb->select('r')
            ->from(Repository::class, 'r')
            ->orderBy('r.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        $items = $qb->getQuery()->getResult();

        // Get total count
        $countQb = $this->em->createQueryBuilder();
        $countQb->select('COUNT(r.id)')
            ->from(Repository::class, 'r');
        $total = (int) $countQb->getQuery()->getSingleScalarResult();

        $data = array_map(fn(Repository $r) => $r->toArray(), $items);

        return [
            'data' => $data,
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => (int) ceil($total / $limit),
            ],
        ];
    }

    public function updateRepository(string $id, array $patch): ?Repository
    {
        /** @var Repository|null $repo */
        $repo = $this->repoRepo->find($id);
        if (!$repo) return null;

        if (isset($patch['name'])) $repo->setName((string)$patch['name']);
        if (array_key_exists('url', $patch)) $repo->setUrl($patch['url']);
        if (array_key_exists('default_branch', $patch)) $repo->setDefaultBranch($patch['default_branch']);
        if (array_key_exists('settings', $patch)) $repo->setSettings($patch['settings']);

        $this->em->flush();

        return $repo;
    }

    public function deleteRepository(string $id): bool
    {
        /** @var Repository|null $repo */
        $repo = $this->repoRepo->find($id);
        if (!$repo) return false;
        $this->em->remove($repo);
        $this->em->flush();
        return true;
    }

    public function getNotificationSettings(string $repoId): ?NotificationSetting
    {
        $repo = $this->repoRepo->find($repoId);
        if (!$repo) return null;

        $ns = $this->notifRepo->findOneBy(['repository' => $repo]);
        if (!$ns) return null;
        return $ns;
    }

    public function replaceNotificationSettings(string $repoId, array $settings): array
    {
        $repo = $this->repoRepo->find($repoId);
        if (!$repo) {
            throw new \InvalidArgumentException('Repository not found');
        }

        $existing = $this->notifRepo->findOneBy(['repository' => $repo]);
        if ($existing) {
            $existing->setEmails($settings['emails'] ?? null);
            $existing->setSlackChannels($settings['slack_channels'] ?? null);
            $existing->setWebhooks($settings['webhooks'] ?? null);
            $this->em->flush();
            return $existing->toArray();
        }

        $new = new NotificationSetting(
            $repo,
            $settings['emails'] ?? null,
            $settings['slack_channels'] ?? null,
            $settings['webhooks'] ?? null
        );
        $this->em->persist($new);
        $this->em->flush();
        return $new->toArray();
    }

    public function patchNotificationSettings(string $repoId, array $patch): array
    {
        $repo = $this->repoRepo->find($repoId);
        if (!$repo) {
            throw new \InvalidArgumentException('Repository not found');
        }

        $existing = $this->notifRepo->findOneBy(['repository' => $repo]);
        if (!$existing) {
            // create new with whatever keys provided
            return $this->replaceNotificationSettings($repoId, $patch);
        }

        if (array_key_exists('emails', $patch)) {
            $existing->setEmails($patch['emails']);
        }
        if (array_key_exists('slack_channels', $patch)) {
            $existing->setSlackChannels($patch['slack_channels']);
        }
        if (array_key_exists('webhooks', $patch)) {
            $existing->setWebhooks($patch['webhooks']);
        }

        $this->em->flush();
        return $existing->toArray();
    }
}
