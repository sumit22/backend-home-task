<?php

namespace App\Entity;

use App\Repository\RepositoryScanRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: RepositoryScanRepository::class)]
#[ORM\HasLifecycleCallbacks]
class RepositoryScan
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Repository::class, inversedBy: 'repositoryScans')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Repository $repository = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $commitSha = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $branch = null;

    #[ORM\Column(length: 64)]
    private ?string $status = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $startedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $finishedAt = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $updatedAt = null;

    /**
     * @var Collection<int, FilesInScan>
     */
    #[ORM\OneToMany(targetEntity: FilesInScan::class, mappedBy: 'scan')]
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
        $this->id = Uuid::v4();
        $this->filesInScans = new ArrayCollection();
        $this->vulnerabilities = new ArrayCollection();
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

    public function getRepository(): ?Repository
    {
        return $this->repository;
    }

    public function setRepository(?Repository $repository): static
    {
        $this->repository = $repository;

        return $this;
    }

    public function getCommitSha(): ?string
    {
        return $this->commitSha;
    }

    public function setCommitSha(?string $commitSha): static
    {
        $this->commitSha = $commitSha;

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

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
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

    public function getFinishedAt(): ?\DateTime
    {
        return $this->finishedAt;
    }

    public function setFinishedAt(?\DateTime $finishedAt): static
    {
        $this->finishedAt = $finishedAt;

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
            $filesInScan->setScan($this);
        }

        return $this;
    }

    public function removeFilesInScan(FilesInScan $filesInScan): static
    {
        if ($this->filesInScans->removeElement($filesInScan)) {
            if ($filesInScan->getScan() === $this) {
                $filesInScan->setScan(null);
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
