# Objective
Create an "Update Budget Profile" feature on the FY Dashboard page (`overview.html.twig`) with identical UI elements and Assert logic as the creation form, while adding a placeholder structure for the future "110 rule" validation logic.

# Context & Logic
- The user requested an "Edit" button specifically on the FY Dashboard (not the landing page).
- The update form should behave identically to the create form.
- The user noted a future business rule (the "110 rule") that will restrict budget updates if the new budget is less than the allocated project budgets or current expenses + 10%. We must acknowledge this logic with a placeholder in the Entity so it is ready for implementation when the transactions section is built.

# Implementation Steps

## 1. Update Controller (`FinancialDashboardController.php`)
- Modify the `overview` route (which serves the FY Dashboard).
- Inject `Request` and `EntityManagerInterface`.
- Create the `BudgetProfileType` form using the existing `$budgetProfile` entity.
- Handle the request: if submitted and valid, flush the entity manager, add a success flash message, and redirect back to the `overview` page.
- Pass the form view to the `overview.html.twig` template.

## 2. Update Template (`overview.html.twig`)
- Add an "Edit Profile" button next to the FY Dashboard breadcrumb/title that triggers a new `#updateProfileModal`.
- Include `_update_profile_modal.html.twig` at the bottom of the file.

## 3. Create Modal (`_update_profile_modal.html.twig`)
- Duplicate the `_create_profile_modal.html.twig` file.
- Change the modal ID to `updateProfileModal`.
- Change the form ID to `updateProfileForm`.
- Update titles and button labels to reflect "Update Budget Profile" and "Save Changes".

## 4. Update JavaScript (`FA_custom.js`)
- Modify `FA_custom.js` to initialize Select2 on both `#createProfileModal` and `#updateProfileModal`.
- Adjust the "Clean Modal on Close" logic to attach to both modals and correctly flush the respective form (`createProfileForm` or `updateProfileForm`).

## 5. Add Placeholder Validation (`BudgetProfile.php`)
- Add a new `#[Assert\Callback]` method named `validateUpdateLogic` in the `BudgetProfile` entity.
- Leave a detailed comment inside this method describing the "110 rule" for expenses and allocated projects, ensuring the system is architecturally prepared for the future implementation.

# Verification
- Navigating to an existing Budget Profile dashboard will display an "Edit Profile" button.
- Clicking the button opens the update modal, pre-filled with the entity's current data.
- The UI toggle (Standard vs Custom) works perfectly.
- Submitting valid data successfully updates the database.
- Submitting invalid data throws the exact same Twig Assert errors as the creation form.