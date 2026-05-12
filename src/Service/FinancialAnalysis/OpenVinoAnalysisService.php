<?php

namespace App\Service\FinancialAnalysis;

use App\Entity\FinancialAnalysis\ProjectBudget;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OpenVinoAnalysisService
{
    public function __construct(
        private HttpClientInterface $httpClient
    ) {}

    public function analyzeProjectBudget(ProjectBudget $budget, ?string $userContext = null): array
    {
        $transactions = [];
        foreach ($budget->getTransactions() as $tx) {
            $transactions[] = [
                'amount' => $tx->getCost(),
                'category' => $tx->getExpenseCategory(),
                'date' => $tx->getDateStamp() ? $tx->getDateStamp()->format('Y-m-d') : 'Unknown Date',
                'description' => $tx->getDescription(),
            ];
        }

        $payload = [
            'project_id' => $budget->getProject() ? $budget->getProject()->getId() : null,
            'budget_id' => $budget->getId(),
            'total_budget' => (float) $budget->getTotalBudget(),
            'actual_spend' => (float) $budget->getActualSpend(),
            'due_date' => $budget->getDueDate() ? $budget->getDueDate()->format('Y-m-d') : null,
            'transactions' => $transactions,
            'user_context' => $userContext ?? '',
        ];

        try {
            $apiUrl = $_ENV['AI_API_URL'] ?? 'http://127.0.0.1:5000';
            $response = $this->httpClient->request('POST', rtrim($apiUrl, '/') . '/api/nexum/analyze', [
                'json' => $payload,
                'timeout' => 300,
            ]);
            return $response->toArray(false); // Return parsed array
        } catch (\Exception $e) {
            return [
                "status" => "ERROR", 
                "message" => "Failed to connect to Nexum Engine: " . $e->getMessage()
            ];
        }
    }
}