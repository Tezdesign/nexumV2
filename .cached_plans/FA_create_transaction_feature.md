# Objective
Implement the "Add Transaction" functionality by fixing the `Transaction` entity types, configuring the `TransactionType` form, building the UI Modal (`_create_transaction_modal.html.twig`), adding the required Repository logic, and integrating it cleanly into the `budgetDetails` Controller.

# Context & Architecture Plan

## 1. Entity Type Fix (`Transaction.php`)
- **Investigation:** I noticed that the `description` property in your `Transaction` entity was accidentally mapped as a nullable `integer`! (`private ?int $description = null;`). 
- **Fix:** I will rewrite the `description` property and its getter/setters to be a nullable `string` with `type: 'string', length: 255`, which aligns with standard text inputs.

## 2. Form Builder (`TransactionType.php`)
- We will configure the form precisely according to your `MODAL_IMPLEMENTATION_GUIDE.md`:
  - `reference`: A `TextType` for the TX-XXXXXX format.
  - `cost`: A `NumberType`.
  - `date_stamp`: A `DateType` configured with the `flatpickr` widget format.
  - `expense_category`: A `ChoiceType` formatted with `select2` (e.g., SOFTWARE, HARDWARE, SERVICES, TRAVEL, OTHER).
  - `description`: A `TextType` (or `TextareaType`) for the note.
- *Note:* We will not include `projectBudget` in the form. The Controller will securely inject the current Project Budget entity into the transaction behind the scenes before saving.

## 3. Repository Methods (`TransactionRepository.php`)
- Add the standard `save(Transaction $entity, bool $flush = false)` method.
- *(Note on DQL: Doctrine DQL does not natively support `INSERT` statements because it manages object state. We use `$em->persist()` wrapped inside our `save()` method for creations. We will build the complex DQL `UPDATE` and `DELETE` queries later when we tackle those specific buttons!)*

## 4. UI Implementation (`_create_transaction_modal.html.twig`)
- **Strict Adherence to Previous Fixes:** As you reminded, we will strictly follow the `MODAL_IMPLEMENTATION_GUIDE.md` to ensure zero regressions!
  - We will *not* use the `.needs-validation` class on the form, avoiding the `app.js` submit interception bug.
  - We will explicitly render the `.text-danger` loops manually under each field.
  - We will use `bootstrap.Modal.getOrCreateInstance` in the auto-open script to prevent the permanent blurry backdrop bug.
  - We will update `FA_custom.js` to securely clear all inputs on modal close.
- Update `_tab_transactions.html.twig` to link the `+` (Add) button in the upper bar to `#createTransactionModal` and include the modal at the bottom.

## 5. Controller Integration (`FinancialDashboardController.php`)
- **Strict Adherence to Date/Entity Bugs:**
  - Inside `budgetDetails()`, instantiate the `TransactionType` form.
  - On valid submission, automatically attach the transaction to the current `$projectBudget`.
  - Pass the newly created transaction to `$transactionRepository->save($transaction, true)`.
  - We will carefully format the mapped transaction properties (e.g., extracting the string value of `$tx->getDateStamp()->format('Y-m-d')` instead of passing the raw `DateTime` object) to prevent the fatal Twig rendering crashes we experienced earlier!
  - Redirect back to the budget details page with a success flash message.
  - Dynamically map the saved transactions from `$projectBudget->getTransactions()` to replace the mock rows in the Twig template!

# Verification
Clicking the "Add" (playlist-add) button on the Transactions tab will seamlessly open a formatted modal. Saving a valid transaction will persist it to the database and instantly render it dynamically in the transaction list row!