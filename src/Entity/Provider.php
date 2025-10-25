<?php

namespace App\Entity;

use App\Repository\ProviderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ProviderRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Provider
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\Column(length: 512)]
    private ?string $name = null;

    #[ORM\Column(length: 128)]
    private ?string $type = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $config = null;

    #[ORM\Column]
    private ?bool $enabled = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $updatedAt = null;

    /**
     * @var Collection<int, Repository>
     */
    #[ORM\OneToMany(targetEntity: Repository::class, mappedBy: 'provider')]
    private Collection $repositories;

    /**
     * @var Collection<int, Integration>
     */
    #[ORM\OneToMany(targetEntity: Integration::class, mappedBy: 'provider')]
    private Collection $integrations;

    /**
     * @var Collection<int, ApiCredential>
     */
    #[ORM\OneToMany(targetEntity: ApiCredential::class, mappedBy: 'provider')]
    private Collection $apiCredentials;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->repositories = new ArrayCollection();
        $this->integrations = new ArrayCollection();
        $this->apiCredentials = new ArrayCollection();
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

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

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    /**
     * @return Collection<int, Repository>
     */
    public function getRepositories(): Collection
    {
        return $this->repositories;
    }

    public function addRepository(Repository $repository): static
    {
        if (!$this->repositories->contains($repository)) {
            $this->repositories->add($repository);
            $repository->setProvider($this);
        }

        return $this;
    }

    public function removeRepository(Repository $repository): static
    {
        if ($this->repositories->removeElement($repository)) {
            if ($repository->getProvider() === $this) {
                $repository->setProvider(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Integration>
     */
    public function getIntegrations(): Collection
    {
        return $this->integrations;
    }

    public function addIntegration(Integration $integration): static
    {
        if (!$this->integrations->contains($integration)) {
            $this->integrations->add($integration);
            $integration->setProvider($this);
        }

        return $this;
    }

    public function removeIntegration(Integration $integration): static
    {
        if ($this->integrations->removeElement($integration)) {
            if ($integration->getProvider() === $this) {
                $integration->setProvider(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ApiCredential>
     */
    public function getApiCredentials(): Collection
    {
        return $this->apiCredentials;
    }

    public function addApiCredential(ApiCredential $apiCredential): static
    {
        if (!$this->apiCredentials->contains($apiCredential)) {
            $this->apiCredentials->add($apiCredential);
            $apiCredential->setProvider($this);
        }

        return $this;
    }

    public function removeApiCredential(ApiCredential $apiCredential): static
    {
        if ($this->apiCredentials->removeElement($apiCredential)) {
            if ($apiCredential->getProvider() === $this) {
                $apiCredential->setProvider(null);
            }
        }

        return $this;
    }
}
