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
                        'num_predict' => 2048, // Prevent truncation
                        'temperature' => 0.2   // More deterministic JSON
                    ]
                ],
                'timeout' => 120, // Local AI generation takes time
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
        
        $transactionsData = "Transaction History:\n";
        $transactions = $budget->getTransactions();
        
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
You are an expert Financial Analyst. Analyze this project budget, calculate the variance, evaluate the spending habits based on the transactions, and predict the project's financial success.
{$userContextPrompt}
Project Overview:
- Name: {$budget->getName()}
- Total Budget: {$totalBudget}
- Actual Spend: {$actualSpend}
- Remaining Budget: {$remaining}
- Current Status: {$status}

{$transactionsData}

You MUST return your analysis STRICTLY as a valid JSON object matching the exact schema below. Do not include any markdown formatting, backticks, or extra text outside the JSON object. Keep your "recommended_solutions" concise and properly escaped.
Schema:
{
  "success_probability": 85,
  "variance_analysis": "string, a brief mathematical summary",
  "risk_level": "string, exactly one of: Low, Medium, High",
  "recommended_solutions": "string, markdown formatted text explaining insights and future steps"
}
PROMPT;
    }
}
