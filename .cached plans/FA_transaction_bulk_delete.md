# Objective
Implement the Multi-Delete functionality for Transactions, ensuring that deleting multiple records in bulk correctly recalculates the parent Project Budget's spending and the Fiscal Year Profile's total expenses using optimized DQL.

# Implementation Steps

## 1. Update Repository (`TransactionRepository.php`)
- Add `bulkDeleteDql(array $ids)`: Executes `DELETE FROM Transaction t WHERE t.id IN (:ids)`.

## 2. Update Service (`BudgetDashboardService.php`)
- Add `handleBulkDeleteCascade(ProjectBudget $budget, array $ids, ?BudgetProfile $profile)`:
  - Executes the bulk DQL delete.
  - Recalculates `actualSpend` for the `ProjectBudget` using the existing `SUM()` DQL.
  - Updates `ProjectBudget` status and pushes changes via DQL.
  - Recalculates total expenses for the `BudgetProfile` and pushes changes via DQL.

## 3. Create Confirmation Modal (`_delete_transactions_modal.html.twig`)
- Create a new partial in `FA_components`.
- Layout:
  - Center-aligned text: "Are you sure you want to delete **N** transactions?"
  - Icon: A large, outlined open trashcan icon (`ti ti-trash-x` or similar).
  - Buttons: "Cancel" (light) and "Delete Transactions" (btn-danger).
  - A hidden form that will be submitted upon confirmation.

## 4. Update Controller (`FinancialDashboardController.php`)
- Add a new `POST` route `#[Route('/budget/{id}/transactions/bulk-delete', name: 'apps-financial-analysis-bulk-delete-transactions', methods: ['POST'])]`.
- Extract IDs from the request and delegate to the service.

## 5. Update UI & JS (`_tab_transactions.html.twig`)
- Link the action bar "Confirm Delete" button to open the confirmation modal instead of submitting directly.
- Update the Javascript:
  - When the action bar "Confirm Delete" is clicked:
    1. Collect all `.transaction-checkbox:checked` IDs.
    2. If empty, show a toast/alert.
    3. If IDs exist, update the "N" text in the modal and store the IDs in the hidden form.
    4. Trigger the Modal to show.
  - When the red button *inside* the modal is clicked, submit the form.

# Verification
- Selecting 3 transactions and clicking "Confirm Delete" opens a modal.
- Modal says "Delete 3 transactions?" with a trash icon.
- Confirming the modal removes the records and updates all parent totals.