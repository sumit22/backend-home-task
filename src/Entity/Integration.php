<?php

namespace App\Entity;

use App\Repository\IntegrationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use App\Entity\Trait\HasTimeStamps;
use App\Entity\Trait\HasId;

#[ORM\Entity(repositoryClass: IntegrationRepository::class)]
#[ORM\Table(name: 'integration')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(columns: ['provider_id'], name: 'idx_integration_provider')]
#[ORM\Index(columns: ['linked_entity_type', 'linked_entity_id'], name: 'idx_integration_linked')]
#[ORM\Index(columns: ['type'], name: 'idx_integration_type')]
class Integration
{
    use HasTimeStamps;
    use HasId;

    #[ORM\ManyToOne(targetEntity: Provider::class, inversedBy: 'integrations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Provider $provider = null;

    #[ORM\Column(length: 1024)]
    private ?string $externalId = null;

    #[ORM\Column(length: 64)]
    private ?string $type = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $linkedEntityType = null;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $linkedEntityId = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $status = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $rawPayload = null;

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

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(string $externalId): static
    {
        $this->externalId = $externalId;

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

    public function getLinkedEntityType(): ?string
    {
        return $this->linkedEntityType;
    }

    public function setLinkedEntityType(?string $linkedEntityType): static
    {
        $this->linkedEntityType = $linkedEntityType;

        return $this;
    }

    public function getLinkedEntityId(): ?Uuid
    {
        return $this->linkedEntityId;
    }

    public function setLinkedEntityId(?Uuid $linkedEntityId): static
    {
        $this->linkedEntityId = $linkedEntityId;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getRawPayload(): ?array
    {
        return $this->rawPayload;
    }

    public function setRawPayload(?array $rawPayload): static
    {
        $this->rawPayload = $rawPayload;

        return $this;
    }

    
}
