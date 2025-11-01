<?php

namespace App\Message;

class StartProviderScanMessage
{
    public function __construct(
        public readonly string $scanId
    ) {
    }
}
