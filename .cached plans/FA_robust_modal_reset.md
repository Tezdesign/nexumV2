# Objective
Implement a simpler, foolproof "reset and recall" mechanism for the Update Modal. When the modal closes, we will visually flush the form and then immediately inject the true, cached PHP entity data directly into the inputs using a Twig-rendered inline script, completely bypassing complex DOM dataset traversal.

# Context & User Feedback
The user suggested a brilliant, pragmatic workaround: instead of wrestling with complex Javascript `dataset` parsing or creating hidden HTML elements, why not just reset the form and use Twig to directly print the original PHP entity values into a tiny script that fires when the modal closes? 
This flawlessly fulfills the requirement of "recalling the data from the cached php object" because Twig will hardcode the pristine database values straight into the Javascript execution block on the server side before the page is even sent to the browser!

# Implementation Steps

## 1. Clean up global `FA_custom.js`
- Open `FA_custom.js`.
- Completely delete the `else if (formId === 'updateProfileForm')` block that attempts to read from the hidden HTML `dataset` elements.
- We will leave `FA_custom.js` strictly responsible for handling the "Create" modal's blank slate reset, and the Flatpickr/Select2 initialization.

## 2. Inline Twig Script (`_update_profile_modal.html.twig`)
- Open `_update_profile_modal.html.twig`.
- Delete the `<div id="originalProfileData">` element (we no longer need it).
- At the bottom of the file, inside the existing `<script>` block, add a new `hidden.bs.modal` event listener specifically for `updateProfileModal`.
- When the modal closes, the script will:
  1. Call `form.reset()` to flush the active typing.
  2. Directly overwrite the inputs with the original PHP data using Twig syntax (e.g., `document.getElementById('budget_profile_budget_disposable').value = '{{ budgetProfile.budgetDisposable }}';`).
  3. Safely update the Select2 and Flatpickr instances using the same direct Twig injections.
  4. Forcefully `.remove()` the `.text-danger` error messages.

# Verification
When the user submits an invalid update, the page reloads. Closing the modal will fire the inline script, immediately stripping the errors and hard-injecting the original PHP database values straight into the inputs. No routing, no complex DOM traversal, just simple, guaranteed restoration!