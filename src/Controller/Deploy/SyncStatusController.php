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
    public function status(
        DatabaseHealthService $healthService, 
        \Doctrine\Persistence\ManagerRegistry $registry,
        \Symfony\Component\HttpKernel\KernelInterface $kernel
    ): JsonResponse {
        $isOnline = $healthService->pingRemote();
        $isSchemaMatching = $isOnline ? $healthService->isSchemaMatching() : false;
        $isActive = $healthService->isSyncActive();
        $lastSyncTimeStr = $healthService->getLastSyncTime();

        // Get pending changes count directly from raw DBAL to avoid issues if entity isn't fully set up yet
        $pendingCountLocal = 0;
        $pendingCountRemote = 0;
        try {
            $conn = $registry->getConnection('default');
            $pendingCountLocal = (int) $conn->fetchOne('SELECT COUNT(id) FROM sync_log');
        } catch (\Throwable $e) {
            // Table might not exist yet or connection failed
            $pendingCountLocal = 0;
        }

        if ($isOnline) {
            try {
                $remoteConn = $registry->getConnection('remote');
                $pendingCountRemote = (int) $remoteConn->fetchOne('SELECT COUNT(id) FROM sync_log');
            } catch (\Throwable $e) {
                $pendingCountRemote = 0;
            }
        }
        
        $totalPendingCount = $pendingCountLocal + $pendingCountRemote;

        // Auto-Sync Trigger: Immediate if DB changes exist, otherwise fallback 15-minute sync
        if ($isActive && $isOnline && $isSchemaMatching) {
            $shouldRun = false;
            
            if ($totalPendingCount > 0) {
                $shouldRun = true;
            } elseif (!$lastSyncTimeStr) {
                $shouldRun = true;
            } else {
                try {
                    $lastSyncTime = new \DateTime($lastSyncTimeStr);
                    $now = new \DateTime();
                    // 15 minutes = 900 seconds
                    if (($now->getTimestamp() - $lastSyncTime->getTimestamp()) >= 900) {
                        $shouldRun = true;
                    }
                } catch (\Throwable $e) {
                    $shouldRun = true;
                }
            }

            if ($shouldRun) {
                // Update time immediately to prevent concurrent AJAX requests from running it twice
                $healthService->setLastSyncTime((new \DateTime())->format('Y-m-d H:i:s'));
                
                try {
                    $application = new \Symfony\Bundle\FrameworkBundle\Console\Application($kernel);
                    $application->setAutoExit(false);
                    
                    $input = new \Symfony\Component\Console\Input\ArrayInput([
                        'command' => 'app:sync:run',
                    ]);
                    
                    $output = new \Symfony\Component\Console\Output\NullOutput();
                    $application->run($input, $output);
                    
                    // Re-calculate pending count after sync
                    $pendingCountLocal = (int) $registry->getConnection('default')->fetchOne('SELECT COUNT(id) FROM sync_log');
                    $pendingCountRemote = (int) $registry->getConnection('remote')->fetchOne('SELECT COUNT(id) FROM sync_log');
                    $totalPendingCount = $pendingCountLocal + $pendingCountRemote;
                } catch (\Throwable $e) {
                    // Silently fail for the AJAX request so the UI doesn't crash
                }
            }
        }

        // Re-fetch last sync time in case it was updated during the run
        return $this->json([
            'is_remote_online' => $isOnline,
            'schema_mismatch' => !$isSchemaMatching,
            'pending_changes_count' => $totalPendingCount,
            'last_sync_time' => $healthService->getLastSyncTime(),
            'is_sync_active' => $isActive
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