# Objective
Fix the layout and logic bugs in the Transactions tab, including the excessive height of the action bar, the simultaneous rendering of the empty state and mock rows, and the permanent visibility of the "Confirm Delete" button and checkboxes (which is caused by a CSS caching issue).

# Investigation & Root Cause
1. **Empty State Bug:** The mock transaction rows were placed *outside* the `{% if transactions is empty %}` Twig block. Because `transactions` is currently an empty array, the script renders the "No Transactions Found" block, but then continues down the file and unconditionally renders the mock rows right below it.
2. **Action Bar Height:** The `<form>` tag inside the `.app-search` container often carries default Bootstrap bottom margins, stretching the height of the action bar. The padding on the container itself (`p-2`) might also be slightly too thick.
3. **Checkboxes & Button Always Visible:** The `FA.css` file contains the exact rules to hide the checkboxes (`left: -40px`, `opacity: 0`) and the confirm button (`max-width: 0`, `opacity: 0`). If they are permanently visible and sitting outside the card, your browser is using an old, cached version of `FA.css` from before we wrote those rules! 

# Implementation Steps

## 1. Twig Logic Fix (`_tab_transactions.html.twig`)
- Open `templates/financial-analysis/FA_components/_tab_transactions.html.twig`.
- Move the two mock `{% include ... %}` rows inside the `{% else %}` block of the `transactions is empty` conditional.
- Update the mock check: For testing the UI, we will temporarily change the condition to `{% if false %}` so the mock rows render and the empty state is hidden. (We will revert this when real data is piped in).

## 2. Action Bar Layout (`_tab_transactions.html.twig`)
- Change the action bar padding from `p-2` to `py-1 px-2`.
- Add the class `mb-0` to the `<form>` element inside the search bar to strip any default bottom margins.

## 3. CSS Cache Busting (`budget_details.html.twig`)
- Open `templates/financial-analysis/budget_details.html.twig`.
- Locate the `<link href="/css/FA.css" rel="stylesheet" type="text/css" />` tag in the `{% block css %}`.
- Append `?v={{ random() }}` to the href to force the browser to download the latest CSS file, which contains the rules to hide the checkboxes and confirm button by default.

# Verification
- When the page reloads, the "Confirm Delete" button and the row checkboxes will be completely invisible.
- The action bar will be compact and perfectly vertically centered.
- Only the mock transaction rows will render (the empty state will be hidden).
- Clicking the Trash Can icon will trigger the Javascript, which will add the `.show-checkboxes` classes, and the CSS transition will smoothly slide the items into view!