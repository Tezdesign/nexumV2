# Objective
Remove the blue dashed border from the empty state graphics in both the landing and overview pages, and fix the bug where invalid form submissions temporarily alter the background KPI values on the FY Dashboard.

# Investigation & Root Cause
1. **Blue Border on Empty State:** The user requested the removal of the blue border surrounding the "No Budget Profile Set" and "No Project Budgets Found" graphics. These graphics are wrapped in a Bootstrap card with the classes `border-dashed border-2 border-primary border-opacity-25`. We will remove these specific classes.
2. **KPIs Showing Invalid Data:** The user noticed that when they submit an invalid update (e.g., an invalid budget value), the background dashboard temporarily updates to show the broken value, even though the update was rejected by the database. 
   - **Why this happens:** When `handleRequest($request)` is executed in Symfony, the Form component takes the user's POST data and injects it directly into the `$budgetProfile` entity stored in PHP's memory. When validation fails, Symfony correctly stops the database from saving. However, the Controller then proceeds to calculate the KPIs using that same `$budgetProfile` entity in memory, which now holds the invalid data! When the user refreshes, it fetches the real data from the database, which is why it reverts back.

# Implementation Steps

## 1. Fix the KPI Logic (`FinancialDashboardController.php`)
- Inside the `overview()` method, we will create a `clone` of the `$budgetProfile` (e.g., `$originalProfile = clone $budgetProfile;`) *before* executing `$form->handleRequest($request)`.
- We will use `$originalProfile` to run the `getTotalsForFiscalYear` and `findByFiscalYearScope` DQL queries, ensuring the background dashboard is always tied to the true database dates.
- We will calculate all KPI variables using `$originalProfile->getBudgetDisposable()`.
- We will pass `$originalProfile` to the Twig template as `'budgetProfile'`, ensuring the background breadcrumbs and headers don't change either.
- The `$form` will continue to use the mutated `$budgetProfile`, so the modal will successfully retain the user's invalid input for them to fix!

## 2. Remove Empty State Borders (`landing.html.twig` & `overview.html.twig`)
- Locate the `<div class="card text-center border-dashed border-2 border-primary border-opacity-25 bg-transparent shadow-none">` in both templates.
- Remove the `border-dashed`, `border-2`, `border-primary`, and `border-opacity-25` classes from both cards.