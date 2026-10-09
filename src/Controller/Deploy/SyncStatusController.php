<?php

namespace App\Controller\Deploy;

use App\Attribute\RequireAdmin;
use App\Service\DatabaseHealthService;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Annotation\Route;

/** Sync is run by `php bin/console app:sync:run` (cron); these routes only show its state and switch it on or off. */
#[RequireAdmin]
class SyncStatusController extends AbstractController
{
    #[Route('/api/sync/status', name: 'api_sync_status', methods: ['GET'])]
    public function status(DatabaseHealthService $healthService, ManagerRegistry $registry): JsonResponse
    {
        // Without a remote connection there is nothing to report, so no query is made (the top bar then hides the widget).
        if (!$healthService->isRemoteConfigured()) {
            return $this->json(['configured' => false]);
        }

        $isOnline = $healthService->pingRemote();
        $pending = $this->pendingCount($registry, 'default') + ($isOnline ? $this->pendingCount($registry, 'remote') : 0);

        return $this->json([
            'configured' => true,
            'is_remote_online' => $isOnline,
            'schema_mismatch' => $isOnline && !$healthService->isSchemaMatching(),
            'pending_changes_count' => $pending,
            'last_sync_time' => $healthService->getLastSyncTime(),
            'is_sync_active' => $healthService->isSyncActive(),
        ]);
    }

    #[Route('/api/sync/toggle', name: 'api_sync_toggle', methods: ['POST'])]
    public function toggle(Request $request, DatabaseHealthService $healthService): JsonResponse
    {
        if (!$this->isCsrfTokenValid('sync_toggle', (string) $request->headers->get('X-CSRF-Token'))) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }
        if (!$healthService->isRemoteConfigured()) {
            return $this->json(['success' => false, 'error' => 'No remote database is configured.'], 409);
        }

        $data = json_decode($request->getContent(), true);
        $isActive = is_array($data) && ($data['active'] ?? false) === true;
        $healthService->setSyncActive($isActive);

        return $this->json(['success' => true, 'is_active' => $isActive]);
    }

    private function pendingCount(ManagerRegistry $registry, string $connection): int
    {
        try {
            return (int) $registry->getConnection($connection)->fetchOne('SELECT COUNT(id) FROM sync_log');
        } catch (\Throwable) {
            return 0; // table missing or database unreachable
        }
    }
}
