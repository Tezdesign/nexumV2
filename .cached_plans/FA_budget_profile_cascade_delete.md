# Objective
Implement the top-level cascading deletion logic for a `BudgetProfile` (Fiscal Year). This will automatically trigger the deletion of all Project Budgets within that year's scope, as well as all their related Transactions, ensuring a clean and consistent database state.

# Architecture & Design
- **Cascade Strategy:** We will continue to handle the cascade at the Service layer using explicit DQL queries to protect the legacy Java database structure.
- **UI placement:** A new red "Delete Profile" button will be added directly next to the "Edit Profile" button on the FY Dashboard (`overview.html.twig`).
- **Confirmation:** A dedicated confirmation modal will be created to warn the user about the massive data loss (FY Profile + Projects + Transactions).

# Implementation Steps

## 1. Repository Updates
- **`TransactionRepository.php`**: Add `deleteByFiscalYearScopeDql(\DateTimeInterface $start, \DateTimeInterface $end)`:
  - Executes: `DELETE FROM Transaction t WHERE t.projectBudget IN (SELECT pb.id FROM ProjectBudget pb WHERE pb.dueDate >= :start AND pb.dueDate <= :end)`.
- **`ProjectBudgetRepository.php`**: Add `deleteByFiscalYearScopeDql(\DateTimeInterface $start, \DateTimeInterface $end)`:
  - Executes: `DELETE FROM ProjectBudget pb WHERE pb.dueDate >= :start AND pb.dueDate <= :end`.
- **`BudgetProfileRepository.php`**: Add `deleteProfileDql(int $id)` to remove the root profile.

## 2. Service Logic (`BudgetDashboardService.php`)
- Add `handleFullFiscalYearDeletionCascade(BudgetProfile $profile)`:
  1. Calls `TransactionRepository->deleteByFiscalYearScopeDql()`.
  2. Calls `ProjectBudgetRepository->deleteByFiscalYearScopeDql()`.
  3. Calls `BudgetProfileRepository->deleteProfileDql()`.

## 3. Controller Integration (`FinancialDashboardController.php`)
- Add a new `POST` route `#[Route('/profile/{id}/delete', name: 'apps-financial-analysis-delete-profile', methods: ['POST'])]`.
- Delegates the cascading deletion to the service and redirects the user back to the main Financial Analysis landing page.

## 4. UI Implementation
- **Confirmation Modal:** Create `templates/financial-analysis/FA_components/_delete_budget_profile_modal.html.twig`.
- **Dashboard Button:** Update `overview.html.twig` to add the Delete button next to the Edit button in the header row.
- **Include Modal:** Ensure the modal is included at the bottom of `overview.html.twig`.

# Verification
- Clicking "Delete Profile" on the FY Dashboard opens a final confirmation modal.
- Confirming the deletion removes the entire Fiscal Year ecosystem (Profile, Projects, and Transactions) in three optimized DQL queries.
- The user is returned to the landing page with a success message.
- No database migrations are required.