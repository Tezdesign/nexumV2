<?php

namespace App\Service\FinancialAnalysis;

use App\Entity\FinancialAnalysis\ExpenseDraft;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class DraftPolicyService
{
    public function __construct(
        private KernelInterface $kernel,
        private HttpClientInterface $httpClient
    ) {}

    public function evaluatePolicy(ExpenseDraft $draft): array
    {
        // Define the hardcoded company policy text
        $companyPolicy = <<<TEXT
1. Software subscriptions exceeding $200 require explicit CTO authorization.
2. Travel expenses are restricted to Economy Class only; First or Business class is strictly prohibited.
3. Office Supplies exceeding $50 per item must be justified with a team-wide benefit description.
4. Any expense for "Entertainment" or "Gifts" must include the recipient's name and relationship to the company.
5. Marketing spend above $1000 must be linked to an approved quarterly campaign ID.
TEXT;

        $payload = [
            'draft_id' => $draft->getId(),
            'amount' => (float) $draft->getAmount(),
            'category' => $draft->getCategory(),
            'description' => $draft->getDescription(),
            'company_policy' => $companyPolicy
        ];

        // Auto-routing: If running on remote server (no C: drive model), send HTTP request
        if (!is_dir('C:/models/gemma-4-ov')) {
            try {
                $apiUrl = $_ENV['AI_API_URL'] ?? 'http://127.0.0.1:5000';
                $response = $this->httpClient->request('POST', rtrim($apiUrl, '/') . '/api/evaluate_draft_policy', [
                    'json' => $payload,
                    'timeout' => 180,
                ]);
                return $response->toArray(false);
            } catch (\Exception $e) {
                return [
                    "status" => "ERROR",
                    "decision" => "FLAG_FOR_HUMAN",
                    "reason" => "Remote AI API unavailable: " . $e->getMessage()
                ];
            }
        }

        $tempFile = sys_get_temp_dir() . '/ai_policy_' . uniqid() . '.json';
        file_put_contents($tempFile, json_encode($payload));

        try {
            $scriptPath = $this->kernel->getProjectDir() . '/src/aitools/FA/evaluate_draft_policy.py';
            $process = new Process(['python', $scriptPath, $tempFile]);
            $process->setTimeout(180); // 3 minutes for policy review
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            return json_decode($process->getOutput(), true);

        } catch (\Exception $e) {
            return [
                "status" => "ERROR",
                "decision" => "FLAG_FOR_HUMAN",
                "reason" => "AI Policy Engine unavailable: " . $e->getMessage()
            ];
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }
}
