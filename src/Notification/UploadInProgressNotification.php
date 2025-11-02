<?php

namespace App\Notification;

use App\Entity\RepositoryScan;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Notification\ChatNotificationInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\RecipientInterface;
use Symfony\Component\Notifier\Bridge\Slack\SlackOptions;

/**
 * Upload In Progress Notification
 * 
 * Sent when scan status is uploaded/queued/running
 * Channels can be configured via constructor (default: chat only - email would be too noisy)
 */
class UploadInProgressNotification extends Notification implements ChatNotificationInterface
{
    public function __construct(
        private RepositoryScan $scan
    ) {
        parent::__construct(
            subject: 'Upload In Progress'
        );
        
        $this->importance(Notification::IMPORTANCE_LOW);
    }

    public function asChatMessage(RecipientInterface $recipient, string $transport = null): ?ChatMessage
    {
        $messageText = sprintf(
            "*Upload In Progress*\n\n" .
            "*Repository:* %s\n" .
            "*Branch:* %s\n" .
            "*Status:* %s\n" .
            "*Provider:* %s\n" .
            "*Scan ID:* `%s`\n" .
            "*Started:* %s",
            $this->scan->getRepository()->getName(),
            $this->scan->getBranch() ?? 'main',
            ucfirst($this->scan->getStatus()),
            $this->scan->getProviderCode() ?? 'unknown',
            $this->scan->getId(),
            $this->scan->getStartedAt()?->format('Y-m-d H:i:s') ?? 'Just now'
        );
        
        $message = new ChatMessage($messageText);
        
        if ($transport === 'slack') {
            $message->options(new SlackOptions());
        }
        
        return $message;
    }
}
