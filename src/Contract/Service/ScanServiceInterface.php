<?php
namespace App\Contract\Service;

use App\Entity\RepositoryScan;
use Symfony\Component\HttpFoundation\File\UploadedFile;

interface ScanServiceInterface
{
    public function createScan(string $repositoryId, ?string $branch = null, ?string $provider = null, ?string $requestedBy = null): RepositoryScan;

    /**
     * Store uploaded files and return array of FilesInScan entities (or their ids).
     *
     * @param string $scanId
     * @param UploadedFile[] $files
     * @param bool $uploadComplete when true, mark scan as 'uploaded' and optionally start provider upload
     * @return array list of FilesInScan entities
     */
    public function handleUploadedFiles(string $scanId, array $files, bool $uploadComplete = false): array;

    /**
     * Enqueue / start provider upload + scan for a scan id (async).
     */
    public function startProviderScan(string $scanId): void;

    public function getScanSummary(string $scanId): ?array;
}
