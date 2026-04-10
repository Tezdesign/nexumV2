# Objective
Fix the custom `#[Assert\Callback]` validation logic in the `BudgetProfile` entity to correctly calculate a 12-month period and decouple the validation checks.

# Investigation & Root Cause
The user reported that the validation fails even when the dates are correct. 
1. **The 12-Month Calculation Bug:** Currently, the logic uses `$expectedEndDate->modify('+1 year');`. If the start date is `2026-01-01`, this modification expects the end date to be `2027-01-01`. However, a standard 12-month fiscal year actually ends on `2026-12-31`. Because the logic did not subtract one day, every correct standard year submission was failing the 12-month validation rule.
2. **Coupled Validation Logic:** The current code uses an `elseif` structure between the 10-year limit check and the 12-month duration check. This means if the first check passes, the second check is evaluated. However, this structure makes the code harder to read and debug. They should be evaluated as independent `if` statements.
3. **The 10-Year Range Bug:** The current code only checks if `$inputYear < ($currentYear - 10)`. It does not check if the year is too far into the future. It should check both bounds.

# Implementation Steps
1. Open `src/Entity/FinancialAnalysis/BudgetProfile.php`.
2. Locate the `validateBusinessLogic()` method.
3. Separate the `if ($inputYear...)` and `elseif ($this->start_date...)` blocks into two completely independent `if` blocks.
4. Update the 10-year bounds check to include the future limit: `if ($inputYear < ($currentYear - 10) || $inputYear > ($currentYear + 10))`.
5. Fix the 12-month duration check by modifying the cloned date object by `+1 year` and then `-1 day` so that a start date of Jan 1st correctly maps to an expected end date of Dec 31st of the same year.

# Verification
When a user selects "2026", the inputs will auto-fill to `2026-01-01` and `2026-12-31`. Submitting this form will successfully pass the validation rules, as it is exactly +1 year minus 1 day. If they enter `2026-01-01` to `2027-01-01`, it will correctly throw the error.