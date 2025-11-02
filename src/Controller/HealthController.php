<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use League\Flysystem\FilesystemOperator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HealthController extends AbstractController
{
    public function __construct(
        private readonly Connection $connection,
        private readonly FilesystemOperator $defaultStorage
    ) {
    }

    #[Route('/', name: 'app_home')]
    public function index(): JsonResponse
    {
        return $this->json([
            'message' => 'Welcome to the Debricked Backend Home Task!',
            'status' => 'healthy',
            'timestamp' => date('c')
        ]);
    }

    #[Route('/health', name: 'app_health')]
    public function health(): JsonResponse
    {
        return $this->json([
            'status' => 'healthy',
            'services' => [
                'database' => 'available',
                'rabbitmq' => 'available',
                'mailhog' => 'available'
            ],
            'timestamp' => date('c')
        ]);
    }

    /**
     * Liveness probe - checks if the application is alive and should not be restarted.
     * Returns 200 if the application is running, 503 if it should be restarted.
     */
    #[Route('/health/live', name: 'app_health_live', methods: ['GET'])]
    public function live(): JsonResponse
    {
        // Simple check - if we can respond, we're alive
        return $this->json([
            'status' => 'alive',
            'timestamp' => date('c')
        ]);
    }

    /**
     * Readiness probe - checks if the application is ready to accept traffic.
     * Returns 200 if ready, 503 if not ready (dependencies unavailable).
     */
    #[Route('/health/ready', name: 'app_health_ready', methods: ['GET'])]
    public function ready(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'filesystem' => $this->checkFilesystem(),
        ];

        $allHealthy = !in_array(false, $checks, true);
        $status = $allHealthy ? 'ready' : 'not_ready';
        $httpStatus = $allHealthy ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE;

        return $this->json([
            'status' => $status,
            'checks' => $checks,
            'timestamp' => date('c')
        ], $httpStatus);
    }

    private function checkDatabase(): bool
    {
        try {
            $this->connection->executeQuery('SELECT 1');
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function checkFilesystem(): bool
    {
        try {
            // Check if we can access the filesystem
            $this->defaultStorage->fileExists('.gitkeep');
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}