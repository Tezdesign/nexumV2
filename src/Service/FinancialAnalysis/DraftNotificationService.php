<?php

namespace App\Service\FinancialAnalysis;

use Psr\Cache\CacheItemPoolInterface;

class DraftNotificationService
{
    private CacheItemPoolInterface $cache;

    public function __construct(CacheItemPoolInterface $cache)
    {
        // By default, Symfony auto-wires the cache.app pool here
        // which uses the filesystem and survives server restarts
        $this->cache = $cache;
    }

    private function getCacheKey(int $userId): string
    {
        return 'draft_notifications_user_' . $userId;
    }

    /**
     * Pushes a new notification to the user's persistent cache.
     */
    public function addNotification(int $userId, string $title, string $message, string $type = 'info'): void
    {
        $key = $this->getCacheKey($userId);
        $item = $this->cache->getItem($key);

        // Fetch existing notifications or initialize empty array
        $notifications = $item->isHit() ? $item->get() : [];

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

        $item->set($notifications);
        $this->cache->save($item);
    }

    /**
     * Gets all undelivered notifications to trigger the popup, and marks them as delivered.
     */
    public function popUndeliveredNotifications(int $userId): array
    {
        $key = $this->getCacheKey($userId);
        $item = $this->cache->getItem($key);

        if (!$item->isHit()) {
            return [];
        }

        $notifications = $item->get();
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
            $item->set($notifications);
            $this->cache->save($item);
        }

        return $undelivered;
    }

    /**
     * Gets all notifications for the user's Alerts page.
     */
    public function getAllNotifications(int $userId): array
    {
        $key = $this->getCacheKey($userId);
        $item = $this->cache->getItem($key);

        return $item->isHit() ? $item->get() : [];
    }

    /**
     * Gets the count of completely unread notifications (for the red badge).
     */
    public function getUnreadCount(int $userId): int
    {
        $key = $this->getCacheKey($userId);
        $item = $this->cache->getItem($key);

        if (!$item->isHit()) {
            return 0;
        }

        $count = 0;
        foreach ($item->get() as $notif) {
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
        $item = $this->cache->getItem($key);

        if (!$item->isHit()) {
            return;
        }

        $notifications = $item->get();
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
            $item->set($notifications);
            $this->cache->save($item);
        }
    }
}
