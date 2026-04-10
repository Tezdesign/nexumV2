# Objective
Implement the "Budget Details" page (Master-Detail workspace) for a specific Project Budget. This includes a main wrapper, a custom Hero Card, and a Tabbed Workspace (Overview, Transactions, Analysis) adhering strictly to the provided architectural guidelines.

# Key Files & Context
- **Controller:** `src/Controller/FinancialAnalysis/FinancialDashboardController.php` (Needs new route)
- **Templates:**
  - `templates/financial-analysis/budget_details.html.twig` (Main Wrapper)
  - `templates/financial-analysis/FA_components/_budget_hero_card.html.twig` (Custom Hero Card)
  - `templates/financial-analysis/FA_components/_tab_overview.html.twig`
  - `templates/financial-analysis/FA_components/_tab_transactions.html.twig`
  - `templates/financial-analysis/FA_components/_tab_analysis.html.twig`
- **Reference Template:** `templates/ui_ideas/ui-tabs.html.twig` (For the tabbed layout structure)

# Implementation Steps

## Phase 1: Controller & Data Preparation
- Add a new route in `FinancialDashboardController`: `#[Route('/budget/{id}', name: 'apps-financial-analysis-budget-details')]`.
- Pass a mock `$projectBudget` object to the template, containing data for the Hero Card (Project Name, Budget Name, Total Budget, Actual Spend, Remaining Balance, Utilization Percentage).

## Phase 2: The Custom Hero Card (`_budget_hero_card.html.twig`)
- Based on user feedback, this component will utilize the structure of the existing KPI widgets from the `overview.html.twig` page, but adapted to serve as a larger "Hero" summary.
- Construct a card that prominently displays the Project and Budget names in the header.
- Display the 3 core statistics (Total Budget, Actual Spend, Remaining Balance) side-by-side or stacked cleanly.
- Include a progress bar for utilization and make it distinctly **bigger/thicker** than the standard widget progress bars (e.g., using custom CSS height or removing `progress-sm`).
- Wrap text in `|trans` and use `currency_symbol`.

## Phase 3: The Main Wrapper & Tabbed Navigation (`budget_details.html.twig`)
- **Consultation Checkpoint:** We will extract the "Tabs Bordered Justified" HTML structure from `ui-tabs.html.twig` to use as the foundation for the tabbed container.
- Update the extracted HTML to use appropriate Tabler icons (`ti-chart-pie` for Overview, `ti-receipt` for Transactions, `ti-report-analytics` for Analysis).
- **Navigation:** Implement a Breadcrumb component exactly like the FY dashboard page (e.g., `Financial Analysis > FY 2026 Dashboard > Project Name`) to handle the "backstep" logic instead of a standalone button.
- Include the `_budget_hero_card.html.twig` partial above the tabs.
- Inject the tab content partials into the corresponding `.tab-pane` divs.

## Phase 4: The Transactions Tab (`_tab_transactions.html.twig`)
- Implement a 70% width layout (e.g., `col-lg-8` or similar) for the transactions list.
- **Toolbar:** Include a Search Bar and a Filter button.
- **Add Button:** Implement the purple "Add" button using the `ti-playlist-add` icon from Tabler (`btn-legacy-purple`).

# Next Action
Before generating the full `budget_details.html.twig` wrapper, I will extract the required HTML for the "Tabs Bordered Justified" component from the provided `ui-tabs.html.twig` file and present it to ensure it matches expectations.