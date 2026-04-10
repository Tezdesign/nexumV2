# Objective
Fix the "Null argument exception" (TypeError) occurring during Transaction submission by allowing null values in the entity's setter methods.

# Investigation & Root Cause
- The user reported a `Null argument exception` for `date_stamp` when submitting a transaction.
- **Why this happens:** In the `Transaction` entity, the setter methods for `cost` and `date_stamp` (and its alias `dateStamp`) have strict type hints that do not allow `null`.
  - `public function setDate_stamp(\DateTimeInterface $date_stamp)`
  - `public function setCost(string $cost)`
- When a user submits an empty form or clears these fields, Symfony's Form component attempts to map the empty input to the entity by calling these setters with a `null` value. Since the type hints are strict, PHP throws a fatal `TypeError` before the `#[Assert\NotBlank]` validation can even run. This matches the exact issue we previously fixed in the `ProjectBudget` entity.

# Implementation Steps
1. Open `src/Entity/FinancialAnalysis/Transaction.php`.
2. Update `setCost` to allow null: `public function setCost(?string $cost): self`.
3. Update `setDate_stamp` to allow null: `public function setDate_stamp(?\DateTimeInterface $date_stamp): self`.
4. Update `setDateStamp` to allow null and use `DateTimeInterface`: `public function setDateStamp(?\DateTimeInterface $date_stamp): static`.

# Verification
Submitting an empty or partially filled Transaction form will no longer cause a fatal PHP error. Instead, the form will submit successfully to the server, and Doctrine's `#[Assert]` rules will trigger the red validation messages in the modal as intended.