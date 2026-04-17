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

    public function analyzeProjectBudget(ProjectBudget $budget): string
    {
        $prompt = $this->buildPrompt($budget);

        try {
            $response = $this->httpClient->request('POST', self::OLLAMA_URL, [
                'json' => [
                    'model' => self::MODEL_NAME,
                    'prompt' => $prompt,
                    'stream' => false,
                ],
                'timeout' => 120, // Local AI generation takes time
            ]);

            $data = $response->toArray();

            return $data['response'] ?? 'Analysis generation failed: No response from model.';
        } catch (TransportExceptionInterface $e) {
            return "Error connecting to local Ollama instance at " . self::OLLAMA_URL . ".\nPlease ensure Ollama is running locally and you have pulled the model (`ollama run " . self::MODEL_NAME . "`).";
        } catch (\Exception $e) {
            return "An unexpected error occurred during analysis: " . $e->getMessage();
        }
    }

    private function buildPrompt(ProjectBudget $budget): string
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

        return <<<PROMPT
You are an expert Financial Analyst. Analyze this project budget, calculate the variance, evaluate the spending habits based on the transactions, and predict the project's financial success.

Project Overview:
- Name: {$budget->getName()}
- Total Budget: {$totalBudget}
- Actual Spend: {$actualSpend}
- Remaining Budget: {$remaining}
- Current Status: {$status}

{$transactionsData}

Please provide a concise analysis structured exactly with these three headings in Markdown format:
### 1. Budget Variance
(Calculate and explain the variance based on current spend vs total budget)

### 2. Spending Habits
(Analyze the transaction categories and descriptions. Where is the money going?)

### 3. Projected Success
(Provide a brief prediction or confidence assessment on whether this project will finish under budget)
PROMPT;
    }
}
