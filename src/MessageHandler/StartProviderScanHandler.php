<?php
// src/MessageHandler/StartProviderScanHandler.php
namespace App\MessageHandler;

use App\Message\StartProviderScanMessage;
use App\Message\PollProviderScanResultsMessage;
use App\Contract\Service\ProviderManagerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use App\Service\Provider\ProviderManager;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;


#[AsMessageHandler]
final class StartProviderScanHandler
{
    public function __construct(
        private ProviderManager $providerManager,
        private EntityManagerInterface $em,
        private FilesystemOperator $filesystem,
        private MessageBusInterface $bus,
        private LoggerInterface $logger,
        private \App\Service\ScanStateMachine $stateMachine
    ) {}

    public function __invoke(StartProviderScanMessage $message): void
    {
        $scan = $this->em->getRepository(\App\Entity\RepositoryScan::class)
            ->find($message->scanId);

        if (!$scan) {
            $this->logger->warning('RepositoryScan not found', ['scanId' => $message->scanId]);
            return;
        }

        $providerCode = $scan->getProviderCode() ?? 'debricked';
        $adapter = $this->providerManager->getAdapter($providerCode);

        if (!$adapter) {
            $this->stateMachine->transition($scan, 'failed', 'No provider adapter found');
            $this->logger->error('No provider adapter found', ['provider' => $providerCode]);
            return;
        }

        // Download files from S3 to temp directory with original filenames
        $localPaths = [];
        $fileMapping = []; // Map temp path to original filename
        
        foreach ($scan->getFilesInScans() as $fileEntity) {
            $originalFilename = $fileEntity->getFileName();
            // Use original filename with unique prefix to avoid conflicts
            $tempPath = sys_get_temp_dir() . '/' . uniqid() . '-' . $originalFilename;
            
            $stream = $this->filesystem->readStream($fileEntity->getFilePath());
            if (!$stream) {
                $this->logger->warning('Could not read file from S3', [
                    'path' => $fileEntity->getFilePath(),
                    'filename' => $originalFilename
                ]);
                continue;
            }
            
            file_put_contents($tempPath, stream_get_contents($stream));
            fclose($stream);
            
            $localPaths[] = $tempPath;
            $fileMapping[$tempPath] = $originalFilename;
        }
        
        if (empty($localPaths)) {
            $this->logger->error('No files could be read from S3');
            $this->stateMachine->transition($scan, 'failed', 'No files could be read from S3');
            return;
        }

        // Verify scan is in uploaded state before starting
        if ($scan->getStatus() !== 'uploaded') {
            $this->logger->warning('Scan not in uploaded state, skipping provider upload', [
                'scan_id' => $scan->getId(),
                'current_status' => $scan->getStatus(),
                'expected_status' => 'uploaded'
            ]);
            return;
        }

        try {
            $result = $adapter->uploadAndCreateScan($scan, $localPaths, [
                'repositoryName' => $scan->getRepository()->getName(),
                'author' => $scan->getRequestedBy(),
                'fileMapping' => $fileMapping, // Pass original filenames
            ]);
            $this->stateMachine->transition($scan, 'running', 'Provider scan started successfully');
            
            // Dispatch polling message to check scan results
            $this->logger->info('Dispatching poll message for scan', ['scanId' => $scan->getId()]);
            $this->bus->dispatch(new PollProviderScanResultsMessage($scan->getId()));
            
        } catch (\Throwable $e) {
            $this->logger->error('Provider upload failed', [
                'scan_id' => $message->scanId,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => substr($e->getTraceAsString(), 0, 2000),
            ]);
            
            $this->stateMachine->transition($scan, 'failed', 'Provider upload failed: ' . $e->getMessage());
        } finally {
            foreach ($localPaths as $p) {
                @unlink($p);
            }
        }
    }
}
