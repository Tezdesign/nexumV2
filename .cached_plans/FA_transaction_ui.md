# Objective
Design and implement the UI layout for individual Transactions in a list view format, replicating the aesthetic of the Project Budget rows but tailored for transaction data. We will also implement a "Multi-Delete" toggle feature in the Transactions tab header.

# UI/UX Design

## 1. Transaction List Row (`_transaction_list_row.html.twig`)
We will create a new partial template that follows the card-based row structure of `_project_budget_list_row.html.twig`. It will contain:
- **Checkbox (Hidden by Default):** A Bootstrap `.form-check-input` aligned to the left, which will only become visible when the multi-delete mode is toggled.
- **Reference & Date:** The `reference` (e.g., TX-123456) alongside an icon (e.g., `ti-receipt`), with the `date_stamp` displayed underneath.
- **Category:** The `expense_category` displayed as a sleek black badge (`badge bg-dark`).
- **Description:** A truncated version of the description.
- **Cost:** The `cost` explicitly styled in red (`text-danger`).
- **Actions:** A pencil icon (`ti-pencil`) button on the far right for editing the transaction.

## 2. Transactions Tab Layout & Empty State (`_tab_transactions.html.twig`)
We need to update the header of the Transactions tab to accommodate the new Multi-Delete flow, and implement a clean "Empty State" graphic exactly like we did for the Project Budgets.
- Add a trash can icon button (`btn-outline-danger`) next to the search bar.
- Add an inline JavaScript block to handle the toggle logic.
- **Toggle Logic:** When the trash can is clicked, the script will toggle a specific class (e.g., `show-checkboxes`) on the parent container of the transaction list.
- **CSS Transitions:** We will use CSS transitions to smoothly slide the content of the transaction rows to the right, revealing the previously hidden checkboxes.
- **Empty State Graphic:** If there are no transactions, we will display a clean, borderless card with a relevant icon (e.g., `ti-receipt-off`) and the text "No Transactions Found", keeping the layout consistent with the rest of the dashboard.
- **Layout & Sizing Constraint:** Because the row contains a checkbox, icon, reference, date, badge, description, cost, and action button, we will be extremely careful with Bootstrap column sizing (`col-md-2`, `col-lg-3`, `text-truncate`, `fs-12`, `fs-14`) and padding (`px-3`) to ensure the row remains spacious and uncrowded.

# Implementation Steps
1. Create `templates/financial-analysis/FA_components/_transaction_list_row.html.twig` with the agreed-upon structure, utilizing precise column widths to prevent crowding, and CSS transition classes for the checkbox.
2. Update `templates/financial-analysis/FA_components/_tab_transactions.html.twig` to include the Trash button, the Javascript toggle logic, and the "No Transactions Found" empty state graphic.
3. We will mock one or two transaction rows directly in the tab for now to demonstrate the perfectly aligned layout and multi-delete animation before we implement the backend PHP logic.

# Verification
- Navigating to the Transactions tab will show the mocked transaction rows with icons, black category badges, red cost text, and a pencil edit icon.
- Clicking the Trash Can button in the header will smoothly slide the rows to the right and reveal a checkbox next to each transaction.
- Clicking the Trash Can again will hide the checkboxes and slide the rows back to their original position.