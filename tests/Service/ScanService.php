<?php
namespace App\Service;

use App\Entity\Repository;
use App\Entity\RepositoryScan;
use App\Entity\FilesInScan;
use App\Contract\Service\ScanServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\MessageBusInterface;

class ScanService implements ScanServiceInterface
{
    private const MAX_FILE_COUNT_PER_REQUEST = 10;
    private const MAX_FILE_SIZE = 5_000_000; // 5MB
    private const ALLOWED_EXT = ['json','xml','lock','gradle','yaml','yml','txt'];

    public function __construct(
        private EntityManagerInterface $em,
        private FilesystemOperator $filesystem,           // flysystem.storage.s3
        private MessageBusInterface $bus                 // messenger for async start
    ) {}

    public function createScan(string $repositoryId, ?string $branch = null, ?string $provider = null, ?string $requestedBy = null): RepositoryScan
    {
        $repo = $this->em->getRepository(Repository::class)->find($repositoryId);
        if (!$repo) {
            throw new \InvalidArgumentException("Repository not found");
        }

        $scan = new RepositoryScan();
        //use setters instead of constructor args if needed
        $scan->setRepository($repo);
        $scan->setBranch($branch);
        $scan->setProvider($provider);
        $scan->setRequestedBy($requestedBy);
        $this->em->persist($scan);
        $this->em->flush();
        return $scan;
    }

    public function handleUploadedFiles(string $scanId, array $files, bool $uploadComplete = false): array
    {
        $scan = $this->em->getRepository(RepositoryScan::class)->find($scanId);
        if (!$scan) {
            throw new \InvalidArgumentException('Scan not found');
        }

        if (count($files) === 0) {
            throw new \InvalidArgumentException('No files provided');
        }
        if (count($files) > self::MAX_FILE_COUNT_PER_REQUEST) {
            throw new \InvalidArgumentException('Too many files in request');
        }

        $stored = [];
        foreach ($files as $file) {
            if (!$file instanceof UploadedFile) continue;

            $ext = strtolower($file->getClientOriginalExtension());
            if (!in_array($ext, self::ALLOWED_EXT, true)) {
                throw new \InvalidArgumentException("Extension .$ext not allowed");
            }
            if ($file->getSize() > self::MAX_FILE_SIZE) {
                throw new \InvalidArgumentException("File exceeds max size");
            }

            $safe = bin2hex(random_bytes(6)) . '-' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $file->getClientOriginalName());
            $path = sprintf('uploads/%s/%s', $scanId, $safe);

            $stream = fopen($file->getRealPath(), 'rb');
            $this->filesystem->writeStream($path, $stream);
            if (is_resource($stream)) fclose($stream);

            $entity = new FilesInScan($scan, $file->getClientOriginalName(), $path, (int)$file->getSize());
            $this->em->persist($entity);
            $scan->addFile($entity);
            $stored[] = $entity;
        }

        // update DB
        $this->em->flush();

        if ($uploadComplete) {
            $scan->setStatus('uploaded');
            $this->em->flush();
            // dispatch start provider scan async
            $this->bus->dispatch(new \App\Message\StartProviderScanMessage($scan->getId()));
        }

        return $stored;
    }

    public function startProviderScan(string $scanId): void
    {
        $scan = $this->em->getRepository(RepositoryScan::class)->find($scanId);
        if (!$scan) {
            throw new \InvalidArgumentException('Scan not found');
        }

        // mark queued and enqueue async
        $scan->setStatus('queued');
        $this->em->flush();
        $this->bus->dispatch(new \App\Message\StartProviderScanMessage($scan->getId()));
    }

    public function getScanSummary(string $scanId): ?array
    {
        $scan = $this->em->getRepository(RepositoryScan::class)->find($scanId);
        if (!$scan) return null;

        $files = $this->em->getRepository(FilesInScan::class)->findBy(['repositoryScan' => $scan]);
        return [
            'scan' => [
                'id' => $scan->getId(),
                'status' => $scan->getStatus(),
                'provider' => $scan->getProviderSelection(),
                'branch' => $scan->getBranch(),
            ],
            'files' => array_map(fn(FilesInScan $f) => [
                'id' => $f->getId(),
                'name' => $f->getFileName(),
                'path' => $f->getFilePath(),
                'size' => $f->getSize(),
                'status' => $f->getStatus(),
            ], $files),
        ];
    }
}
