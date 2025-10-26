<?php

namespace App\Entity;

use App\Repository\RuleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use App\Entity\Trait\HasTimeStamps;
use App\Entity\Trait\HasId;

#[ORM\Entity(repositoryClass: RuleRepository::class)]
#[ORM\Table(name: 'rule')]
#[ORM\HasLifecycleCallbacks]
class Rule
{
    use HasTimeStamps;
    use HasId;
    
    #[ORM\Column(length: 512)]
    private ?string $name = null;

    #[ORM\Column]
    private ?bool $enabled = true;

    #[ORM\Column(length: 128)]
    private ?string $triggerType = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $triggerPayload = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $scope = null;

    #[ORM\Column]
    private ?bool $autoRemediation = false;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $remediationConfig = null;

    /**
     * @var Collection<int, RuleAction>
     */
    #[ORM\OneToMany(targetEntity: RuleAction::class, mappedBy: 'rule')]
    private Collection $ruleActions;

    public function __construct()
    {
        $this->initializeId();
        $this->ruleActions = new ArrayCollection();
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

    public function isEnabled(): ?bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getTriggerType(): ?string
    {
        return $this->triggerType;
    }

    public function setTriggerType(string $triggerType): static
    {
        $this->triggerType = $triggerType;

        return $this;
    }

    public function getTriggerPayload(): ?array
    {
        return $this->triggerPayload;
    }

    public function setTriggerPayload(?array $triggerPayload): static
    {
        $this->triggerPayload = $triggerPayload;

        return $this;
    }

    public function getScope(): ?string
    {
        return $this->scope;
    }

    public function setScope(?string $scope): static
    {
        $this->scope = $scope;

        return $this;
    }

    public function isAutoRemediation(): ?bool
    {
        return $this->autoRemediation;
    }

    public function setAutoRemediation(bool $autoRemediation): static
    {
        $this->autoRemediation = $autoRemediation;

        return $this;
    }

    public function getRemediationConfig(): ?array
    {
        return $this->remediationConfig;
    }

    public function setRemediationConfig(?array $remediationConfig): static
    {
        $this->remediationConfig = $remediationConfig;

        return $this;
    }

    /**
     * @return Collection<int, RuleAction>
     */
    public function getRuleActions(): Collection
    {
        return $this->ruleActions;
    }

    public function addRuleAction(RuleAction $ruleAction): static
    {
        if (!$this->ruleActions->contains($ruleAction)) {
            $this->ruleActions->add($ruleAction);
            $ruleAction->setRule($this);
        }

        return $this;
    }

    public function removeRuleAction(RuleAction $ruleAction): static
    {
        if ($this->ruleActions->removeElement($ruleAction)) {
            if ($ruleAction->getRule() === $this) {
                $ruleAction->setRule(null);
            }
        }

        return $this;
    }
}
