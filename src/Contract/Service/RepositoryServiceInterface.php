<?php
namespace App\Contracts\Service;

use App\Entity\NotificationSetting;
use App\Entity\Repository;

interface RepositoryServiceInterface
{
    public function createRepository(array $data): Repository;
    public function getRepository(string $id): ?Repository;
    public function listRepositories(int $page = 1, int $limit = 20): array;
    public function updateRepository(string $id, array $patch): ?Repository;
    public function deleteRepository(string $id): bool;

    public function getNotificationSettings(string $repoId): ?NotificationSetting;
    public function replaceNotificationSettings(string $repoId, array $settings): array;
    public function patchNotificationSettings(string $repoId, array $patch): array;
}
