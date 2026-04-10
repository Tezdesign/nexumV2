# Objective
Implement custom, high-performance scrollbars using the SimpleBar library across the Financial Analysis module. This will allow the data lists (Fiscal Years, Project Budgets, and Transactions) to be scrollable while keeping their respective headers and action bars fixed at the top.

# Architecture & Design

## 1. Helper CSS Classes (`FA.css`)
We will add standard scrollbar container classes to ensure consistency:
- `.fa-scrollable-container`: Basic wrapper with `data-simplebar` and `overflow-x: hidden`.
- Toggled `max-height` logic for the "Fuller View" feature.

## 2. Landing Page Integration (`landing.html.twig`)
- Wrap the `row row-cols-...` grid in a `<div data-simplebar style="max-height: 600px; padding-right: 10px;">`.
- This ensures that if the user has 20+ Fiscal Year profiles, they can scroll through them without the main page search/filter bar moving out of sight.

## 3. All Projects Page Integration (`all_projects.html.twig`)
- Wrap the project display area in a `<div id="project-scroll-container" data-simplebar style="max-height: 600px; transition: max-height 0.3s ease; padding-right: 10px;">`.
- **Extended View Feature:** Add a "Maximize" icon button (`ti-arrows-maximize`) to the `_project_budget_action_bar.html.twig`. Clicking this will toggle the container height to `1200px` for a "fuller view."

## 4. Transactions Tab Integration (`_tab_transactions.html.twig`)
- Apply `data-simplebar` directly to the `<div class="card-body" id="transaction-list-container">`.
- Set `max-height: 500px`.
- This keeps the transaction details (right-side column) and the action bar (top of left column) perfectly fixed while the user scrolls through dozens of transaction records.

# Implementation Steps
1. Update `public/css/FA.css` with smooth transition rules for the scroll containers.
2. Modify `templates/financial-analysis/landing.html.twig` to add the scroll wrapper.
3. Modify `templates/financial-analysis/FA_components/_project_budget_action_bar.html.twig` to add the "Expand" button.
4. Modify `templates/financial-analysis/all_projects.html.twig` to add the scroll wrapper.
5. Modify `templates/financial-analysis/FA_components/_tab_transactions.html.twig` to add `data-simplebar`.

# Verification
- Navigating to each page reveals a custom, sleek scrollbar when content exceeds the height limit.
- The action bars (Search, Filter, Add) remain visible at all times while scrolling.
- Clicking the "Expand" button on the All Projects page smoothly doubles the scroll area height.