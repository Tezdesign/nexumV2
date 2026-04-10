# Objective
Implement the Update functionality for Project Budgets using a custom DQL query in the Repository. The update will be strictly gated by a new `#[Assert\Callback]` validating the "110% Rule" for actual spending, and the Project Budget's `status` will automatically calculate based on its utilization percentage before the DQL update is executed.

# Context & Architecture
1. **The 110% Rule (Assert):** The user stated that when changing a project's total budget, the new budget cannot be so small that the current `actualSpend` exceeds 110% of it. 
   - Math: `actualSpend <= total_budget * 1.10`.
   - We will add a custom `#[Assert\Callback]` in `ProjectBudget` to throw a red Twig error if the user tries to shrink the budget below this threshold.
2. **Dynamic Status Calculation:** The user requested that the `status` automatically update based on utilization (`actualSpend / total_budget`):
   - 0% to 70%: `ON TRACK`
   - > 70% to 100%: `AT RISK`
   - > 100% to 110%: `OVER BUDGET`
   - *Note: Completed state is postponed.*
   We will add a `calculateStatus()` method to the entity. Because we are using DQL for the update, Doctrine lifecycle callbacks (`PreUpdate`) will be bypassed. Therefore, the Controller will explicitly call `$projectBudget->calculateStatus()` before passing the entity to the repository.
3. **DQL Update Repository Function:** The user explicitly requested to use DQL in the repository for the update. We will create an `updateBudgetDql(ProjectBudget $budget)` method in `ProjectBudgetRepository.php` that extracts the modified fields from the entity and runs a `UPDATE` query builder.

# Implementation Steps

## 1. Entity Updates (`ProjectBudget.php`)
- Add `calculateStatus()` to calculate utilization and assign the correct string (`ON TRACK`, `AT RISK`, `OVER BUDGET`).
- Add `validateBudgetUpdateLogic()` with `#[Assert\Callback]` to throw a violation if `actualSpend > total_budget * 1.10`.

## 2. Repository DQL Update (`ProjectBudgetRepository.php`)
- Add `updateBudgetDql(ProjectBudget $budget)` to explicitly run an `UPDATE` query setting the `name`, `project`, `total_budget`, `dueDate`, and `status`.

## 3. Create the Update Modal (`_update_project_budget_modal.html.twig`)
- Duplicate `_create_project_budget_modal.html.twig`.
- Rename IDs to `updateProjectBudgetModal` and `updateProjectBudgetForm`.
- Add the inline Twig script at the bottom to flawlessly "Recall" the pristine database data when the modal closes, utilizing the `number_format(0, "", "")` trick for the budget field.

## 4. Controller Integration (`FinancialDashboardController.php`)
- Create a new POST-only route `#[Route('/project-budget/{id}/update', name: 'apps-financial-analysis-update-project-budget', methods: ['POST'])]`.
- This route will process the `ProjectBudgetType` form.
- If valid, it will call `$projectBudget->calculateStatus()`, then `$projectBudgetRepository->updateBudgetDql($projectBudget)`, and finally redirect back to the Budget Details page (or the FY dashboard, depending on where the request originated).
- We will embed the modal in `budget_details.html.twig` and link the 3-dot dropdown "Update" button to open it.

# Verification
- The user clicks "Update" on the Hero Card 3-dot menu.
- If they lower the budget so much that spending exceeds 110%, the red `#[Assert]` triggers natively.
- When saved successfully, the `status` automatically shifts, and the database is updated directly via DQL.