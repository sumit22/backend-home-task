<?php

namespace App\Notification;

use App\Entity\RepositoryScan;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Email;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\EmailMessage;
use Symfony\Component\Notifier\Notification\ChatNotificationInterface;
use Symfony\Component\Notifier\Notification\EmailNotificationInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\EmailRecipientInterface;
use Symfony\Component\Notifier\Recipient\RecipientInterface;
use Symfony\Component\Notifier\Bridge\Slack\SlackOptions;

/**
 * Scan Failed Notification
 * 
 * Sent when scan status is failed or timeout
 * Channels can be configured via constructor (default: email + chat)
 */
class ScanFailedNotification extends Notification implements EmailNotificationInterface, ChatNotificationInterface
{
    public function __construct(
        private RepositoryScan $scan,
        array $channels = ['email', 'chat']
    ) {
        parent::__construct(
            subject: '❌ Scan Failed',
            channels: $channels
        );
        
        $this->importance(Notification::IMPORTANCE_HIGH);
    }

    public function asEmailMessage(EmailRecipientInterface $recipient, string $transport = null): ?EmailMessage
    {
        $email = (new TemplatedEmail())
            ->to($recipient->getEmail())
            ->subject($this->getSubject())
            ->htmlTemplate('emails/scan_failed.html.twig')
            ->context([
                'repository_name' => $this->scan->getRepository()->getName(),
                'branch' => $this->scan->getBranch() ?? 'main',
                'status' => $this->scan->getStatus(),
                'provider_code' => $this->scan->getProviderCode() ?? 'unknown',
                'scan_id' => $this->scan->getId(),
                'started_at' => $this->scan->getStartedAt()?->format('Y-m-d H:i:s') ?? 'Unknown',
                'completed_at' => $this->scan->getCompletedAt()?->format('Y-m-d H:i:s'),
            ]);
        
        return new EmailMessage($email);
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
        $emoji = $status === 'timeout' ? '⏱️' : '❌';
        $failureReason = $status === 'timeout' ? 'Scan timed out' : 'Scan failed';
        
        return sprintf(
            "%s *Scan Failed*\n\n" .
            "*Repository:* %s\n" .
            "*Branch:* %s\n" .
            "*Status:* %s\n" .
            "*Reason:* %s\n" .
            "*Provider:* %s\n" .
            "*Scan ID:* `%s`\n" .
            "*Started:* %s\n" .
            "*Failed At:* %s\n\n" .
            "⚠️ _Please investigate and retry if necessary._",
            $emoji,
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
