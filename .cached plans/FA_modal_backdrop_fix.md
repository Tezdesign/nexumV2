# Objective
Fix the Bootstrap modal backdrop bug (where the screen remains blurred after closing a failed form) and resolve the browser's "Confirm Form Resubmission" warning when reloading the page.

# Investigation & Root Cause
1. **Stuck Blurred Background:** In `_create_profile_modal.html.twig`, the script uses `new bootstrap.Modal(document.getElementById('createProfileModal'))` to auto-open the modal after a validation error. Because the modal is also attached to a button via `data-bs-toggle="modal"`, creating a `new` instance in JavaScript duplicates the backdrop logic. When the user clicks "Cancel", Bootstrap hides the modal but leaves the `.modal-backdrop` div orphaned in the DOM, keeping the screen blurred.
2. **"Confirm Form Resubmission" Warning:** This is standard HTTP behavior. When the user submits the form and it fails validation, the Symfony Controller returns the Twig template directly within the `POST` request. If the user closes the modal and then clicks "Refresh" on their browser, the browser attempts to re-send the `POST` payload. 

# Implementation Steps

## 1. Fix the Bootstrap Duplicate Instance (`_create_profile_modal.html.twig`)
- **The Fix:** Instead of initializing a `new` modal, we will instruct Bootstrap to fetch the existing instance or safely create one using its native `.getOrCreateInstance()` method.
- **Code Change:**
  Change `const myModal = new bootstrap.Modal(...)` to:
  `const myModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('createProfileModal'));`

## 2. Fix the POST Resubmission Warning (`FA_custom.js`)
- **The Fix:** When the user closes the modal after experiencing a validation error (meaning they are abandoning their bad submission), the current page state is still technically a `POST`. We need to "flush" the browser state back to a clean `GET` request. 
- **Code Change:** Inside the `hidden.bs.modal` event listener in `FA_custom.js`, we will add a simple check: if the form has the `.is-invalid` or `.text-danger` classes visible (meaning the page loaded via a failed POST), we will forcefully redirect the browser to the clean URL (`window.location.href = window.location.pathname;`). This instantly wipes the POST data from the browser's memory, clearing the refresh warning and ensuring the user starts perfectly fresh!

# Verification
- Submitting an empty form pops the modal open.
- Clicking "Cancel" closes the modal, instantly unblurs the screen, and resets the page to a clean GET state.
- Hitting F5/Refresh after canceling will reload the page without any browser warnings.