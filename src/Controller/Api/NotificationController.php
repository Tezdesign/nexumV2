<?php

namespace App\Controller\Api;

use App\Service\AuthService;
use App\Service\FinancialAnalysis\DraftNotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/desktop/notifications', name: 'api_desktop_notifications_')]
class NotificationController extends AbstractController
{
    public function __construct(
        private AuthService $authService,
        private DraftNotificationService $notificationService
    ) {}

    #[Route('/unread', name: 'unread', methods: ['GET'])]
    public function getUnreadCount(): JsonResponse
    {
        if (!$this->authService->isLoggedIn()) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $userId = (int) $this->authService->getCurrentUserId();
        $count = $this->notificationService->getUnreadCount($userId);

        return $this->json(['unreadCount' => $count]);
    }

    #[Route('/list', name: 'list', methods: ['GET'])]
    public function getList(): JsonResponse
    {
        if (!$this->authService->isLoggedIn()) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $userId = (int) $this->authService->getCurrentUserId();
        
        // Fetch all notifications for the dropdown
        $notifications = $this->notificationService->getAllNotifications($userId);
        
        // Mark them as read in the Redis cache so the unread count drops to 0
        $this->notificationService->markAllAsRead($userId);

        $html = $this->renderView('partials/_notification_item.html.twig', [
            'notifications' => $notifications
        ]);

        return $this->json(['html' => $html]);
    }
}
