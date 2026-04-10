# Objective
Update the `_budget_hero_card.html.twig` partial (used on the individual Project Budget details page) to display the correct `status` badge, the formatted `dueDate`, and add a standard 3-dot dropdown menu containing "Update" and "Delete" actions (with the delete action styled red).

# Investigation & Context
- The user has already updated the `FinancialDashboardController.php` to correctly pass `status` and `dueDate` into the `$formattedBudget` array (now accessed via `projectBudget` in the Twig template).
- The current `_budget_hero_card.html.twig` only displays the project name, financials, and a utilization progress bar.
- We need to modify the header of the Hero Card to mimic the KPI cards, placing the Status badge and a 3-dot action dropdown in the top right.
- We also need to add the `dueDate` text, likely with a calendar icon, near the project name or below it.

# Implementation Steps

## 1. Update Hero Card Template (`_budget_hero_card.html.twig`)
- Open `templates/financial-analysis/FA_components/_budget_hero_card.html.twig`.
- Modify the top row:
  - Inside the left column (next to or below the Project Name), add the `dueDate` with a calendar icon (`<i class="ti ti-calendar-event me-1"></i> Due: {{ projectBudget.dueDate }}`).
  - Inside the right column (above the financial numbers or integrated into the flex layout), add the Status Badge using the existing logic:
    `{% if projectBudget.status == 'ON TRACK' %}badge-soft-success{% elseif projectBudget.status == 'AT RISK' %}badge-soft-warning{% else %}badge-soft-danger{% endif %}`.
  - Add the 3-dot dropdown (copied from the KPI structure).
    - Provide an "Update" action with a pencil icon (`text-dark`).
    - Provide a "Delete" action with a trash icon (`text-danger`).

# Verification
When the user navigates to a specific Project Budget details page, the Hero Card at the top will clearly display the active Status badge and the Due Date. Clicking the 3-dot menu in the top right will reveal "Update" and "Delete" actions styled correctly.