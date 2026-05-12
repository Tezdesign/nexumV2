<?php

namespace App\Service\Project\AI;

use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class OpenVinoRapportService
{
    public function __construct(
        private HttpClientInterface $httpClient
    ) {
    }

    /**
     * @return array{backend: string, label: string, available: bool, message: string}
     */
    public function getStatus(): array
    {
        // Simply check if we have an AI API configured
        $apiUrl = $_ENV['AI_API_URL'] ?? 'http://127.0.0.1:5000';
        
        if (empty($apiUrl)) {
            return [
                'backend' => 'openvino_api',
                'label' => 'Nexum AI Engine',
                'available' => false,
                'message' => 'AI_API_URL n est pas configure dans le fichier .env.',
            ];
        }

        return [
            'backend' => 'openvino_api',
            'label' => 'Nexum AI Engine',
            'available' => true,
            'message' => sprintf('Connecte a l\'API Nexum via %s', $apiUrl),
        ];
    }

    /**
     * @param array<string, mixed> $projet
     * @param array<int, array<string, mixed>> $taches
     */
    public function genererRapport(array $projet, array $taches): string
    {
        $payload = $this->buildPayload($projet, $taches);
        $apiUrl = $_ENV['AI_API_URL'] ?? 'http://127.0.0.1:5000';

        try {
            $response = $this->httpClient->request('POST', rtrim($apiUrl, '/') . '/api/nexum/rapport/generate', [
                'json' => $payload,
                'timeout' => 300,
            ]);

            return $response->getContent();
        } catch (\Exception $e) {
            throw new \RuntimeException('Impossible de contacter l\'API Nexum: ' . $e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $projet
     * @param array<int, array<string, mixed>> $taches
     */
    public function streamerRapport(array $projet, array $taches): StreamedResponse
    {
        $payload = $this->buildPayload($projet, $taches);

        return new StreamedResponse(function () use ($payload): void {
            @ini_set('zlib.output_compression', '0');
            $this->disableExecutionTimeLimit();

            $apiUrl = $_ENV['AI_API_URL'] ?? 'http://127.0.0.1:5000';

            try {
                $response = $this->httpClient->request('POST', rtrim($apiUrl, '/') . '/api/nexum/rapport/stream', [
                    'json' => $payload,
                    'buffer' => false, // Ensure we receive chunks immediately
                    'timeout' => 300,
                ]);

                foreach ($this->httpClient->stream($response) as $chunk) {
                    echo $chunk->getContent();
                    $this->flushStream();
                }
                
            } catch (\Throwable $exception) {
                $this->emitServerError('Impossible de connecter au flux Nexum AI: ' . $exception->getMessage());
            }
        }, 200, [
            'Content-Type' => 'text/event-stream; charset=utf-8',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function disableExecutionTimeLimit(): void
    {
        @ini_set('max_execution_time', '0');

        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
    }

    /**
     * @param array<string, mixed> $projet
     * @param array<int, array<string, mixed>> $taches
     * @return array<string, mixed>
     */
    private function buildPayload(array $projet, array $taches): array
    {
        return [
            'max_new_tokens' => 900,
            'project' => $this->buildProjectPrompt($projet),
            'tasks' => $this->buildTasksPrompt($taches),
        ];
    }

    /**
     * @param array<string, mixed> $projet
     */
    private function buildProjectPrompt(array $projet): string
    {
        $projectLines = [
            'Nom du projet : ' . ($projet['nom'] ?? 'Projet sans nom'),
            'Description : ' . ($projet['description'] ?? 'Aucune description fournie.'),
            'Date de debut : ' . ($projet['date_debut'] ?? 'Non definie'),
            'Date de fin : ' . ($projet['date_fin'] ?? 'Non definie'),
            'Responsable : ' . ($projet['responsable'] ?? 'Non attribue'),
            'Membres du projet : ' . $this->formatProjectMembers($projet['membres'] ?? []),
            'Statut : ' . ($projet['statut'] ?? 'Non defini'),
            'Progression estimee actuelle : ' . ($projet['progression'] ?? '0') . '%',
            'Budget : ' . ($projet['budget'] ?? 'Non defini'),
            'Taches totales : ' . ($projet['stats']['total'] ?? 0),
            'Taches terminees : ' . ($projet['stats']['completed'] ?? 0),
            'Taches en retard : ' . ($projet['stats']['overdue'] ?? 0),
        ];
        
        return implode("\n", $projectLines);
    }

    /**
     * @param array<int, array<string, mixed>> $taches
     */
    private function buildTasksPrompt(array $taches): string
    {
        $taskLines = [];
        foreach ($taches as $index => $tache) {
            $taskLines[] = sprintf(
                "%d. Titre: %s | Statut: %s | Priorite: %s | Assigne a: %s | Echeance: %s | Description: %s",
                $index + 1,
                $tache['titre'] ?? 'Sans titre',
                $tache['statut'] ?? 'Non defini',
                $tache['priorite'] ?? 'Non definie',
                $tache['assigne_a'] ?? 'Non attribue',
                $tache['echeance'] ?? 'Non definie',
                $tache['description'] ?? 'Aucune description'
            );
        }

        if ($taskLines === []) {
            return 'Aucune tache n est associee a ce projet pour le moment.';
        }
        
        return implode("\n", $taskLines);
    }

    private function formatProjectMembers(mixed $members): string
    {
        if (!is_array($members)) {
            return 'Aucun membre identifie.';
        }

        $cleanMembers = array_values(array_filter(
            array_map(static fn (mixed $member): string => is_string($member) ? trim($member) : '', $members),
            static fn (string $member): bool => $member !== ''
        ));

        if ($cleanMembers === []) {
            return 'Aucun membre identifie.';
        }

        return implode(', ', $cleanMembers);
    }

    private function emitServerError(string $message): void
    {
        echo "event: server-error\n";
        echo 'data: ' . json_encode(['message' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
        $this->flushStream();
    }

    private function flushStream(): void
    {
        if (function_exists('ob_flush')) {
            @ob_flush();
        }

        flush();
    }
}
