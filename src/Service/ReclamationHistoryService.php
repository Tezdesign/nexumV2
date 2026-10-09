<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class ReclamationHistoryService
{
    private string $historyDir;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly AuthService $authService,
        private readonly RequestStack $requestStack,
        string $projectDir,
    ) {
        // One small file per reclamation: reading a history never scans the others.
        $this->historyDir = $projectDir . '/var/reclamation_history';
    }

    /**
     * @param array<string, mixed> $data
     */
    public function logActivity(int $reclamationId, string $action, array $data = []): void
    {
        $actor = $this->authService->getCurrentUser();
        $entry = [
            'timestamp' => (new \DateTime())->format('Y-m-d H:i:s'),
            'reclamation_id' => $reclamationId,
            'action' => $action,
            'user_id' => $this->authService->getCurrentUserId(),
            'user_email' => is_array($actor) ? (string) ($actor['email'] ?? 'system') : 'system',
            'data' => $data,
            'ip' => $this->requestStack->getCurrentRequest()?->getClientIp() ?? 'unknown',
        ];

        $this->logger->info('Reclamation activity', $entry);

        if (!is_dir($this->historyDir) && !@mkdir($this->historyDir, 0775, true) && !is_dir($this->historyDir)) {
            $this->logger->error('Reclamation history folder cannot be created', ['dir' => $this->historyDir]);

            return;
        }
        if (@file_put_contents($this->fileFor($reclamationId), json_encode($entry) . "\n", FILE_APPEND | LOCK_EX) === false) {
            $this->logger->error('Reclamation history entry could not be written', ['reclamation' => $reclamationId]);
        }
    }

    /**
     * @return array<int, array<string, mixed>> newest first
     */
    public function getReclamationHistory(int $reclamationId): array
    {
        $lines = is_file($this->fileFor($reclamationId))
            ? file($this->fileFor($reclamationId), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
            : false;
        if ($lines === false) {
            return [];
        }

        $history = array_values(array_filter(array_map(
            static fn (string $line): mixed => json_decode($line, true),
            $lines
        ), 'is_array'));

        return array_reverse($history);
    }

    private function fileFor(int $reclamationId): string
    {
        return $this->historyDir . '/' . $reclamationId . '.jsonl';
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
