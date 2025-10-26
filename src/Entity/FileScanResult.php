<?php

namespace App\Entity;

use App\Repository\FileScanResultRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use App\Entity\Trait\HasTimeStamps;
use App\Entity\Trait\HasId;

#[ORM\Entity(repositoryClass: FileScanResultRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(columns: ['file_id'], name: 'idx_file_scan_result_file')]
#[ORM\Index(columns: ['scan_result_id'], name: 'idx_file_scan_result_scan')]
class FileScanResult
{
    use HasTimeStamps;
    use HasId;
    
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

    public function __construct()
    {
        $this->initializeId();
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
}
