# Objective
Refactor the `ProjectBudgetType` Symfony form to strictly respect the `ProjectBudget` entity constraints and UI paradigms (Flatpickr, Select2), and implement standard CRUD methods in the `ProjectBudgetRepository` as requested by the user. 
*(Note: As reminded by the user, the "Add" button already exists in the UI next to the search bar and grid/list view toggles, so we are strictly focusing on the Form Type and Repository architecture in this step—no new buttons are being created!)*

# Architecture & Changes

## 1. Update `ProjectBudgetType.php`
The console-generated form lacks the UI configuration needed to match the application's design system and ignores some of the data rules. We will update it to:
- **`name`**: Set as a standard `TextType` with `.form-control`.
- **`project`**: Update the `EntityType` to use `choice_label => 'name'` (instead of 'id') and apply the `.form-select .select2` classes. Add a placeholder so the `NotBlank` assert can correctly trigger if unselected.
- **`total_budget`**: Set as a `NumberType` with `.form-control`.
- **`actualSpend`**: Completely remove this from the form builder. As the user noted, this field will be populated via future updates/transactions and should not be settable during creation.
- **`status`**: Change to a `ChoiceType` containing standard statuses (`ON TRACK`, `AT RISK`, `OVER BUDGET`, `COMPLETED`), apply the `.form-select .select2` classes, and set it to `disabled => true` so it mimics the "Draft" status behavior of the Budget Profile.
- **`dueDate`**: Set as a `DateType` configured precisely for Flatpickr (`'widget' => 'single_text'`, `data-provider => flatpickr`).

## 2. Update `ProjectBudgetRepository.php`
The user explicitly requested that all CRUD database operations move into the Repository layer instead of calling `$entityManager->persist()` directly in the Controller.
- We will add standard `save(ProjectBudget $entity, bool $flush = false)` and `remove(ProjectBudget $entity, bool $flush = false)` methods to the repository to encapsulate the `EntityManager` logic.

## 3. Create the Modal Twig File (`_create_project_budget_modal.html.twig`)
As per the user's request, we will build the UI for this new form inside the `FA_components` folder so it matches the architecture of the previous modals.
- Create `templates/financial-analysis/FA_components/_create_project_budget_modal.html.twig`.
- Implement the exact same robust `form_start` block (no `.needs-validation`), the standard `text-danger` inline error blocks, and the inline Twig script for flushing/reloading the modal on validation failure that we documented in the `MODAL_IMPLEMENTATION_GUIDE.md`.

# Implementation Steps
1. Replace the entire content of `src/Form/FinancialAnalysis/ProjectBudgetType.php` with the properly configured form builder logic.
2. Inject the `save` and `remove` methods into `src/Repository/FinancialAnalysis/ProjectBudgetRepository.php`.
3. Create the new modal template file in `templates/financial-analysis/FA_components/_create_project_budget_modal.html.twig`.

# Verification
- The `ProjectBudgetType` will correctly render Flatpickr and Select2 inputs when embedded in a Twig template.
- `actualSpend` is safely hidden from user manipulation during creation.
- Future controllers can seamlessly call `$projectBudgetRepository->save($projectBudget, true)` to persist data.