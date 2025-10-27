<?php

namespace App\Tests\Service\Provider;

use App\Service\Provider\DebrickedClient;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class DebrickedClientTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private DebrickedClient $client;
    private string $token = 'test-jwt-token';
    private string $baseUrl = 'https://debricked.com/api';

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->client = new DebrickedClient($this->httpClient, $this->token, $this->baseUrl);
    }

    public function testProviderCode(): void
    {
        $this->assertSame('debricked', $this->client->providerCode());
    }

    public function testConstructorTrimsTrailingSlashFromBaseUrl(): void
    {
        $clientWithSlash = new DebrickedClient($this->httpClient, $this->token, 'https://debricked.com/api/');
        
        // We'll verify by checking the URL used in a request
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn(['id' => 'file_123']);

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://debricked.com/api/v1/files', // No double slash
                $this->anything()
            )
            ->willReturn($response);

        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, 'test content');
        
        try {
            $clientWithSlash->uploadFileStream($tempFile);
        } finally {
            unlink($tempFile);
        }
    }

    public function testUploadFileStreamSuccess(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, 'test file content');

        $expectedResponse = [
            'id' => 'file_abc123',
            'name' => 'composer.lock',
            'size' => 1024,
        ];

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->with(false)->willReturn($expectedResponse);

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://debricked.com/api/v1/files',
                $this->callback(function ($options) use ($tempFile) {
                    // Verify headers
                    $this->assertArrayHasKey('headers', $options);
                    $this->assertArrayHasKey('Authorization', $options['headers']);
                    $this->assertSame('Bearer test-jwt-token', $options['headers']['Authorization']);

                    // Verify body contains file
                    $this->assertArrayHasKey('body', $options);
                    $this->assertArrayHasKey('file', $options['body']);
                    
                    return true;
                })
            )
            ->willReturn($response);

        $result = $this->client->uploadFileStream($tempFile);

        $this->assertSame($expectedResponse, $result);
        $this->assertArrayHasKey('id', $result);
        $this->assertSame('file_abc123', $result['id']);

        unlink($tempFile);
    }

    public function testUploadFileStreamWithMetadata(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, 'test content');

        $meta = [
            'repository_id' => '12345',
            'branch' => 'main',
        ];

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(201);
        $response->method('toArray')->willReturn(['id' => 'file_xyz']);

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://debricked.com/api/v1/files',
                $this->anything()
            )
            ->willReturn($response);

        $result = $this->client->uploadFileStream($tempFile, $meta);

        $this->assertArrayHasKey('id', $result);
        $this->assertSame('file_xyz', $result['id']);

        unlink($tempFile);
    }

    public function testUploadFileStreamThrowsExceptionOnError(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, 'test content');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(400);
        $response->method('toArray')->willReturn(['error' => 'Bad request']);

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Debricked upload failed: 400');

        try {
            $this->client->uploadFileStream($tempFile);
        } finally {
            unlink($tempFile);
        }
    }

    public function testUploadFileStreamThrowsExceptionOn500Error(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, 'test content');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(500);
        $response->method('toArray')->willReturn(['error' => 'Internal server error']);

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Debricked upload failed: 500');

        try {
            $this->client->uploadFileStream($tempFile);
        } finally {
            unlink($tempFile);
        }
    }

    public function testCreateScanSuccess(): void
    {
        $providerFileIds = ['file_abc', 'file_def', 'file_ghi'];
        $options = [
            'branch' => 'main',
            'repository' => 'my-repo',
        ];

        $expectedResponse = [
            'id' => 'scan_123456',
            'status' => 'pending',
            'created_at' => '2025-10-28T10:00:00Z',
        ];

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->with(false)->willReturn($expectedResponse);

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://debricked.com/api/v1/scans',
                $this->callback(function ($options) use ($providerFileIds) {
                    // Verify headers
                    $this->assertArrayHasKey('headers', $options);
                    $this->assertSame('Bearer test-jwt-token', $options['headers']['Authorization']);
                    $this->assertSame('application/json', $options['headers']['Content-Type']);

                    // Verify JSON body
                    $this->assertArrayHasKey('json', $options);
                    $this->assertArrayHasKey('file_ids', $options['json']);
                    $this->assertSame($providerFileIds, $options['json']['file_ids']);
                    $this->assertSame('main', $options['json']['branch']);
                    $this->assertSame('my-repo', $options['json']['repository']);
                    
                    return true;
                })
            )
            ->willReturn($response);

        $result = $this->client->createScan($providerFileIds, $options);

        $this->assertSame($expectedResponse, $result);
        $this->assertArrayHasKey('id', $result);
        $this->assertSame('scan_123456', $result['id']);
        $this->assertSame('pending', $result['status']);
    }

    public function testCreateScanWithMinimalOptions(): void
    {
        $providerFileIds = ['file_only'];

        $expectedResponse = [
            'id' => 'scan_minimal',
            'status' => 'created',
        ];

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn($expectedResponse);

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://debricked.com/api/v1/scans',
                $this->callback(function ($options) use ($providerFileIds) {
                    $this->assertArrayHasKey('json', $options);
                    $this->assertSame($providerFileIds, $options['json']['file_ids']);
                    // Branch and repository should be null when not provided
                    $this->assertNull($options['json']['branch']);
                    $this->assertNull($options['json']['repository']);
                    
                    return true;
                })
            )
            ->willReturn($response);

        $result = $this->client->createScan($providerFileIds);

        $this->assertSame($expectedResponse, $result);
    }

    public function testCreateScanWithEmptyFileIds(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['id' => 'scan_empty', 'status' => 'invalid']);

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://debricked.com/api/v1/scans',
                $this->callback(function ($options) {
                    $this->assertSame([], $options['json']['file_ids']);
                    return true;
                })
            )
            ->willReturn($response);

        $result = $this->client->createScan([]);

        $this->assertArrayHasKey('id', $result);
    }

    public function testGetScanResultSuccess(): void
    {
        $providerScanId = 'scan_abc123';

        $expectedResponse = [
            'id' => 'scan_abc123',
            'status' => 'completed',
            'vulnerabilities' => [
                ['id' => 'CVE-2023-1234', 'severity' => 'high'],
                ['id' => 'CVE-2023-5678', 'severity' => 'medium'],
            ],
            'finished_at' => '2025-10-28T11:00:00Z',
        ];

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->with(false)->willReturn($expectedResponse);

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                'https://debricked.com/api/v1/scans/scan_abc123',
                $this->callback(function ($options) {
                    // Verify authorization header
                    $this->assertArrayHasKey('headers', $options);
                    $this->assertSame('Bearer test-jwt-token', $options['headers']['Authorization']);
                    
                    return true;
                })
            )
            ->willReturn($response);

        $result = $this->client->getScanResult($providerScanId);

        $this->assertSame($expectedResponse, $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertSame('completed', $result['status']);
        $this->assertArrayHasKey('vulnerabilities', $result);
        $this->assertCount(2, $result['vulnerabilities']);
    }

    public function testGetScanResultWithPendingStatus(): void
    {
        $providerScanId = 'scan_pending_123';

        $expectedResponse = [
            'id' => 'scan_pending_123',
            'status' => 'pending',
            'progress' => 45,
        ];

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn($expectedResponse);

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with('GET', 'https://debricked.com/api/v1/scans/scan_pending_123', $this->anything())
            ->willReturn($response);

        $result = $this->client->getScanResult($providerScanId);

        $this->assertSame('pending', $result['status']);
        $this->assertSame(45, $result['progress']);
    }

    public function testGetScanResultWithFailedStatus(): void
    {
        $providerScanId = 'scan_failed_456';

        $expectedResponse = [
            'id' => 'scan_failed_456',
            'status' => 'failed',
            'error' => 'Analysis timeout',
        ];

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn($expectedResponse);

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with('GET', 'https://debricked.com/api/v1/scans/scan_failed_456', $this->anything())
            ->willReturn($response);

        $result = $this->client->getScanResult($providerScanId);

        $this->assertSame('failed', $result['status']);
        $this->assertArrayHasKey('error', $result);
    }
}
