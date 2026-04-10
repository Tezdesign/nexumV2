# Objective
Fix the two validation bugs reported by the user:
1. The 12-month period check failing on custom date ranges.
2. The 10-year limit check falsely triggering when switching to Custom mode.

# Investigation & Root Cause
1. **The 12-Month Period Bug:** The `BudgetProfile` entity uses `clone $this->start_date`. However, Symfony Forms generate `\DateTimeImmutable` objects by default for date fields. Calling `modify()` on a `DateTimeImmutable` object returns a *new* object, but does not mutate the original. Therefore, `$expectedEndDate` remained identical to the start date! When the script compared `end_date` against it, they never matched, incorrectly triggering the "must be exactly 12 months" error even for perfectly valid inputs.
2. **The 10-Year Range Bug:** In `FA_custom.js`, when the user toggles to "Custom Dates", the Javascript physically disables the `fiscal_year` dropdown `<select disabled>`. Browsers *do not submit* disabled fields. Therefore, Symfony receives `null` for `fiscal_year`. Because it is null, it triggers the `#[Assert\NotBlank]` constraint. The message for that constraint is *"select the fiscal year within a 10-year range"*. The user saw this message and incorrectly assumed the 10-year bounds check had failed, when in reality, the field was just empty!

# Implementation Steps

## 1. Fix the Entity Validation (`BudgetProfile.php`)
- Replace the buggy `clone` approach with a new mutable `\DateTime` instance parsed directly from the `start_date` format string.
- This guarantees that `.modify('+1 year')` safely mutates the `$expectedEndDate` object regardless of whether Symfony passed in a `DateTime` or `DateTimeImmutable`.

## 2. Fix the Javascript Submit Bug (`FA_custom.js`)
- Update the `toggleDateMode()` function so that it **no longer disables** the `fiscal_year` dropdown when switching to "Custom" mode. 
- The user must still select the Fiscal Year that their custom date range belongs to, and keeping it enabled ensures the value is successfully submitted in the POST request to pass the `NotBlank` check.

# Verification
When a user selects "Custom Dates", picks `2028-06-01` to `2029-05-31`, and selects "2028" as the Fiscal Year:
1. The dropdown is enabled, so "2028" is submitted.
2. The backend safely calculates that 2029-05-31 is exactly +1 year minus 1 day from 2028-06-01.
3. Both assertions pass perfectly without error.