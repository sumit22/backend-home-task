<?php
namespace App\Service;

use App\Entity\Repository;
use App\Entity\RepositoryScan;
use App\Entity\FilesInScan;
use App\Entity\Provider;
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
        private MessageBusInterface $bus,                // messenger for async start
        private ScanStateMachine $stateMachine           // FSM for status transitions
    ) {}

    public function createScan(string $repositoryId, ?string $branch = null, ?string $providerCode = null, ?string $requestedBy = null): RepositoryScan
    {
        $repo = $this->em->getRepository(Repository::class)->find($repositoryId);
        if (!$repo) {
            throw new \InvalidArgumentException("Repository not found");
        }

        // Validate provider code if provided
        if ($providerCode) {
            $provider = $this->em->getRepository(Provider::class)->findOneBy(['code' => $providerCode]);
            if (!$provider) {
                throw new \InvalidArgumentException("Provider not found");
            }
        }

        $scan = new RepositoryScan();
        $scan->setRepository($repo);
        $scan->setBranch($branch);
        $scan->setProviderCode($providerCode);
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
            if ($file->getSize() === 0 || $file->getSize() === null) {
                throw new \InvalidArgumentException("File cannot be empty");
            }
            if ($file->getSize() > self::MAX_FILE_SIZE) {
                throw new \InvalidArgumentException("File exceeds max size");
            }

            $safe = bin2hex(random_bytes(6)) . '-' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $file->getClientOriginalName());
            $path = sprintf('uploads/%s/%s', $scanId, $safe);

            $stream = fopen($file->getRealPath(), 'rb');
            $this->filesystem->writeStream($path, $stream);
            if (is_resource($stream)) fclose($stream);

            $entity = new FilesInScan();
            $entity->setRepositoryScan($scan);
            $entity->setFileName($file->getClientOriginalName());
            $entity->setFilePath($path);
            $entity->setSize((int)$file->getSize());
            $this->em->persist($entity);
            $scan->addFilesInScan($entity);
            $stored[] = $entity;
        }

        // update DB
        $this->em->flush();

        if ($uploadComplete) {
            // Use state machine for transition
            $this->stateMachine->transition($scan, 'uploaded', 'All files uploaded successfully');
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
        $this->stateMachine->transition($scan, 'queued', 'Manual scan execution triggered');
        $this->bus->dispatch(new \App\Message\StartProviderScanMessage($scan->getId()));
    }

    public function getScanSummary(string $scanId): ?array
    {
        $scan = $this->em->getRepository(RepositoryScan::class)->find($scanId);
        if (!$scan) return null;

        $files = $this->em->getRepository(FilesInScan::class)->findBy(['repositoryScan' => $scan]);
        
        $repository = $scan->getRepository();
        $repositoryId = $repository ? ($repository->getId()?->toRfc4122() ?? $repository->getId()) : null;
        
        return [
            'repository_id' => $repositoryId,
            'scan' => [
                'id' => $scan->getId()?->toRfc4122() ?? $scan->getId(),
                'status' => $scan->getStatus(),
                'provider_code' => $scan->getProviderCode(),
                'branch' => $scan->getBranch(),
            ],
            'files' => array_map(fn(FilesInScan $f) => [
                'id' => $f->getId()?->toRfc4122() ?? $f->getId(),
                'name' => $f->getFileName(),
                'path' => $f->getFilePath(),
                'size' => $f->getSize(),
                'status' => $f->getStatus(),
            ], $files),
        ];
    }
}
