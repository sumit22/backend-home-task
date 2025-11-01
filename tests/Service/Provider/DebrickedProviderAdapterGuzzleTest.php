<?php

namespace App\Tests\Service\Provider;

use App\Entity\Repository;
use App\Entity\RepositoryScan;
use App\Service\ExternalMappingService;
use App\Service\Provider\DebrickedAuthServiceInterface;
use App\Service\Provider\DebrickedProviderAdapterGuzzle;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class DebrickedProviderAdapterGuzzleTest extends TestCase
{
    private Client&MockObject $httpClient;
    private ExternalMappingService&MockObject $mappingService;
    private LoggerInterface&MockObject $logger;
    private DebrickedAuthServiceInterface&MockObject $authService;
    private DebrickedProviderAdapterGuzzle $adapter;
    private string $baseUrl = 'https://debricked.com/api';

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(Client::class);
        $this->mappingService = $this->createMock(ExternalMappingService::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->authService = $this->createMock(DebrickedAuthServiceInterface::class);

        $this->adapter = new DebrickedProviderAdapterGuzzle(
            $this->httpClient,
            $this->mappingService,
            $this->logger,
            $this->authService,
            $this->baseUrl
        );
    }

    public function testProviderCode(): void
    {
        $this->assertSame('debricked', $this->adapter->providerCode());
    }

    public function testUploadAndCreateScanWithSingleFile(): void
    {
        $repo = new Repository();
        $repo->setName('test-repo');
        $repo->setUrl('https://github.com/test/repo');

        $scan = new RepositoryScan();
        $scan->setRepository($repo);
        $scan->setBranch('main');

        // Create a temporary test file
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tmpFile, 'test content');
        $localPaths = [$tmpFile];

        $jwt = 'test-jwt-token';

        $this->authService
            ->expects($this->once())
            ->method('getJwtToken')
            ->willReturn($jwt);

        // Mock upload response
        $uploadResponseBody = json_encode([
            'ciUploadId' => 'upload-123',
            'files' => [
                ['dependencyFileId' => 'file-456', 'name' => basename($tmpFile)]
            ]
        ]);
        $uploadResponse = new Response(200, [], $uploadResponseBody);

        // Mock finish response (204 No Content)
        $finishResponse = new Response(204);

        $this->httpClient
            ->expects($this->exactly(2))
            ->method('post')
            ->willReturnCallback(function ($url, $options) use ($uploadResponse, $finishResponse, $jwt, $tmpFile) {
                if (strpos($url, '/uploads/dependencies/files') !== false) {
                    $this->assertArrayHasKey('headers', $options);
                    $this->assertSame("Bearer {$jwt}", $options['headers']['Authorization']);
                    $this->assertArrayHasKey('multipart', $options);
                    return $uploadResponse;
                } elseif (strpos($url, '/finishes/dependencies/files/uploads') !== false) {
                    $this->assertArrayHasKey('json', $options);
                    $this->assertSame('upload-123', $options['json']['ciUploadId']);
                    return $finishResponse;
                }
                throw new \RuntimeException('Unexpected request');
            });

        $this->mappingService
            ->expects($this->exactly(2))
            ->method('createMapping')
            ->willReturnCallback(function ($providerCode, $type, $externalId, $entityType, $entityId, $metadata) {
                $this->assertSame('debricked', $providerCode);
                if ($type === 'file') {
                    $this->assertSame('file-456', $externalId);
                    $this->assertSame('FilesInScan', $entityType);
                    return 'mapping-id-1';
                } elseif ($type === 'ci_upload') {
                    $this->assertSame('upload-123', $externalId);
                    $this->assertSame('RepositoryScan', $entityType);
                    return 'mapping-id-2';
                }
                return 'mapping-id-default';
            });

        $result = $this->adapter->uploadAndCreateScan($scan, $localPaths);

        $this->assertArrayHasKey('ciUploadId', $result);
        $this->assertSame('upload-123', $result['ciUploadId']);
        $this->assertArrayHasKey('provider_file_ids', $result);
        $this->assertSame(['file-456'], $result['provider_file_ids']);
        $this->assertSame('finished', $result['status']);

        // Cleanup
        unlink($tmpFile);
    }

    public function testUploadAndCreateScanWithMultipleFiles(): void
    {
        $repo = new Repository();
        $repo->setName('multi-file-repo');

        $scan = new RepositoryScan();
        $scan->setRepository($repo);

        // Create temporary test files
        $tmpFile1 = tempnam(sys_get_temp_dir(), 'test1_');
        $tmpFile2 = tempnam(sys_get_temp_dir(), 'test2_');
        file_put_contents($tmpFile1, 'content1');
        file_put_contents($tmpFile2, 'content2');
        $localPaths = [$tmpFile1, $tmpFile2];

        $jwt = 'multi-jwt-token';

        $this->authService
            ->expects($this->once())
            ->method('getJwtToken')
            ->willReturn($jwt);

        // Mock upload responses for both files
        $uploadResponse1 = new Response(200, [], json_encode([
            'ciUploadId' => 'upload-multi-123',
            'files' => [['dependencyFileId' => 'file-001']]
        ]));

        $uploadResponse2 = new Response(200, [], json_encode([
            'ciUploadId' => 'upload-multi-123',
            'files' => [['dependencyFileId' => 'file-002']]
        ]));

        $finishResponse = new Response(200, [], json_encode(['success' => true]));

        $callCount = 0;
        $this->httpClient
            ->expects($this->exactly(3))
            ->method('post')
            ->willReturnCallback(function () use (&$callCount, $uploadResponse1, $uploadResponse2, $finishResponse) {
                $callCount++;
                if ($callCount === 1) return $uploadResponse1;
                if ($callCount === 2) return $uploadResponse2;
                return $finishResponse;
            });

        $this->mappingService
            ->expects($this->exactly(3)) // 2 files + 1 scan mapping
            ->method('createMapping');

        $result = $this->adapter->uploadAndCreateScan($scan, $localPaths);

        $this->assertSame('upload-multi-123', $result['ciUploadId']);
        $this->assertCount(2, $result['provider_file_ids']);
        $this->assertContains('file-001', $result['provider_file_ids']);
        $this->assertContains('file-002', $result['provider_file_ids']);

        // Cleanup
        unlink($tmpFile1);
        unlink($tmpFile2);
    }

    public function testUploadAndCreateScanWithCustomOptions(): void
    {
        $repo = new Repository();
        $repo->setName('default-repo');

        $scan = new RepositoryScan();
        $scan->setRepository($repo);

        $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tmpFile, 'test');
        $localPaths = [$tmpFile];

        $options = [
            'repositoryName' => 'custom-repo',
            'commitName' => 'abc123',
        ];

        $jwt = 'jwt-custom';

        $this->authService
            ->expects($this->once())
            ->method('getJwtToken')
            ->willReturn($jwt);

        $uploadResponse = new Response(200, [], json_encode([
            'ciUploadId' => 'custom-upload',
            'files' => [['id' => 'file-custom']]
        ]));

        $finishResponse = new Response(204);

        $this->httpClient
            ->expects($this->exactly(2))
            ->method('post')
            ->willReturnOnConsecutiveCalls($uploadResponse, $finishResponse);

        $this->mappingService
            ->expects($this->exactly(2))
            ->method('createMapping');

        $result = $this->adapter->uploadAndCreateScan($scan, $localPaths, $options);

        $this->assertSame('custom-upload', $result['ciUploadId']);

        unlink($tmpFile);
    }

    public function testUploadAndCreateScanUsesUploadIdFallback(): void
    {
        $repo = new Repository();
        $repo->setName('fallback-repo');

        $scan = new RepositoryScan();
        $scan->setRepository($repo);

        $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tmpFile, 'test');
        $localPaths = [$tmpFile];

        $this->authService
            ->expects($this->once())
            ->method('getJwtToken')
            ->willReturn('jwt-fallback');

        // Response uses 'uploadId' instead of 'ciUploadId'
        $uploadResponse = new Response(200, [], json_encode([
            'uploadId' => 'fallback-upload-789',
            'files' => [['id' => 'file-fallback']]
        ]));

        $finishResponse = new Response(204);

        $this->httpClient
            ->expects($this->exactly(2))
            ->method('post')
            ->willReturnOnConsecutiveCalls($uploadResponse, $finishResponse);

        $this->mappingService
            ->expects($this->exactly(2))
            ->method('createMapping');

        $result = $this->adapter->uploadAndCreateScan($scan, $localPaths);

        $this->assertSame('fallback-upload-789', $result['ciUploadId']);

        unlink($tmpFile);
    }

    public function testUploadAndCreateScanReusesExistingMapping(): void
    {
        $repo = new Repository();
        $repo->setName('existing-repo');

        $scan = new RepositoryScan();
        $scan->setRepository($repo);

        $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tmpFile, 'test');
        $localPaths = [$tmpFile];

        $this->authService
            ->expects($this->once())
            ->method('getJwtToken')
            ->willReturn('jwt-existing');

        // Upload response without ciUploadId
        $uploadResponse = new Response(200, [], json_encode([
            'files' => [['id' => 'file-existing']]
        ]));

        $finishResponse = new Response(204);

        $this->httpClient
            ->expects($this->exactly(2))
            ->method('post')
            ->willReturnOnConsecutiveCalls($uploadResponse, $finishResponse);

        // Mock existing mapping
        $this->mappingService
            ->expects($this->once())
            ->method('findMapping')
            ->with('debricked', 'ci_upload', $scan->getId())
            ->willReturn(['external_id' => 'existing-upload-999']);

        $this->mappingService
            ->expects($this->exactly(2))
            ->method('createMapping');

        $result = $this->adapter->uploadAndCreateScan($scan, $localPaths);

        $this->assertSame('existing-upload-999', $result['ciUploadId']);

        unlink($tmpFile);
    }

    public function testUploadAndCreateScanThrowsExceptionWhenNoUploadId(): void
    {
        $repo = new Repository();
        $repo->setName('error-repo');

        $scan = new RepositoryScan();
        $scan->setRepository($repo);

        $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tmpFile, 'test');
        $localPaths = [$tmpFile];

        $this->authService
            ->expects($this->once())
            ->method('getJwtToken')
            ->willReturn('jwt-error');

        // Upload response without any upload ID
        $uploadResponse = new Response(200, [], json_encode([
            'files' => [['id' => 'file-error']]
        ]));

        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->willReturn($uploadResponse);

        // No existing mapping found
        $this->mappingService
            ->expects($this->once())
            ->method('findMapping')
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Debricked did not return ciUploadId on upload');

        $this->adapter->uploadAndCreateScan($scan, $localPaths);

        unlink($tmpFile);
    }

    public function testUploadAndCreateScanThrowsExceptionWhenFileNotFound(): void
    {
        $repo = new Repository();
        $repo->setName('missing-file-repo');

        $scan = new RepositoryScan();
        $scan->setRepository($repo);

        $localPaths = ['/nonexistent/file.txt'];

        $this->authService
            ->expects($this->once())
            ->method('getJwtToken')
            ->willReturn('jwt-test');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('File does not exist');

        $this->adapter->uploadAndCreateScan($scan, $localPaths);
    }

    public function testUploadAndCreateScanHandlesGuzzleException(): void
    {
        $repo = new Repository();
        $repo->setName('guzzle-error-repo');

        $scan = new RepositoryScan();
        $scan->setRepository($repo);

        $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tmpFile, 'test');
        $localPaths = [$tmpFile];

        $this->authService
            ->expects($this->once())
            ->method('getJwtToken')
            ->willReturn('jwt-test');

        $request = new Request('POST', 'http://example.com');
        $exception = new RequestException('Network error', $request);

        $this->httpClient
            ->expects($this->once())
            ->method('post')
            ->willThrowException($exception);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to upload file');

        $this->adapter->uploadAndCreateScan($scan, $localPaths);

        unlink($tmpFile);
    }

    public function testUploadAndCreateScanWithFileMapping(): void
    {
        $repo = new Repository();
        $repo->setName('file-mapping-repo');

        $scan = new RepositoryScan();
        $scan->setRepository($repo);

        $tmpFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tmpFile, 'test');
        $localPaths = [$tmpFile];

        $options = [
            'fileMapping' => [
                $tmpFile => 'composer.lock' // Map temp file to original name
            ]
        ];

        $this->authService
            ->expects($this->once())
            ->method('getJwtToken')
            ->willReturn('jwt-test');

        $uploadResponse = new Response(200, [], json_encode([
            'ciUploadId' => 'upload-123',
            'files' => [['dependencyFileId' => 'file-456']]
        ]));

        $finishResponse = new Response(204);

        $this->httpClient
            ->expects($this->exactly(2))
            ->method('post')
            ->willReturnOnConsecutiveCalls($uploadResponse, $finishResponse);

        $this->mappingService
            ->expects($this->exactly(2))
            ->method('createMapping');

        $result = $this->adapter->uploadAndCreateScan($scan, $localPaths, $options);

        $this->assertSame('upload-123', $result['ciUploadId']);

        unlink($tmpFile);
    }

    public function testPollScanStatusSuccess(): void
    {
        $ciUploadId = 'test-upload-123';
        $jwt = 'test-jwt';

        $this->authService
            ->expects($this->once())
            ->method('getJwtToken')
            ->willReturn($jwt);

        $responseData = [
            'progress' => 100,
            'vulnerabilitiesFound' => 5,
            'detailsUrl' => 'https://debricked.com/details/123'
        ];

        $response = new Response(200, [], json_encode($responseData));

        $this->httpClient
            ->expects($this->once())
            ->method('get')
            ->with(
                $this->stringContains('/open/ci/upload/status'),
                $this->callback(function ($options) use ($jwt, $ciUploadId) {
                    return $options['headers']['Authorization'] === "Bearer {$jwt}"
                        && $options['query']['ciUploadId'] === $ciUploadId;
                })
            )
            ->willReturn($response);

        $result = $this->adapter->pollScanStatus($ciUploadId);

        $this->assertSame(100, $result['progress']);
        $this->assertTrue($result['scan_completed']);
        $this->assertSame(5, $result['vulnerabilities_found']);
        $this->assertSame('https://debricked.com/details/123', $result['details_url']);
    }

    public function testPollScanStatusInProgress(): void
    {
        $ciUploadId = 'in-progress-123';
        $jwt = 'test-jwt';

        $this->authService
            ->expects($this->once())
            ->method('getJwtToken')
            ->willReturn($jwt);

        $responseData = [
            'progress' => 50,
            'vulnerabilitiesFound' => 0
        ];

        $response = new Response(200, [], json_encode($responseData));

        $this->httpClient
            ->expects($this->once())
            ->method('get')
            ->willReturn($response);

        $result = $this->adapter->pollScanStatus($ciUploadId);

        $this->assertSame(50, $result['progress']);
        $this->assertFalse($result['scan_completed']);
        $this->assertSame(0, $result['vulnerabilities_found']);
    }

    public function testPollScanStatusHandlesGuzzleException(): void
    {
        $ciUploadId = 'error-upload-123';
        $jwt = 'test-jwt';

        $this->authService
            ->expects($this->once())
            ->method('getJwtToken')
            ->willReturn($jwt);

        $request = new Request('GET', 'http://example.com');
        $exception = new RequestException('Connection timeout', $request);

        $this->httpClient
            ->expects($this->once())
            ->method('get')
            ->willThrowException($exception);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to poll scan status');

        $this->adapter->pollScanStatus($ciUploadId);
    }

    public function testNormalizeScanResultWithCompletedScan(): void
    {
        $raw = [
            'scanCompleted' => true,
            'vulnerableDependencies' => [
                ['name' => 'symfony/http-kernel', 'cve' => 'CVE-2023-1234'],
                ['name' => 'laravel/framework', 'cve' => 'CVE-2023-5678']
            ],
            'vulnerabilitiesFound' => 2
        ];

        $result = $this->adapter->normalizeScanResult($raw);

        $this->assertSame('completed', $result['status']);
        $this->assertCount(2, $result['vulnerabilities']);
        $this->assertSame(2, $result['vulnerability_count']);
    }

    public function testNormalizeScanResultWithRunningScan(): void
    {
        $raw = [
            'scanCompleted' => false,
            'vulnerabilitiesFound' => 0
        ];

        $result = $this->adapter->normalizeScanResult($raw);

        $this->assertSame('running', $result['status']);
        $this->assertSame(0, $result['vulnerability_count']);
    }

    public function testNormalizeScanResultWithMissingFields(): void
    {
        $raw = [
            'scanCompleted' => true
        ];

        $result = $this->adapter->normalizeScanResult($raw);

        $this->assertSame('completed', $result['status']);
        $this->assertSame([], $result['vulnerabilities']);
        $this->assertSame(0, $result['vulnerability_count']);
    }
}
