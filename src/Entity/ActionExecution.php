<?php

namespace App\Entity;

use App\Repository\ActionExecutionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ActionExecutionRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(columns: ['scan_id'], name: 'idx_action_execution_scan')]
#[ORM\Index(columns: ['rule_id'], name: 'idx_action_execution_rule')]
class ActionExecution
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Rule::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Rule $rule = null;

    #[ORM\ManyToOne(targetEntity: RuleAction::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?RuleAction $ruleAction = null;

    #[ORM\ManyToOne(targetEntity: RepositoryScan::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?RepositoryScan $scan = null;

    #[ORM\ManyToOne(targetEntity: Vulnerability::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Vulnerability $vulnerability = null;

    #[ORM\Column(length: 64)]
    private ?string $status = 'pending';

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $resultPayload = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $finishedAt = null;

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

    public function getRuleAction(): ?RuleAction
    {
        return $this->ruleAction;
    }

    public function setRuleAction(?RuleAction $ruleAction): static
    {
        $this->ruleAction = $ruleAction;

        return $this;
    }

    public function getScan(): ?RepositoryScan
    {
        return $this->scan;
    }

    public function setScan(?RepositoryScan $scan): static
    {
        $this->scan = $scan;

        return $this;
    }

    public function getVulnerability(): ?Vulnerability
    {
        return $this->vulnerability;
    }

    public function setVulnerability(?Vulnerability $vulnerability): static
    {
        $this->vulnerability = $vulnerability;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getResultPayload(): ?array
    {
        return $this->resultPayload;
    }

    public function setResultPayload(?array $resultPayload): static
    {
        $this->resultPayload = $resultPayload;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getFinishedAt(): ?\DateTime
    {
        return $this->finishedAt;
    }

    public function setFinishedAt(?\DateTime $finishedAt): static
    {
        $this->finishedAt = $finishedAt;

        return $this;
    }
}
