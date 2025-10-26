<?php
// src/Controller/RepositoryController.php
namespace App\Controller;

use App\Contract\Service\RepositoryServiceInterface;
use App\Request\CreateRepositoryRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/repositories')]
class RepositoryController extends AbstractController
{
    public function __construct(
        private RepositoryServiceInterface $svc,
        private SerializerInterface $serializer
    ) {}

    #[Route('', methods: ['POST'])]
    public function create(Request $request, ValidatorInterface $validator): JsonResponse
    {
        // Parse JSON content
        $data = json_decode($request->getContent(), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->json(['error' => 'Invalid JSON'], 400);
        }

        // Create and populate request object
        $createRequest = new CreateRepositoryRequest();
        $createRequest->setName($data['name'] ?? null);
        $createRequest->setUrl($data['url'] ?? null);
        $createRequest->setDefaultBranch($data['default_branch'] ?? null);
        
        if (isset($data['notification_settings'])) {
            $createRequest->setSettings($data['notification_settings']);
        }

        // Validate the request
        $errors = $validator->validate($createRequest);
        
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], 400);
        }

        // Create repository using validated data
        $repo = $this->svc->createRepository($createRequest->toArray());
        $json = $this->serializer->serialize($repo, 'json', ['groups' => ['repo:read']]);
        return new JsonResponse($json, 201, [], true);
    }

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = max(1, (int)$request->query->get('page', 1));
        $limit = max(1, (int)$request->query->get('limit', 20));
        $res = $this->svc->listRepositories($page, $limit);
        // serialize data array of entities
        $json = $this->serializer->serialize($res, 'json', ['groups' => ['repo:read']]);
        return new JsonResponse($json, 200, [], true);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        $repo = $this->svc->getRepository($id);
        if (!$repo) return $this->json(['error' => 'not found'], 404);
        $json = $this->serializer->serialize($repo, 'json', ['groups' => ['repo:read']]);
        return new JsonResponse($json, 200, [], true);
    }

    // patch/delete similar: call service then serialize entity
}
