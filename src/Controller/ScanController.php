<?php
namespace App\Controller;

use App\Contract\Service\ScanServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;

#[Route('/api')]
class ScanController extends AbstractController
{
    public function __construct(private ScanServiceInterface $scanService) {}

    #[Route('/repositories/{repoId}/scans', name: 'create_scan', methods: ['POST'])]
    public function createScan(string $repoId, Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true) ?: [];
        $branch = $body['branch'] ?? null;
        $provider = $body['provider'] ?? null;
        $requestedBy = $body['requested_by'] ?? null;

        try {
            $scan = $this->scanService->createScan($repoId, $branch, $provider, $requestedBy);
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
    public function uploadFiles(string $scanId, Request $request): JsonResponse
    {
        // upload_complete flag can be form field or query param
        $uploadComplete = filter_var($request->get('upload_complete', $request->request->get('upload_complete', false)), FILTER_VALIDATE_BOOLEAN);

        /** @var UploadedFile[] $files */
        $files = $request->files->all()['files'] ?? [];

        try {
            $entities = $this->scanService->handleUploadedFiles($scanId, $files, $uploadComplete);
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

        $status = $uploadComplete ? 'uploaded' : 'pending';
        $message = $uploadComplete ? 'Upload complete, scan marked ready' : 'Files stored successfully';

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
