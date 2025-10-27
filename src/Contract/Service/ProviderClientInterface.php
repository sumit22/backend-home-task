<?php

namespace App\Contract\Service;

interface ProviderClientInterface
{
    /**
     * Upload a local file stream to provider and return provider file id + metadata.
     */
    public function uploadFileStream(string $streamPath, array $meta = []): array;

    /**
     * Create/start a scan on the provider using providerFileIds and options.
     * Returns providerScanRecord (array: provider_scan_id, status, ...).
     */
    public function createScan(array $providerFileIds, array $options = []): array;

    /**
     * Fetch scan result/status from provider by providerScanId.
     */
    public function getScanResult(string $providerScanId): array;

    /**
     * Optional: return provider code this client belongs to.
     */
    public function providerCode(): string;
}
