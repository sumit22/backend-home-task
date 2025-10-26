<?php

namespace App\Entity;

use App\Repository\FilesInScanRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use App\Entity\Trait\HasTimeStamps;
use App\Entity\Trait\HasId;

#[ORM\Entity(repositoryClass: FilesInScanRepository::class)]
#[ORM\Table(name: 'files_in_scan')]
#[ORM\Index(columns: ['repository_scan_id'], name: 'idx_files_in_scan_scan')]
#[ORM\Index(columns: ['content_hash'], name: 'idx_files_in_scan_hash')]
#[ORM\HasLifecycleCallbacks]
class FilesInScan
{
    use HasTimeStamps;
    use HasId;

    #[ORM\ManyToOne(targetEntity: RepositoryScan::class, inversedBy: 'filesInScans')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?RepositoryScan $repositoryScan = null;

    #[ORM\Column(length: 1024)]
    private ?string $fileName = null;

    #[ORM\Column(length: 4096)]
    private ?string $filePath = null;

    #[ORM\Column(type: Types::BIGINT)]
    private ?int $size = 0;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $mimeType = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $contentHash = null;

    #[ORM\Column(length: 64)]
    private ?string $status = 'stored';

    /**
     * @var Collection<int, FileScanResult>
     */
    #[ORM\OneToMany(targetEntity: FileScanResult::class, mappedBy: 'file')]
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

    public function getFileName(): ?string
    {
        return $this->fileName;
    }

    public function setFileName(string $fileName): static
    {
        $this->fileName = $fileName;

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

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function setSize(int $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(?string $mimeType): static
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function getContentHash(): ?string
    {
        return $this->contentHash;
    }

    public function setContentHash(?string $contentHash): static
    {
        $this->contentHash = $contentHash;

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
