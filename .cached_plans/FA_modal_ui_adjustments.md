# Objective
Refine the "Create Budget Profile" modal UI based on user feedback. This includes disassociating the standard year select from the date pickers, making date pickers always visible, adding a disabled Select2 input for Status, and updating the year range to be from 2000 to 10 years in the future.

# Key Context
- **Validation Question Answered:** Yes, the strict validation rules (like checking the exact 10-year constraint and 12-month duration) are handled securely on the backend via the `BudgetProfile` entity's `#[Assert]` constraints. The JS only checks for required fields (Bootstrap's `checkValidity()`). The backend errors are then passed back to Twig.

# Implementation Steps

## 1. UI Updates (`_create_profile_modal.html.twig`)
- **Status Field:** Add a disabled `<select class="form-select" data-toggle="select2" disabled>` next to the Fiscal Period type toggle to visually represent the "DRAFT" status.
- **Always Visible Dates:** Remove `style="display: none;"` from the `#customDatesWrapper`.
- **Year Range Update:** Modify the Twig `for` loop for the `standardYearSelect` from `{% for year in currentYear..(currentYear + 5) %}` to `{% for year in 2000..(currentYear + 10) %}`. Set the current year to be `selected` by default.

## 2. Javascript Logic Updates (`FA_custom.js`)
- **Disassociate Visibility:** Remove the logic in `toggleDateMode()` that hides/shows the `customDatesWrapper` and `standardYearWrapper`. Both should remain visible.
- **Read-Only Toggle:** Instead of hiding fields, `toggleDateMode()` will now:
  - If "Standard" is selected: Make the `start_date` and `end_date` inputs `readonly` (or `disabled` via Flatpickr) so the user cannot manually edit them, and force them to auto-fill based on the `standardYearSelect` dropdown.
  - If "Custom" is selected: Remove the `readonly`/`disabled` state from `start_date` and `end_date` so the user can manually pick dates. Disable the `standardYearSelect` dropdown so it doesn't interfere.
- **Maintain Auto-Fill Logic:** Keep the logic that auto-populates the hidden `fiscal_year` input when either the Standard Year dropdown changes or the Custom `end_date` changes.

# Verification
- The modal displays the Status field as a disabled Select2 dropdown.
- The date pickers are always visible on the screen.
- Toggling between Standard and Custom disables/enables the appropriate inputs without hiding them.
- The year dropdown spans from 2000 to Current Year + 10.