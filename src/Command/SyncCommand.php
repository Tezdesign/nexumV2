<?php

namespace App\Command;

use App\Service\DatabaseHealthService;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:sync:run', description: 'Bidirectional synchronization of pending changes between local and remote databases.')]
class SyncCommand extends Command
{
    public function __construct(
        private ManagerRegistry $registry,
        private DatabaseHealthService $healthService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->healthService->isSyncActive()) {
            $output->writeln('Sync is disabled via UI toggle.');
            return Command::SUCCESS;
        }

        if (!$this->healthService->pingRemote()) {
            $output->writeln('<error>Remote database is offline.</error>');
            return Command::FAILURE;
        }

        if (!$this->healthService->isSchemaMatching()) {
            $output->writeln('<error>Schema mismatch detected. Sync aborted.</error>');
            return Command::FAILURE;
        }

        $localConn = $this->registry->getConnection('default');
        $remoteConn = $this->registry->getConnection('remote');

        $output->writeln('Starting bidirectional sync...');

        // ==========================================
        // STEP 1: PULL (Remote -> Local)
        // ==========================================
        $output->writeln('Fetching remote changes to pull...');
        $remoteLogs = [];
        try {
            $remoteLogs = $remoteConn->fetchAllAssociative('SELECT * FROM sync_log ORDER BY created_at ASC LIMIT 100');
        } catch (\Throwable $e) {
            $output->writeln('<comment>Remote sync_log table might not exist yet. Skipping pull.</comment>');
        }

        if (!empty($remoteLogs)) {
            $output->writeln(sprintf('Pulling %d changes from remote...', count($remoteLogs)));
            foreach ($remoteLogs as $log) {
                $tableName = $this->getTableName($log['entity_class']);
                if (!$tableName) continue;

                try {
                    if ($log['action_type'] === 'DELETE') {
                        $localConn->delete($tableName, ['id' => $log['entity_id']]);
                    } else {
                        // INSERT or UPDATE
                        $remoteRecord = $remoteConn->fetchAssociative(sprintf('SELECT * FROM %s WHERE id = ?', $tableName), [$log['entity_id']]);
                        if ($remoteRecord) {
                            $localExists = $localConn->fetchOne(sprintf('SELECT id FROM %s WHERE id = ?', $tableName), [$log['entity_id']]);
                            if ($localExists) {
                                $localConn->update($tableName, $remoteRecord, ['id' => $log['entity_id']]);
                            } else {
                                $localConn->insert($tableName, $remoteRecord);
                            }
                        }
                    }
                    // Remove log from remote
                    $remoteConn->delete('sync_log', ['id' => $log['id']]);
                } catch (\Throwable $e) {
                    $output->writeln(sprintf('<error>Failed to pull log %d: %s</error>', $log['id'], $e->getMessage()));
                }
            }
        }

        // ==========================================
        // STEP 2: PUSH (Local -> Remote)
        // ==========================================
        $output->writeln('Fetching local changes to push...');
        $localLogs = [];
        try {
            $localLogs = $localConn->fetchAllAssociative('SELECT * FROM sync_log ORDER BY created_at ASC LIMIT 100');
        } catch (\Throwable $e) {
            $output->writeln('<error>Local sync_log table not found. Aborting push.</error>');
            return Command::FAILURE;
        }

        if (!empty($localLogs)) {
            $output->writeln(sprintf('Pushing %d changes to remote...', count($localLogs)));
            foreach ($localLogs as $log) {
                $tableName = $this->getTableName($log['entity_class']);
                if (!$tableName) continue;

                try {
                    if ($log['action_type'] === 'DELETE') {
                        $remoteConn->delete($tableName, ['id' => $log['entity_id']]);
                    } else {
                        // INSERT or UPDATE
                        $localRecord = $localConn->fetchAssociative(sprintf('SELECT * FROM %s WHERE id = ?', $tableName), [$log['entity_id']]);
                        if ($localRecord) {
                            $remoteExists = $remoteConn->fetchOne(sprintf('SELECT id FROM %s WHERE id = ?', $tableName), [$log['entity_id']]);
                            if ($remoteExists) {
                                $output->writeln(sprintf('<info>Pushing update for %s ID %d to remote.</info>', $tableName, $log['entity_id']));
                                $remoteConn->update($tableName, $localRecord, ['id' => $log['entity_id']]);
                            } else {
                                $output->writeln(sprintf('<info>Pushing insert for %s ID %d to remote.</info>', $tableName, $log['entity_id']));
                                $remoteConn->insert($tableName, $localRecord);
                            }
                        }
                    }
                    // Remove log from local
                    $localConn->delete('sync_log', ['id' => $log['id']]);
                } catch (\Throwable $e) {
                    $output->writeln(sprintf('<error>Failed to push log %d: %s</error>', $log['id'], $e->getMessage()));
                }
            }
        }

        // Update the last sync time
        $this->healthService->setLastSyncTime((new \DateTime())->format('Y-m-d H:i:s'));
        $output->writeln('<info>Bidirectional sync completed successfully.</info>');

        return Command::SUCCESS;
    }

    private function getTableName(string $entityClass): string
    {
        if (str_contains($entityClass, 'ExpenseDraft')) {
            return 'expense_draft';
        } elseif (str_contains($entityClass, 'Transaction')) {
            return 'transaction';
        } elseif (str_contains($entityClass, 'ProjectBudget')) {
            return 'project_budget';
        }
        return '';
    }
}