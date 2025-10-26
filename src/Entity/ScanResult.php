<?php

namespace App\Entity;

use App\Repository\ScanResultRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use App\Entity\Trait\HasTimeStamps;
use App\Entity\Trait\HasId;

#[ORM\Entity(repositoryClass: ScanResultRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ScanResult
{
    use HasTimeStamps;
    use HasId;
    
    #[ORM\OneToOne(targetEntity: RepositoryScan::class, inversedBy: 'scanResult')]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private ?RepositoryScan $repositoryScan = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $summaryJson = null;

    #[ORM\Column(length: 64)]
    private ?string $status = 'unknown';

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $vulnerabilityCount = 0;

    /**
     * @var Collection<int, FileScanResult>
     */
    #[ORM\OneToMany(targetEntity: FileScanResult::class, mappedBy: 'scanResult')]
    private Collection $fileScanResults;

    public function __construct()
    {
        $this->initializeId();
        $this->fileScanResults = new ArrayCollection();
    }

    public function getRepositoryScan(): ?RepositoryScan
    {
        return $this->repositoryScan;
    }

    public function setRepositoryScan(?RepositoryScan $repositoryScan): static
    {
        $this->repositoryScan = $repositoryScan;

        return $this;
    }

    public function getSummaryJson(): ?array
    {
        return $this->summaryJson;
    }

    public function setSummaryJson(?array $summaryJson): static
    {
        $this->summaryJson = $summaryJson;

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

    public function getVulnerabilityCount(): ?int
    {
        return $this->vulnerabilityCount;
    }

    public function setVulnerabilityCount(int $vulnerabilityCount): static
    {
        $this->vulnerabilityCount = $vulnerabilityCount;

        return $this;
    }

    /**
     * @return Collection<int, FileScanResult>
     */
    public function getFileScanResults(): Collection
    {
        return $this->fileScanResults;
    }

    public function addFileScanResult(FileScanResult $fileScanResult): static
    {
        if (!$this->fileScanResults->contains($fileScanResult)) {
            $this->fileScanResults->add($fileScanResult);
            $fileScanResult->setScanResult($this);
        }

        return $this;
    }

    public function removeFileScanResult(FileScanResult $fileScanResult): static
    {
        if ($this->fileScanResults->removeElement($fileScanResult)) {
            if ($fileScanResult->getScanResult() === $this) {
                $fileScanResult->setScanResult(null);
            }
        }

        return $this;
    }
}
