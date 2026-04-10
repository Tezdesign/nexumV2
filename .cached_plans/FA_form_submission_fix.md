# Objective
Resolve the silent form submission failure by removing the global JavaScript interceptor that is blocking the modal form from reaching the Symfony backend.

# Investigation & Root Cause
The user reported that clicking the submit button does absolutely nothing (the page does not reload, and no errors appear). 
- I investigated `public/js/app.js` (the core theme JavaScript file).
- The theme has a global function called `initFormValidation()` that runs on `DOMContentLoaded`.
- This function attaches a `submit` event listener to every form on the page that has the class `.needs-validation`.
- If the browser's native `checkValidity()` fails (which it will, because Symfony automatically adds `required="required"` to the hidden `<input>` elements underneath Select2 and Flatpickr), the script calls `event.preventDefault()` and `event.stopPropagation()`.
- Because the invalid inputs are visually hidden by the plugins, the browser cannot scroll to them to display the native "Please fill out this field" tooltip.
- As a result, the form is silently blocked on the client side. It never reaches the Symfony Controller, so Doctrine `#[Assert]` rules never execute, and Twig never renders the server-side errors.

# Implementation Steps

## 1. Bypass the Global Interceptor (`_create_profile_modal.html.twig`)
- We must remove the `.needs-validation` class from the `form_start()` Twig function. 
- **Change from:** `{{ form_start(form, {'attr': {'class': 'needs-validation', 'novalidate': 'novalidate'}}) }}`
- **Change to:** `{{ form_start(form, {'attr': {'id': 'createProfileForm', 'novalidate': 'novalidate'}}) }}`
- By removing this specific class, `app.js` will ignore our modal form. When the user clicks "Submit", the browser will obey the `novalidate` attribute and immediately `POST` the data to the Symfony Controller. 
- *Note: We do not lose our custom styling. Our Twig logic (`is-invalid`) and custom `<div class="text-danger mt-1">` blocks do not rely on the parent `.needs-validation` class to render correctly.*

## 2. Update Flush Logic (`FA_custom.js`)
- Since the form no longer has the `.needs-validation` class, we must update the "Clean Modal on Close" script to target the new ID.
- **Change:** `const form = document.querySelector('.needs-validation');` -> `const form = document.getElementById('createProfileForm');`

# Verification
When the user submits an empty form, the page will instantly reload, the modal will pop back open, and the red Doctrine `#[Assert]` messages will be visible underneath the Select2 and Flatpickr fields!