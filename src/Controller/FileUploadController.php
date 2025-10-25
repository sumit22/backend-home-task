<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class FileUploadController extends AbstractController
{

    #[Route('/upload', name: 'app_file_upload', methods: ['POST'])]
    public function upload(): JsonResponse
    {
        // Implement file upload handling logic here

        return $this->json([
            'success' => true,
            'message' => 'File uploaded successfully.',
        ]);

        
    }
    
}
