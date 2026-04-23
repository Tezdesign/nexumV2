<?php

namespace App\Service\FinancialAnalysis;

use App\Entity\FinancialAnalysis\ProjectBudget;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class OllamaAnalysisService
{
    private const OLLAMA_URL = 'http://127.0.0.1:11434/api/generate';
    private const MODEL_NAME = 'gemma4:e4b'; // or whatever specific tag the user has

    public function __construct(
        private HttpClientInterface $httpClient
    ) {}

    public function analyzeProjectBudget(ProjectBudget $budget, ?string $userContext = null): string
    {
        $prompt = $this->buildPrompt($budget, $userContext);

        try {
            $response = $this->httpClient->request('POST', self::OLLAMA_URL, [
                'json' => [
                    'model' => self::MODEL_NAME,
                    'prompt' => $prompt,
                    'stream' => false,
                    'format' => 'json',
                    'options' => [
                        'num_predict' => 2048,
                        'temperature' => 0.4   // Give the model more creative freedom to analyze data properly
                    ]
                ],
                'timeout' => 120, // Reverted to 120s since we are sending more data
            ]);

            $data = $response->toArray();

            return $data['response'] ?? '{"error": "Analysis generation failed: No response from model."}';
        } catch (TransportExceptionInterface $e) {
            return json_encode(["error" => "Error connecting to local Ollama instance at " . self::OLLAMA_URL . ".\nPlease ensure Ollama is running locally and you have pulled the model (`ollama run " . self::MODEL_NAME . "`)."]);
        } catch (\Exception $e) {
            return json_encode(["error" => "An unexpected error occurred during analysis: " . $e->getMessage()]);
        }
    }

    private function buildPrompt(ProjectBudget $budget, ?string $userContext): string
    {
        $totalBudget = $budget->getTotalBudget();
        $actualSpend = $budget->getActualSpend();
        $remaining = $totalBudget - $actualSpend;
        $status = $budget->getStatus();
        
        $transactions = $budget->getTransactions()->toArray();
        // Give the model the full context of spending to make accurate projections
        
        $transactionsData = "Transaction History:\n";
        
        if (count($transactions) === 0) {
            $transactionsData .= "No transactions recorded yet.\n";
        } else {
            foreach ($transactions as $tx) {
                $date = $tx->getDateStamp() ? $tx->getDateStamp()->format('Y-m-d') : 'Unknown Date';
                $transactionsData .= sprintf("- [%s] %s: $%s (%s)\n", $date, $tx->getExpenseCategory(), $tx->getCost(), $tx->getDescription());
            }
        }

        $userContextPrompt = "";
        if (!empty($userContext)) {
            $userContextPrompt = "\nUser Context & Future Planned Actions:\n" . $userContext . "\n";
        }

        return <<<PROMPT
You are an expert Financial Analyst. Thoroughly analyze this project budget and its transaction history to calculate variance, evaluate spending habits, and accurately project the final total spending.
{$userContextPrompt}
Project Overview:
- Name: {$budget->getName()}
- Allocated Budget: {$totalBudget}
- Current Actual Spend: {$actualSpend}
- Remaining Budget: {$remaining}
- Current Status: {$status}

{$transactionsData}

You MUST return your analysis STRICTLY as a valid JSON object matching the exact schema below. Use the transaction history to intelligently estimate the "projected_total" spending at completion (which may be higher or lower than the allocated budget).
Schema:
{
  "success_probability": (integer 0-100, confidence in staying under budget),
  "variance_amount": "(string) e.g., '+$500' or '-$200'",
  "variance_status": "(string) e.g., 'Over Budget' or 'Under Budget'",
  "projected_total": "(string) e.g., '$12,000' (your estimate of final total spending)",
  "inflection_date": "(string) e.g., 'Oct 15, 2026' (predicted date funds run out, or 'N/A' if safe)",
  "risk_level": "(string) exactly one of: Low, Medium, High",
  "recommended_solutions": "(string) detailed markdown text explaining insights, spending trajectory, and future steps"
}
PROMPT;
    }
}
