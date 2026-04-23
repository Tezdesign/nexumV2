<?php

namespace App\Service\Project\AI;

use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Process\Process;

final class LmStudioService
{
    public function __construct(
        private readonly string $projectDir,
        private readonly string $lmStudioUrl,
        private readonly string $lmStudioModel,
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @return array{backend: string, label: string, available: bool, message: string}
     */
    public function getStatus(): array
    {
        $baseUrl = trim($this->lmStudioUrl);
        if ($baseUrl === '') {
            return [
                'backend' => 'lmstudio',
                'label' => 'LM Studio local',
                'available' => false,
                'message' => 'LM Studio n est pas configure.',
            ];
        }

        try {
            $response = $this->httpClient->request('GET', rtrim($baseUrl, '/') . '/v1/models', [
                'timeout' => 3,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                return [
                    'backend' => 'lmstudio',
                    'label' => 'LM Studio local',
                    'available' => false,
                    'message' => sprintf('LM Studio a repondu avec le statut HTTP %d.', $statusCode),
                ];
            }

            $payload = $response->toArray(false);
            $models = [];
            foreach ((array) ($payload['data'] ?? []) as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $modelId = $item['id'] ?? null;
                if (is_string($modelId) && trim($modelId) !== '') {
                    $models[] = trim($modelId);
                }
            }

            if ($models !== [] && !in_array($this->lmStudioModel, $models, true)) {
                return [
                    'backend' => 'lmstudio',
                    'label' => 'LM Studio local',
                    'available' => false,
                    'message' => sprintf(
                        'LM Studio est demarre, mais le modele configure "%s" n est pas expose par /v1/models.',
                        $this->lmStudioModel
                    ),
                ];
            }

            return [
                'backend' => 'lmstudio',
                'label' => 'LM Studio local',
                'available' => true,
                'message' => sprintf(
                    'LM Studio est joignable sur %s%s',
                    $baseUrl,
                    $this->lmStudioModel !== '' ? sprintf(' avec le modele %s.', $this->lmStudioModel) : '.'
                ),
            ];
        } catch (\Throwable) {
            return [
                'backend' => 'lmstudio',
                'label' => 'LM Studio local',
                'available' => false,
                'message' => 'LM Studio est inaccessible. Verifiez que le serveur local est demarre sur http://127.0.0.1:1234.',
            ];
        }
    }

    public function genererRapport(array $projet, array $taches): string
    {
        $this->disableExecutionTimeLimit();

        $process = $this->buildPythonProcess($this->buildPayload($projet, $taches, false), false);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException($this->extractProcessError($process));
        }

        $rapport = trim($process->getOutput());
        if ($rapport === '') {
            throw new \RuntimeException("Le modele local n'a retourne aucun rapport exploitable.");
        }

        return $rapport;
    }

    public function streamerRapport(array $projet, array $taches): StreamedResponse
    {
        $payload = $this->buildPayload($projet, $taches, true);

        return new StreamedResponse(function () use ($payload): void {
            @ini_set('zlib.output_compression', '0');
            $this->disableExecutionTimeLimit();

            try {
                $process = $this->buildPythonProcess($payload, true);
                $process->start();

                $buffer = '';
                $completed = false;
                $errorEmitted = false;

                foreach ($process as $type => $data) {
                    if ($type !== Process::OUT) {
                        continue;
                    }

                    $buffer .= $data;

                    while (($lineBreak = strpos($buffer, "\n")) !== false) {
                        $line = trim(substr($buffer, 0, $lineBreak));
                        $buffer = substr($buffer, $lineBreak + 1);

                        if ($line === '') {
                            continue;
                        }

                        $this->handlePythonStreamLine($line, $completed, $errorEmitted);
                    }
                }

                if (trim($buffer) !== '') {
                    $this->handlePythonStreamLine(trim($buffer), $completed, $errorEmitted);
                }

                if ($errorEmitted) {
                    return;
                }

                if (!$process->isSuccessful()) {
                    $this->emitServerError($this->extractProcessError($process));

                    return;
                }

                if (!$completed) {
                    $this->emitDone();
                }
            } catch (\Throwable $exception) {
                $this->emitServerError('Impossible de lancer le processus Python pour generer le rapport.');
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

    private function buildPythonProcess(array $payload, bool $stream): Process
    {
        $command = ['python', $this->projectDir . '/scripts/generate_rapport.py'];        if ($stream) {
            $command[] = '--stream';
        }

        $input = json_encode([
            'lm_studio_url' => $this->lmStudioUrl,
            'payload' => $payload,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $process = new Process($command, $this->projectDir, ['PYTHONUNBUFFERED' => '1'], $input);
        $process->setTimeout(null);

        return $process;
    }

    private function handlePythonStreamLine(string $line, bool &$completed, bool &$errorEmitted): void
    {
        $event = json_decode($line, true);
        if (!is_array($event)) {
            return;
        }

        $type = $event['type'] ?? null;
        if (!is_string($type)) {
            return;
        }

        if ($type === 'token') {
            $token = $event['content'] ?? null;
            if (is_string($token) && $token !== '') {
                $this->emitToken($token);
            }

            return;
        }

        if ($type === 'done') {
            $completed = true;
            $this->emitDone();

            return;
        }

        if ($type === 'error') {
            $errorEmitted = true;
            $message = $event['message'] ?? null;
            $this->emitServerError(
                is_string($message) && trim($message) !== ''
                    ? trim($message)
                    : 'LM Studio a retourne une erreur.'
            );
        }
    }

    private function extractProcessError(Process $process): string
    {
        $errorOutput = trim($process->getErrorOutput());
        if ($errorOutput !== '') {
            return $errorOutput;
        }

        $output = trim($process->getOutput());
        if ($output !== '') {
            return $output;
        }

        return 'Impossible de contacter LM Studio pour generer le rapport.';
    }

    private function buildPayload(array $projet, array $taches, bool $stream): array
    {
        return [
            'model' => $this->lmStudioModel,
            'stream' => $stream,
            'temperature' => 0.3,
            'messages' => $this->buildPrompt($projet, $taches),
        ];
    }

    /**
     * @return array<int, array{role: string, content: string}>
     */
    private function buildPrompt(array $projet, array $taches): array
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
            $taskLines[] = 'Aucune tache n est associee a ce projet pour le moment.';
        }

        return [
            [
                'role' => 'system',
                'content' => implode("\n", [
                    'Tu es un assistant de gestion de projet francophone.',
                    'Tu rediges un rapport professionnel, clair et utile pour un manager.',
                    'Le rapport doit etre entierement en francais.',
                    'N utilise jamais de tableaux, ni en texte, ni en Markdown, ni sous forme de colonnes.',
                    'Le rapport doit contenir les sections suivantes, avec des titres visibles :',
                    '0. Membres du projet',
                    '1. Resume executif',
                    '2. Etat d avancement global',
                    '3. Analyse des taches par statut',
                    '4. Risques identifies et recommandations',
                    '5. Conclusion et prochaines etapes',
                    'La section "Membres du projet" doit apparaitre au debut du rapport, avant le resume executif, sous forme de liste simple.',
                    'Quand une information manque, indique-le sobrement au lieu d inventer.',
                ]),
            ],
            [
                'role' => 'user',
                'content' => implode("\n\n", [
                    "Informations sur le projet :\n" . implode("\n", $projectLines),
                    "Liste des taches :\n" . implode("\n", $taskLines),
                    'Genere maintenant un rapport detaille et professionnel en francais sur ce projet.',
                ]),
            ],
        ];
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

    private function emitToken(string $token): void
    {
        echo 'data: ' . json_encode(['token' => $token], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
        $this->flushStream();
    }

    private function emitDone(): void
    {
        echo "event: done\n";
        echo "data: " . json_encode(['done' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
        $this->flushStream();
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
