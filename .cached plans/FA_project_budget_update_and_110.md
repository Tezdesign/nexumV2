# Objective
Implement the Update functionality for Project Budgets, including the 110% Rule constraint via a custom `#[Assert]`, automatic `status` calculation based on utilization, and replicating the robust Update Modal UI (Twig Recall) that we established in our `MODAL_IMPLEMENTATION_GUIDE.md`.

# Context & Architecture
1. **The 110% Rule (Assert):** The user stated that the new `total_budget` cannot be so small that the `actualSpend` exceeds 110% of it. In math: `actualSpend <= total_budget * 1.10`. We will add a custom `#[Assert\Callback]` in `ProjectBudget` to throw a red Twig error if the user tries to shrink the budget below this threshold.
2. **Dynamic Status (Entity Lifecycle):** The user requested that the `status` automatically update based on utilization (`actualSpend / total_budget`):
   - 0% to 70%: `ON TRACK`
   - > 70% to 100%: `AT RISK`
   - > 100% to 110%: `OVER BUDGET`
   We will implement this using an `#[ORM\PrePersist]` and `#[ORM\PreUpdate]` lifecycle callback inside the `ProjectBudget` entity.
3. **Repository DQL vs Doctrine Flush:** The user mentioned "creating the update function that will use Dql in repository". Because we are using Symfony Forms to trigger the `#[Assert]` validation and show errors in the modal, we *must* use the Doctrine entity lifecycle (`$repository->save($entity, true)`) rather than a raw DQL `UPDATE` string (which bypasses validation entirely). We will use the existing `save()` repository method, but we can add any custom DQL data-fetching methods if needed.

# Implementation Steps

## 1. Entity Updates (`ProjectBudget.php`)
- Add `#[ORM\HasLifecycleCallbacks]` to the class if not already present.
- Create `calculateStatus()` with PrePersist/PreUpdate to calculate utilization and assign the correct string (`ON TRACK`, `AT RISK`, `OVER BUDGET`).
- Create `validateBudgetUpdateLogic()` with `#[Assert\Callback]` to throw a violation if `actualSpend > total_budget * 1.10`.

## 2. Create the Update Modal (`_update_project_budget_modal.html.twig`)
- Duplicate `_create_project_budget_modal.html.twig`.
- Rename IDs to `updateProjectBudgetModal` and `updateProjectBudgetForm`.
- Add the inline Twig script at the bottom to flawlessly "Recall" the pristine database data when the modal closes, exactly as we documented in the guide.

## 3. Controller Integration (`FinancialDashboardController.php`)
- The user requested the update action to happen on the FY Dashboard. 
- However, we have a list of *many* project budgets on the dashboard. Standard Symfony forms handle one entity at a time. To update a specific project budget from the grid, the easiest way is to create a dedicated route (e.g., `/project-budget/{id}/update`) that processes the POST request, or handle it dynamically.
- *Wait:* The standard way to handle grid row updates via modals without AJAX is to have the modal target a specific action URL (e.g. `action="{{ path('update_project_budget', {id: project.id}) }}"`). We will create a small controller route to handle the `POST` submission of the `ProjectBudgetType`, run validation, and redirect back to the `overview`.

# Verification
- The user can click "Update" on a project budget card to open the modal.
- If they lower the budget so much that spending exceeds 110%, the red `#[Assert]` triggers natively.
- When saved successfully, the `status` automatically shifts between ON TRACK, AT RISK, and OVER BUDGET based on the math.