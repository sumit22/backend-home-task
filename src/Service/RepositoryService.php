<?php
// src/Service/RepositoryService.php
namespace App\Service;

use App\Entity\Repository as RepositoryEntity;
use App\Entity\NotificationSetting;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use App\Contract\Service\RepositoryServiceInterface;

class RepositoryService implements RepositoryServiceInterface
{
    private EntityManagerInterface $em;
    private EntityRepository $repoRepo;
    private EntityRepository $notifRepo;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
        $this->repoRepo = $em->getRepository(RepositoryEntity::class);
        $this->notifRepo = $em->getRepository(NotificationSetting::class);
    }

    public function createRepository(array $data): RepositoryEntity
    {
        $name = $data['name'] ?? null;
        if (empty($name)) {
            throw new \InvalidArgumentException('Repository name is required');
        }

        // Check for duplicate name
        $existing = $this->repoRepo->findOneBy(['name' => $name]);
        if ($existing) {
            throw new \InvalidArgumentException(sprintf('Repository with name "%s" already exists', $name));
        }

        $url = $data['url'] ?? null;
        // Validate URL format
        if ($url && !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Invalid repository URL format');
        }

        $defaultBranch = $data['default_branch'] ?? null;
        $settings = $data['settings'] ?? null;

        $repo = new RepositoryEntity();
        $repo->setName($name);
        if ($url) {
            $repo->setUrl($url);
        }
        if ($defaultBranch) {
            $repo->setDefaultBranch($defaultBranch);
        }
        if ($settings) {
            $repo->setSettings($settings);
        }
        
        $this->em->persist($repo);

        if (!empty($data['notification_settings']) && is_array($data['notification_settings'])) {
            $ns = $data['notification_settings'];
            $notif = new NotificationSetting();
            $notif->setRepository($repo);
            if (isset($ns['emails'])) {
                $notif->setEmails($ns['emails']);
            }
            if (isset($ns['slack_channels'])) {
                $notif->setSlackChannels($ns['slack_channels']);
            }
            if (isset($ns['webhooks'])) {
                $notif->setWebhooks($ns['webhooks']);
            }
            $this->em->persist($notif);
        }

        $this->em->flush();

        return $repo;
    }

    public function getRepository(string $id): ?RepositoryEntity
    {
        return $this->repoRepo->find($id);
    }

    public function listRepositories(int $page = 1, int $limit = 20): array
    {
        $page = max(1, $page);
        $limit = max(1, $limit);
        $offset = ($page - 1) * $limit;

        $qb = $this->em->createQueryBuilder()
            ->select('r')
            ->from(RepositoryEntity::class, 'r')
            ->orderBy('r.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        $items = $qb->getQuery()->getResult();
        $total = (int) $this->repoRepo->count([]);

        return [
            'data' => $items, // array of RepositoryEntity
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
            ],
        ];
    }

    public function updateRepository(string $id, array $patch): ?RepositoryEntity
    {
        /** @var RepositoryEntity|null $repo */
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
        /** @var RepositoryEntity|null $repo */
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
        return $this->notifRepo->findOneBy(['repository' => $repo]);
    }

    public function replaceNotificationSettings(string $repoId, array $settings): NotificationSetting
    {
        $repo = $this->repoRepo->find($repoId);
        if (!$repo) {
            throw new \InvalidArgumentException('Repository not found');
        }

        // Validate emails if provided
        if (isset($settings['emails'])) {
            if (empty($settings['emails'])) {
                throw new \InvalidArgumentException('At least one notification email is required');
            }
            foreach ($settings['emails'] as $email) {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new \InvalidArgumentException('Invalid email format');
                }
            }
        }

        $existing = $this->notifRepo->findOneBy(['repository' => $repo]);
        if ($existing) {
            $existing->setEmails($settings['emails'] ?? null);
            $existing->setSlackChannels($settings['slack_channels'] ?? null);
            $existing->setWebhooks($settings['webhooks'] ?? null);
            $this->em->flush();
            return $existing;
        }

        $new = new NotificationSetting();
        $new->setRepository($repo);
        $new->setEmails($settings['emails'] ?? null);
        $new->setSlackChannels($settings['slack_channels'] ?? null);
        $new->setWebhooks($settings['webhooks'] ?? null);
        $this->em->persist($new);
        $this->em->flush();
        return $new;
    }

    public function patchNotificationSettings(string $repoId, array $patch): NotificationSetting
    {
        $repo = $this->repoRepo->find($repoId);
        if (!$repo) {
            throw new \InvalidArgumentException('Repository not found');
        }

        $existing = $this->notifRepo->findOneBy(['repository' => $repo]);
        if (!$existing) {
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
        return $existing;
    }
}
