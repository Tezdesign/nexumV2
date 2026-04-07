# Objective
Implement the "Project Budgets" section within the Financial Analysis module, utilizing a Master-Detail pattern with Grid and List views, populated with component-driven Twig partials based on the user-contacts UI model.

# Key Files & Context
- `templates/financial-analysis/overview.html.twig`: The parent container that will house the search bar, the toggle for Grid/List view, the "Show All" button, and the `for` loop to render up to 6 project cards.
- `templates/financial-analysis/FA_components/_project_budget_card.html.twig`: A new Twig component for the **Grid View**. Styled similar to the user-contact cards, but adapted for financial data (Header, Status Badge, 3-Column Metrics, Due Date footer).
- `templates/financial-analysis/FA_components/_project_budget_list_row.html.twig`: A new Twig component for the **List View**. A horizontal layout utilizing icons, badges, and buttons matching the card data.
- `templates/ui_ideas/apps-user-contacts.html.twig`: The reference UI for the grid structure and visual components (cards, badges, buttons).

# Architecture & Implementation Steps

## 1. Directory Structure
use a new directory: `templates/financial-analysis/FA_components/` to hold the reusable UI components.

## 2. Create the Grid Card Component (`_project_budget_card.html.twig`)
- Adapt the `.card.text-center` structure from the user-contacts model.
- **Dynamic Data:** Use `{{ project.name }}`, `{{ project.subtitle }}`, `{{ project.status }}`, `{{ project.totalBudget }}`, `{{ project.actualSpend }}`, `{{ project.remaining }}`, `{{ project.dueDate }}`.
- **UI Elements:** Use Bootstrap badges for the Status, layout the 3 financial metrics side-by-side using Flexbox or Grid, and add a "Details" button that acts as a placeholder link (use `href="#"` for now as the behavior is undecided).
- **i18n & Currency:** Wrap text in `|trans` and use the `{{ currency_symbol|default('$') }}` fallback for the financial metrics.

## 3. Create the List Row Component (`_project_budget_list_row.html.twig`)
- Build a horizontal equivalent of the card (e.g., a `.card` acting as a single list row or a styled `<tr>` if using a table structure, though flexbox rows are preferred to match the user-contacts aesthetic).
- Must include the identical data points, badges, and "Details" button (with `href="#"`) as the grid card.

## 4. Update the Parent Container (`overview.html.twig`)
- Add a new "Project Budgets" section below the KPI widgets.
- **Toolbar:** Include a Search Bar input and a Grid/List view toggle button.
- **Display Logic:**
  - Create a mock `projects` array variable at the top of the file (until the Controller provides live data).
  - Use `{% for project in projects|slice(0, 6) %}` to restrict the initial display to a maximum of 6 elements.
  - Implement the `{% include 'financial-analysis/FA_components/_project_budget_card.html.twig' with {'project': project} %}` logic.
- **"Show All" Button:** Add a button below the grid/list that says "Show All Projects" (wrapped in `|trans`), which would either trigger a full page load or a JS expansion to reveal projects beyond the initial 6.

## 5. Save Plan to Project
- Once out of Plan Mode, save this final agreed-upon architecture plan to an `.md` file in the main project directory (e.g., `FA_project_budgets_plan.md`) for future consultation.

# Verification & Testing
- The frontend will gracefully render up to 6 custom mock project cards.
- The UI will maintain the agreed-upon standards for translation (`|trans`) and currency (`currency_symbol`).
- The components will be cleanly segregated into the `FA_components` folder.