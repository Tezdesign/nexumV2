<?php

namespace App\Service\FinancialAnalysis;

use App\Entity\FinancialAnalysis\ProjectBudget;
use App\Repository\FinancialAnalysis\ProjectBudgetRepository;
use App\Repository\FinancialAnalysis\TransactionRepository;

class BudgetProjectStService
{
    public function __construct(
        private ProjectBudgetRepository $projectBudgetRepository,
        private TransactionRepository $transactionRepository
    ) {
    }

    /**
     * Calculates the statistical attributes and prediction for a project budget.
     *
     * @return array<string, mixed>
     */
    public function calculateProjectBudgetStatistics(ProjectBudget $budget): array
    {
        $projectId = $budget->getProject() ? $budget->getProject()->getId() : null;
        
        if (!$projectId) {
            echo $projectId;
            return $this->getEmptyStats();
        }

        $currentAllocated = (float) $budget->getTotalBudget();
        $currentSpend = (float) $budget->getActualSpend();

        // 1. Fetch Aggregates
        $projectAggregates = $this->projectBudgetRepository->getProjectBudgetsAggregates($projectId);
        $totalProjectAllocated = $projectAggregates['allocated'];
        $totalProjectSpent = $projectAggregates['spent'];

        // 2. Fetch Ranking
        $rankData = $this->projectBudgetRepository->getBudgetSpendingRank($projectId, $currentSpend);
        $rank = $rankData['rank'];
        $totalBudgets = $rankData['totalBudgets'];

        // 3. Calculate Percentages
        if ($totalBudgets === 1) {
            $allocatedPercentage = 100;
            $spentPercentage = 100;
        } else {
            $allocatedPercentage = $totalProjectAllocated > 0 ? ($currentAllocated / $totalProjectAllocated) * 100 : 0;
            $spentPercentage = $totalProjectSpent > 0 ? ($currentSpend / $totalProjectSpent) * 100 : 0;
        }

        // 4. Calculate Prediction
        $budgetId = $budget->getId();
        $avgTransactionCost = $budgetId === null ? 0.0 : $this->transactionRepository->getAverageTransactionCost($budgetId);
        $projectedFutureSpend = $currentSpend + ($avgTransactionCost * 10);
        $projectedPercentage = $currentAllocated > 0 ? ($projectedFutureSpend / $currentAllocated) * 100 : 0;
        
        // Deviation Index: Positive means spending share > allocated share
        $deviationIndex = $spentPercentage - $allocatedPercentage;

        $predictionStatus = 'STABLE';
        $predictionClass = 'success'; // Text color class
        
        if ($projectedPercentage > 100) {
            $predictionStatus = 'CRITICAL';
            $predictionClass = 'danger';
        } elseif ($deviationIndex > 10 || $projectedPercentage > 80) {
            // If spending share is 10% higher than allocated share OR projecting to hit 80% capacity
            $predictionStatus = 'VOLATILE';
            $predictionClass = 'warning';
        }

        return [
            'ranking' => [
                'rank' => $rank,
                'total' => $totalBudgets,
                'display' => ($totalBudgets === 1) ? 'Sole Budget' : "#{$rank} of {$totalBudgets}"
            ],
            'allocatedShare' => [
                'percentage' => round($allocatedPercentage, 1),
                'display' => round($allocatedPercentage, 1) . '%'
            ],
            'spentShare' => [
                'percentage' => round($spentPercentage, 1),
                'display' => round($spentPercentage, 1) . '%'
            ],
            'prediction' => [
                'status' => $predictionStatus,
                'class' => $predictionClass,
                'projectedPercentage' => round($projectedPercentage, 1),
                'displayPercentage' => round($projectedPercentage, 1) . '%'
            ]
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getEmptyStats(): array
    {
        return [
            'ranking' => ['rank' => 0, 'total' => 0, 'display' => "N/A"],
            'allocatedShare' => ['percentage' => 0, 'display' => "0%"],
            'spentShare' => ['percentage' => 0, 'display' => "0%"],
            'prediction' => [
                'status' => 'UNKNOWN',
                'class' => 'secondary',
                'projectedPercentage' => 0,
                'displayPercentage' => '0%'
            ]
        ];
    }
}
