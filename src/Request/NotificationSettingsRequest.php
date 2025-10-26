<?php

namespace App\Request;

use Symfony\Component\Validator\Constraints as Assert;

class NotificationSettingsRequest
{
    #[Assert\Type('array')]
    #[Assert\All([
        new Assert\Email(message: 'The email "{{ value }}" is not a valid email address')
    ])]
    private ?array $emails = null;

    #[Assert\Type('array')]
    private ?array $slackChannels = null;

    #[Assert\Type('array')]
    #[Assert\All([
        new Assert\Url(message: 'The webhook URL "{{ value }}" is not a valid URL')
    ])]
    private ?array $webhooks = null;

    public function getEmails(): ?array
    {
        return $this->emails;
    }

    public function setEmails(?array $emails): self
    {
        $this->emails = $emails;
        return $this;
    }

    public function getSlackChannels(): ?array
    {
        return $this->slackChannels;
    }

    public function setSlackChannels(?array $slackChannels): self
    {
        $this->slackChannels = $slackChannels;
        return $this;
    }

    public function getWebhooks(): ?array
    {
        return $this->webhooks;
    }

    public function setWebhooks(?array $webhooks): self
    {
        $this->webhooks = $webhooks;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'emails' => $this->emails,
            'slack_channels' => $this->slackChannels,
            'webhooks' => $this->webhooks,
        ];
    }
}
