<?php

namespace App\Tests\Service;

use App\Entity\Repository;
use App\Entity\RepositoryScan;
use App\Entity\FilesInScan;
use App\Entity\Provider;
use App\Message\StartProviderScanMessage;
use App\Service\ScanService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class ScanServiceTest extends TestCase
{
    private $em;
    private $filesystem;
    private $bus;
    private $stateMachine;
    private $repoRepo;
    private $scanRepo;
    private $providerRepo;
    private $filesInScanRepo;
    private ScanService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->filesystem = $this->createMock(FilesystemOperator::class);
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->stateMachine = $this->createMock(\App\Service\ScanStateMachine::class);
        
        $this->repoRepo = $this->createMock(EntityRepository::class);
        $this->scanRepo = $this->createMock(EntityRepository::class);
        $this->providerRepo = $this->createMock(EntityRepository::class);
        $this->filesInScanRepo = $this->createMock(EntityRepository::class);

        // Configure EM->getRepository to return our mocked repositories
        $this->em->method('getRepository')
            ->willReturnCallback(function ($class) {
                if ($class === Repository::class) return $this->repoRepo;
                if ($class === RepositoryScan::class) return $this->scanRepo;
                if ($class === Provider::class) return $this->providerRepo;
                if ($class === FilesInScan::class) return $this->filesInScanRepo;
                return null;
            });

        $this->service = new ScanService($this->em, $this->filesystem, $this->bus, $this->stateMachine);
    }

    public function testCreateScanThrowsExceptionWhenRepositoryNotFound()
    {
        $this->repoRepo->expects($this->once())
            ->method('find')
            ->with('non-existent-id')
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Repository not found');

        $this->service->createScan('non-existent-id');
    }

    public function testCreateScanWithoutProvider()
    {
        $repo = new Repository();
        $repo->setName('test-repo');
        $repo->setUrl('https://github.com/test/repo');

        $this->repoRepo->expects($this->once())
            ->method('find')
            ->with('repo-id')
            ->willReturn($repo);

        $this->em->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(RepositoryScan::class));

        $this->em->expects($this->once())
            ->method('flush');

        $result = $this->service->createScan('repo-id', 'main', null, 'test-user');

        $this->assertInstanceOf(RepositoryScan::class, $result);
        $this->assertEquals($repo, $result->getRepository());
        $this->assertEquals('main', $result->getBranch());
        $this->assertEquals('test-user', $result->getRequestedBy());
        $this->assertNull($result->getProviderCode());
    }

    public function testCreateScanWithProvider()
    {
        $repo = new Repository();
        $repo->setName('test-repo');
        $repo->setUrl('https://github.com/test/repo');

        $provider = new Provider();
        $provider->setCode('snyk');
        $provider->setName('Snyk');

        $this->repoRepo->expects($this->once())
            ->method('find')
            ->with('repo-id')
            ->willReturn($repo);

        $this->providerRepo->expects($this->once())
            ->method('findOneBy')
            ->with(['code' => 'snyk'])
            ->willReturn($provider);

        $this->em->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(RepositoryScan::class));

        $this->em->expects($this->once())
            ->method('flush');

        $result = $this->service->createScan('repo-id', 'main', 'snyk', 'test-user');

        $this->assertInstanceOf(RepositoryScan::class, $result);
        $this->assertEquals('snyk', $result->getProviderCode());
    }

    public function testCreateScanThrowsExceptionWhenProviderNotFound()
    {
        $repo = new Repository();
        $repo->setName('test-repo');

        $this->repoRepo->expects($this->once())
            ->method('find')
            ->with('repo-id')
            ->willReturn($repo);

        $this->providerRepo->expects($this->once())
            ->method('findOneBy')
            ->with(['code' => 'non-existent'])
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Provider not found');

        $this->service->createScan('repo-id', 'main', 'non-existent', 'test-user');
    }

    public function testHandleUploadedFilesThrowsExceptionWhenScanNotFound()
    {
        $this->scanRepo->expects($this->once())
            ->method('find')
            ->with('non-existent-scan')
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Scan not found');

        $this->service->handleUploadedFiles('non-existent-scan', []);
    }

    public function testHandleUploadedFilesThrowsExceptionWhenNoFilesProvided()
    {
        $scan = new RepositoryScan();
        
        $this->scanRepo->expects($this->once())
            ->method('find')
            ->with('scan-id')
            ->willReturn($scan);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No files provided');

        $this->service->handleUploadedFiles('scan-id', []);
    }

    public function testHandleUploadedFilesThrowsExceptionWhenTooManyFiles()
    {
        $scan = new RepositoryScan();
        
        $this->scanRepo->expects($this->once())
            ->method('find')
            ->with('scan-id')
            ->willReturn($scan);

        // Create 11 mock files (more than the limit of 10)
        $files = array_fill(0, 11, $this->createMock(UploadedFile::class));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Too many files in request');

        $this->service->handleUploadedFiles('scan-id', $files);
    }

    public function testHandleUploadedFilesThrowsExceptionForInvalidExtension()
    {
        $scan = new RepositoryScan();
        
        $this->scanRepo->expects($this->once())
            ->method('find')
            ->with('scan-id')
            ->willReturn($scan);

        $file = $this->createMock(UploadedFile::class);
        $file->method('getClientOriginalExtension')->willReturn('exe');
        $file->method('getClientOriginalName')->willReturn('malicious.exe');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Extension .exe not allowed');

        $this->service->handleUploadedFiles('scan-id', [$file]);
    }

    public function testHandleUploadedFilesThrowsExceptionForOversizedFile()
    {
        $scan = new RepositoryScan();
        
        $this->scanRepo->expects($this->once())
            ->method('find')
            ->with('scan-id')
            ->willReturn($scan);

        $file = $this->createMock(UploadedFile::class);
        $file->method('getClientOriginalExtension')->willReturn('json');
        $file->method('getClientOriginalName')->willReturn('large.json');
        $file->method('getSize')->willReturn(6_000_000); // 6MB (over 5MB limit)

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('File exceeds max size');

        $this->service->handleUploadedFiles('scan-id', [$file]);
    }

    public function testHandleUploadedFilesSuccessfullyStoresFiles()
    {
        $scan = new RepositoryScan();
        
        $this->scanRepo->expects($this->once())
            ->method('find')
            ->with('scan-id')
            ->willReturn($scan);

        // Create a temporary file for testing
        $tmpFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tmpFile, '{"test": "data"}');

        $file = $this->createMock(UploadedFile::class);
        $file->method('getClientOriginalExtension')->willReturn('json');
        $file->method('getClientOriginalName')->willReturn('package.json');
        $file->method('getSize')->willReturn(100);
        $file->method('getRealPath')->willReturn($tmpFile);

        $this->filesystem->expects($this->once())
            ->method('writeStream')
            ->with(
                $this->stringContains('uploads/scan-id/'),
                $this->isType('resource')
            );

        $this->em->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(FilesInScan::class));

        $this->em->expects($this->once())
            ->method('flush');

        $result = $this->service->handleUploadedFiles('scan-id', [$file], false);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(FilesInScan::class, $result[0]);

        // Cleanup
        if (file_exists($tmpFile)) {
            unlink($tmpFile);
        }
    }

    public function testHandleUploadedFilesWithUploadCompleteDispatchesMessage()
    {
        $scan = new RepositoryScan();
        
        $this->scanRepo->expects($this->once())
            ->method('find')
            ->with('scan-id')
            ->willReturn($scan);

        $tmpFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tmpFile, '{"test": "data"}');

        $file = $this->createMock(UploadedFile::class);
        $file->method('getClientOriginalExtension')->willReturn('json');
        $file->method('getClientOriginalName')->willReturn('package.json');
        $file->method('getSize')->willReturn(100);
        $file->method('getRealPath')->willReturn($tmpFile);

        $this->filesystem->expects($this->once())
            ->method('writeStream');

        // Expect flush to be called once after persisting files (state machine handles its own flush)
        $this->em->expects($this->once())
            ->method('flush');

        // Expect state machine transition to 'uploaded'
        $this->stateMachine->expects($this->once())
            ->method('transition')
            ->with($scan, 'uploaded', 'All files uploaded successfully');

        $this->bus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(StartProviderScanMessage::class))
            ->willReturn(new Envelope(new StartProviderScanMessage('scan-id')));

        $result = $this->service->handleUploadedFiles('scan-id', [$file], true);

        // State machine will call setStatus, so we don't assert it here
        // $this->assertEquals('uploaded', $scan->getStatus());
        $this->assertCount(1, $result);

        // Cleanup
        if (file_exists($tmpFile)) {
            unlink($tmpFile);
        }
    }

    public function testStartProviderScanThrowsExceptionWhenScanNotFound()
    {
        $this->scanRepo->expects($this->once())
            ->method('find')
            ->with('non-existent-scan')
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Scan not found');

        $this->service->startProviderScan('non-existent-scan');
    }

    public function testStartProviderScanMarksAsQueuedAndDispatchesMessage()
    {
        $scan = new RepositoryScan();
        $scan->setStatus('uploaded');
        
        $this->scanRepo->expects($this->once())
            ->method('find')
            ->with('scan-id')
            ->willReturn($scan);

        // State machine handles flush internally
        $this->stateMachine->expects($this->once())
            ->method('transition')
            ->with($scan, 'queued', 'Manual scan execution triggered');

        $this->bus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(StartProviderScanMessage::class))
            ->willReturn(new Envelope(new StartProviderScanMessage('scan-id')));

        $this->service->startProviderScan('scan-id');

        // State machine will call setStatus, so we don't assert it here
        // $this->assertEquals('queued', $scan->getStatus());
    }

    public function testGetScanSummaryReturnsNullWhenScanNotFound()
    {
        $this->scanRepo->expects($this->once())
            ->method('find')
            ->with('non-existent-scan')
            ->willReturn(null);

        $result = $this->service->getScanSummary('non-existent-scan');

        $this->assertNull($result);
    }

    public function testGetScanSummaryReturnsArrayWithScanAndFiles()
    {
        $scan = new RepositoryScan();
        $scan->setBranch('main');
        $scan->setStatus('completed');
        $scan->setProviderCode('snyk');

        $file1 = new FilesInScan();
        $file1->setFileName('package.json');
        $file1->setFilePath('uploads/scan-id/package.json');
        $file1->setSize(1024);
        $file1->setStatus('scanned');

        $file2 = new FilesInScan();
        $file2->setFileName('composer.lock');
        $file2->setFilePath('uploads/scan-id/composer.lock');
        $file2->setSize(2048);
        $file2->setStatus('scanned');

        $this->scanRepo->expects($this->once())
            ->method('find')
            ->with('scan-id')
            ->willReturn($scan);

        $this->filesInScanRepo->expects($this->once())
            ->method('findBy')
            ->with(['repositoryScan' => $scan])
            ->willReturn([$file1, $file2]);

        $result = $this->service->getScanSummary('scan-id');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('scan', $result);
        $this->assertArrayHasKey('files', $result);

        $this->assertEquals('completed', $result['scan']['status']);
        $this->assertEquals('snyk', $result['scan']['provider_code']);
        $this->assertEquals('main', $result['scan']['branch']);

        $this->assertCount(2, $result['files']);
        $this->assertEquals('package.json', $result['files'][0]['name']);
        $this->assertEquals('composer.lock', $result['files'][1]['name']);
        $this->assertEquals(1024, $result['files'][0]['size']);
        $this->assertEquals(2048, $result['files'][1]['size']);
    }

    public function testGetScanSummaryHandlesNullProvider()
    {
        $scan = new RepositoryScan();
        $scan->setBranch('main');
        $scan->setStatus('pending');
        $scan->setProviderCode(null);

        $this->scanRepo->expects($this->once())
            ->method('find')
            ->with('scan-id')
            ->willReturn($scan);

        $this->filesInScanRepo->expects($this->once())
            ->method('findBy')
            ->with(['repositoryScan' => $scan])
            ->willReturn([]);

        $result = $this->service->getScanSummary('scan-id');

        $this->assertIsArray($result);
        $this->assertNull($result['scan']['provider_code']);
        $this->assertCount(0, $result['files']);
    }

    /**
     * Test boundary: file at exactly 5MB (5,000,000 bytes) should pass
     */
    public function testHandleUploadedFilesAcceptsExactly5MBFile()
    {
        $scan = new RepositoryScan();
        
        $this->scanRepo->expects($this->once())
            ->method('find')
            ->with('scan-id')
            ->willReturn($scan);

        // Create a temporary file for testing
        $tmpFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tmpFile, str_repeat('x', 100)); // Small content for test

        $file = $this->createMock(UploadedFile::class);
        $file->method('getClientOriginalExtension')->willReturn('json');
        $file->method('getClientOriginalName')->willReturn('exactly5mb.json');
        $file->method('getSize')->willReturn(5_000_000); // Exactly 5MB (5,000,000 bytes)
        $file->method('getRealPath')->willReturn($tmpFile);

        $this->filesystem->expects($this->once())
            ->method('writeStream')
            ->with(
                $this->stringContains('uploads/scan-id/'),
                $this->isType('resource')
            );

        $this->em->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(FilesInScan::class));

        $this->em->expects($this->once())
            ->method('flush');

        $result = $this->service->handleUploadedFiles('scan-id', [$file], false);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(FilesInScan::class, $result[0]);

        // Cleanup
        if (file_exists($tmpFile)) {
            unlink($tmpFile);
        }
    }

    /**
     * Test boundary: file at 5MB + 1 byte should fail
     */
    public function testHandleUploadedFilesRejectsFileOver5MB()
    {
        $scan = new RepositoryScan();
        
        $this->scanRepo->expects($this->once())
            ->method('find')
            ->with('scan-id')
            ->willReturn($scan);

        $file = $this->createMock(UploadedFile::class);
        $file->method('getClientOriginalExtension')->willReturn('json');
        $file->method('getClientOriginalName')->willReturn('over5mb.json');
        $file->method('getSize')->willReturn(5_000_001); // 5MB + 1 byte

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('File exceeds max size');

        $this->service->handleUploadedFiles('scan-id', [$file]);
    }

    /**
     * Test boundary: exactly 10 files should pass
     */
    public function testHandleUploadedFilesAcceptsExactly10Files()
    {
        $scan = new RepositoryScan();
        
        $this->scanRepo->expects($this->once())
            ->method('find')
            ->with('scan-id')
            ->willReturn($scan);

        // Create exactly 10 mock files (at the limit)
        $files = [];
        for ($i = 0; $i < 10; $i++) {
            $tmpFile = tempnam(sys_get_temp_dir(), "test{$i}");
            file_put_contents($tmpFile, "content {$i}");

            $file = $this->createMock(UploadedFile::class);
            $file->method('getClientOriginalExtension')->willReturn('json');
            $file->method('getClientOriginalName')->willReturn("file{$i}.json");
            $file->method('getSize')->willReturn(100);
            $file->method('getRealPath')->willReturn($tmpFile);
            $files[] = $file;
        }

        $this->filesystem->expects($this->exactly(10))
            ->method('writeStream');

        $this->em->expects($this->exactly(10))
            ->method('persist');

        $this->em->expects($this->once())
            ->method('flush');

        $result = $this->service->handleUploadedFiles('scan-id', $files, false);

        $this->assertIsArray($result);
        $this->assertCount(10, $result);

        // Cleanup
        foreach ($files as $i => $file) {
            $tmpFile = sys_get_temp_dir() . "/test{$i}";
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }
    }

    /**
     * Test edge case: empty file (0 bytes) should be rejected
     */
    public function testHandleUploadedFilesRejectsEmptyFile()
    {
        $scan = new RepositoryScan();
        
        $this->scanRepo->expects($this->once())
            ->method('find')
            ->with('scan-id')
            ->willReturn($scan);

        $file = $this->createMock(UploadedFile::class);
        $file->method('getClientOriginalExtension')->willReturn('json');
        $file->method('getClientOriginalName')->willReturn('empty.json');
        $file->method('getSize')->willReturn(0); // Empty file

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('File cannot be empty');

        $this->service->handleUploadedFiles('scan-id', [$file]);
    }
}
