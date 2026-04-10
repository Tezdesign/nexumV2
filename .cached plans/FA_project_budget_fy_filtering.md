# Objective
Filter the `Project` options available in the Project Budget creation form so that only projects whose active dates overlap with the current Fiscal Year are selectable. Additionally, add a validation rule to the `ProjectBudget` entity ensuring its `dueDate` falls within the scope of the Fiscal Year.

# Investigation & Context
You are absolutely correct! The `dueDate` of a Project Budget will now be protected by **TWO completely independent Assert constraints** inside the `ProjectBudget` entity:
1. **The Project Constraint (Existing):** The `dueDate` cannot be later than the parent Project's end date.
2. **The Fiscal Year Constraint (New):** The `dueDate` must fall strictly between the Fiscal Year's start date and end date.

Because the `ProjectBudget` entity doesn't natively "know" the Fiscal Year dates during validation (it only knows about its parent `Project`), we must use **transient properties** to temporarily inject the FY dates into the entity right before validation runs, just like we did for the 110 Rule on `BudgetProfile` earlier.

# Implementation Steps

## 1. Add Transient Properties (`ProjectBudget.php`)
- Add two new transient properties: `private ?\DateTimeInterface $transientFiscalStart = null;` and `private ?\DateTimeInterface $transientFiscalEnd = null;`.
- Add their respective setters.
- In `validateProjectLogic()`, add the new assertion:
  - Check if `$this->dueDate < $this->transientFiscalStart` or `$this->dueDate > $this->transientFiscalEnd`.
  - If out of bounds, add a violation to `dueDate` stating it must fall between the Fiscal Year start and end dates.

## 2. Pass Options & Filter Query (`ProjectBudgetType.php`)
- In `configureOptions()`, add `'fiscal_start' => null` and `'fiscal_end' => null` as expected options.
- In `buildForm()`, update the `project` `EntityType` to use a `query_builder`.
- The `query_builder` will check if `p.end_date >= :fiscal_start` AND `p.start_date <= :fiscal_end`, ensuring the project was active during the Fiscal Year. If a project ended before the FY started, or starts after the FY ends, it is completely hidden from the dropdown!

## 3. Handle Empty State in Twig (`_create_project_budget_modal.html.twig`)
- As requested by the user, if the DQL filter results in zero available projects for the selected Fiscal Year, the Select2 dropdown shouldn't just be confusingly empty.
- We will add a Twig check: `{% if projectBudgetForm.project.vars.choices is empty %}`.
- If true, we will render a disabled `<select>` with a single option stating `"No projects are planned for this fiscal year"`, and optionally disable the Submit button so the user knows they cannot proceed without an active project.

## 4. Inject Data in Controller (`FinancialDashboardController.php`)
- Before `$projectBudgetForm->handleRequest($request);`, inject the transient dates into the entity:
  `$projectBudget->setTransientFiscalStart($originalProfile->getStartDate());`
  `$projectBudget->setTransientFiscalEnd($originalProfile->getEndDate());`
- Pass the dates into the form options array:
  `$this->createForm(ProjectBudgetType::class, $projectBudget, ['fiscal_start' => $originalProfile->getStartDate(), 'fiscal_end' => $originalProfile->getEndDate()]);`

# Verification
- When opening the Create Project Budget modal on the FY 2028 Dashboard, the "Linked Project" dropdown will only show projects that physically overlap with 2028.
- If there are zero projects planned for 2028, the dropdown will cleanly disable itself and display "No projects are planned for this fiscal year".
- If a user attempts to bypass the UI and submit an invalid due date (e.g., `2026-05-01`), the backend `#[Assert]` will successfully catch it and display a red error message because it fails one of the two strict date constraints.