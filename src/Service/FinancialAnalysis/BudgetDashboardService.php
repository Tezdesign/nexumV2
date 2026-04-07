<?php

namespace App\Service\FinancialAnalysis;

use App\Repository\FinancialAnalysis\ProjectBudgetRepository;

class BudgetDashboardService
{
    public function __construct(
        private ProjectBudgetRepository $projectBudgetRepository
    ) {
    }

    /**
     * Fetches all project budgets and formats them for the dashboard UI.
     */
    public function getFormattedBudgets(): array
    {
        $budgets = $this->projectBudgetRepository->findAll();
        
        $projects = [];
        foreach ($budgets as $budget) {
            $total = (float) $budget->getTotalBudget();
            $spend = (float) $budget->getActualSpend();
            $remaining = $total - $spend;

            $projects[] = [
                'id' => $budget->getId(),
                'name' => $budget->getName(),
                'subtitle' => $budget->getProject() ? $budget->getProject()->getName() : 'Unknown Project',
                'status' => $budget->getStatus() ?: 'ON TRACK',
                'totalBudget' => number_format($total / 1000, 1) . 'k',
                'actualSpend' => number_format($spend / 1000, 1) . 'k',
                'remaining' => number_format($remaining / 1000, 1) . 'k',
                'dueDate' => $budget->getDueDate() ? $budget->getDueDate()->format('Y-m-d') : ''
            ];
        }

        return $projects;
    }
}
