# Objective
Implement the "Create Budget Profile" feature via a centered, background-blurring modal on the landing page. This involves updating the `BudgetProfileType` form, handling submission in `FinancialDashboardController`, rendering the form with Bootstrap validation styles, integrating Flatpickr for dates, Select2 for currencies, and applying the requested business logic for Fiscal Year derivation.

# Key Context & Constraints
- **Entity Validation:** Rely on the `BudgetProfile` entity's `#[Assert]` rules.
- **Form UI:** Use standard `needs-validation` styling from the "Custom Styles" section in `form-validation.html.twig`. This means specifically applying `.is-invalid` to `.form-control` and `.form-select` elements, and rendering errors inside `<div class="invalid-feedback">` blocks.
- **Modal UI:** Vertically centered (`modal-dialog-centered`) with a blurred backdrop (`backdrop-filter: blur(5px)` on `.modal-backdrop`).
- **Currency:** Use Select2. Top 20 currencies grouped by continent.
- **Date Logic:** Provide a toggle between "Standard" (e.g., Jan 1 - Dec 31 or Jul 1 - Jun 30) and "Custom" dates. `fiscal_year` is automatically derived from the selected dates.
- **Excluded Fields:** `status`, `margin_profit`, `total_expense` must NOT be in the form.

# Implementation Steps

## 1. Update `BudgetProfileType.php`
- Remove `total_expense`, `margin_profit`, and `status`.
- Configure `base_currency` as a `ChoiceType` with grouped options (Top 20 currencies by continent).
- Configure `start_date` and `end_date` as `DateType` with `widget => 'single_text'` and `html5 => false` so Flatpickr can attach to them.
- Configure `fiscal_year` as a `TextType` (read-only on the frontend, populated via JS).

## 2. Controller Logic (`FinancialDashboardController.php`)
- In the `index` method, create the form: `$form = $this->createForm(BudgetProfileType::class, new BudgetProfile());`.
- Handle `$request`. If submitted and valid:
  - Persist the new `BudgetProfile`.
  - Add a flash message (e.g., `this->addFlash('success', 'Profile created!');`).
  - Redirect to the same landing page.
- Pass `$form->createView()` to `landing.html.twig`.

## 3. Modal & Form UI (`landing.html.twig`)
- **Alerts:** Add a section at the top to display Symfony flash messages using the UI alerts design (e.g., `alert-success`).
- **Modal Structure:** Add the HTML for `#createProfileModal`. Add custom CSS block for `.modal-backdrop { backdrop-filter: blur(5px); background-color: rgba(0,0,0,0.4); }`.
- **Form Rendering:** Render the form fields manually to apply the `needs-validation` classes (`is-invalid`, `invalid-feedback`, etc.) based on `$form.vars.errors`.
- **Pickers & Selects:** Attach Flatpickr to the date fields and Select2 to the currency field.

## 4. Frontend Business Logic (JavaScript)
- Add a radio button toggle (not mapped to the entity) for "Standard Period" vs "Custom Period".
- **Standard:** Shows a dropdown of upcoming years. Selecting "2026" automatically fills `start_date` (2026-01-01), `end_date` (2026-12-31), and `fiscal_year` (2026).
- **Custom:** Shows the Flatpickr date inputs. When `end_date` is selected, extract the year and auto-fill the `fiscal_year` input.

# Verification
- Form submission triggers entity validation.
- Errors render correctly in the modal using Bootstrap validation styles.
- Success shows a dismissible alert and the new profile appears in the grid.