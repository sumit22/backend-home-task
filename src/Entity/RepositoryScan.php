<?php

namespace App\Entity;

use App\Repository\RepositoryScanRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use App\Entity\Trait\HasTimeStamps;
use App\Entity\Trait\HasId;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: RepositoryScanRepository::class)]
#[ORM\Table(name: 'repository_scan')]
#[ORM\Index(columns: ['repository_id'], name: 'idx_repository_scan_repo')]
#[ORM\Index(columns: ['status'], name: 'idx_repository_scan_status')]
#[ORM\Index(columns: ['provider_id'], name: 'idx_repository_scan_provider')]
#[ORM\HasLifecycleCallbacks]
class RepositoryScan
{
    use HasTimeStamps;
    use HasId;

    #[ORM\ManyToOne(targetEntity: Repository::class, inversedBy: 'repositoryScans')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Repository $repository = null;

    #[ORM\Column(length: 128, nullable: true)]
    #[Groups(['scan:read'])]
    private ?string $branch = null;

    #[ORM\Column(length: 256, nullable: true)]
    #[Groups(['scan:read'])]
    private ?string $requestedBy = null;

    #[ORM\ManyToOne(targetEntity: Provider::class)]
    #[ORM\JoinColumn(name: 'provider_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['scan:read'])]
    private ?Provider $provider = null;

    #[ORM\Column(length: 64)]
    #[Groups(['scan:read'])]
    private ?string $status = 'pending';

    #[ORM\Column(length: 64, nullable: true)]
    #[Groups(['scan:read'])]
    private ?string $scanType = null;

    #[ORM\Column(length: 128, nullable: true)]
    #[Groups(['scan:read'])]
    private ?string $scannerVersion = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['scan:read'])]
    private ?int $vulnerabilityCount = 0;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['scan:read'])]
    private ?array $rawSummary = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['scan:read'])]
    private ?\DateTime $startedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['scan:read'])]
    private ?\DateTime $completedAt = null;

    /**
     * @var Collection<int, FilesInScan>
     */
    #[ORM\OneToMany(targetEntity: FilesInScan::class, mappedBy: 'repositoryScan')]
    private Collection $filesInScans;

    /**
     * @var Collection<int, Vulnerability>
     */
    #[ORM\OneToMany(targetEntity: Vulnerability::class, mappedBy: 'scan')]
    private Collection $vulnerabilities;

    #[ORM\OneToOne(targetEntity: ScanResult::class, mappedBy: 'repositoryScan')]
    private ?ScanResult $scanResult = null;

    public function __construct()
    {
        $this->initializeId();
        $this->filesInScans = new ArrayCollection();
        $this->vulnerabilities = new ArrayCollection();
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

    public function getBranch(): ?string
    {
        return $this->branch;
    }

    public function setBranch(?string $branch): static
    {
        $this->branch = $branch;

        return $this;
    }

    public function getRequestedBy(): ?string
    {
        return $this->requestedBy;
    }

    public function setRequestedBy(?string $requestedBy): static
    {
        $this->requestedBy = $requestedBy;

        return $this;
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

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getScanType(): ?string
    {
        return $this->scanType;
    }

    public function setScanType(?string $scanType): static
    {
        $this->scanType = $scanType;

        return $this;
    }

    public function getScannerVersion(): ?string
    {
        return $this->scannerVersion;
    }

    public function setScannerVersion(?string $scannerVersion): static
    {
        $this->scannerVersion = $scannerVersion;

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

    public function getRawSummary(): ?array
    {
        return $this->rawSummary;
    }

    public function setRawSummary(?array $rawSummary): static
    {
        $this->rawSummary = $rawSummary;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getStartedAt(): ?\DateTime
    {
        return $this->startedAt;
    }

    public function setStartedAt(?\DateTime $startedAt): static
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    public function getCompletedAt(): ?\DateTime
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTime $completedAt): static
    {
        $this->completedAt = $completedAt;

        return $this;
    }

    

    /**
     * @return Collection<int, FilesInScan>
     */
    public function getFilesInScans(): Collection
    {
        return $this->filesInScans;
    }

    public function addFilesInScan(FilesInScan $filesInScan): static
    {
        if (!$this->filesInScans->contains($filesInScan)) {
            $this->filesInScans->add($filesInScan);
            $filesInScan->setRepositoryScan($this);
        }

        return $this;
    }

    public function removeFilesInScan(FilesInScan $filesInScan): static
    {
        if ($this->filesInScans->removeElement($filesInScan)) {
            if ($filesInScan->getRepositoryScan() === $this) {
                $filesInScan->setRepositoryScan(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Vulnerability>
     */
    public function getVulnerabilities(): Collection
    {
        return $this->vulnerabilities;
    }

    public function addVulnerability(Vulnerability $vulnerability): static
    {
        if (!$this->vulnerabilities->contains($vulnerability)) {
            $this->vulnerabilities->add($vulnerability);
            $vulnerability->setScan($this);
        }

        return $this;
    }

    public function removeVulnerability(Vulnerability $vulnerability): static
    {
        if ($this->vulnerabilities->removeElement($vulnerability)) {
            if ($vulnerability->getScan() === $this) {
                $vulnerability->setScan(null);
            }
        }

        return $this;
    }

    public function getScanResult(): ?ScanResult
    {
        return $this->scanResult;
    }

    public function setScanResult(?ScanResult $scanResult): static
    {
        // unset the owning side of the relation if necessary
        if ($scanResult === null && $this->scanResult !== null) {
            $this->scanResult->setRepositoryScan(null);
        }

        // set the owning side of the relation if necessary
        if ($scanResult !== null && $scanResult->getRepositoryScan() !== $this) {
            $scanResult->setRepositoryScan($this);
        }

        $this->scanResult = $scanResult;

        return $this;
    }
}
