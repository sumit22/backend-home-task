<?php
namespace App\Message;

class PollProviderScanResultsMessage
{
    public function __construct(
        public string $scanId,
        public int $attemptNumber = 1
    ) {}
}
