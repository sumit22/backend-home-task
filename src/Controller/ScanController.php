<?php
namespace App\Controller;

use App\Contract\Service\ScanServiceInterface;
use App\Request\CreateScanRequest;
use App\Request\UploadFilesRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api')]
class ScanController extends AbstractController
{
    public function __construct(private ScanServiceInterface $scanService) {}

    #[Route('/repositories/{repoId}/scans', name: 'create_scan', methods: ['POST'])]
    public function createScan(string $repoId, Request $request, ValidatorInterface $validator): JsonResponse
    {
        // Parse JSON content
        $data = json_decode($request->getContent(), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->json(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        // Create and populate request object
        $createScanRequest = new CreateScanRequest();
        $createScanRequest->setBranch($data['branch'] ?? null);
        $createScanRequest->setProvider($data['provider'] ?? null);
        $createScanRequest->setRequestedBy($data['requested_by'] ?? null);

        // Validate the request
        $errors = $validator->validate($createScanRequest);
        
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        try {
            $scan = $this->scanService->createScan(
                $repoId, 
                $createScanRequest->getBranch(), 
                $createScanRequest->getProvider(), 
                $createScanRequest->getRequestedBy()
            );
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'id' => $scan->getId(),
            'repository_id' => $scan->getRepository()->getId(),
            'branch' => $scan->getBranch(),
            'status' => $scan->getStatus(),
            'provider_code' => $scan->getProviderCode(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/scans/{scanId}/files', name: 'upload_scan_files', methods: ['POST'])]
    public function uploadFiles(string $scanId, Request $request, ValidatorInterface $validator): JsonResponse
    {
        // Create and populate request object
        $uploadFilesRequest = new UploadFilesRequest();
        
        // Get files from request
        /** @var UploadedFile[] $files */
        $files = $request->files->all()['files'] ?? [];
        $uploadFilesRequest->setFiles($files);
        
        // Get upload_complete flag (can be form field or query param)
        $uploadComplete = filter_var(
            $request->get('upload_complete', $request->request->get('upload_complete', false)), 
            FILTER_VALIDATE_BOOLEAN
        );
        $uploadFilesRequest->setUploadComplete($uploadComplete);

        // Validate the request
        $errors = $validator->validate($uploadFilesRequest);
        
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        try {
            $entities = $this->scanService->handleUploadedFiles(
                $scanId, 
                $uploadFilesRequest->getFiles(), 
                $uploadFilesRequest->isUploadComplete()
            );
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $uploaded = array_map(fn($f) => [
            'id' => $f->getId(),
            'name' => $f->getFileName(),
            'path' => $f->getFilePath(),
            'size' => $f->getSize(),
            'status' => $f->getStatus(),
        ], $entities);

        $status = $uploadFilesRequest->isUploadComplete() ? 'uploaded' : 'pending';
        $message = $uploadFilesRequest->isUploadComplete() ? 'Upload complete, scan marked ready' : 'Files stored successfully';

        return $this->json(['uploaded' => $uploaded, 'status' => $status, 'message' => $message], Response::HTTP_CREATED);
    }

    #[Route('/scans/{scanId}/execute', name: 'execute_scan', methods: ['POST'])]
    public function executeScan(string $scanId): JsonResponse
    {
        try {
            $this->scanService->startProviderScan($scanId);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json(['scan_id' => $scanId, 'status' => 'queued'], Response::HTTP_ACCEPTED);
    }

    #[Route('/repositories/{repoId}/scans/{scanId}', name: 'get_scan_status', methods: ['GET'])]
    public function getScanStatus(string $repoId, string $scanId): JsonResponse
    {
        try {
            $summary = $this->scanService->getScanSummary($scanId);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }

        if (!$summary) {
            return $this->json(['error' => 'Scan not found'], Response::HTTP_NOT_FOUND);
        }

        // Verify the scan belongs to the specified repository
        if ($summary['repository_id'] !== $repoId) {
            return $this->json(['error' => 'Scan does not belong to this repository'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($summary, Response::HTTP_OK);
    }
}
