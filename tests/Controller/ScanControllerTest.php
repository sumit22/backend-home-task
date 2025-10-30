<?php

namespace App\Tests\Controller;

use App\Entity\Repository;
use App\Entity\RepositoryScan;
use App\Entity\FilesInScan;
use App\Entity\Provider;
use App\Contract\Service\ScanServiceInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ScanControllerTest extends WebTestCase
{
    public function testCreateScanSuccessfully()
    {
        $client = static::createClient();

        $repo = new Repository();
        $repo->setName('test-repo');
        $repo->setUrl('https://github.com/test/repo');

        $scan = new RepositoryScan();
        $scan->setRepository($repo);
        $scan->setBranch('main');
        $scan->setProviderCode('snyk');
        $scan->setRequestedBy('test-user');
        $scan->setStatus('pending');

        $scanService = $this->createMock(ScanServiceInterface::class);
        $scanService->expects($this->once())
            ->method('createScan')
            ->with('repo-id', 'main', 'snyk', 'test-user')
            ->willReturn($scan);

        $client->getContainer()->set(ScanServiceInterface::class, $scanService);

        $client->request(
            'POST',
            '/api/repositories/repo-id/scans',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'branch' => 'main',
                'provider' => 'snyk',
                'requested_by' => 'test-user'
            ])
        );

        $this->assertResponseStatusCodeSame(201);
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('repository_id', $data);
        $this->assertArrayHasKey('branch', $data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('provider_code', $data);
        $this->assertEquals('main', $data['branch']);
        $this->assertEquals('pending', $data['status']);
    }

    public function testCreateScanWithMinimalData()
    {
        $client = static::createClient();

        $repo = new Repository();
        $repo->setName('test-repo');

        $scan = new RepositoryScan();
        $scan->setRepository($repo);
        $scan->setStatus('pending');

        $scanService = $this->createMock(ScanServiceInterface::class);
        $scanService->expects($this->once())
            ->method('createScan')
            ->with('repo-id', null, null, null)
            ->willReturn($scan);

        $client->getContainer()->set(ScanServiceInterface::class, $scanService);

        $client->request(
            'POST',
            '/api/repositories/repo-id/scans',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{}'
        );

        $this->assertResponseStatusCodeSame(201);
    }

    public function testCreateScanWithInvalidRepository()
    {
        $client = static::createClient();

        $scanService = $this->createMock(ScanServiceInterface::class);
        $scanService->expects($this->once())
            ->method('createScan')
            ->with('non-existent-id', null, null, null)
            ->willThrowException(new \InvalidArgumentException('Repository not found'));

        $client->getContainer()->set(ScanServiceInterface::class, $scanService);

        $client->request(
            'POST',
            '/api/repositories/non-existent-id/scans',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{}'
        );

        $this->assertResponseStatusCodeSame(400);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Repository not found', $data['error']);
    }

    public function testCreateScanWithInvalidProvider()
    {
        $client = static::createClient();

        $scanService = $this->createMock(ScanServiceInterface::class);
        $scanService->expects($this->once())
            ->method('createScan')
            ->with('repo-id', 'main', 'invalid-provider', null)
            ->willThrowException(new \InvalidArgumentException('Provider not found'));

        $client->getContainer()->set(ScanServiceInterface::class, $scanService);

        $client->request(
            'POST',
            '/api/repositories/repo-id/scans',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['branch' => 'main', 'provider' => 'invalid-provider'])
        );

        $this->assertResponseStatusCodeSame(400);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Provider not found', $data['error']);
    }

    public function testUploadFilesSuccessfully()
    {
        $client = static::createClient();

        $file1 = new FilesInScan();
        $file1->setFileName('package.json');
        $file1->setFilePath('uploads/scan-id/package.json');
        $file1->setSize(1024);
        $file1->setStatus('stored');

        $file2 = new FilesInScan();
        $file2->setFileName('composer.lock');
        $file2->setFilePath('uploads/scan-id/composer.lock');
        $file2->setSize(2048);
        $file2->setStatus('stored');

        $scanService = $this->createMock(ScanServiceInterface::class);
        $scanService->expects($this->once())
            ->method('handleUploadedFiles')
            ->with('scan-id', $this->isType('array'), false)
            ->willReturn([$file1, $file2]);

        $client->getContainer()->set(ScanServiceInterface::class, $scanService);

        // Create temporary files for upload
        $tmpFile1 = tempnam(sys_get_temp_dir(), 'test1');
        $tmpFile2 = tempnam(sys_get_temp_dir(), 'test2');
        file_put_contents($tmpFile1, '{"name": "test"}');
        file_put_contents($tmpFile2, 'test content');

        $uploadedFile1 = new UploadedFile($tmpFile1, 'package.json', 'application/json', null, true);
        $uploadedFile2 = new UploadedFile($tmpFile2, 'composer.lock', 'text/plain', null, true);

        $client->request(
            'POST',
            '/api/scans/scan-id/files',
            ['upload_complete' => 'false'],
            ['files' => [$uploadedFile1, $uploadedFile2]],
            []
        );

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('uploaded', $data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertCount(2, $data['uploaded']);
        $this->assertEquals('pending', $data['status']);
        $this->assertEquals('Files stored successfully', $data['message']);
        $this->assertEquals('package.json', $data['uploaded'][0]['name']);
        $this->assertEquals('composer.lock', $data['uploaded'][1]['name']);
    }

    public function testUploadFilesWithUploadComplete()
    {
        $client = static::createClient();

        $file1 = new FilesInScan();
        $file1->setFileName('package.json');
        $file1->setFilePath('uploads/scan-id/package.json');
        $file1->setSize(1024);
        $file1->setStatus('stored');

        $scanService = $this->createMock(ScanServiceInterface::class);
        $scanService->expects($this->once())
            ->method('handleUploadedFiles')
            ->with('scan-id', $this->isType('array'), true)
            ->willReturn([$file1]);

        $client->getContainer()->set(ScanServiceInterface::class, $scanService);

        $tmpFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tmpFile, '{"name": "test"}');
        $uploadedFile = new UploadedFile($tmpFile, 'package.json', 'application/json', null, true);

        $client->request(
            'POST',
            '/api/scans/scan-id/files',
            ['upload_complete' => 'true'],
            ['files' => [$uploadedFile]],
            []
        );

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertEquals('uploaded', $data['status']);
        $this->assertEquals('Upload complete, scan marked ready', $data['message']);
    }

    public function testUploadFilesWithUploadCompleteAsQueryParam()
    {
        $client = static::createClient();

        $file1 = new FilesInScan();
        $file1->setFileName('package.json');
        $file1->setFilePath('uploads/scan-id/package.json');
        $file1->setSize(1024);
        $file1->setStatus('stored');

        $scanService = $this->createMock(ScanServiceInterface::class);
        $scanService->expects($this->once())
            ->method('handleUploadedFiles')
            ->with('scan-id', $this->isType('array'), true)
            ->willReturn([$file1]);

        $client->getContainer()->set(ScanServiceInterface::class, $scanService);

        $tmpFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tmpFile, '{"name": "test"}');
        $uploadedFile = new UploadedFile($tmpFile, 'package.json', 'application/json', null, true);

        $client->request(
            'POST',
            '/api/scans/scan-id/files?upload_complete=1',
            [],
            ['files' => [$uploadedFile]],
            []
        );

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertEquals('uploaded', $data['status']);
    }

    public function testUploadFilesWithInvalidScanId()
    {
        $client = static::createClient();

        $scanService = $this->createMock(ScanServiceInterface::class);
        $scanService->expects($this->once())
            ->method('handleUploadedFiles')
            ->willThrowException(new \InvalidArgumentException('Scan not found'));

        $client->getContainer()->set(ScanServiceInterface::class, $scanService);

        $tmpFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tmpFile, '{"name": "test"}');
        $uploadedFile = new UploadedFile($tmpFile, 'package.json', 'application/json', null, true);

        $client->request(
            'POST',
            '/api/scans/invalid-id/files',
            [],
            ['files' => [$uploadedFile]],
            []
        );

        $this->assertResponseStatusCodeSame(400);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Scan not found', $data['error']);
    }

    public function testUploadFilesWithNoFiles()
    {
        $client = static::createClient();

        $scanService = $this->createMock(ScanServiceInterface::class);
        $scanService->expects($this->once())
            ->method('handleUploadedFiles')
            ->with('scan-id', [], false)
            ->willThrowException(new \InvalidArgumentException('No files provided'));

        $client->getContainer()->set(ScanServiceInterface::class, $scanService);

        $client->request(
            'POST',
            '/api/scans/scan-id/files',
            [],
            [],
            []
        );

        $this->assertResponseStatusCodeSame(400);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('No files provided', $data['error']);
    }

    public function testUploadFilesWithTooManyFiles()
    {
        $client = static::createClient();

        $scanService = $this->createMock(ScanServiceInterface::class);
        $scanService->expects($this->once())
            ->method('handleUploadedFiles')
            ->willThrowException(new \InvalidArgumentException('Too many files in request'));

        $client->getContainer()->set(ScanServiceInterface::class, $scanService);

        // Create 11 files
        $files = [];
        for ($i = 0; $i < 11; $i++) {
            $tmpFile = tempnam(sys_get_temp_dir(), "test$i");
            file_put_contents($tmpFile, "content $i");
            $files[] = new UploadedFile($tmpFile, "file$i.json", 'application/json', null, true);
        }

        $client->request(
            'POST',
            '/api/scans/scan-id/files',
            [],
            ['files' => $files],
            []
        );

        $this->assertResponseStatusCodeSame(400);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Too many files in request', $data['error']);
    }

    public function testExecuteScanSuccessfully()
    {
        $client = static::createClient();

        $scanService = $this->createMock(ScanServiceInterface::class);
        $scanService->expects($this->once())
            ->method('startProviderScan')
            ->with('scan-id');

        $client->getContainer()->set(ScanServiceInterface::class, $scanService);

        $client->request('POST', '/api/scans/scan-id/execute');

        $this->assertResponseStatusCodeSame(202);
        $data = json_decode($client->getResponse()->getContent(), true);
        
        $this->assertArrayHasKey('scan_id', $data);
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals('scan-id', $data['scan_id']);
        $this->assertEquals('queued', $data['status']);
    }

    public function testExecuteScanWithInvalidScanId()
    {
        $client = static::createClient();

        $scanService = $this->createMock(ScanServiceInterface::class);
        $scanService->expects($this->once())
            ->method('startProviderScan')
            ->with('invalid-id')
            ->willThrowException(new \InvalidArgumentException('Scan not found'));

        $client->getContainer()->set(ScanServiceInterface::class, $scanService);

        $client->request('POST', '/api/scans/invalid-id/execute');

        $this->assertResponseStatusCodeSame(400);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Scan not found', $data['error']);
    }
}
