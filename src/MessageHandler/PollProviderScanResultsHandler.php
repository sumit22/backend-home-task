<?php
namespace App\MessageHandler;

use App\Message\PollProviderScanResultsMessage;
use App\Service\Provider\ProviderManager;
use App\Service\ExternalMappingService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class PollProviderScanResultsHandler
{
    private const MAX_POLL_ATTEMPTS = 30; // 30 attempts = ~15 minutes with 30s delay
    private const POLL_DELAY_SECONDS = 30;

    public function __construct(
        private EntityManagerInterface $em,
        private ProviderManager $providerManager,
        private ExternalMappingService $mapping,
        private MessageBusInterface $bus,
        private LoggerInterface $logger,
        private \App\Service\ScanStateMachine $stateMachine
    ) {}

    public function __invoke(PollProviderScanResultsMessage $message): void
    {
        $scan = $this->em->getRepository(\App\Entity\RepositoryScan::class)
            ->find($message->scanId);

        if (!$scan) {
            $this->logger->warning('RepositoryScan not found for polling', ['scanId' => $message->scanId]);
            return;
        }

        // Don't poll if scan is already in a final state
        if (in_array($scan->getStatus(), ['completed', 'failed', 'cancelled'])) {
            $this->logger->info('Scan already in final state, skipping poll', [
                'scanId' => $message->scanId,
                'status' => $scan->getStatus()
            ]);
            return;
        }

        $providerCode = $scan->getProviderCode() ?? 'debricked';
        
        // Find the integration mapping to get ciUploadId
        $mapping = $this->mapping->findMappingByLinkedEntity(
            $providerCode,
            'ci_upload',
            'RepositoryScan',
            $scan->getId()
        );

        if (!$mapping) {
            $this->logger->error('No provider mapping found for scan', [
                'scanId' => $message->scanId,
                'provider' => $providerCode
            ]);
            $this->stateMachine->transition($scan, 'failed', 'No provider mapping found');
            return;
        }

        $ciUploadId = $mapping['external_id'];
        $adapter = $this->providerManager->getAdapter($providerCode);

        if (!$adapter) {
            $this->logger->error('No provider adapter found', ['provider' => $providerCode]);
            $this->stateMachine->transition($scan, 'failed', 'No provider adapter found');
            return;
        }

        try {
            // Poll the provider for scan status
            $statusData = $adapter->pollScanStatus($ciUploadId);
            
            $this->logger->info('Poll scan status', [
                'scanId' => $message->scanId,
                'attempt' => $message->attemptNumber,
                'progress' => $statusData['progress'],
                'completed' => $statusData['scan_completed']
            ]);

            if ($statusData['scan_completed']) {
                // Scan completed - update status and save results
                $this->stateMachine->transition($scan, 'completed', 'Provider scan completed successfully');
                $scan->setCompletedAt(new \DateTime());
                
                // Store scan results in the raw_summary field (for debugging)
                $scan->setRawSummary([
                    'provider' => $providerCode,
                    'ci_upload_id' => $ciUploadId,
                    'vulnerabilities_found' => $statusData['vulnerabilities_found'],
                    'details_url' => $statusData['details_url'],
                    'completed_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                    'raw' => $statusData['raw']
                ]);
                
                // Update vulnerability count
                $scan->setVulnerabilityCount($statusData['vulnerabilities_found']);
                
                // Create ScanResult entity
                $scanResult = new \App\Entity\ScanResult();
                $scanResult->setRepositoryScan($scan);
                $scanResult->setStatus('completed');
                $scanResult->setVulnerabilityCount($statusData['vulnerabilities_found']);
                $scanResult->setSummaryJson([
                    'provider' => $providerCode,
                    'details_url' => $statusData['details_url'],
                    'total_vulnerabilities' => $statusData['vulnerabilities_found'],
                    'scan_completed_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')
                ]);
                $this->em->persist($scanResult);
                
                // Parse and create Vulnerability entities from raw data
                $this->createVulnerabilityEntities($scan, $statusData['raw']);
                
                // Create FileScanResult entities for each file in the scan
                $this->createFileScanResults($scan, $scanResult);
                
                $this->em->flush();
                
                $this->logger->info('Scan completed successfully', [
                    'scanId' => $message->scanId,
                    'vulnerabilities' => $statusData['vulnerabilities_found'],
                    'detailsUrl' => $statusData['details_url']
                ]);
            } else {
                // Scan still running - schedule another poll
                if ($message->attemptNumber >= self::MAX_POLL_ATTEMPTS) {
                    $this->logger->warning('Max poll attempts reached', [
                        'scanId' => $message->scanId,
                        'attempts' => $message->attemptNumber
                    ]);
                    $this->stateMachine->transition($scan, 'timeout', 'Max poll attempts reached');
                } else {
                    // Re-queue the message with incremented attempt number
                    // The message will be processed after POLL_DELAY_SECONDS
                    $this->logger->info('Re-queuing poll message', [
                        'scanId' => $message->scanId,
                        'nextAttempt' => $message->attemptNumber + 1,
                        'delaySeconds' => self::POLL_DELAY_SECONDS
                    ]);
                    
                    // Note: Delay will be handled by messenger configuration
                    $this->bus->dispatch(
                        new PollProviderScanResultsMessage(
                            $message->scanId,
                            $message->attemptNumber + 1
                        )
                    );
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Error polling scan status', [
                'scanId' => $message->scanId,
                'attempt' => $message->attemptNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // On error, retry if we haven't exceeded max attempts
            if ($message->attemptNumber < self::MAX_POLL_ATTEMPTS) {
                $this->bus->dispatch(
                    new PollProviderScanResultsMessage(
                        $message->scanId,
                        $message->attemptNumber + 1
                    )
                );
            } else {
                $this->stateMachine->transition($scan, 'failed', 'Max poll attempts reached after error');
            }
        }
    }

    /**
     * Create individual Vulnerability entities from the raw provider data
     */
    private function createVulnerabilityEntities(\App\Entity\RepositoryScan $scan, array $rawData): void
    {
        // Extract vulnerability details from automation rules in Debricked response
        $vulnerabilities = [];
        
        if (isset($rawData['automationRules'])) {
            foreach ($rawData['automationRules'] as $rule) {
                if (isset($rule['hasCves']) && $rule['hasCves'] && isset($rule['triggerEvents'])) {
                    foreach ($rule['triggerEvents'] as $event) {
                        if (isset($event['cve'])) {
                            $vulnerabilities[] = $event;
                        }
                    }
                }
            }
        }

        // Create Vulnerability entity for each CVE
        foreach ($vulnerabilities as $vulnData) {
            $vulnerability = new \App\Entity\Vulnerability();
            $vulnerability->setScan($scan);
            $vulnerability->setCve($vulnData['cve'] ?? null);
            $vulnerability->setTitle($vulnData['cve'] ?? 'Unknown Vulnerability');
            
            // Normalize severity from CVSS score
            $cvss3 = $vulnData['cvss3'] ?? 0;
            $severity = $this->normalizeSeverity($cvss3);
            $vulnerability->setSeverity($severity);
            $vulnerability->setScore((string) $cvss3);
            
            // Extract package information
            $dependency = $vulnData['dependency'] ?? '';
            if (preg_match('/^(.+?)\s*\((.+?)\)$/', $dependency, $matches)) {
                $vulnerability->setPackageName($matches[1]);
                $vulnerability->setEcosystem($matches[2]);
            } else {
                $vulnerability->setPackageName($dependency);
            }
            
            // Store additional metadata
            $vulnerability->setReferences([
                'cve_link' => $vulnData['cveLink'] ?? null,
                'dependency_link' => $vulnData['dependencyLink'] ?? null,
                'cvss2' => $vulnData['cvss2'] ?? null,
                'cvss3' => $vulnData['cvss3'] ?? null
            ]);
            
            $vulnerability->setPackageMetadata([
                'licenses' => $vulnData['licenses'] ?? [],
                'raw_event' => $vulnData
            ]);
            
            $vulnerability->setIgnored(false);
            
            $this->em->persist($vulnerability);
            
            $this->logger->debug('Created vulnerability entity', [
                'cve' => $vulnerability->getCve(),
                'package' => $vulnerability->getPackageName(),
                'severity' => $vulnerability->getSeverity()
            ]);
        }
    }

    /**
     * Normalize CVSS score to severity level
     */
    private function normalizeSeverity(float $cvssScore): string
    {
        if ($cvssScore >= 9.0) return 'critical';
        if ($cvssScore >= 7.0) return 'high';
        if ($cvssScore >= 4.0) return 'medium';
        if ($cvssScore >= 0.1) return 'low';
        return 'info';
    }

    /**
     * Create FileScanResult entities for each file in the scan
     */
    private function createFileScanResults(\App\Entity\RepositoryScan $scan, \App\Entity\ScanResult $scanResult): void
    {
        // Get all files associated with this scan
        $files = $this->em->getRepository(\App\Entity\FilesInScan::class)
            ->findBy(['repositoryScan' => $scan]);

        foreach ($files as $file) {
            $fileScanResult = new \App\Entity\FileScanResult();
            $fileScanResult->setFile($file);
            $fileScanResult->setScanResult($scanResult);
            $fileScanResult->setStatus('completed');
            $fileScanResult->setRawPayload([
                'file_name' => $file->getFileName(),
                'file_path' => $file->getFilePath(),
                'size' => $file->getSize(),
                'scanned_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')
            ]);
            
            $this->em->persist($fileScanResult);
            
            $this->logger->debug('Created file scan result', [
                'file' => $file->getFileName(),
                'scanResultId' => $scanResult->getId()
            ]);
        }
    }
}
