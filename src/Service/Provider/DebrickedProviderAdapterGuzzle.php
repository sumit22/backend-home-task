<?php
namespace App\Service\Provider;

use App\Contract\Service\ProviderAdapterInterface;
use App\Entity\RepositoryScan;
use App\Service\ExternalMappingService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Guzzle-based Debricked Provider Adapter
 * 
 * This is an alternate implementation using Guzzle HTTP client instead of native cURL.
 * This provides better testability through mockable HTTP client interface.
 */
final class DebrickedProviderAdapterGuzzle implements ProviderAdapterInterface
{
    private string $baseUrl;

    public function __construct(
        private Client $httpClient,
        private ExternalMappingService $mapping,
        private LoggerInterface $logger,
        private DebrickedAuthServiceInterface $auth,
        string $baseUrl = 'https://debricked.com/api'
    ) {
        // Ensure base URL has version 1.0 for upload endpoints
        $this->baseUrl = rtrim($baseUrl, '/') . '/1.0';
    }

    public function providerCode(): string
    {
        return 'debricked';
    }

    public function uploadAndCreateScan(RepositoryScan $scan, array $localPaths, array $options = []): array
    {
        $providerFileIds = [];
        $ciUploadId = null;
        $jwt = $this->auth->getJwtToken();

        // Get file mapping if provided (maps temp path to original filename)
        $fileMapping = $options['fileMapping'] ?? [];
        
        foreach ($localPaths as $index => $localPath) {
            try {
                // Use file mapping to get original filename
                $originalFilename = $fileMapping[$localPath] ?? basename($localPath);
                
                $this->logger->info('Uploading file to Debricked (Guzzle)', [
                    'file' => $originalFilename,
                    'localPath' => $localPath,
                    'repositoryName' => $options['repositoryName'] ?? $scan->getRepository()->getName(),
                    'commitName' => $options['commitName'] ?? $scan->getId(),
                ]);
                
                // Verify file exists and is readable
                if (!file_exists($localPath)) {
                    throw new \RuntimeException("File does not exist: $localPath");
                }
                if (!is_readable($localPath)) {
                    throw new \RuntimeException("File is not readable: $localPath");
                }
                
                $repositoryName = $options['repositoryName'] ?? $scan->getRepository()->getName();
                $commitName = $options['commitName'] ?? $scan->getId();
                
                // Upload file using Guzzle multipart form
                $response = $this->httpClient->post(
                    "{$this->baseUrl}/open/uploads/dependencies/files",
                    [
                        'headers' => [
                            'Authorization' => "Bearer {$jwt}",
                        ],
                        'multipart' => [
                            [
                                'name' => 'fileData',
                                'contents' => fopen($localPath, 'r'),
                                'filename' => $originalFilename,
                            ],
                            [
                                'name' => 'repositoryName',
                                'contents' => $repositoryName,
                            ],
                            [
                                'name' => 'commitName',
                                'contents' => $commitName,
                            ],
                        ],
                    ]
                );
                
                $statusCode = $response->getStatusCode();
                $responseBody = $response->getBody()->getContents();
                
                if ($statusCode >= 400) {
                    $this->logger->error('Debricked upload failed', [
                        'status' => $statusCode,
                        'response_body' => $responseBody,
                        'file' => $originalFilename,
                    ]);
                    throw new \RuntimeException("Debricked upload failed with status {$statusCode}: {$responseBody}");
                }
                
                // Parse response JSON
                $data = json_decode($responseBody, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \RuntimeException("Failed to parse Debricked response: " . json_last_error_msg());
                }
                
                $this->logger->info('Debricked upload SUCCESS (Guzzle)', [
                    'status' => $statusCode,
                    'response' => $data,
                    'file' => $originalFilename,
                ]);
                
                // Collect provider file ids
                if (!empty($data['files'])) {
                    foreach ($data['files'] as $f) {
                        $fileId = $f['dependencyFileId'] ?? ($f['id'] ?? null);
                        if ($fileId) {
                            $providerFileIds[] = $fileId;
                            // Persist file mapping
                            $this->mapping->createMapping(
                                $this->providerCode(),
                                'file',
                                (string)$fileId,
                                'FilesInScan',
                                $localPath,
                                $f
                            );
                        }
                    }
                }

                // Capture ciUploadId
                if (!empty($data['ciUploadId'])) {
                    $ciUploadId = $data['ciUploadId'];
                } elseif (!empty($data['uploadId'])) {
                    $ciUploadId = $data['uploadId'];
                }
            } catch (GuzzleException $e) {
                $this->logger->error('Guzzle exception during upload', [
                    'file' => basename($localPath),
                    'exception_message' => $e->getMessage(),
                ]);
                throw new \RuntimeException("Failed to upload file: {$e->getMessage()}", 0, $e);
            }
        }

        if (!$ciUploadId) {
            // Check for existing mapping
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

        // Finish upload
        try {
            $finishResponse = $this->httpClient->post(
                "{$this->baseUrl}/open/finishes/dependencies/files/uploads",
                [
                    'headers' => [
                        'Authorization' => "Bearer {$jwt}",
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'ciUploadId' => $ciUploadId,
                    ],
                ]
            );
            
            $finishStatusCode = $finishResponse->getStatusCode();
            
            // Handle 204 No Content - success with no response body
            if ($finishStatusCode === 204) {
                return [
                    'ciUploadId' => $ciUploadId,
                    'status' => 'finished',
                    'provider_file_ids' => $providerFileIds,
                ];
            }
            
            $finishResponseBody = $finishResponse->getBody()->getContents();
            
            if (empty($finishResponseBody)) {
                return [
                    'ciUploadId' => $ciUploadId,
                    'status' => 'finished',
                    'provider_file_ids' => $providerFileIds,
                ];
            }
            
            $finishData = json_decode($finishResponseBody, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException("Failed to parse Debricked finish response: " . json_last_error_msg());
            }

            return [
                'ciUploadId' => $ciUploadId,
                'provider_file_ids' => $providerFileIds,
                'raw' => $finishData,
            ];
        } catch (GuzzleException $e) {
            $this->logger->error('Guzzle exception during finish', [
                'ciUploadId' => $ciUploadId,
                'exception_message' => $e->getMessage(),
            ]);
            throw new \RuntimeException("Failed to finish upload: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Poll scan status from Debricked
     * 
     * @param string $ciUploadId The CI upload ID from Debricked
     * @return array Status data including progress, vulnerabilities, etc.
     */
    public function pollScanStatus(string $ciUploadId): array
    {
        try {
            $jwt = $this->auth->getJwtToken();
            
            $response = $this->httpClient->get(
                "{$this->baseUrl}/open/ci/upload/status",
                [
                    'headers' => [
                        'Authorization' => "Bearer {$jwt}",
                    ],
                    'query' => [
                        'ciUploadId' => $ciUploadId,
                    ],
                ]
            );
            
            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            
            if ($statusCode >= 400) {
                throw new \RuntimeException("Debricked poll status failed with status {$statusCode}: {$responseBody}");
            }
            
            $data = json_decode($responseBody, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException("Failed to parse Debricked status response: " . json_last_error_msg());
            }
            
            $this->logger->info('Debricked poll status (Guzzle)', [
                'ciUploadId' => $ciUploadId,
                'progress' => $data['progress'] ?? 0,
            ]);
            
            return [
                'progress' => $data['progress'] ?? 0,
                'scan_completed' => ($data['progress'] ?? 0) >= 100,
                'vulnerabilities_found' => $data['vulnerabilitiesFound'] ?? 0,
                'details_url' => $data['detailsUrl'] ?? null,
                'raw' => $data,
            ];
        } catch (GuzzleException $e) {
            $this->logger->error('Guzzle exception during poll status', [
                'ciUploadId' => $ciUploadId,
                'exception_message' => $e->getMessage(),
            ]);
            throw new \RuntimeException("Failed to poll scan status: {$e->getMessage()}", 0, $e);
        }
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
