<?php

namespace App\Contract\Service;

use App\Entity\RepositoryScan;

/**
 * Adapter: orchestrates client + normalization + mapping persistence.
 */
interface ProviderAdapterInterface
{
    /**
     * Provider code e.g. "debricked"
     */
    public function providerCode(): string;

    /**
     * Upload local files (paths or streams) and create provider scan.
     * Returns ['provider_scan_id' => '...', 'provider_file_ids' => [...], 'raw' => [...]]
     */
    public function uploadAndCreateScan(RepositoryScan $scan, array $localPaths, array $options = []): array;

    /**
     * Normalize provider raw result into canonical structure (for rule engine).
     */
    public function normalizeScanResult(array $raw): array;
}
