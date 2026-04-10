# Objective
Fix the formatting of the `budget_disposable` value when it is recalled in the update modal's JavaScript reset logic to return an integer (e.g., `300000`) instead of a decimal string (`300000.00`), directly answering the user's concern about Euclidean division validation.

# Investigation & User Question
The user asked: *"Does this .00 decimal actually pose an issue, because the assert condition says it must be divisible by 10 (Euclidean division)?"*

**The Answer:** YES, it absolutely can pose an issue! 
1. **The Validation Risk:** Symfony's `#[Assert\DivisibleBy(value: 10)]` constraint relies on the PHP `fmod()` function under the hood. While `fmod(300000.00, 10)` *should* return `0`, floating-point math in PHP is notoriously inaccurate (the classic `0.1 + 0.2 = 0.30000000000000004` problem). If the user submits a value with decimals, the Euclidean division check can randomly fail with a false positive!
2. **The UI Problem:** It is also bad UX to have `.00` appear in the input box when the user is trying to type raw integers. 

Because the database stores it as `DECIMAL(10,2)`, Doctrine natively returns `"300000.00"`. We must format it cleanly in Twig before passing it to Javascript!

# Implementation Steps
1. Open `templates/financial-analysis/FA_components/_update_profile_modal.html.twig`.
2. Locate the inline JavaScript block at the bottom of the file.
3. Update the Twig injection for the budget input to use the `number_format` filter.
   - **Change from:** `if (budgetInput) budgetInput.value = '{{ budgetProfile.budgetDisposable }}';`
   - **Change to:** `if (budgetInput) budgetInput.value = '{{ budgetProfile.budgetDisposable|number_format(0, "", "") }}';` 
   - *Note: `number_format(0, "", "")` perfectly strips the decimals and prevents commas from being added (so `300000` doesn't accidentally become `300,000`, which would also break the number input!).*

# Verification
When the user closes the update modal, the `budget_disposable` input will correctly reset to the pure integer representation (e.g., `300000`), guaranteeing that the `DivisibleBy` Euclidean math works perfectly every time.