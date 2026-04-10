# Objective
Heavily simplify the "Create Budget Profile" modal logic by completely removing the complex JavaScript validations, the "Standard vs Custom" radio buttons, and the hidden input fields. Instead, rely on a single, unified Select2 field for the Fiscal Year that automatically populates the visible date pickers, allowing the user to either accept standard dates or manually change them. Let Twig natively handle all validation.

# Implementation Steps

## 1. Update Form Type (`BudgetProfileType.php`)
- Change the `fiscal_year` field from a `TextType` to a `ChoiceType`.
- Populate the `choices` array dynamically with years ranging from 2000 to CurrentYear + 10.
- Apply the `select2` CSS classes and `data-toggle="select2"` to the field so it natively renders as a searchable dropdown.

## 2. Clean Modal HTML (`_create_profile_modal.html.twig`)
- **Remove Radio Buttons:** Completely delete the "Fiscal Period Type" radio inputs (`periodStandard` and `periodCustom`).
- **Remove Dummy Dropdown:** Delete the `standardYearSelect` `<select>` element.
- **Unhide Fiscal Year:** Place the real `form.fiscal_year` field exactly where the dummy dropdown used to be, applying the standard Bootstrap `needs-validation` styling (`is-invalid` and `.invalid-feedback`).
- **Keep Date Pickers Visible:** Ensure the `start_date` and `end_date` inputs are rendered normally without any `display: none` wrappers.

## 3. Purge Complex JavaScript (`FA_custom.js`)
- **Delete Toggle Logic:** Completely erase the `toggleDateMode()` function and the event listeners attached to the radio buttons.
- **Implement Clean Auto-Fill:** Write a simple jQuery `change` listener attached to the new `budget_profile_fiscal_year` Select2 dropdown. 
  - When the user selects a year (e.g., 2026), the script simply sets `startPicker` to `2026-01-01` and `endPicker` to `2026-12-31`.
  - Since there are no `readonly` locks anymore, if the user wants custom dates, they literally just click the calendar icon and pick different dates.
- **Preserve Modal Flush:** Keep the `hidden.bs.modal` event listener that flushes the form data, resets the Select2 values, clears the Flatpickr instances, and removes the `.is-invalid` validation classes when the modal is closed.

# Verification
- The modal UI is significantly cleaner, showing only the Status, Fiscal Year dropdown, and the two Date Pickers.
- Selecting a year auto-fills the dates, but the user is free to change them manually.
- Submitting bad data triggers pure server-side `#[Assert]` validation, and the red error text appears under the fields via Twig.