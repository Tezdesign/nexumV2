<?php

namespace App\Controller\Deploy;

use App\Service\DatabaseHealthService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class SyncStatusController extends AbstractController
{
    #[Route('/api/sync/status', name: 'api_sync_status', methods: ['GET'])]
    public function status(DatabaseHealthService $healthService, EntityManagerInterface $em): JsonResponse
    {
        // Get pending changes count directly from raw DBAL to avoid issues if entity isn't fully set up yet
        $pendingCount = 0;
        try {
            $conn = $em->getConnection();
            $pendingCount = (int) $conn->fetchOne('SELECT COUNT(id) FROM sync_log');
        } catch (\Throwable $e) {
            // Table might not exist yet or connection failed
            $pendingCount = 0;
        }

        $isOnline = $healthService->pingRemote();
        $isSchemaMatching = $isOnline ? $healthService->isSchemaMatching() : false;

        return $this->json([
            'is_remote_online' => $isOnline,
            'schema_mismatch' => !$isSchemaMatching,
            'pending_changes_count' => $pendingCount,
            'last_sync_time' => $healthService->getLastSyncTime(),
            'is_sync_active' => $healthService->isSyncActive()
        ]);
    }
    
    #[Route('/api/sync/toggle', name: 'api_sync_toggle', methods: ['POST'])]
    public function toggle(Request $request, DatabaseHealthService $healthService): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $isActive = (bool) ($data['active'] ?? false);
        
        $healthService->setSyncActive($isActive);
        
        return $this->json(['success' => true, 'is_active' => $isActive]);
    }
}