<?php

namespace App\Request;

use Symfony\Component\Validator\Constraints as Assert;

class CreateRepositoryRequest
{
    #[Assert\NotBlank(message: 'Repository name is required')]
    #[Assert\Length(
        max: 512,
        maxMessage: 'Repository name cannot be longer than {{ limit }} characters'
    )]
    private ?string $name = null;

    #[Assert\Url(message: 'The URL "{{ value }}" is not a valid URL')]
    #[Assert\Length(
        max: 2048,
        maxMessage: 'URL cannot be longer than {{ limit }} characters'
    )]
    private ?string $url = null;

    #[Assert\Length(
        max: 128,
        maxMessage: 'Default branch name cannot be longer than {{ limit }} characters'
    )]
    private ?string $defaultBranch = null;

    private ?array $settings = null;

    private ?NotificationSettingsRequest $notificationSettings = null;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): self
    {
        $this->url = $url;
        return $this;
    }

    public function getDefaultBranch(): ?string
    {
        return $this->defaultBranch;
    }

    public function setDefaultBranch(?string $defaultBranch): self
    {
        $this->defaultBranch = $defaultBranch;
        return $this;
    }

    public function getSettings(): ?array
    {
        return $this->settings;
    }

    public function setSettings(?array $settings): self
    {
        $this->settings = $settings;
        return $this;
    }

    public function getNotificationSettings(): ?NotificationSettingsRequest
    {
        return $this->notificationSettings;
    }

    public function setNotificationSettings(?NotificationSettingsRequest $notificationSettings): self
    {
        $this->notificationSettings = $notificationSettings;
        return $this;
    }

    public function toArray(): array
    {
        $data = [
            'name' => $this->name,
            'url' => $this->url,
            'default_branch' => $this->defaultBranch,
            'settings' => $this->settings,
        ];

        if ($this->notificationSettings) {
            $data['notification_settings'] = $this->notificationSettings->toArray();
        }

        return $data;
    }
}
