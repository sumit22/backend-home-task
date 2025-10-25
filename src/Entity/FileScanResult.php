<?php

namespace App\Entity;

use App\Repository\FileScanResultRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: FileScanResultRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(columns: ['file_id'], name: 'idx_file_scan_result_file')]
#[ORM\Index(columns: ['scan_result_id'], name: 'idx_file_scan_result_scan')]
class FileScanResult
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: FilesInScan::class, inversedBy: 'fileScanResults')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?FilesInScan $file = null;

    #[ORM\ManyToOne(targetEntity: ScanResult::class, inversedBy: 'fileScanResults')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ScanResult $scanResult = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $rawPayload = null;

    #[ORM\Column(length: 64)]
    private ?string $status = 'unknown';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $updatedAt = null;

    public function __construct()
    {
        $this->id = Uuid::v4();
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

    public function getFile(): ?FilesInScan
    {
        return $this->file;
    }

    public function setFile(?FilesInScan $file): static
    {
        $this->file = $file;

        return $this;
    }

    public function getScanResult(): ?ScanResult
    {
        return $this->scanResult;
    }

    public function setScanResult(?ScanResult $scanResult): static
    {
        $this->scanResult = $scanResult;

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

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

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
}
