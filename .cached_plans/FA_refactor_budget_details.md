# Objective
Refactor the `budgetDetails` method in `FinancialDashboardController.php` to extract complex data fetching, DQL handling, and array formatting logic into `BudgetDashboardService.php`. This will drastically reduce the controller's bloat and adhere to the "Fat Model/Service, Skinny Controller" design pattern.

# Investigation & Context
- The user correctly observed that the `budgetDetails` method has grown to over 100 lines of code. It currently handles two separate forms (Project Budget Update and Transaction Create), executes multiple DQL queries to find the associated Fiscal Year profile, manually triggers entity lifecycle updates, and loops over transactions to format them into a Twig-friendly array.
- The `BudgetProfileRepository` logic (finding the parent FY profile based on the budget due date) does not belong in the controller.
- The DQL cascade logic (updating Project Budget Actual Spend and Fiscal Year Total Expenses) after a transaction is created is robust but pollutes the controller.
- The manual array formatting for `$formattedBudget` and `$transactionsData` should be extracted to a service to keep the controller clean and maintainable.

# Implementation Steps

## 1. Update `BudgetDashboardService.php`
We will inject `BudgetProfileRepository`, `ProjectBudgetRepository`, and `TransactionRepository` into the service. We will add four new methods:
- `getFiscalProfileForBudget(ProjectBudget $budget)`: Handles the DQL query to find the overlapping FY profile.
- `formatBudgetDetails(ProjectBudget $budget)`: Encapsulates the math and formatting for the Hero Card.
- `formatTransactions(ProjectBudget $budget)`: Loops over the transactions and formats them into an array.
- `handleTransactionCascade(ProjectBudget $budget, Transaction $transaction, ?BudgetProfile $profile)`: Moves the complex DQL cascade logic entirely out of the controller.

## 2. Refactor `FinancialDashboardController.php`
- Clean up the `budgetDetails` route.
- Use `$dashboardService->getFiscalProfileForBudget(...)` instead of writing the QueryBuilder inline.
- Simplify the transaction submission block by calling `$dashboardService->handleTransactionCascade(...)`.
- Use `$dashboardService->formatBudgetDetails(...)` and `$dashboardService->formatTransactions(...)` before passing the data to the `render` function.

# Verification
The `budgetDetails` method in the controller will shrink significantly, focusing exclusively on HTTP routing, form handling, and flashing messages. The actual business logic and DQL queries will be securely hidden inside the injected `BudgetDashboardService`, executing the exact same mathematical updates and UI formatting as before.