# Objective
Implement the "Update Transaction" functionality with cascading DQL updates and resolve the breadcrumb "POST loop" bug.

# Investigation & Root Cause
1. **The Breadcrumb Bug:** The breadcrumb in `budget_details.html.twig` currently uses `javascript:history.back()`. When a user submits a form (creating a transaction), the "previous" page in the browser history is the POST request itself. Clicking "back" triggers a page reload or re-submission.
2. **Transaction Update Architecture:** We need to implement a robust DQL update for transactions that mirrors the project budget update, including the 110% rule validation and cascading totals up to the FY profile.

# Implementation Steps

## 1. Fix Breadcrumb Logic (`budget_details.html.twig` & Controller)
- **Controller:** In `budgetDetails`, pass the `$profile` object (the `BudgetProfile` entity) to the Twig template.
- **Twig:** Replace `javascript:history.back()` with a real Symfony route: `path('apps-financial-analysis-profile', {id: budgetProfile.id})`. This ensures clicking "Back" always leads to a clean GET request of the FY Dashboard.

## 2. Repository Update (`TransactionRepository.php`)
- Implement `updateTransactionDql(Transaction $transaction)` to explicitly set `reference`, `cost`, `date_stamp`, `expense_category`, and `description` via DQL.

## 3. Service Enhancement (`BudgetDashboardService.php`)
- Add `handleTransactionUpdateCascade(Transaction $transaction, ProjectBudget $budget, ?BudgetProfile $profile)`:
  - This method will execute the DQL update for the transaction.
  - It will then trigger the existing `SUM()` recalculations for the Project Budget and the Fiscal Year Profile to keep all financials perfectly in sync.

## 4. Create Update Modal (`_update_transaction_modal.html.twig`)
- We will follow the **Twig Recall** pattern.
- Because there are multiple transactions in a list, we will include the update modal **inside the loop** for each transaction row. This ensures each modal is hardcoded with its specific transaction's data.
- ID naming convention: `updateTransactionModal_{{ transaction.id }}`.

## 5. Controller Update (`FinancialDashboardController.php`)
- Create a new POST route `#[Route('/transaction/{id}/update', ...)]` to handle the specific transaction update submission.
- This keeps the main `budgetDetails` route clean and prevents form collision.

# Verification
- Clicking the "pencil" icon on a transaction row opens a pre-filled modal.
- Updating the cost (e.g., from $200 to $700) instantly updates the Project Budget's `Actual Spend` and the FY Dashboard's `Total Spending`.
- Clicking the breadcrumb after a submission leads directly back to the FY Dashboard without any "Resubmit" warnings.