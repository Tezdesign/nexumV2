<?php

namespace App\Controller\Project;

use App\Service\AuthService;
use App\Service\Project\AI\ProjectTaskSuggestionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/project/ai', name: 'app_project_ai_')]
final class ProjectAiController extends AbstractController
{
    #[Route('/task-suggestions', name: 'task_suggestions', methods: ['POST'])]
    public function taskSuggestions(
        Request $request,
        AuthService $authService,
        ProjectTaskSuggestionService $projectTaskSuggestionService,
    ): JsonResponse {
        if (!$authService->isManager()) {
            return $this->json(['message' => 'Access denied.'], JsonResponse::HTTP_FORBIDDEN);
        }

        $payload = json_decode((string) $request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['message' => 'Invalid request payload.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $name = trim((string) ($payload['name'] ?? ''));
        $description = isset($payload['description']) ? trim((string) $payload['description']) : null;
        $startRaw = trim((string) ($payload['start_date'] ?? ''));
        $endRaw = trim((string) ($payload['end_date'] ?? ''));

        if ($name === '' || $startRaw === '' || $endRaw === '') {
            return $this->json([
                'message' => 'Project name, start date, and due date are required before requesting AI suggestions.',
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $startDate = new \DateTimeImmutable($startRaw);
            $endDate = new \DateTimeImmutable($endRaw);
        } catch (\Throwable) {
            return $this->json([
                'message' => 'Provide valid project dates before requesting AI suggestions.',
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($endDate < $startDate) {
            return $this->json([
                'message' => 'Project due date must be on or after the start date.',
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $tasks = $projectTaskSuggestionService->suggest($name, $description, $startDate, $endDate);
        } catch (\Throwable $e) {
            return $this->json([
                'message' => $e->getMessage() !== '' ? $e->getMessage() : 'AI task suggestions are unavailable right now.',
            ], JsonResponse::HTTP_BAD_GATEWAY);
        }

        return $this->json([
            'tasks' => $tasks,
        ]);
    }
}
