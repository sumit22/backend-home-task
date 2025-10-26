<?php

namespace App\Entity;

use App\Repository\ApiCredentialRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use App\Entity\Trait\HasTimeStamps;
use App\Entity\Trait\HasId;

#[ORM\Entity(repositoryClass: ApiCredentialRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(columns: ['provider_id'], name: 'idx_api_credential_provider')]
class ApiCredential
{
    use HasTimeStamps;
    use HasId;

    #[ORM\ManyToOne(targetEntity: Provider::class, inversedBy: 'apiCredentials')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Provider $provider = null;

    #[ORM\Column(type: Types::JSON)]
    private ?array $credentialData = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $lastRotatedAt = null;

    public function __construct()
    {
        $this->initializeId();
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

    public function getCredentialData(): ?array
    {
        return $this->credentialData;
    }

    public function setCredentialData(array $credentialData): static
    {
        $this->credentialData = $credentialData;

        return $this;
    }

    public function getLastRotatedAt(): ?\DateTime
    {
        return $this->lastRotatedAt;
    }

    public function setLastRotatedAt(?\DateTime $lastRotatedAt): static
    {
        $this->lastRotatedAt = $lastRotatedAt;

        return $this;
    }

    
}
