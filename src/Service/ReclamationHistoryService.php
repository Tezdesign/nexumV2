<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Security;

class ReclamationHistoryService
{
    private LoggerInterface $logger;
    private Security $security;
    private string $logFile;

    public function __construct(LoggerInterface $logger, Security $security, string $projectDir)
    {
        $this->logger = $logger;
        $this->security = $security;
        $this->logFile = $projectDir . '/var/logs/reclamation_history.log';
    }

    /**
     * @param array<string, mixed> $data
     */
    public function logActivity(int $reclamationId, string $action, array $data = []): void
    {
        $user = $this->security->getUser();
        $userId = null;
        if ($user && method_exists($user, 'getId')) {
            $userId = $user->getId();
        }
        
        $logEntry = [
            'timestamp' => (new \DateTime())->format('Y-m-d H:i:s'),
            'reclamation_id' => $reclamationId,
            'action' => $action,
            'user_id' => $userId,
            'user_email' => $user ? $user->getUserIdentifier() : 'system',
            'data' => $data,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];

        // Log to Monolog
        $this->logger->info('Reclamation activity', $logEntry);

        // Also write to dedicated history file
        $this->writeToHistoryFile($logEntry);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getReclamationHistory(int $reclamationId): array
    {
        if (!file_exists($this->logFile)) {
            return [];
        }

        $history = [];
        $lines = file($this->logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }

        foreach ($lines as $line) {
            $entry = json_decode($line, true);
            if (is_array($entry) && isset($entry['reclamation_id']) && $entry['reclamation_id'] == $reclamationId) {
                $history[] = $entry;
            }
        }

        // Sort by timestamp (newest first)
        usort($history, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });

        return $history;
    }

    /**
     * @param array<string, mixed> $logEntry
     */
    private function writeToHistoryFile(array $logEntry): void
    {
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $jsonLine = json_encode($logEntry) . "\n";
        file_put_contents($this->logFile, $jsonLine, FILE_APPEND | LOCK_EX);
    }

    public function getActionLabel(string $action): string
    {
        $labels = [
            'create' => 'Créée',
            'update' => 'Modifiée',
            'status_change' => 'Statut modifié',
            'delete' => 'Supprimée',
            'file_added' => 'Fichier ajouté',
            'file_removed' => 'Fichier supprimé',
            'assigned' => 'Assignée',
            'comment_added' => 'Commentaire ajouté'
        ];

        return $labels[$action] ?? $action;
    }
}
