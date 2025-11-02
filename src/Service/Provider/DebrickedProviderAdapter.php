<?php
namespace App\Service\Provider;

use App\Contract\Service\ProviderAdapterInterface;
use App\Entity\RepositoryScan;
use App\Service\ExternalMappingService;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

final class DebrickedProviderAdapter implements ProviderAdapterInterface
{
    private string $baseUrl;

    public function __construct(
        private HttpClientInterface $http,
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
            $data = null; // Initialize here so it's accessible outside try block
            try {
                // Use file mapping to get original filename
                $originalFilename = $fileMapping[$localPath] ?? basename($localPath);
                
                $this->logger->info('Uploading file to Debricked', [
                    'file' => $originalFilename,
                    'localPath' => $localPath,
                    'repositoryName' => $options['repositoryName'] ?? $scan->getRepository()->getName(),
                    'commitName' => $options['commitName'] ?? $scan->getId(),
                    'url' => "{$this->baseUrl}/open/uploads/dependencies/files",
                ]);
                
                // Log file details
                $fileSize = filesize($localPath);
                $fileMd5 = md5_file($localPath);
                $this->logger->info('File details before upload', [
                    'original' => $originalFilename,
                    'size' => $fileSize,
                    'md5' => $fileMd5,
                    'tempPath' => $localPath,
                ]);
                
                // Use native cURL for file upload to have full control over multipart format
                $repositoryName = $options['repositoryName'] ?? $scan->getRepository()->getName();
                $commitName = $options['commitName'] ?? $scan->getId();
                
                // Verify file exists and is readable
                if (!file_exists($localPath)) {
                    throw new \RuntimeException("File does not exist: $localPath");
                }
                if (!is_readable($localPath)) {
                    throw new \RuntimeException("File is not readable: $localPath");
                }
                
                $this->logger->info('About to upload with cURL', [
                    'localPath' => $localPath,
                    'originalFilename' => $originalFilename,
                    'fileExists' => file_exists($localPath),
                    'fileSize' => filesize($localPath),
                    'isReadable' => is_readable($localPath),
                ]);
                
                // Try using @ prefix for file upload (older cURL style)
                $postFields = [
                    'fileData' => new \CURLFile($localPath, 'application/octet-stream', $originalFilename),
                    'repositoryName' => $repositoryName,
                    'commitName' => $commitName,
                ];
                
                $ch = curl_init("{$this->baseUrl}/open/uploads/dependencies/files");
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "Authorization: Bearer {$jwt}",
                ]);
                // Enable verbose output for debugging
                curl_setopt($ch, CURLOPT_VERBOSE, true);
                $verbose = fopen('php://temp', 'w+');
                curl_setopt($ch, CURLOPT_STDERR, $verbose);
                
                $responseBody = curl_exec($ch);
                $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                
                // Close verbose output
                fclose($verbose);
                
                curl_close($ch);
                
                $this->logger->debug('Debricked API request completed', [
                    'status_code' => $statusCode,
                    'file' => basename($localPath),
                ]);
                
                if ($error) {
                    throw new \RuntimeException("cURL error: {$error}");
                }
                
                if ($statusCode >= 400) {
                    $this->logger->error('Debricked upload failed with non-2xx status', [
                        'status' => $statusCode,
                        'response_body' => $responseBody,
                        'file' => basename($localPath),
                        'repository' => $scan->getRepository()->getName(),
                        'commit' => $scan->getId(),
                    ]);
                    throw new \RuntimeException("Debricked upload failed with status {$statusCode}: {$responseBody}");
                }
                
                // Parse response JSON
                $data = json_decode($responseBody, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \RuntimeException("Failed to parse Debricked response: " . json_last_error_msg());
                }
                
                $this->logger->info('Debricked upload SUCCESS', [
                    'status' => $statusCode,
                    'response' => $data,
                    'file' => $originalFilename,
                ]);
            } catch (\Throwable $e) {
                // Log error
                $this->logger->error('Debricked upload exception', [
                    'file' => basename($localPath),
                    'repository' => $scan->getRepository()->getName(),
                    'commit' => $scan->getId(),
                    'exception_class' => get_class($e),
                    'exception_message' => $e->getMessage(),
                    'exception_trace' => substr($e->getTraceAsString(), 0, 2000),
                ]);
                
                throw $e;
            }
            
            // Old code that used Symfony HttpClient response object - now using parsed JSON
            // $data = $response->toArray(false);

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
        $finishUrl = "{$this->baseUrl}/open/finishes/dependencies/files/uploads";
        $finishPayload = json_encode(['ciUploadId' => $ciUploadId]);
        
        $ch = curl_init($finishUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $finishPayload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$jwt}",
            "Content-Type: application/json",
        ]);
        
        $finishResponseBody = curl_exec($ch);
        $finishStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $finishError = curl_error($ch);
        curl_close($ch);
        
        if ($finishError) {
            throw new \RuntimeException("cURL error on finish: {$finishError}");
        }
        
        if ($finishStatusCode >= 400) {
            $this->logger->error('Debricked finalization failed', [
                'status_code' => $finishStatusCode,
                'response' => $finishResponseBody,
                'ci_upload_id' => $ciUploadId,
            ]);
            throw new \RuntimeException("Debricked finish failed with status {$finishStatusCode}: {$finishResponseBody}");
        }
        
        // Handle 204 No Content - success with no response body
        if ($finishStatusCode === 204 || empty($finishResponseBody)) {
            $this->logger->info('Debricked scan finalized successfully', [
                'status_code' => $finishStatusCode,
                'ci_upload_id' => $ciUploadId,
            ]);
            return [
                'ciUploadId' => $ciUploadId,
                'status' => 'finished',
            ];
        }
        
        $finishData = json_decode($finishResponseBody, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error('Failed to parse Debricked finish response', [
                'status_code' => $finishStatusCode,
                'response' => $finishResponseBody,
                'json_error' => json_last_error_msg(),
            ]);
            throw new \RuntimeException("Failed to parse Debricked finish response: " . json_last_error_msg());
        }
        
        $this->logger->info('Debricked scan finalized with response', [
            'status_code' => $finishStatusCode,
            'ci_upload_id' => $ciUploadId,
        ]);

        return [
            'ciUploadId' => $ciUploadId,
            'provider_file_ids' => $providerFileIds,
            'raw' => $finishData,
        ];
    }


    /**
     * Poll scan status from Debricked
     * 
     * @param string $ciUploadId The CI upload ID from Debricked
     * @return array Status data including progress, vulnerabilities, etc.
     */
    public function pollScanStatus(string $ciUploadId): array
    {
        $jwt = $this->auth->getJwtToken();
        
        $statusUrl = "{$this->baseUrl}/open/ci/upload/status?ciUploadId={$ciUploadId}";
        
        $ch = curl_init($statusUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$jwt}",
        ]);
        
        $responseBody = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new \RuntimeException("cURL error on poll status: {$error}");
        }
        
        if ($statusCode >= 400) {
            throw new \RuntimeException("Debricked poll status failed with status {$statusCode}: {$responseBody}");
        }
        
        $data = json_decode($responseBody, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Failed to parse Debricked status response: " . json_last_error_msg());
        }
        
        $this->logger->debug('Debricked scan status polled', [
            'ci_upload_id' => $ciUploadId,
            'progress' => $data['progress'] ?? 0,
            'vulnerabilities_found' => $data['vulnerabilitiesFound'] ?? 0,
        ]);
        
        return [
            'progress' => $data['progress'] ?? 0,
            'scan_completed' => ($data['progress'] ?? 0) >= 100,
            'vulnerabilities_found' => $data['vulnerabilitiesFound'] ?? 0,
            'details_url' => $data['detailsUrl'] ?? null,
            'raw' => $data,
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
