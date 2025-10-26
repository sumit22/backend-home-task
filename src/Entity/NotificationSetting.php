<?php

namespace App\Entity;

use App\Repository\NotificationSettingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use App\Entity\Trait\HasTimeStamps;
use App\Entity\Trait\HasId;

#[ORM\Entity(repositoryClass: NotificationSettingRepository::class)]
#[ORM\Table(name: 'notification_setting')]
#[ORM\HasLifecycleCallbacks]
class NotificationSetting
{
    use HasTimeStamps;
    use HasId;

    #[ORM\ManyToOne(targetEntity: Repository::class, inversedBy: 'notificationSettings')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Repository $repository = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $emails = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $slackChannels = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $webhooks = null;

    public function __construct()
    {
        $this->initializeId();
    }

    

    public function getRepository(): ?Repository
    {
        return $this->repository;
    }

    public function setRepository(?Repository $repository): static
    {
        $this->repository = $repository;

        return $this;
    }

    public function getEmails(): ?array
    {
        return $this->emails;
    }

    public function setEmails(?array $emails): static
    {
        $this->emails = $emails;

        return $this;
    }

    public function getSlackChannels(): ?array
    {
        return $this->slackChannels;
    }

    public function setSlackChannels(?array $slackChannels): static
    {
        $this->slackChannels = $slackChannels;

        return $this;
    }

    public function getWebhooks(): ?array
    {
        return $this->webhooks;
    }

    public function setWebhooks(?array $webhooks): static
    {
        $this->webhooks = $webhooks;

        return $this;
    }

    
}
