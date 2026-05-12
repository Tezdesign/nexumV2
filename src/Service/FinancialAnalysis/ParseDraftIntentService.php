<?php

namespace App\Service\FinancialAnalysis;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class ParseDraftIntentService
{
    public function __construct(
        private HttpClientInterface $httpClient
    ) {}

    public function parseIntent(string $userInput, array $availableProjects, array $availableCategories): array
    {
        $payload = [
            'user_input' => $userInput,
            'available_projects' => $availableProjects,
            'available_categories' => $availableCategories,
        ];

        try {
            $apiUrl = $_ENV['AI_API_URL'] ?? 'http://127.0.0.1:5000';
            $response = $this->httpClient->request('POST', rtrim($apiUrl, '/') . '/api/nexum/intent', [
                'json' => $payload,
                'timeout' => 180,
            ]);
            
            return $response->toArray(false);
        } catch (\Exception $e) {
            return [
                "status" => "ERROR",
                "message" => "Nexum Engine unavailable: " . $e->getMessage()
            ];
        }
    }
}