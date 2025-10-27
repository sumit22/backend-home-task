<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

class ExternalMappingService
{
    private Connection $conn;

    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }

    public function createMapping(string $providerCode, string $type, string $externalId, string $linkedEntityType, string $linkedEntityId, ?array $rawPayload = null): string
    {
        $id = bin2hex(random_bytes(16)); // or use UUID lib
        $this->conn->insert('integration', [
            'id' => $id,
            'provider_code' => $providerCode,
            'type' => $type,
            'external_id' => $externalId,
            'linked_entity_type' => $linkedEntityType,
            'linked_entity_id' => $linkedEntityId,
            'status' => null,
            'raw_payload' => $rawPayload ? json_encode($rawPayload) : null,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        return $id;
    }

    public function updateMappingRaw(string $providerCode, string $type, string $externalId, array $payload): void
    {
        $this->conn->update('integration', ['raw_payload' => json_encode($payload), 'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')], ['provider_code' => $providerCode, 'type' => $type, 'external_id' => $externalId]);
    }

    public function findMapping(string $providerCode, string $type, string $externalId): ?array
    {
        $row = $this->conn->fetchAssociative('SELECT * FROM integration WHERE provider_code = ? AND type = ? AND external_id = ?', [$providerCode, $type, $externalId]);
        return $row ?: null;
    }

    public function findMappingByLinkedEntity(string $providerCode, string $type, string $linkedEntityType, string $linkedEntityId): ?array
    {
        return $this->conn->fetchAssociative(
            'SELECT * FROM integration WHERE provider_code = ? AND type = ? AND linked_entity_type = ? AND linked_entity_id = ?',
            [$providerCode, $type, $linkedEntityType, $linkedEntityId]
        ) ?: null;
    }
}
