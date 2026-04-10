# Objective
Clean up the Action Bar layout in the Transactions tab to resolve the spacing issues related to the `confirm-delete-wrapper` and properly align the right-side action buttons.

# Investigation & Root Cause
You have a great eye—the `confirm-delete-wrapper` absolutely was causing a ghost spacing issue!
1. **The Wrapper Ghost Space:** Even though the wrapper had `max-width: 0` and `opacity: 0`, the browser's flexbox layout engine still sometimes calculates a tiny bit of width for hidden elements if they contain text nodes or if `width: 0` isn't explicitly set. This created a microscopic but annoying invisible gap between the Trash icon and the Search bar.
2. **The `me-auto` Issue:** By removing `me-auto`, you correctly stopped the giant empty space from forming in the middle. However, we still need the "Filter" and "Add" buttons to sit on the far right edge of the gray box. 

# Implementation Steps

## 1. Fix the CSS Wrapper (`FA.css`)
- We will add `width: 0;` and `padding: 0;` explicitly to `.confirm-delete-wrapper` to guarantee it collapses to absolute zero pixels when hidden.
- We will add `width: auto;` back to it when the `.show-checkboxes-mode` class is activated so it expands smoothly.

## 2. Refine the Layout (`_tab_transactions.html.twig`)
- We will structure the Action Bar as a single, rigid flex row (removing `flex-wrap` so the Confirm button sliding out doesn't force the bar to break onto two lines).
- **Left Side:** Trash Button -> Confirm Wrapper -> Search Bar -> Parameters Icon.
- **Right Side:** We will wrap the final two buttons (Filter and Add) in a `<div class="d-flex align-items-center gap-2 ms-auto">`. The `ms-auto` (margin-start: auto) on this specific wrapper will gracefully push *only* those two buttons to the far right, leaving the search bar exactly where it belongs without giant empty spaces!

# Verification
When the page loads, the Trash icon and Search bar will be perfectly flush. The Parameters icon will sit closely next to the search bar. The final two action buttons will sit cleanly on the far right. Clicking the Trash icon will slide the Confirm button out smoothly, safely pushing the search bar to the right without breaking the layout.