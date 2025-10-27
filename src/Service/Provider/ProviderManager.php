<?php
// src/Service/Provider/ProviderManager.php
namespace App\Service\Provider;

use App\Contract\Service\ProviderAdapterInterface;
use Psr\Log\LoggerInterface;

class ProviderManager
{
    /** @var ProviderAdapterInterface[] */
    private array $adapters = [];

    public function __construct(iterable $adapters, private LoggerInterface $logger)
    {
        // autowiring: pass an iterator of all ProviderAdapterInterface services
        foreach ($adapters as $adapter) {
            $this->adapters[$adapter->providerCode()] = $adapter;
        }
    }

    public function getAdapter(?string $providerCode): ?ProviderAdapterInterface
    {
        if ($providerCode === null) {
            // fallback to default provider if configured
            $providerCode = 'debricked';
        }
        return $this->adapters[$providerCode] ?? null;
    }
}
