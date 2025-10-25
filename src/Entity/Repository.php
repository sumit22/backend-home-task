<?php

namespace App\Entity;

use App\Repository\RepositoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: RepositoryRepository::class)]
#[ORM\Table(name: 'repository')]
#[ORM\HasLifecycleCallbacks]
class Repository
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Provider::class, inversedBy: 'repositories')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Provider $provider = null;

    #[ORM\Column(length: 1024)]
    private ?string $name = null;

    #[ORM\Column(length: 2048, nullable: true)]
    private ?string $fullPath = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $defaultBranch = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $updatedAt = null;

    /**
     * @var Collection<int, NotificationSetting>
     */
    #[ORM\OneToMany(targetEntity: NotificationSetting::class, mappedBy: 'repository')]
    private Collection $notificationSettings;

    /**
     * @var Collection<int, RepositoryScan>
     */
    #[ORM\OneToMany(targetEntity: RepositoryScan::class, mappedBy: 'repository')]
    private Collection $repositoryScans;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->notificationSettings = new ArrayCollection();
        $this->repositoryScans = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getProvider(): ?Provider
    {
        return $this->provider;
    }

    public function setProvider(?Provider $provider): static
    {
        $this->provider = $provider;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getFullPath(): ?string
    {
        return $this->fullPath;
    }

    public function setFullPath(?string $fullPath): static
    {
        $this->fullPath = $fullPath;

        return $this;
    }

    public function getDefaultBranch(): ?string
    {
        return $this->defaultBranch;
    }

    public function setDefaultBranch(?string $defaultBranch): static
    {
        $this->defaultBranch = $defaultBranch;

        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    /**
     * @return Collection<int, NotificationSetting>
     */
    public function getNotificationSettings(): Collection
    {
        return $this->notificationSettings;
    }

    public function addNotificationSetting(NotificationSetting $notificationSetting): static
    {
        if (!$this->notificationSettings->contains($notificationSetting)) {
            $this->notificationSettings->add($notificationSetting);
            $notificationSetting->setRepository($this);
        }

        return $this;
    }

    public function removeNotificationSetting(NotificationSetting $notificationSetting): static
    {
        if ($this->notificationSettings->removeElement($notificationSetting)) {
            if ($notificationSetting->getRepository() === $this) {
                $notificationSetting->setRepository(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, RepositoryScan>
     */
    public function getRepositoryScans(): Collection
    {
        return $this->repositoryScans;
    }

    public function addRepositoryScan(RepositoryScan $repositoryScan): static
    {
        if (!$this->repositoryScans->contains($repositoryScan)) {
            $this->repositoryScans->add($repositoryScan);
            $repositoryScan->setRepository($this);
        }

        return $this;
    }

    public function removeRepositoryScan(RepositoryScan $repositoryScan): static
    {
        if ($this->repositoryScans->removeElement($repositoryScan)) {
            if ($repositoryScan->getRepository() === $this) {
                $repositoryScan->setRepository(null);
            }
        }

        return $this;
    }
}
