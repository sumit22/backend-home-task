<?php

namespace App\Notification;

use App\Entity\RepositoryScan;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Notification\ChatNotificationInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\RecipientInterface;
use Symfony\Component\Notifier\Bridge\Slack\SlackOptions;

/**
 * Scan Completed Notification
 * 
 * Sent when scan status is completed (any vulnerability count)
 * Note: Emails are now sent via Mailer directly, this notification is only for Slack
 */
class ScanCompletedNotification extends Notification implements ChatNotificationInterface
{
    public function __construct(
        private RepositoryScan $scan
    ) {
        parent::__construct(
            subject: 'Scan Completed Successfully'
        );
        
        $this->importance(Notification::IMPORTANCE_MEDIUM);
    }

    public function asChatMessage(RecipientInterface $recipient, string $transport = null): ?ChatMessage
    {
        $message = new ChatMessage($this->buildSlackMessage());
        
        if ($transport === 'slack') {
            $message->options(new SlackOptions());
        }
        
        return $message;
    }

    private function buildSlackMessage(): string
    {
        $vulnCount = $this->scan->getVulnerabilityCount();
        $duration = $this->calculateDuration();
        
        return sprintf(
            "*Scan Completed Successfully*\n\n" .
            "*Repository:* %s\n" .
            "*Branch:* %s\n" .
            "*Vulnerabilities Found:* %d\n" .
            "*Provider:* %s\n" .
            "*Scan ID:* `%s`\n" .
            "*Duration:* %s\n\n" .
            "%s",
            $this->scan->getRepository()->getName(),
            $this->scan->getBranch() ?? 'main',
            $vulnCount,
            $this->scan->getProviderCode() ?? 'unknown',
            $this->scan->getId(),
            $duration,
            $vulnCount === 0 
                ? '_No vulnerabilities detected._' 
                : '_Review the scan results for details._'
        );
    }

    private function calculateDuration(): string
    {
        $startedAt = $this->scan->getStartedAt();
        $completedAt = $this->scan->getCompletedAt();
        
        if (!$startedAt || !$completedAt) {
            return 'Unknown';
        }
        
        $diff = $completedAt->diff($startedAt);
        
        if ($diff->h > 0) {
            return sprintf('%dh %dm %ds', $diff->h, $diff->i, $diff->s);
        } elseif ($diff->i > 0) {
            return sprintf('%dm %ds', $diff->i, $diff->s);
        } else {
            return sprintf('%ds', $diff->s);
        }
    }
}
