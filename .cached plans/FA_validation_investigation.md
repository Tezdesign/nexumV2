# Objective
Investigate why Assert error messages are not appearing via Twig, verify backend validation is executing correctly, and completely strip Javascript logic related to date parsing and form submission. Instead, rely on a Twig-based approach using form elements (specifically the custom switch and select logic).

# Investigation
The user indicated that despite removing the `form.checkValidity()` interceptor in JS, the `#[Assert]` error messages are *still* not appearing on the screen. 
**Root Cause Analysis:**
1. The form in `landing.html.twig` uses `novalidate: 'novalidate'`. When JS was stripped, if the Symfony form submits with errors, the Controller re-renders `landing.html.twig`.
2. However, the modal is hidden by default in Bootstrap! If the form submits and the page reloads with errors, the user is just looking at the landing page. The modal with the red errors is actually in the HTML, but it's physically hidden because the page reloaded. 
3. *Proof:* The previous JS had a snippet `{% if form.vars.submitted and not form.vars.valid %} myModal.show(); {% endif %}`. Because we completely stripped the JS, that auto-open logic was also deleted. The errors *are* there, the user just can't see them because the modal is closed.

# Solution & Implementation Steps

## 1. Pure Twig Error Handling (Re-opening the Modal)
We must restore the tiny piece of Javascript that re-opens the modal if the form has backend validation errors. Without this, the user will submit the form, the page will reload, and it will look like nothing happened.
- Inject a tiny `<script>` block at the bottom of the `_create_profile_modal.html.twig` file that strictly checks `{% if form.vars.submitted and not form.vars.valid %}` and calls `new bootstrap.Modal(document.getElementById('createProfileModal')).show();`.

## 2. Refactoring to a Pure Twig/HTML UI
The user requested we abandon the complex Javascript date auto-filling and instead rely on standard Twig/HTML rendering, keeping the radio buttons as the interaction point.
- **The "Period Type" Toggle:** We will keep the radio buttons (`<input type="radio">`) exactly as they are currently styled in the modal.
- **The Logic (UI Toggling via JS):**
  - We MUST use a small amount of Javascript strictly for **UI toggling** (hiding/showing/disabling fields) when the user clicks the radio buttons, because Twig (which renders on the server) cannot respond to real-time browser clicks.
  - If "Standard" is checked: The `fiscal_year` Select2 dropdown is enabled. The Flatpickr date inputs are disabled or hidden so the user cannot interact with them.
  - If "Custom" is checked: The `fiscal_year` dropdown is disabled or hidden. The Flatpickr date inputs become visible/enabled so the user can manually pick dates.
  - **Crucially:** We will completely strip out the JS that tried to "type" dates into the inputs or parse the year from a string. If the user selects "Standard" and picks "2026", the Controller will simply receive `fiscal_year = 2026`. We will handle all the Date logic (Jan 1 to Dec 31) securely in the backend Controller before persisting, completely removing the burden from the frontend JS.

## 3. "Choose..." Placeholder Validation Fix
The user asked why "Choose..." marks the field as valid.
- **Cause:** When a `ChoiceType` has a `'placeholder'`, Symfony sends an empty string (`""`) when that option is selected. If the `base_currency` property is nullable (`?string`), and the `#[Assert\NotBlank]` is present, it *should* fail. However, if the field is somehow not strictly mapped or the empty value isn't catching, we can explicitly add `'empty_data' => ''` and ensure the Assert is checking correctly.

# Verification
1. Submitting an empty form will reload the page, instantly pop the modal open, and display red `invalid-feedback` text under the Currency and Budget fields.
2. The UI toggle between Standard and Custom will be driven by a clean, modern Custom Switch.
3. The complex, buggy auto-filling JS is permanently purged.