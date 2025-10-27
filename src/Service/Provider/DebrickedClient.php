<?php
// src/Service/Provider/DebrickedClient.php
namespace App\Service\Provider;

use App\Contract\Service\ProviderClientInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class DebrickedClient implements ProviderClientInterface
{
    private HttpClientInterface $http;
    private string $token;
    private string $base;

    public function __construct(HttpClientInterface $http, string $token, string $base)
    {
        $this->http = $http;
        $this->token = $token;
        $this->base = rtrim($base, '/');
    }

    public function providerCode(): string { return 'debricked'; }

    public function uploadFileStream(string $streamPath, array $meta = []): array
    {
        // Debricked API specifics: assume it supports file uploads (multipart/form-data).
        // Implementation below uses HttpClient::request() with body as stream.
        $response = $this->http->request('POST', $this->base . '/v1/files', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token,
            ],
            'body' => [
                'file' => fopen($streamPath, 'rb'),
                // additional meta fields if needed
            ],
        ]);
        $status = $response->getStatusCode();
        $content = $response->toArray(false);
        if ($status >= 400) {
            throw new \RuntimeException('Debricked upload failed: ' . $status);
        }
        // Example: provider returns { "id": "file_abc", ... }
        return $content;
    }

    public function createScan(array $providerFileIds, array $options = []): array
    {
        $body = [
            'file_ids' => $providerFileIds,
            'branch' => $options['branch'] ?? null,
            'repository' => $options['repository'] ?? null,
        ];
        $response = $this->http->request('POST', $this->base . '/v1/scans', [
            'headers' => ['Authorization' => 'Bearer ' . $this->token, 'Content-Type' => 'application/json'],
            'json' => $body,
        ]);
        return $response->toArray(false);
    }

    public function getScanResult(string $providerScanId): array
    {
        $response = $this->http->request('GET', $this->base . "/v1/scans/{$providerScanId}", [
            'headers' => ['Authorization' => 'Bearer ' . $this->token],
        ]);
        return $response->toArray(false);
    }
}
