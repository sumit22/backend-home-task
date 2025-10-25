<?php

namespace App\Entity;

use App\Repository\RuleActionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: RuleActionRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(columns: ['rule_id'], name: 'idx_rule_action_rule')]
class RuleAction
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Rule::class, inversedBy: 'ruleActions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Rule $rule = null;

    #[ORM\Column(length: 64)]
    private ?string $actionType = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $actionPayload = null;

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

    public function getRule(): ?Rule
    {
        return $this->rule;
    }

    public function setRule(?Rule $rule): static
    {
        $this->rule = $rule;

        return $this;
    }

    public function getActionType(): ?string
    {
        return $this->actionType;
    }

    public function setActionType(string $actionType): static
    {
        $this->actionType = $actionType;

        return $this;
    }

    public function getActionPayload(): ?array
    {
        return $this->actionPayload;
    }

    public function setActionPayload(?array $actionPayload): static
    {
        $this->actionPayload = $actionPayload;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }
}
