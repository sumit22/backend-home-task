<?php

namespace App\Notification;

use App\Entity\RepositoryScan;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Notification\ChatNotificationInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\RecipientInterface;
use Symfony\Component\Notifier\Bridge\Slack\SlackOptions;

/**
 * Scan Failed Notification
 * 
 * Sent when scan status is failed or timeout
 * Note: Emails are now sent via Mailer directly, this notification is only for Slack
 */
class ScanFailedNotification extends Notification implements ChatNotificationInterface
{
    public function __construct(
        private RepositoryScan $scan
    ) {
        parent::__construct(
            subject: 'Scan Failed'
        );
        
        $this->importance(Notification::IMPORTANCE_HIGH);
    }

    public function asChatMessage(RecipientInterface $recipient, string $transport = null): ?ChatMessage
    {
        $message = new ChatMessage($this->buildSlackMessage());
        
        if ($transport === 'slack') {
            $message->options(
                (new SlackOptions())
                    ->iconEmoji(':x:')
            );
        }
        
        return $message;
    }

    private function buildSlackMessage(): string
    {
        $status = $this->scan->getStatus();
        $failureReason = $status === 'timeout' ? 'Scan timed out' : 'Scan failed';
        
        return sprintf(
            "*Scan Failed*\n\n" .
            "*Repository:* %s\n" .
            "*Branch:* %s\n" .
            "*Status:* %s\n" .
            "*Reason:* %s\n" .
            "*Provider:* %s\n" .
            "*Scan ID:* `%s`\n" .
            "*Started:* %s\n" .
            "*Failed At:* %s\n\n" .
            "_Please investigate and retry if necessary._",
            $this->scan->getRepository()->getName(),
            $this->scan->getBranch() ?? 'main',
            ucfirst($status),
            $failureReason,
            $this->scan->getProviderCode() ?? 'unknown',
            $this->scan->getId(),
            $this->scan->getStartedAt()?->format('Y-m-d H:i:s') ?? 'Unknown',
            $this->scan->getCompletedAt()?->format('Y-m-d H:i:s') ?? 'Just now'
        );
    }
}
