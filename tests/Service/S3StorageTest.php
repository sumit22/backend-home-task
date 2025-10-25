<?php

namespace App\Tests\Service;

use Aws\S3\S3Client;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;

class S3StorageTest extends TestCase
{
    private FilesystemOperator $storage;

    protected function setUp(): void
    {
        // Create S3 client for LocalStack
        $client = new S3Client([
            'version' => 'latest',
            'region' => 'us-east-1',
            'endpoint' => 'http://localstack:4566',
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key' => 'test',
                'secret' => 'test',
            ],
        ]);

        // Create adapter and filesystem
        $adapter = new AwsS3V3Adapter($client, 'rule-engine-files', 'test-uploads');
        $this->storage = new Filesystem($adapter);
    }

    public function testWriteAndReadFile(): void
    {
        $path = 'test-files/test.txt';
        $content = 'Hello from Flysystem with LocalStack S3!';

        // Write file
        $this->storage->write($path, $content);

        // Verify file exists
        $this->assertTrue($this->storage->fileExists($path));

        // Read file content
        $readContent = $this->storage->read($path);
        $this->assertEquals($content, $readContent);

        // Clean up
        $this->storage->delete($path);
    }

    public function testFileMetadata(): void
    {
        $path = 'test-files/metadata-test.txt';
        $content = 'Testing file metadata';

        $this->storage->write($path, $content);

        // Get file size
        $size = $this->storage->fileSize($path);
        $this->assertEquals(strlen($content), $size);

        // Get mime type
        $mimeType = $this->storage->mimeType($path);
        $this->assertStringContainsString('text', $mimeType);

        // Get last modified timestamp
        $lastModified = $this->storage->lastModified($path);
        $this->assertIsInt($lastModified);
        $this->assertGreaterThan(0, $lastModified);

        // Clean up
        $this->storage->delete($path);
    }

    public function testListContents(): void
    {
        $directory = 'test-directory';
        $files = [
            "$directory/file1.txt" => 'Content 1',
            "$directory/file2.txt" => 'Content 2',
            "$directory/subfolder/file3.txt" => 'Content 3',
        ];

        // Write multiple files
        foreach ($files as $path => $content) {
            $this->storage->write($path, $content);
        }

        // List directory contents (non-recursive)
        $listing = $this->storage->listContents($directory, false);
        $paths = [];
        foreach ($listing as $item) {
            $paths[] = $item->path();
        }

        $this->assertContains("$directory/file1.txt", $paths);
        $this->assertContains("$directory/file2.txt", $paths);

        // Clean up
        foreach (array_keys($files) as $path) {
            $this->storage->delete($path);
        }
    }

    public function testDeleteFile(): void
    {
        $path = 'test-files/delete-test.txt';
        $content = 'This file will be deleted';

        // Write file
        $this->storage->write($path, $content);
        $this->assertTrue($this->storage->fileExists($path));

        // Delete file
        $this->storage->delete($path);
        $this->assertFalse($this->storage->fileExists($path));
    }

    public function testCopyFile(): void
    {
        $sourcePath = 'test-files/source.txt';
        $destinationPath = 'test-files/destination.txt';
        $content = 'Content to be copied';

        // Write source file
        $this->storage->write($sourcePath, $content);

        // Copy file
        $this->storage->copy($sourcePath, $destinationPath);

        // Verify both files exist with same content
        $this->assertTrue($this->storage->fileExists($sourcePath));
        $this->assertTrue($this->storage->fileExists($destinationPath));
        $this->assertEquals($content, $this->storage->read($destinationPath));

        // Clean up
        $this->storage->delete($sourcePath);
        $this->storage->delete($destinationPath);
    }

    public function testMoveFile(): void
    {
        $sourcePath = 'test-files/move-source.txt';
        $destinationPath = 'test-files/move-destination.txt';
        $content = 'Content to be moved';

        // Write source file
        $this->storage->write($sourcePath, $content);

        // Move file
        $this->storage->move($sourcePath, $destinationPath);

        // Verify source is gone and destination exists
        $this->assertFalse($this->storage->fileExists($sourcePath));
        $this->assertTrue($this->storage->fileExists($destinationPath));
        $this->assertEquals($content, $this->storage->read($destinationPath));

        // Clean up
        $this->storage->delete($destinationPath);
    }

    public function testWriteStream(): void
    {
        $path = 'test-files/stream-test.txt';
        $content = 'Testing stream write operation';

        // Create a stream
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        // Write using stream
        $this->storage->writeStream($path, $stream);
        fclose($stream);

        // Verify content
        $this->assertEquals($content, $this->storage->read($path));

        // Clean up
        $this->storage->delete($path);
    }

    public function testReadStream(): void
    {
        $path = 'test-files/read-stream-test.txt';
        $content = 'Testing stream read operation';

        // Write file
        $this->storage->write($path, $content);

        // Read as stream
        $stream = $this->storage->readStream($path);
        $readContent = stream_get_contents($stream);
        fclose($stream);

        $this->assertEquals($content, $readContent);

        // Clean up
        $this->storage->delete($path);
    }
}
