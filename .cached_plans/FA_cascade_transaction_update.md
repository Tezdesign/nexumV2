# Objective
Implement the cascading logic when a new Transaction is created so that it automatically recalculates and updates the parent `ProjectBudget`'s `actualSpend` and `status`, and subsequently updates the associated Fiscal Year `BudgetProfile`'s `total_expense` using highly optimized DQL queries in their respective Repositories.

# Investigation & Architecture
- The user requested that adding a transaction must ripple up the hierarchy (Transaction -> Project Budget -> Budget Profile) using DQL updates.
- **Transaction to Project Budget:** When a new transaction is saved, we need to calculate the sum of all transaction costs for that specific Project Budget. We will create a DQL method in `TransactionRepository` to fetch this SUM. Then we update the `ProjectBudget`'s `actualSpend`, recalculate its `status`, and push it to the database via a new DQL method in `ProjectBudgetRepository`.
- **Project Budget to Budget Profile:** Since the Project Budget's `actualSpend` just changed, the overall Fiscal Year's expenses have changed. We already have the DQL method `getTotalsForFiscalYear` in `ProjectBudgetRepository`. We will call it to get the fresh total expenses for the FY, and then update the `BudgetProfile`'s `total_expense` via a new DQL method in `BudgetProfileRepository`.

# Implementation Steps

## 1. Add DQL to `TransactionRepository.php`
- Create `getTotalCostForProjectBudget(int $projectBudgetId): float` which executes a DQL query: `SELECT SUM(t.cost) ... WHERE t.projectBudget = :id`.

## 2. Add DQL to `ProjectBudgetRepository.php`
- Create `updateActualSpendAndStatusDql(ProjectBudget $budget): void` which executes a DQL `UPDATE` to set `pb.actualSpend` and `pb.status`.

## 3. Add DQL to `BudgetProfileRepository.php`
- Create `updateTotalExpenseDql(BudgetProfile $profile, float $totalExpense): void` which executes a DQL `UPDATE` to set `bp.total_expense`.

## 4. Integrate into Controller (`FinancialDashboardController.php`)
- Inside `budgetDetails()`, immediately after saving the `$transaction`:
  1. Call `getTotalCostForProjectBudget` and update `$projectBudget`'s `actualSpend`.
  2. Call `$projectBudget->calculateStatus()`.
  3. Call `updateActualSpendAndStatusDql($projectBudget)`.
  4. Fetch the new FY total expenses using the `ProjectBudgetRepository->getTotalsForFiscalYear()` method using the `$originalProfile` dates.
  5. Call `updateTotalExpenseDql($originalProfile, $newFyTotal)`.

# Verification
When a user adds a transaction (e.g., $500), the Transaction will save. The parent Project Budget's `Actual Spend` will instantly increase by $500 and its `Remaining Balance` will drop by $500, potentially shifting its `status` badge. Additionally, if the user navigates back to the main FY Dashboard, the overall KPI for `Total Spending` will reflect the new $+500 expense!