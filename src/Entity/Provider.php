<?php

namespace App\Entity;

use App\Repository\ProviderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use App\Entity\Trait\HasTimeStamps;
use App\Entity\Trait\HasId;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ProviderRepository::class)]
#[ORM\Table(name: 'provider')]
#[ORM\HasLifecycleCallbacks]
class Provider
{
    use HasTimeStamps;
    use HasId;

    #[ORM\Column(length: 128, unique: true)]
    #[Groups(['provider:read'])]
    private ?string $code = null;

    #[ORM\Column(length: 255)]
    #[Groups(['provider:read'])]
    private ?string $name = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['provider:read'])]
    private ?array $config = null;

    

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
        $this->initializeId();
        $this->integrations = new ArrayCollection();
        $this->apiCredentials = new ArrayCollection();
    }

    

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

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

    public function getConfig(): ?array
    {
        return $this->config;
    }

    public function setConfig(?array $config): static
    {
        $this->config = $config;

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
