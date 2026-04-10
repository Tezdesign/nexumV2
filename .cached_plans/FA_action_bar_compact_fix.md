# Objective
Revert the unwanted layout spreading in the Transactions action bar, ensure all elements sit directly next to each other on the far right, and implement the exact left-to-right DOM order requested by the user to make the "Confirm Delete" button expand smoothly to the left without pushing the other elements to the right.

# Investigation & Root Cause
- **The Layout Reversion:** You correctly diagnosed that I undid your compact layout! By adding `w-100`, `flex-grow-1`, and `ms-auto`, I forced the gray bar to stretch across the entire screen and artificially pushed elements apart. You wanted the bar to naturally shrink-to-fit its contents and sit nicely on the far right.
- **The Bootstrap DOM Order:** You are completely right about Bootstrap's flexbox behavior. Because the gray action bar is sitting in a `justify-content-between` header, the entire gray box is naturally anchored to the right edge of the screen. 
- If we place the Confirm Wrapper *before* (to the left of) the Trash can, when the wrapper expands from 0px to 200px, it simply forces the left edge of the gray box further to the left. The Trash can, Search bar, and all other buttons remain perfectly anchored to the right side of the screen and do not move a single pixel!

# Implementation Steps

## 1. Action Bar Layout Fix (`_tab_transactions.html.twig`)
- We will completely strip out `w-100`, `flex-grow-1`, `flex-shrink-0`, and `ms-auto` from the action bar and its child containers.
- We will rely purely on a simple `<div class="d-flex align-items-center gap-2 py-1 px-2 rounded">` so all elements sit tightly next to each other.
- Because the parent card-header has `justify-content-between`, the entire compact gray bar will automatically dock to the far right.

## 2. Re-ordering the Elements
We will strictly follow your left-to-right DOM order inside the gray container:
1. `confirm-delete-wrapper`
2. Trash Can button
3. Search Bar
4. Filter Options Icon button
5. Filter button
6. Add button

## 3. CSS Update (`FA.css`)
- Update `.show-checkboxes-mode .confirm-delete-wrapper` to use `margin-right: 0.5rem;` instead of `margin-left` since it now sits to the left of the Trash button and needs spacing on its right side.

# Verification
- The entire action bar will be a compact, tight group of icons and inputs sitting on the far right of the header.
- Clicking the Trash button will cause the Confirm Delete button to slide out to the *left* of the Trash icon. The gray bar will simply extend its left edge outward, and absolutely nothing on the right side (Trash, Search, Filter, Add) will move or be pushed!