<?php

namespace App\Entity;

use App\Repository\FilesInScanRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: FilesInScanRepository::class)]
#[ORM\HasLifecycleCallbacks]
class FilesInScan
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: RepositoryScan::class, inversedBy: 'filesInScans')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?RepositoryScan $scan = null;

    #[ORM\Column(length: 2048)]
    private ?string $filePath = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $fileHash = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $fileSize = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * @var Collection<int, FileScanResult>
     */
    #[ORM\OneToMany(targetEntity: FileScanResult::class, mappedBy: 'file')]
    private Collection $fileScanResults;

    public function __construct()
    {
        $this->id = Uuid::v4();
        $this->fileScanResults = new ArrayCollection();
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

    public function getScan(): ?RepositoryScan
    {
        return $this->scan;
    }

    public function setScan(?RepositoryScan $scan): static
    {
        $this->scan = $scan;

        return $this;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function setFilePath(string $filePath): static
    {
        $this->filePath = $filePath;

        return $this;
    }

    public function getFileHash(): ?string
    {
        return $this->fileHash;
    }

    public function setFileHash(?string $fileHash): static
    {
        $this->fileHash = $fileHash;

        return $this;
    }

    public function getFileSize(): ?int
    {
        return $this->fileSize;
    }

    public function setFileSize(?int $fileSize): static
    {
        $this->fileSize = $fileSize;

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
            $fileScanResult->setFile($this);
        }

        return $this;
    }

    public function removeFileScanResult(FileScanResult $fileScanResult): static
    {
        if ($this->fileScanResults->removeElement($fileScanResult)) {
            if ($fileScanResult->getFile() === $this) {
                $fileScanResult->setFile(null);
            }
        }

        return $this;
    }
}
