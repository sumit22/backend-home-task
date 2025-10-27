<?php
// src/MessageHandler/StartProviderScanHandler.php
namespace App\MessageHandler;

use App\Message\StartProviderScanMessage;
use App\Service\ProviderManagerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

// TODO: Uncomment when ProviderManagerInterface is implemented
// #[AsMessageHandler]
class StartProviderScanHandler
{
    public function __construct(
        //private ProviderManagerInterface $providerManager,
        private EntityManagerInterface $em
    ) {}

    public function __invoke(StartProviderScanMessage $msg): void
    {
        $scanId = $msg->getScanId();

        $scan = $this->em->getRepository(\App\Entity\RepositoryScan::class)->find($scanId);
        if (!$scan) {
            // Optionally log or throw, but never hard fail worker
            return;
        }

        // Retrieve provider adapter dynamically
        $adapter = $this->providerManager->getAdapter($scan->getProviderSelection());
        if (!$adapter) {
            // log: unknown provider
            $scan->setStatus('failed');
            $this->em->flush();
            return;
        }

        // Gather file paths for upload
        $paths = [];
        foreach ($scan->getFiles() as $file) {
            $paths[] = $file->getFilePath();
        }

        // Delegate upload & scan creation to adapter
        try {
            $result = $adapter->uploadAndCreateScan($paths, [
                'repository' => $scan->getRepository()->getId(),
                'branch' => $scan->getBranch(),
            ]);

            // result might be ['provider_scan_id' => '...', 'files' => [...]]
            // Persist mapping in external_mapping (not shown)
            $scan->setStatus('running');
            $this->em->flush();
        } catch (\Throwable $e) {
            $scan->setStatus('failed');
            $this->em->flush();
            // log exception message for observability
        }
    }
}
