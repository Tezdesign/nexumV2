<?php

namespace App\Service\Project\AI;

use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Process\Process;

final class OpenVinoRapportService
{
    public function __construct(
        private readonly string $projectDir,
        private readonly string $openVinoModelPath,
        private readonly string $openVinoPipeline,
    ) {
    }

    /**
     * @return array{backend: string, label: string, available: bool, message: string}
     */
    public function getStatus(): array
    {
        $modelPath = trim($this->openVinoModelPath);
        if ($modelPath === '') {
            return [
                'backend' => 'openvino',
                'label' => 'OpenVINO local',
                'available' => false,
                'message' => 'OPENVINO_MODEL_PATH n est pas configure.',
            ];
        }

        if (!file_exists($modelPath)) {
            return [
                'backend' => 'openvino',
                'label' => 'OpenVINO local',
                'available' => false,
                'message' => sprintf('Le modele OpenVINO est introuvable: %s', $modelPath),
            ];
        }

        return [
            'backend' => 'openvino',
            'label' => 'OpenVINO local',
            'available' => true,
            'message' => sprintf('OpenVINO est configure avec le modele %s.', $modelPath),
        ];
    }

    /**
     * @param array<string, mixed> $projet
     * @param array<int, array<string, mixed>> $taches
     */
    public function genererRapport(array $projet, array $taches): string
    {
        $this->disableExecutionTimeLimit();

        $process = $this->buildPythonProcess($this->buildPayload($projet, $taches), false);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException($this->extractProcessError($process));
        }

        $rapport = trim($process->getOutput());
        if ($rapport === '') {
            throw new \RuntimeException("Le modele OpenVINO n'a retourne aucun rapport exploitable.");
        }

        return $rapport;
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
                $this->emitServerError('Impossible de lancer le processus Python OpenVINO pour generer le rapport.');
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
     * @param array<string, mixed> $payload
     */
    private function buildPythonProcess(array $payload, bool $stream): Process
    {
        $command = ['python3', $this->projectDir . '/scripts/generate_rapport_openvino.py'];
        if ($stream) {
            $command[] = '--stream';
        }

        $input = json_encode([
            'model_path' => $this->openVinoModelPath,
            'pipeline' => $this->openVinoPipeline,
            'payload' => $payload,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $process = new Process($command, $this->projectDir, ['PYTHONUNBUFFERED' => '1'], $input);
        $process->setTimeout(null);

        return $process;
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
            'prompt' => $this->buildTextPrompt($projet, $taches),
        ];
    }

    /**
     * @param array<string, mixed> $projet
     * @param array<int, array<string, mixed>> $taches
     */
    private function buildTextPrompt(array $projet, array $taches): string
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

        $instructions = implode("\n", [
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
        ]);

        return implode("\n\n", [
            "Instructions :\n" . $instructions,
            "Informations sur le projet :\n" . implode("\n", $projectLines),
            "Liste des taches :\n" . implode("\n", $taskLines),
            'Genere maintenant un rapport detaille et professionnel en francais sur ce projet.',
        ]);
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
                    : 'OpenVINO a retourne une erreur.'
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

        return 'Impossible de lancer OpenVINO pour generer le rapport.';
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
