<?php
// src/Service/FileUploadInterface.php
namespace App\Service;

interface FileUploadInterface
{
    public function upload(array $files, string $repositoryName, string $commitName): array;
}