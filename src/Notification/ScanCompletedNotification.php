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
 * Scan Completed Notification
 * 
 * Sent when scan status is completed (any vulnerability count)
 * Channels can be configured via constructor (default: email + chat)
 */
class ScanCompletedNotification extends Notification implements EmailNotificationInterface, ChatNotificationInterface
{
    public function __construct(
        private RepositoryScan $scan,
        array $channels = ['email', 'chat']
    ) {
        $vulnCount = $scan->getVulnerabilityCount();
        $subject = $vulnCount === 0 
            ? '🎉 Congratulations! No Vulnerabilities Found' 
            : '✅ Scan Completed Successfully';
            
        parent::__construct(
            subject: $subject,
            channels: $channels
        );
        
        $this->importance(Notification::IMPORTANCE_MEDIUM);
    }

    public function asEmailMessage(EmailRecipientInterface $recipient, string $transport = null): ?EmailMessage
    {
        $email = (new TemplatedEmail())
            ->to($recipient->getEmail())
            ->subject($this->getSubject())
            ->htmlTemplate('emails/scan_completed.html.twig')
            ->context([
                'repository_name' => $this->scan->getRepository()->getName(),
                'branch' => $this->scan->getBranch() ?? 'main',
                'vulnerability_count' => $this->scan->getVulnerabilityCount(),
                'provider_code' => $this->scan->getProviderCode() ?? 'unknown',
                'scan_id' => $this->scan->getId(),
                'started_at' => $this->scan->getStartedAt()?->format('Y-m-d H:i:s') ?? 'Unknown',
                'completed_at' => $this->scan->getCompletedAt()?->format('Y-m-d H:i:s') ?? 'Just now',
                'duration' => $this->calculateDuration(),
            ]);
        
        return new EmailMessage($email);
    }

    public function asChatMessage(RecipientInterface $recipient, string $transport = null): ?ChatMessage
    {
        $message = new ChatMessage($this->buildSlackMessage());
        
        if ($transport === 'slack') {
            $vulnCount = $this->scan->getVulnerabilityCount();
            $emoji = $vulnCount === 0 ? ':white_check_mark:' : ':large_blue_circle:';
            
            $message->options(
                (new SlackOptions())
                    ->iconEmoji($emoji)
            );
        }
        
        return $message;
    }

    private function buildSlackMessage(): string
    {
        $vulnCount = $this->scan->getVulnerabilityCount();
        $duration = $this->calculateDuration();
        $emoji = $vulnCount === 0 ? '🎉' : '✅';
        
        return sprintf(
            "%s *Scan Completed Successfully*\n\n" .
            "*Repository:* %s\n" .
            "*Branch:* %s\n" .
            "*Vulnerabilities Found:* %d\n" .
            "*Provider:* %s\n" .
            "*Scan ID:* `%s`\n" .
            "*Duration:* %s\n\n" .
            "%s",
            $emoji,
            $this->scan->getRepository()->getName(),
            $this->scan->getBranch() ?? 'main',
            $vulnCount,
            $this->scan->getProviderCode() ?? 'unknown',
            $this->scan->getId(),
            $duration,
            $vulnCount === 0 
                ? '🎉 _No vulnerabilities detected!_' 
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
