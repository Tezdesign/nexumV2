<?php

namespace App\Service\FinancialAnalysis;

use App\Entity\FinancialAnalysis\BudgetProfile;
use App\Entity\FinancialAnalysis\ProjectBudget;
use App\Entity\FinancialAnalysis\Transaction;
use App\Repository\FinancialAnalysis\BudgetProfileRepository;
use App\Repository\FinancialAnalysis\ProjectBudgetRepository;
use App\Repository\FinancialAnalysis\TransactionRepository;
use DateTime;
use DateTimeImmutable;
use App\Service\FinancialAnalysis\BudgetTrendCacheService;

class BudgetDashboardService
{
    public function __construct(
        private ProjectBudgetRepository $projectBudgetRepository,
        private BudgetProfileRepository $budgetProfileRepository,
        private TransactionRepository $transactionRepository,
        private BudgetTrendCacheService $trendCacheService
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
            'rawTotal' => $total,
            'rawSpend' => $spend,
            'rawRemaining' => $remaining,
            'utilization' => $utilization,
            'dueDate' => $dueDate,
            'status' => $status,
        ];
    }

    /**
     * Formats the list of transactions for the UI.
     */
    public function formatTransactions(ProjectBudget $budget, ?string $searchTerm = null): array
    {
        if ($searchTerm) {
            $transactions = $this->transactionRepository->searchByReferenceOrDescriptionDql($budget->getId(), $searchTerm);
        } else {
            $transactions = $budget->getTransactions();
        }

        $transactionsData = [];
        foreach ($transactions as $tx) {
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
        if ($profile) {
            $totals = $this->projectBudgetRepository->getTotalsForFiscalYear($profile->getStartDate(), $profile->getEndDate());
            $this->trendCacheService->savePreUpdateState($profile, $totals['allocated'], $totals['expenses']);
        }

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
        if ($profile) {
            $totals = $this->projectBudgetRepository->getTotalsForFiscalYear($profile->getStartDate(), $profile->getEndDate());
            $this->trendCacheService->savePreUpdateState($profile, $totals['allocated'], $totals['expenses']);
        }

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
        if ($profile) {
            $totals = $this->projectBudgetRepository->getTotalsForFiscalYear($profile->getStartDate(), $profile->getEndDate());
            $this->trendCacheService->savePreUpdateState($profile, $totals['allocated'], $totals['expenses']);
        }

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
        if ($profile) {
            $totals = $this->projectBudgetRepository->getTotalsForFiscalYear($profile->getStartDate(), $profile->getEndDate());
            $this->trendCacheService->savePreUpdateState($profile, $totals['allocated'], $totals['expenses']);
        }

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

    /**
     * Determines the currency symbol based on the active Fiscal Year (BudgetProfile)
     * the project falls under.
     *
     * @param \App\Entity\Projects\Project $project
     * @return string The currency symbol (e.g. $, €, £), abbreviation, or default '$'
     */
    public function getCurrencySymbolForProject(\App\Entity\Projects\Project $project): string
    {
        // Find the active BudgetProfile for this project's dates
        $startDate = $project->getStartDate();

        if (!$startDate) {
            return '$'; // Fallback if no start date
        }

        $profile = $this->budgetProfileRepository->createQueryBuilder('bp')
            ->where('bp.fiscal_year >= :date') // using fiscal_year or start_date based on entity
            ->setParameter('date', $startDate->format('Y-m-d'))
            ->andWhere('bp.status = :status')
            ->setParameter('status', 'ACTIVE')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$profile) {
            return '$'; // Fallback if no active profile found
        }

        $currencyCode = strtoupper($profile->getBaseCurrency() ?? 'USD');

        // Map known currency codes to symbols
        $currencySymbols = [
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
            'CAD' => '$',
            'AUD' => '$',
            'CHF' => 'Fr',
            'CNY' => '¥',
            'INR' => '₹',
            'RUB' => '₽',
            'TND' => 'DT', // Tunisian Dinar
        ];

        return $currencySymbols[$currencyCode] ?? $currencyCode; // Return abbreviation if symbol unknown
    }

    function decreaseAndConvert(DateTimeImmutable $immutableDate): DateTime {

        $decreasedDate = $immutableDate->modify('-2 months');

        return DateTime::createFromImmutable($decreasedDate);
    }
}
