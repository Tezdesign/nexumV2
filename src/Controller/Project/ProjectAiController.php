<?php

namespace App\Controller\Project;

use App\Service\AuthService;
use App\Service\Project\AI\ProjectTaskSuggestionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[Route('/project/ai', name: 'app_project_ai_')]
final class ProjectAiController extends AbstractController
{
    private const MAX_NAME_LENGTH = 255;
    private const MAX_DESCRIPTION_LENGTH = 2000;
    private const MAX_CALLS = 10;
    private const WINDOW_SECONDS = 600;

    #[Route('/task-suggestions', name: 'task_suggestions', methods: ['POST'])]
    public function taskSuggestions(
        Request $request,
        AuthService $authService,
        ProjectTaskSuggestionService $projectTaskSuggestionService,
        CacheInterface $cache,
    ): JsonResponse {
        if (!$authService->isManager()) {
            return $this->json(['message' => 'Access denied.'], JsonResponse::HTTP_FORBIDDEN);
        }

        if ($this->callsExhausted($cache, (int) $authService->getCurrentUserId())) {
            return $this->json(['message' => 'Too many AI requests. Please wait a few minutes.'], JsonResponse::HTTP_TOO_MANY_REQUESTS);
        }

        $payload = json_decode((string) $request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['message' => 'Invalid request payload.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $name = mb_substr(trim((string) ($payload['name'] ?? '')), 0, self::MAX_NAME_LENGTH);
        $description = isset($payload['description'])
            ? mb_substr(trim((string) $payload['description']), 0, self::MAX_DESCRIPTION_LENGTH)
            : null;
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

    /** Counts this call; true once the user has used up their calls for the current window. */
    private function callsExhausted(CacheInterface $cache, int $userId): bool
    {
        $key = 'ai_task_suggestions_calls_' . $userId;
        /** @var int $calls */
        $calls = $cache->get($key, static function (ItemInterface $item): int {
            $item->expiresAfter(self::WINDOW_SECONDS);

            return 0;
        });

        if ($calls >= self::MAX_CALLS) {
            return true;
        }

        // Rewrite with the new count, keeping the window short enough that it still expires.
        $cache->delete($key);
        $cache->get($key, static function (ItemInterface $item) use ($calls): int {
            $item->expiresAfter(self::WINDOW_SECONDS);

            return $calls + 1;
        });

        return false;
    }
}
