<?php

namespace App\Service\FinancialAnalysis;

use App\Entity\FinancialAnalysis\ProjectBudget;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OpenVinoAnalysisService
{
    public function __construct(
        private KernelInterface $kernel,
        private HttpClientInterface $httpClient
    ) {}

    public function analyzeProjectBudget(ProjectBudget $budget, ?string $userContext = null): string
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
            'project_id' => $budget->getId(),
            'total_budget' => (float) $budget->getTotalBudget(),
            'actual_spend' => (float) $budget->getActualSpend(),
            'due_date' => $budget->getDueDate() ? $budget->getDueDate()->format('Y-m-d') : null,
            'transactions' => $transactions,
            'user_context' => $userContext ?? '',
        ];

        // Auto-routing: If running on remote server (no C: drive model), send HTTP request
        if (!is_dir('C:/models/gemma-4-ov')) {
            try {
                $apiUrl = $_ENV['AI_API_URL'] ?? 'http://127.0.0.1:5000';
                $response = $this->httpClient->request('POST', rtrim($apiUrl, '/') . '/api/analyze_financials', [
                    'json' => $payload,
                    'timeout' => 300,
                ]);
                return $response->getContent(false); // return raw JSON string to match original return type
            } catch (\Exception $e) {
                return json_encode([
                    "status" => "ERROR", 
                    "message" => "Failed to connect to remote Desktop AI: " . $e->getMessage()
                ]);
            }
        }

        $tempFile = sys_get_temp_dir() . '/ai_analysis_' . uniqid() . '.json';
        file_put_contents($tempFile, json_encode($payload));

        try {

            $scriptPath = $this->kernel->getProjectDir() . '/src/aitools/FA/analyze_financials.py';
            

            $process = new Process(['python', $scriptPath, $tempFile]);
            $process->setTimeout(300);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $output = $process->getOutput();
            return $output;

        } catch (\Exception $e) {
            return json_encode([
                "status" => "ERROR", 
                "message" => "Failed to run local OpenVINO model: " . $e->getMessage()
            ]);
        } finally {
            // Cleanup temp file
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }
}
