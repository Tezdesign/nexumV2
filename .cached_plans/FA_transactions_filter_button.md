# Objective
Fill the empty space in the Transactions action bar by moving the layout pushing class (`me-auto`) to a new, borderless, backgroundless "Filter Parameters" button using Tabler icons.

# Investigation & Context
- The user noted that there is still a block of empty space in the upper action bar. This is caused by the `me-auto` Bootstrap class on the `.app-search` div, which forcefully pushes all subsequent elements (Filter and Add buttons) to the far right.
- To resolve this and add functionality, the user requested a borderless, transparent button containing a filter parameters icon from the Tabler library, placed in that empty space.

# Implementation Steps
1. Open `templates/financial-analysis/FA_components/_tab_transactions.html.twig`.
2. Locate the Search Bar div: `<div class="app-search d-none d-sm-block me-auto ms-2">`.
3. Remove the `me-auto` class from the Search Bar.
4. Immediately following the Search Bar, inject a new `<div>` that contains the `me-auto ms-2` classes.
5. Inside this div, add a button styled as a `btn btn-link text-muted p-1 text-decoration-none` to make it borderless and backgroundless.
6. Inside the button, use a Tabler icon suitable for parameters (e.g., `<i class="ti ti-adjustments-horizontal fs-20"></i>`).

# Verification
When the dashboard loads, the search bar will sit next to the trash icon. Immediately next to the search bar will be a sleek, borderless slider icon button. This new button wrapper will push the remaining action buttons cleanly to the right side of the card header, perfectly utilizing the empty space.