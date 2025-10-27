<?php
namespace App\Message;

class StartProviderScanMessage
{
    public function __construct(private string $scanId) {}
    public function getScanId(): string { return $this->scanId; }
}
