<?php

namespace App\Service\FinancialAnalysis;

use Predis\Client;

class DraftNotificationService
{
    private ?Client $redis = null;

    public function __construct()
    {
        $url = $_ENV['REDIS_URL'] ?? null;
        if ($url) {
            $parsed = parse_url($url);
            $host = $parsed['host'] ?? '127.0.0.1';
            $port = $parsed['port'] ?? 6379;
            $pass = isset($parsed['pass']) ? $parsed['pass'] : '';
            
            $clientParams = [
                'scheme' => 'tcp',
                'host'   => $host,
                'port'   => $port,
                'timeout' => 2.5,
            ];
            
            if (!empty($pass)) {
                $clientParams['password'] = $pass;
            }
            
            $this->redis = new Client($clientParams);
        }
    }

    private function getCacheKey(int $userId): string
    {
        return 'draft_notifications_user_' . $userId;
    }
    
    /**
     * @return array<int, array<string, mixed>>
     */
    private function getNotificationsFromRedis(string $key): array
    {
        if (!$this->redis) return [];
        try {
            $data = $this->redis->get($key);
            if ($data) {
                $decoded = json_decode($data, true);
                return is_array($decoded) ? $decoded : [];
            }
        } catch (\Exception $e) {}
        return [];
    }
    
    /**
     * @param array<int, array<string, mixed>> $notifications
     */
    private function saveNotificationsToRedis(string $key, array $notifications): void
    {
        if (!$this->redis) return;
        try {
            $this->redis->set($key, json_encode($notifications));
        } catch (\Exception $e) {}
    }

    /**
     * Pushes a new notification to the user's persistent cache.
     */
    public function addNotification(int $userId, string $title, string $message, string $type = 'info'): void
    {
        $key = $this->getCacheKey($userId);
        $notifications = $this->getNotificationsFromRedis($key);

        // Add new notification at the beginning
        $notificationId = uniqid('notif_', true);
        array_unshift($notifications, [
            'id' => $notificationId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'timestamp' => time(),
            'delivered' => false, // Used for popping up the slide-down animation once
            'read' => false       // Used for highlighting in the Alerts page
        ]);

        // Keep maximum 50 notifications per user to save disk space
        if (count($notifications) > 50) {
            $notifications = array_slice($notifications, 0, 50);
        }

        $this->saveNotificationsToRedis($key, $notifications);
    }

    /**
     * Gets all undelivered notifications to trigger the popup, and marks them as delivered.
     *
     * @return array<int, array<string, mixed>>
     */
    public function popUndeliveredNotifications(int $userId): array
    {
        $key = $this->getCacheKey($userId);
        $notifications = $this->getNotificationsFromRedis($key);

        if (empty($notifications)) {
            return [];
        }

        $undelivered = [];
        $modified = false;

        foreach ($notifications as &$notif) {
            if (!$notif['delivered']) {
                $undelivered[] = $notif;
                $notif['delivered'] = true;
                $modified = true;
            }
        }
        unset($notif);

        if ($modified) {
            $this->saveNotificationsToRedis($key, $notifications);
        }

        return $undelivered;
    }

    /**
     * Gets all notifications for the user's Alerts page.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAllNotifications(int $userId): array
    {
        $key = $this->getCacheKey($userId);
        return $this->getNotificationsFromRedis($key);
    }

    /**
     * Gets the count of completely unread notifications (for the red badge).
     */
    public function getUnreadCount(int $userId): int
    {
        $key = $this->getCacheKey($userId);
        $notifications = $this->getNotificationsFromRedis($key);

        if (empty($notifications)) {
            return 0;
        }

        $count = 0;
        foreach ($notifications as $notif) {
            if (!$notif['read']) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Marks all notifications as read when the user visits the Alerts page.
     */
    public function markAllAsRead(int $userId): void
    {
        $key = $this->getCacheKey($userId);
        $notifications = $this->getNotificationsFromRedis($key);

        if (empty($notifications)) {
            return;
        }

        $modified = false;

        foreach ($notifications as &$notif) {
            if (!$notif['read']) {
                $notif['read'] = true;
                // If they visit the page before the popup triggers, mark it delivered too
                $notif['delivered'] = true; 
                $modified = true;
            }
        }
        unset($notif);

        if ($modified) {
            $this->saveNotificationsToRedis($key, $notifications);
        }
    }
}
