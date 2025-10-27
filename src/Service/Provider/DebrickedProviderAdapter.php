<?php
namespace App\Service\Provider;

use App\Contract\Service\ProviderAdapterInterface;
use App\Entity\RepositoryScan;
use App\Service\ExternalMappingService;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

final class DebrickedProviderAdapter implements ProviderAdapterInterface
{
    public function __construct(
        private HttpClientInterface $http,
        private ExternalMappingService $mapping,
        private LoggerInterface $logger,
        private DebrickedAuthServiceInterface $auth,
        private string $baseUrl = 'https://debricked.com/api/1.0'
    ) {}

    public function providerCode(): string
    {
        return 'sca_debricked';
    }

    public function uploadAndCreateScan(RepositoryScan $scan, array $localPaths, array $options = []): array
    {
        $providerFileIds = [];
        $ciUploadId = null;
        $jwt = $this->auth->getJwtToken();

        foreach ($localPaths as $localPath) {
            $response = $this->http->request('POST', "{$this->baseUrl}/open/uploads/dependencies/files", [
                'headers' => ['Authorization' => "Bearer {$jwt}"],
                'body' => [
                    'file' => fopen($localPath, 'r'),
                    'repositoryName' => $options['repositoryName'] ?? $scan->getRepository()->getName(),
                    'commitName' => $options['commitName'] ?? $scan->getId(),
                    'branchName' => $options['branchName'] ?? $scan->getBranch(),
                    'repositoryUrl' => $options['repositoryUrl'] ?? $scan->getRepository()->getUrl(),
                ],
            ]);

            $data = $response->toArray(false);

            // Collect provider file ids (depends on response shape)
            if (!empty($data['files'])) {
                foreach ($data['files'] as $f) {
                    $providerFileIds[] = $f['dependencyFileId'] ?? ($f['id'] ?? null);
                    // persist file mapping (provider file id -> FilesInScan)
                    $this->mapping->createMapping(
                        $this->providerCode(),
                        'file',
                        (string)($f['dependencyFileId'] ?? $f['id']),
                        'FilesInScan',
                        $localPath,
                        $f
                    );
                }
            }

            // Capture ciUploadId returned by provider (it may be in top-level or returned per upload)
            if (!empty($data['ciUploadId'])) {
                $ciUploadId = $data['ciUploadId'];
            } elseif (!empty($data['uploadId'])) {
                $ciUploadId = $data['uploadId']; // fallback name variants
            }
        }

        if (!$ciUploadId) {
            // If an existing mapping exists for this scan (previous attempt), reuse it
            $existing = $this->mapping->findMapping($this->providerCode(), 'ci_upload', $scan->getId());
            if ($existing && !empty($existing['external_id'])) {
                $ciUploadId = $existing['external_id'];
            } else {
                throw new \RuntimeException('Debricked did not return ciUploadId on upload');
            }
        }

        // Persist mapping for the scan -> ciUploadId
        $this->mapping->createMapping(
            $this->providerCode(),
            'ci_upload',
            (string)$ciUploadId,
            'RepositoryScan',
            $scan->getId(),
            ['files' => $providerFileIds]
        );

        // Finish upload using the provider-supplied ciUploadId
        $finishResp = $this->http->request('POST', "{$this->baseUrl}/open/finishes/dependencies/files/uploads", [
            'headers' => ['Authorization' => "Bearer {$jwt}", 'Content-Type' => 'application/json'],
            'json' => ['ciUploadId' => $ciUploadId],
        ]);
        $finishData = $finishResp->toArray(false);

        return [
            'ciUploadId' => $ciUploadId,
            'provider_file_ids' => $providerFileIds,
            'raw' => $finishData,
        ];
    }


    public function normalizeScanResult(array $raw): array
    {
        return [
            'status' => $raw['scanCompleted'] ? 'completed' : 'running',
            'vulnerabilities' => $raw['vulnerableDependencies'] ?? [],
            'vulnerability_count' => $raw['vulnerabilitiesFound'] ?? 0,
        ];
    }
}
