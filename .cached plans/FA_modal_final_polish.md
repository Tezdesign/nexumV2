# Objective
Refine the "Create Budget Profile" modal UI to address the final user feedback regarding error message placement and the restoration of the "Standard vs Custom" toggle buttons.

# Key Context & User Feedback
- **Global Error Warning:** The user found the red alert box at the top of the modal "crooked" and redundant since the inline errors are now working. It should be removed.
- **Budget Field Error Placement:** The inline error message for `budget_disposable` is currently rendering *inside* the Bootstrap `.input-group`, causing the UI to warp and the error to appear next to the input instead of cleanly below it.
- **Period Toggle Restoration:** The user wants the "Standard" vs "Custom" radio buttons brought back to the UI.
- **JavaScript Logic:** The JS needs to be re-added to listen to the restored radio toggles so that when "Standard" is selected and a year is chosen from the `fiscal_year` Select2 dropdown, the start and end dates are automatically filled in the Flatpickr inputs. The validation remains pure Twig/Asserts.

# Implementation Steps

## 1. UI Updates (`_create_profile_modal.html.twig`)
- **Remove Global Alert:** Delete the `{% if form.vars.submitted and not form.vars.valid %}` block containing the `.alert-danger` box at the top of the modal body.
- **Fix Budget Error Layout:** Move the `{% if not form.budget_disposable.vars.valid %}` error rendering block outside and directly below the `<div class="input-group">` wrapper so it sits flush under the input field.
- **Restore Radio Toggles:** Inject the "Fiscal Period Type" radio buttons (`periodStandard` and `periodCustom`) back into the modal, placing them in a row above the `fiscal_year` dropdown, next to the disabled `Profile Status` field.

## 2. JavaScript Updates (`FA_custom.js`)
- Restore the `toggleDateMode` logic that listens to the `periodStandard` and `periodCustom` radio buttons.
- When "Standard" is checked, selecting a year from the `fiscal_year` Select2 dropdown will automatically populate the `start_date` (YYYY-01-01) and `end_date` (YYYY-12-31) inputs.
- When "Custom" is checked, the `fiscal_year` dropdown simply functions as a year selector without overwriting the dates, allowing the user to pick their own start and end bounds from the calendar.

# Verification
- The global red alert box no longer appears.
- Form errors for `budget_disposable` appear cleanly below the input group.
- The "Standard" vs "Custom" radio buttons are visible and interactive.
- Selecting a year in "Standard" mode correctly auto-fills the Date Pickers without interfering with the backend validation cycle.