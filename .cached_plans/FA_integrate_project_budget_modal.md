# Objective
Integrate the newly created `ProjectBudgetType` and its corresponding UI modal into the Financial Dashboard (`overview.html.twig`) to allow users to add new Project Budgets directly from the FY Dashboard.

# Implementation Steps

## 1. Controller Update (`FinancialDashboardController.php`)
- Instantiate the `ProjectBudgetType` form inside the `overview` method.
- Pass the form to the view as `projectBudgetForm`.
- Add submission handling: If `$projectBudgetForm->isSubmitted() && $projectBudgetForm->isValid()`, call the newly implemented `$projectBudgetRepository->save($projectBudget, true)` method, flash a success message, and redirect back to the same page.
- Make sure to keep the existing logic for the `BudgetProfileType` form intact.

## 2. UI Update (`overview.html.twig`)
- Locate the existing `layout-grid-add` button in the Project Budgets section header.
- Add `data-bs-toggle="modal" data-bs-target="#createProjectBudgetModal"` to this button so it opens the new modal.
- Include the `_create_project_budget_modal.html.twig` template at the bottom of the page, next to the `_update_profile_modal.html.twig` include.

# Verification
- Clicking the "plus" icon button next to the search bar successfully opens the "Create Project Budget" modal.
- The Flatpickr and Select2 inputs initialize correctly.
- Submitting an empty or invalid form reloads the page, pops the modal back open, and displays the correct inline `#[Assert]` errors.
- Submitting a valid form successfully creates a new Project Budget in the database via the Repository and returns the user to the dashboard with a success message.