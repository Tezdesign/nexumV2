<?php

namespace App\Service\FinancialAnalysis;

use App\Entity\FinancialAnalysis\BudgetProfile;
use App\Entity\FinancialAnalysis\ProjectBudget;
use App\Entity\FinancialAnalysis\Transaction;
use App\Repository\FinancialAnalysis\BudgetProfileRepository;
use App\Repository\FinancialAnalysis\ProjectBudgetRepository;
use App\Repository\FinancialAnalysis\TransactionRepository;

class BudgetDashboardService
{
    public function __construct(
        private ProjectBudgetRepository $projectBudgetRepository,
        private BudgetProfileRepository $budgetProfileRepository,
        private TransactionRepository $transactionRepository
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

    /**
     * Attempts to find the Fiscal Year Profile this budget falls into.
     */
    public function getFiscalProfileForBudget(ProjectBudget $budget): ?BudgetProfile
    {
        if (!$budget->getDueDate()) {
            return null;
        }

        return $this->budgetProfileRepository->createQueryBuilder('bp')
            ->where('bp.start_date <= :date')
            ->andWhere('bp.end_date >= :date')
            ->setParameter('date', $budget->getDueDate()->format('Y-m-d'))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Formats the project budget details for the UI.
     */
    public function formatBudgetDetails(ProjectBudget $budget): array
    {
        $total = (float) $budget->getTotalBudget();
        $spend = (float) $budget->getActualSpend();
        $remaining = $total - $spend;
        $utilization = $total > 0 ? round(($spend / $total) * 100) : 0;
        $dueDate = $budget->getDueDate() ? $budget->getDueDate()->format('M d, Y') : 'N/A';
        $status = $budget->getStatus();

        return [
            'id' => $budget->getId(),
            'name' => $budget->getName(),
            'projectName' => $budget->getProject() ? $budget->getProject()->getName() : 'Unknown Project',
            'totalBudget' => number_format($total / 1000, 1) . 'k',
            'actualSpend' => number_format($spend / 1000, 1) . 'k',
            'remaining' => number_format($remaining / 1000, 1) . 'k',
            'utilization' => $utilization,
            'dueDate' => $dueDate,
            'status' => $status,
        ];
    }

    /**
     * Formats the list of transactions for the UI.
     */
    public function formatTransactions(ProjectBudget $budget): array
    {
        $transactionsData = [];
        foreach ($budget->getTransactions() as $tx) {
            $transactionsData[] = [
                'id' => $tx->getId(),
                'reference' => $tx->getReference(),
                'expenseCategory' => $tx->getExpenseCategory(),
                'cost' => $tx->getCost(),
                'dateStamp' => $tx->getDateStamp() ? $tx->getDateStamp()->format('Y-m-d') : null,
                'description' => $tx->getDescription(),
            ];
        }
        return $transactionsData;
    }

    /**
     * Handles the cascading updates when a transaction is saved.
     */
    public function handleTransactionCascade(ProjectBudget $projectBudget, Transaction $transaction, ?BudgetProfile $profile): void
    {
        // 1. Save Transaction
        $this->transactionRepository->save($transaction, true);
        
        // 2. Recalculate ProjectBudget actualSpend
        $totalCost = $this->transactionRepository->getTotalCostForProjectBudget($projectBudget->getId());
        $projectBudget->setActualSpend((string) $totalCost);
        
        // 3. Recalculate ProjectBudget status
        $projectBudget->calculateStatus();
        
        // 4. Update ProjectBudget via DQL
        $this->projectBudgetRepository->updateActualSpendAndStatusDql($projectBudget);
        
        // 5. Recalculate and update BudgetProfile via DQL if it exists
        if ($profile) {
            $totals = $this->projectBudgetRepository->getTotalsForFiscalYear($profile->getStartDate(), $profile->getEndDate());
            $this->budgetProfileRepository->updateTotalExpenseDql($profile, $totals['expenses']);
        }
    }

    /**
     * Handles the cascading updates when an existing transaction is updated via DQL.
     */
    public function handleTransactionUpdateCascade(ProjectBudget $projectBudget, Transaction $transaction, ?BudgetProfile $profile): void
    {
        // 1. Execute the transaction update via DQL
        $this->transactionRepository->updateTransactionDql($transaction);
        
        // 2. Recalculate ProjectBudget actualSpend
        $totalCost = $this->transactionRepository->getTotalCostForProjectBudget($projectBudget->getId());
        $projectBudget->setActualSpend((string) $totalCost);
        
        // 3. Recalculate ProjectBudget status
        $projectBudget->calculateStatus();
        
        // 4. Update ProjectBudget via DQL
        $this->projectBudgetRepository->updateActualSpendAndStatusDql($projectBudget);
        
        // 5. Recalculate and update BudgetProfile via DQL if it exists
        if ($profile) {
            $totals = $this->projectBudgetRepository->getTotalsForFiscalYear($profile->getStartDate(), $profile->getEndDate());
            $this->budgetProfileRepository->updateTotalExpenseDql($profile, $totals['expenses']);
        }
    }

    /**
     * Handles the cascading updates after multiple transactions are deleted.
     */
    public function handleBulkDeleteCascade(ProjectBudget $projectBudget, array $ids, ?BudgetProfile $profile): void
    {
        // 1. Execute bulk delete via DQL
        $this->transactionRepository->bulkDeleteDql($ids);
        
        // 2. Recalculate ProjectBudget actualSpend (now lower)
        $totalCost = $this->transactionRepository->getTotalCostForProjectBudget($projectBudget->getId());
        $projectBudget->setActualSpend((string) $totalCost);
        
        // 3. Recalculate ProjectBudget status (potentially improved)
        $projectBudget->calculateStatus();
        
        // 4. Update ProjectBudget via DQL
        $this->projectBudgetRepository->updateActualSpendAndStatusDql($projectBudget);
        
        // 5. Recalculate and update BudgetProfile via DQL if it exists
        if ($profile) {
            $totals = $this->projectBudgetRepository->getTotalsForFiscalYear($profile->getStartDate(), $profile->getEndDate());
            $this->budgetProfileRepository->updateTotalExpenseDql($profile, $totals['expenses']);
        }
    }

    /**
     * Handles the cascading deletion of a ProjectBudget and its transactions.
     */
    public function handleProjectBudgetDeletionCascade(ProjectBudget $budget, ?BudgetProfile $profile): void
    {
        // 1. Delete all associated transactions
        $this->transactionRepository->deleteByProjectBudgetDql($budget->getId());

        // 2. Delete the project budget itself
        $this->projectBudgetRepository->deleteProjectBudgetDql($budget->getId());

        // 3. Recalculate and update BudgetProfile via DQL if it exists
        if ($profile) {
            $totals = $this->projectBudgetRepository->getTotalsForFiscalYear($profile->getStartDate(), $profile->getEndDate());
            $this->budgetProfileRepository->updateTotalExpenseDql($profile, $totals['expenses']);
        }
    }

    /**
     * Handles the full cascading deletion of a Fiscal Year Profile and all its children.
     */
    public function handleFullFiscalYearDeletionCascade(BudgetProfile $profile): void
    {
        if ($profile->getStartDate() && $profile->getEndDate()) {
            // 1. Delete all Transactions belonging to projects in this FY scope
            $this->transactionRepository->deleteByFiscalYearScopeDql($profile->getStartDate(), $profile->getEndDate());

            // 2. Delete all ProjectBudgets in this FY scope
            $this->projectBudgetRepository->deleteByFiscalYearScopeDql($profile->getStartDate(), $profile->getEndDate());
        }

        // 3. Finally delete the Profile itself
        $this->budgetProfileRepository->deleteProfileDql($profile->getId());
    }
}
