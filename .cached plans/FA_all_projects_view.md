# Objective
Implement a "View All Project Budgets" page for a specific Fiscal Year, allowing users to see the full list of projects beyond the limited subset shown on the main Dashboard. This new page will mirror the landing page's card-based layout while maintaining the dashboard's action bar and breadcrumb structure.

# Architecture & Changes

## 1. Extract Shared Action Bar (`_project_budget_action_bar.html.twig`)
To ensure the "Upper Bar" is identical and "wider" on the new page, we will extract the Project Budget header (Search, Filter, Add, and View Toggles) into a reusable partial in `FA_components`. 
- This partial will be used by both `overview.html.twig` and the new `all_projects.html.twig`.
- It will include the "Show All Projects" button, which we will conditionally hide if the user is already on the "All Projects" page.

## 2. Controller Enhancement (`FinancialDashboardController.php`)
- **`overview` method:** Update the logic to only pass the first 6 project budgets to the main dashboard to keep it concise.
- **`allProjects` method:** Create a new route `#[Route('/profile/{id}/projects', ...)]` that fetches the *entire* list of project budgets for the Fiscal Year scope. 
- Reuse the same `ProjectBudgetType` form handling logic so users can also add projects from the "All Projects" view.

## 3. New Template (`all_projects.html.twig`)
- Create a new template that extends `vertical.html.twig`.
- **Layout:** Use a full-width container (`col-12`) for the action bar and the project grid/list.
- **Card Grid:** We will use a responsive grid (`row-cols-1 row-cols-md-2 row-cols-lg-3 row-cols-xxl-4`). As per the user's feedback, the cards will **remain the exact same size** as they are on the dashboard; we are simply expanding the available space to render more cards per row (the "optimal amount") rather than limiting them to a narrow column.
- **Breadcrumbs:** Implement the three-step breadcrumb: `Fiscal Budget Profiles` -> `FY [YEAR] Dashboard` -> `All Projects`.

# Implementation Steps
1. Create `templates/financial-analysis/FA_components/_project_budget_action_bar.html.twig` by moving the logic from `overview.html.twig`.
2. Update `FinancialDashboardController.php` with the new `allProjects` route and sliced data for `overview`.
3. Create `templates/financial-analysis/all_projects.html.twig`.
4. Update `overview.html.twig` to use the new action bar partial.

# Verification
- Clicking "Show All Projects" on the FY Dashboard correctly redirects to the new page.
- The "All Projects" page displays all budgets in a full-width grid.
- The action bar (Search, Add, View Toggles) works perfectly on both pages.
- Breadcrumbs allow for easy navigation back to the Dashboard or Landing page.