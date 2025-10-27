<?php
// src/MessageHandler/StartProviderScanHandler.php
namespace App\MessageHandler;

use App\Message\StartProviderScanMessage;
use App\Contract\Service\ProviderManagerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
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
        private LoggerInterface $logger
    ) {}

    public function __invoke(StartProviderScanMessage $message): void
    {
        $scan = $this->em->getRepository(\App\Entity\RepositoryScan::class)
            ->find($message->scanId);

        if (!$scan) {
            $this->logger->warning('RepositoryScan not found', ['scanId' => $message->scanId]);
            return;
        }

        $providerCode = $scan->getProviderSelection() ?? 'sca_debricked';
        $adapter = $this->providerManager->getAdapter($providerCode);

        if (!$adapter) {
            $scan->setStatus('failed');
            $this->em->flush();
            $this->logger->error('No provider adapter found', ['provider' => $providerCode]);
            return;
        }

        // Prepare S3 temporary files
        $localPaths = [];
        foreach ($scan->getFiles() as $fileEntity) {
            $path = sys_get_temp_dir() . '/' . basename($fileEntity->getFilePath());
            $stream = $this->filesystem->readStream($fileEntity->getFilePath());
            if (!$stream) continue;
            file_put_contents($path, stream_get_contents($stream));
            fclose($stream);
            $localPaths[] = $path;
        }

        try {
            $result = $adapter->uploadAndCreateScan($scan, $localPaths, [
                'repositoryName' => $scan->getRepository()->getName(),
                'branchName' => $scan->getBranch(),
                'repositoryUrl' => $scan->getRepository()->getUrl(),
                'author' => $scan->getRequestedBy(),
            ]);
            $scan->setStatus('running');
            $this->em->flush();
        } catch (\Throwable $e) {
            $this->logger->error('Provider upload failed', ['error' => $e->getMessage()]);
            $scan->setStatus('failed');
            $this->em->flush();
        } finally {
            foreach ($localPaths as $p) {
                @unlink($p);
            }
        }
    }
}
