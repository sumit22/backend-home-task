<?php

namespace App\MessageHandler;

use App\Entity\RepositoryScan;
use App\Message\RepositoryScanStatusChangedMessage;
use App\Service\RuleEvaluationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class RepositoryScanStatusChangedHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RuleEvaluationService $ruleEvaluationService,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(RepositoryScanStatusChangedMessage $message): void
    {
        $scan = $this->entityManager->getRepository(RepositoryScan::class)
            ->find($message->getScanId());

        if (!$scan) {
            $this->logger->warning('RepositoryScan not found when handling status change message', [
                'scan_id' => $message->getScanId(),
            ]);
            return;
        }

        if ($scan->getStatus() !== $message->getNewStatus()) {
            $this->logger->info('Skipping rule evaluation, scan status already moved on', [
                'scan_id' => $message->getScanId(),
                'expected_status' => $message->getNewStatus(),
                'current_status' => $scan->getStatus(),
            ]);
            return;
        }

        $this->ruleEvaluationService->evaluateAndNotify($scan);
    }
}
