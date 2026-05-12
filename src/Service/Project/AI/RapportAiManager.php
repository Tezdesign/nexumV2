<?php

namespace App\Service\Project\AI;

use Symfony\Component\HttpFoundation\StreamedResponse;

final class RapportAiManager
{
    public function __construct(
        private readonly OpenVinoRapportService $openVinoRapportService
    ) {
    }

    /**
     * @param array<string, mixed> $projet
     * @param array<int, array<string, mixed>> $taches
     */
    public function genererRapport(array $projet, array $taches): string
    {
        return $this->openVinoRapportService->genererRapport($projet, $taches);
    }

    /**
     * @param array<string, mixed> $projet
     * @param array<int, array<string, mixed>> $taches
     */
    public function streamerRapport(array $projet, array $taches): StreamedResponse
    {
        return $this->openVinoRapportService->streamerRapport($projet, $taches);
    }

    /**
     * @return array{backend: string, label: string, available: bool, message: string}
     */
    public function getBackendStatus(): array
    {
        return $this->openVinoRapportService->getStatus();
    }
}
