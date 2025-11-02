<?php

namespace App\Request;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

class UploadFilesRequest
{
    #[Assert\NotNull(message: 'Files array is required')]
    #[Assert\Type('array', message: 'Files must be an array')]
    #[Assert\Count(
        min: 1,
        max: 10,
        minMessage: 'At least one file must be uploaded',
        maxMessage: 'Cannot upload more than {{ limit }} files at once'
    )]
    #[Assert\All([
        new Assert\Type(UploadedFile::class, message: 'Each file must be a valid uploaded file')
    ])]
    private array $files = [];

    #[Assert\Type('bool', message: 'Upload complete flag must be a boolean')]
    private bool $uploadComplete = false;

    public function getFiles(): array
    {
        return $this->files;
    }

    public function setFiles(array $files): self
    {
        $this->files = $files;
        return $this;
    }

    public function isUploadComplete(): bool
    {
        return $this->uploadComplete;
    }

    public function setUploadComplete(bool $uploadComplete): self
    {
        $this->uploadComplete = $uploadComplete;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'files' => $this->files,
            'upload_complete' => $this->uploadComplete,
        ];
    }
}
