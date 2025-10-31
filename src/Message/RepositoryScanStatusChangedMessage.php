<?php

namespace App\Message;

/**
 * Messenger message dispatched whenever a RepositoryScan status transitions
 */
class RepositoryScanStatusChangedMessage
{
    public function __construct(
        private string $scanId,
        private string $oldStatus,
        private string $newStatus
    ) {}

    public function getScanId(): string
    {
        return $this->scanId;
    }

    public function getOldStatus(): string
    {
        return $this->oldStatus;
    }

    public function getNewStatus(): string
    {
        return $this->newStatus;
    }
}
