<?php

namespace App\Tests\Service;

use App\Service\ExternalMappingService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class ExternalMappingServiceTest extends TestCase
{
    private Connection&MockObject $connection;
    private ExternalMappingService $service;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->service = new ExternalMappingService($this->connection);
    }

    public function testCreateMapping(): void
    {
        $providerCode = 'debricked';
        $type = 'scan';
        $externalId = '12345';
        $linkedEntityType = 'repository_scan';
        $linkedEntityId = '01JAB1C2D3E4F5G6H7J8K9M0N1';
        $rawPayload = ['status' => 'pending'];

        $this->connection->expects($this->once())
            ->method('insert')
            ->with(
                'integration',
                $this->callback(function ($data) use ($providerCode, $type, $externalId, $linkedEntityType) {
                    return $data['provider_code'] === $providerCode
                        && $data['type'] === $type
                        && $data['external_id'] === $externalId
                        && $data['linked_entity_type'] === $linkedEntityType
                        && isset($data['id'])
                        && isset($data['created_at'])
                        && isset($data['updated_at']);
                })
            );

        $mappingId = $this->service->createMapping(
            $providerCode,
            $type,
            $externalId,
            $linkedEntityType,
            $linkedEntityId,
            $rawPayload
        );

        $this->assertNotEmpty($mappingId);
        $this->assertTrue(Uuid::isValid($mappingId));
    }

    public function testCreateMappingWithoutRawPayload(): void
    {
        $providerCode = 'debricked';
        $type = 'scan';
        $externalId = '12345';
        $linkedEntityType = 'repository_scan';
        $linkedEntityId = '01JAB1C2D3E4F5G6H7J8K9M0N1';

        $this->connection->expects($this->once())
            ->method('insert')
            ->with(
                'integration',
                $this->callback(function ($data) {
                    return $data['raw_payload'] === null;
                })
            );

        $mappingId = $this->service->createMapping(
            $providerCode,
            $type,
            $externalId,
            $linkedEntityType,
            $linkedEntityId,
            null
        );

        $this->assertTrue(Uuid::isValid($mappingId));
    }

    public function testUpdateMappingRaw(): void
    {
        $providerCode = 'debricked';
        $type = 'scan';
        $externalId = '12345';
        $payload = ['status' => 'completed', 'result' => 'success'];

        $this->connection->expects($this->once())
            ->method('update')
            ->with(
                'integration',
                $this->callback(function ($data) use ($payload) {
                    return $data['raw_payload'] === json_encode($payload)
                        && isset($data['updated_at']);
                }),
                [
                    'provider_code' => $providerCode,
                    'type' => $type,
                    'external_id' => $externalId
                ]
            );

        $this->service->updateMappingRaw($providerCode, $type, $externalId, $payload);
    }

    public function testFindMappingFound(): void
    {
        $providerCode = 'debricked';
        $type = 'scan';
        $externalId = '12345';
        $expectedRow = [
            'id' => 'some-uuid-binary',
            'provider_code' => $providerCode,
            'type' => $type,
            'external_id' => $externalId,
            'linked_entity_type' => 'repository_scan',
            'linked_entity_id' => 'linked-uuid-binary',
            'status' => 'completed',
            'raw_payload' => '{"status":"completed"}',
            'created_at' => '2025-11-01 10:00:00',
            'updated_at' => '2025-11-01 10:05:00',
        ];

        $this->connection->expects($this->once())
            ->method('fetchAssociative')
            ->with(
                'SELECT * FROM integration WHERE provider_code = ? AND type = ? AND external_id = ?',
                [$providerCode, $type, $externalId]
            )
            ->willReturn($expectedRow);

        $result = $this->service->findMapping($providerCode, $type, $externalId);

        $this->assertSame($expectedRow, $result);
    }

    public function testFindMappingNotFound(): void
    {
        $providerCode = 'debricked';
        $type = 'scan';
        $externalId = 'non-existent';

        $this->connection->expects($this->once())
            ->method('fetchAssociative')
            ->with(
                'SELECT * FROM integration WHERE provider_code = ? AND type = ? AND external_id = ?',
                [$providerCode, $type, $externalId]
            )
            ->willReturn(false);

        $result = $this->service->findMapping($providerCode, $type, $externalId);

        $this->assertNull($result);
    }

    public function testFindMappingByLinkedEntityFound(): void
    {
        $providerCode = 'debricked';
        $type = 'scan';
        $linkedEntityType = 'repository_scan';
        $linkedEntityId = '01JAB1C2D3E4F5G6H7J8K9M0N1';
        $expectedRow = [
            'id' => 'some-uuid-binary',
            'provider_code' => $providerCode,
            'type' => $type,
            'external_id' => '12345',
            'linked_entity_type' => $linkedEntityType,
            'linked_entity_id' => 'linked-uuid-binary',
        ];

        $this->connection->expects($this->once())
            ->method('fetchAssociative')
            ->with(
                'SELECT * FROM integration WHERE provider_code = ? AND type = ? AND linked_entity_type = ? AND linked_entity_id = ?',
                $this->callback(function ($params) use ($providerCode, $type, $linkedEntityType) {
                    return $params[0] === $providerCode
                        && $params[1] === $type
                        && $params[2] === $linkedEntityType
                        && is_string($params[3]); // Binary UUID
                })
            )
            ->willReturn($expectedRow);

        $result = $this->service->findMappingByLinkedEntity(
            $providerCode,
            $type,
            $linkedEntityType,
            $linkedEntityId
        );

        $this->assertSame($expectedRow, $result);
    }

    public function testFindMappingByLinkedEntityNotFound(): void
    {
        $providerCode = 'debricked';
        $type = 'scan';
        $linkedEntityType = 'repository_scan';
        $linkedEntityId = '01JAB1C2D3E4F5G6H7J8K9M0N1';

        $this->connection->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn(false);

        $result = $this->service->findMappingByLinkedEntity(
            $providerCode,
            $type,
            $linkedEntityType,
            $linkedEntityId
        );

        $this->assertNull($result);
    }
}
