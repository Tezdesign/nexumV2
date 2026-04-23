<?php

namespace App\Controller\tasks;

use App\Service\AuthService;
use App\Service\Project\Calendar\CalendarEventProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/calendar', name: 'api_calendar_')]
class CalendarController extends AbstractController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly CalendarEventProvider $calendarEventProvider,
    ) {
    }

    #[Route('/events', name: 'events', methods: ['GET'])]
    public function events(Request $request): JsonResponse
    {
        if (!$this->authService->isLoggedIn()) {
            return $this->json(['message' => 'Authentication required.'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $currentUserId = (int) ($this->authService->getCurrentUserId() ?? 0);
        $isManager = $this->authService->isManager();

        $events = $this->calendarEventProvider->getEvents(
            $currentUserId,
            $isManager,
            $this->parseDate($request->query->get('start')),
            $this->parseDate($request->query->get('end')),
            $request->query->getInt('project_id') ?: null,
        );

        return $this->json($events);
    }

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
