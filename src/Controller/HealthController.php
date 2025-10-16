<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class HealthController extends AbstractController
{
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
}