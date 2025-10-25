<?php

namespace App\Entity;

use App\Repository\NotificationSettingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: NotificationSettingRepository::class)]
#[ORM\HasLifecycleCallbacks]
class NotificationSetting
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Repository::class, inversedBy: 'notificationSettings')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Repository $repository = null;

    #[ORM\Column(length: 64)]
    private ?string $channel = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $config = null;

    #[ORM\Column]
    private ?bool $enabled = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->id = Uuid::v4();
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
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

    public function getChannel(): ?string
    {
        return $this->channel;
    }

    public function setChannel(string $channel): static
    {
        $this->channel = $channel;

        return $this;
    }

    public function getConfig(): ?array
    {
        return $this->config;
    }

    public function setConfig(?array $config): static
    {
        $this->config = $config;

        return $this;
    }

    public function isEnabled(): ?bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
