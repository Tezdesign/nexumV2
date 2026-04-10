# Objective
Ensure the Update Budget Profile modal correctly restores the true database values when it is closed and reopened after a failed validation attempt, without relying on page reloads or JavaScript routing.

# Investigation & Root Cause
- The user observed that after submitting an invalid update, closing the modal, and reopening it, the invalid data and old error messages are still present in the fields.
- **Why is this happening?**
  1. In a previous step, I explicitly told the "Clean Modal on Close" JavaScript to *skip* clearing the inputs for the `updateProfileForm`, assuming we wanted to preserve the database values.
  2. However, when a form fails validation in Symfony, Symfony re-renders the page and overwrites the HTML `value="..."` attributes with the user's *invalid POST data*. 
  3. Because we stopped the JavaScript from doing a page reload (`window.location.href`) to drop the POST state, the browser is stuck on the failed POST page. Therefore, when the modal closes and reopens, it just displays the tainted HTML values that Symfony injected.

# Implementation Steps
Since we cannot use JavaScript routing to refresh the page, the only way to "fetch" the original database values without an HTTP request is to embed them into the HTML and use JavaScript to restore them manually on modal close.

## 1. Embed Original Data (`_update_profile_modal.html.twig`)
- We will add a hidden `<div>` with the ID `originalProfileData`.
- We will use Twig to map the true database values (which we already safely passed from the Controller as `budgetProfile`) into HTML `data-*` attributes (e.g., `data-budget-disposable="{{ budgetProfile.budgetDisposable }}"`).

## 2. Restore Data on Close (`FA_custom.js`)
- We will update the `attachModalCloseListener` function.
- When `formId === 'updateProfileForm'`, the script will:
  - Find the `#originalProfileData` element.
  - Read the `data-*` attributes.
  - Overwrite the input values (Text fields, Select2 dropdowns, and Flatpickr instances) with these pristine, true database values.
  - Delete the red `.text-danger` error messages (this part is already working).

# Verification
When the user submits an invalid update (e.g., "5" for the budget), sees the error, and clicks "Cancel":
- The script immediately restores the inputs to the true database values (e.g., "50000").
- The error messages vanish.
- Reopening the modal shows a perfectly clean form identical to the database state, completely bypassing the tainted POST data.