<?php

namespace App\Service\Project\AI;

use Symfony\Component\HttpFoundation\StreamedResponse;

final class RapportAiManager
{
    public function __construct(
        private readonly LmStudioService $lmStudioService,
        private readonly OpenVinoRapportService $openVinoRapportService,
        private readonly string $aiBackend,
    ) {
    }

    /**
     * @param array<string, mixed> $projet
     * @param array<int, array<string, mixed>> $taches
     */
    public function genererRapport(array $projet, array $taches): string
    {
        return $this->resolveBackend()->genererRapport($projet, $taches);
    }

    /**
     * @param array<string, mixed> $projet
     * @param array<int, array<string, mixed>> $taches
     */
    public function streamerRapport(array $projet, array $taches): StreamedResponse
    {
        return $this->resolveBackend()->streamerRapport($projet, $taches);
    }

    /**
     * @return array{backend: string, label: string, available: bool, message: string}
     */
    public function getBackendStatus(): array
    {
        return match (strtolower(trim($this->aiBackend))) {
            'openvino' => $this->openVinoRapportService->getStatus(),
            default => $this->lmStudioService->getStatus(),
        };
    }

    private function resolveBackend(): LmStudioService|OpenVinoRapportService
    {
        return match (strtolower(trim($this->aiBackend))) {
            'openvino' => $this->openVinoRapportService,
            default => $this->lmStudioService,
        };
    }
}
