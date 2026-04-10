# Objective
Implement the high-level deletion logic for a `ProjectBudget`, which will automatically trigger the deletion of all its related transactions and update the parent Fiscal Year `BudgetProfile` totals, ensuring full data consistency.

# Investigation & Architecture
- **Cascade Deletion:** To avoid complex and potentially dangerous migrations on the legacy Java database, we will handle the "cascade" at the Service layer using explicit DQL queries. This means we do not need a database migration for this feature.
- **Data Integrity:** When a project budget is deleted, its associated spending must be removed from the overall Fiscal Year profile. By using the Service layer, we can perform this recalculation in one atomic step.

# Implementation Steps

## 1. Repository Updates
- **`TransactionRepository.php`**: Add `deleteByProjectBudgetDql(int $pbId)` to remove all transactions linked to a budget in a single optimized query.
- **`ProjectBudgetRepository.php`**: Add `deleteProjectBudgetDql(int $id)` to remove the budget record.

## 2. Service Logic (`BudgetDashboardService.php`)
- Add `handleProjectBudgetDeletionCascade(ProjectBudget $budget, ?BudgetProfile $profile)`:
  1. Calls `TransactionRepository->deleteByProjectBudgetDql()`.
  2. Calls `ProjectBudgetRepository->deleteProjectBudgetDql()`.
  3. Uses `ProjectBudgetRepository->getTotalsForFiscalYear()` to find the new sum of expenses for all *remaining* projects.
  4. Calls `BudgetProfileRepository->updateTotalExpenseDql()` to push the fresh total to the root profile.

## 3. Controller Integration (`FinancialDashboardController.php`)
- Add a new `POST` route `#[Route('/budget/{id}/delete', name: 'apps-financial-analysis-delete-project-budget', methods: ['POST'])]`.
- This route will delegate the entire cascade to the service and then redirect the user back to the parent FY Dashboard.

## 4. UI Implementation
- **Confirmation Modal:** Create `templates/financial-analysis/FA_components/_delete_project_budget_modal.html.twig`. It will follow our strict architectural standards, featuring a large red trash icon and a clear warning message.
- **Hero Card Hookup:** Update `_budget_hero_card.html.twig` to link the "Delete" dropdown option to the new modal.
- **Include Modal:** Ensure the modal is included at the bottom of the `budget_details.html.twig` file.

# Verification
- Clicking "Delete" from the Project Budget Hero Card opens a confirmation modal.
- Confirming the deletion removes the budget and its 10+ transactions instantly.
- The user is returned to the FY Dashboard, where the "Total Spending" KPI has correctly decreased by the budget's total actual spend.
- No database migrations are required.