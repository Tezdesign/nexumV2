<?php

namespace App\Service;

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class DatabaseHealthService
{
    public function __construct(
        private ManagerRegistry $registry,
        private CacheInterface $cache
    ) {}

    public const ACTIVE_KEY = 'database_auto_sync_active';

    /** True when a `remote` Doctrine connection exists (it was removed from doctrine.yaml, so today this is false). */
    public function isRemoteConfigured(): bool
    {
        return array_key_exists('remote', $this->registry->getConnectionNames());
    }

    public function pingRemote(): bool
    {
        return $this->cache->get('remote_db_ping', function (ItemInterface $item) {
            $item->expiresAfter(300); // 5 minutes cache
            try {
                $conn = $this->registry->getConnection('remote');
                $conn->executeQuery('SELECT 1');
                return true;
            } catch (\Throwable $e) {
                return false;
            }
        });
    }

    public function isSchemaMatching(): bool
    {
        return $this->cache->get('remote_db_schema_match', function (ItemInterface $item) {
            $item->expiresAfter(300); // 5 minutes cache
            
            try {
                $localConn = $this->registry->getConnection('default');
                $remoteConn = $this->registry->getConnection('remote');

                $localSm = $localConn->createSchemaManager();
                $remoteSm = $remoteConn->createSchemaManager();

                $tablesToCheck = ['expense_draft', 'transaction'];

                foreach ($tablesToCheck as $tableName) {
                    if (!$localSm->tablesExist([$tableName]) || !$remoteSm->tablesExist([$tableName])) {
                        return false;
                    }

                    $localColumns = $localSm->listTableColumns($tableName);
                    $remoteColumns = $remoteSm->listTableColumns($tableName);

                    if (count($localColumns) !== count($remoteColumns)) {
                        return false;
                    }

                    foreach ($localColumns as $colName => $localCol) {
                        if (!isset($remoteColumns[$colName])) {
                            return false;
                        }
                        // Compare pure physical database types (e.g. integer vs bigint)
                        // Bypassing PHP Doctrine Entity definitions to prevent false alarms
                        if (get_class($localCol->getType()) !== get_class($remoteColumns[$colName]->getType())) {
                            return false;
                        }
                    }
                }
                return true;
            } catch (\Throwable $e) {
                return false; // Mismatch or unreachable
            }
        });
    }
    
    public function isSyncActive(): bool
    {
        return $this->cache->get(self::ACTIVE_KEY, function() {
            return false;
        });
    }
    
    public function setSyncActive(bool $isActive): void
    {
        $this->cache->delete(self::ACTIVE_KEY);
        $this->cache->get(self::ACTIVE_KEY, function(ItemInterface $item) use ($isActive) {
            return $isActive;
        });
    }
    
    public function getLastSyncTime(): ?string
    {
        return $this->cache->get('database_last_sync_time', function() {
            return null;
        });
    }
    
    public function setLastSyncTime(string $time): void
    {
        $this->cache->delete('database_last_sync_time');
        $this->cache->get('database_last_sync_time', function(ItemInterface $item) use ($time) {
            return $time;
        });
    }
}