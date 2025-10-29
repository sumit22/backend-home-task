<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

class ExternalMappingService
{
    private Connection $conn;

    public function __construct(Connection $conn)
    {
        $this->conn = $conn;
    }

    public function createMapping(string $providerCode, string $type, string $externalId, string $linkedEntityType, string $linkedEntityId, ?array $rawPayload = null): string
    {
        $id = Uuid::v4();
        
        // Convert linkedEntityId string to UUID binary
        $linkedEntityUuid = Uuid::fromString($linkedEntityId);
        
        $this->conn->insert('integration', [
            'id' => $id->toBinary(),
            'provider_code' => $providerCode,  // Use provider_code string column
            'type' => $type,
            'external_id' => $externalId,
            'linked_entity_type' => $linkedEntityType,
            'linked_entity_id' => $linkedEntityUuid->toBinary(),
            'status' => null,
            'raw_payload' => $rawPayload ? json_encode($rawPayload) : null,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        return (string)$id;
    }

    public function updateMappingRaw(string $providerCode, string $type, string $externalId, array $payload): void
    {
        $this->conn->update('integration', [
            'raw_payload' => json_encode($payload), 
            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')
        ], [
            'provider_code' => $providerCode,
            'type' => $type, 
            'external_id' => $externalId
        ]);
    }

    public function findMapping(string $providerCode, string $type, string $externalId): ?array
    {
        $row = $this->conn->fetchAssociative(
            'SELECT * FROM integration WHERE provider_code = ? AND type = ? AND external_id = ?', 
            [$providerCode, $type, $externalId]
        );
        return $row ?: null;
    }

    public function findMappingByLinkedEntity(string $providerCode, string $type, string $linkedEntityType, string $linkedEntityId): ?array
    {
        $linkedEntityUuid = Uuid::fromString($linkedEntityId);
        
        return $this->conn->fetchAssociative(
            'SELECT * FROM integration WHERE provider_code = ? AND type = ? AND linked_entity_type = ? AND linked_entity_id = ?',
            [$providerCode, $type, $linkedEntityType, $linkedEntityUuid->toBinary()]
        ) ?: null;
    }
}
