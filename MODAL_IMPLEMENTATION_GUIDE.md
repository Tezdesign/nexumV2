# Nexum Web - Modal Implementation Guide
*This guide documents the strict architectural standards and bug fixes discovered while building the Financial Analysis module's CRUD modals (Budget Profiles, Project Budgets, Transactions).*

## 1. Form Submission & Validation Architecture
- **No JS Interception:** Never use `form.checkValidity()`, `event.preventDefault()`, or the Bootstrap `.needs-validation` class on the `form_start()` Twig function. These trigger client-side validation blocks that prevent the form from reaching the server, hiding backend errors.
- **Pure Server-Side Validation:** Allow the form to natively `POST` to the Symfony Controller. Let Doctrine's `#[Assert]` rules evaluate the data and return the Twig template with errors if validation fails.

## 2. Rendering Errors in Twig
- Do not use Bootstrap's default `.invalid-feedback` class, as it often gets hidden or warped by flexbox containers (especially inside `.input-group` wrappers for currencies).
- **Standard Error Block:** Manually render errors directly underneath the input using this exact HTML structure:
  ```twig
  {% if not form.fieldName.vars.valid %}
      <div class="text-danger mt-1" style="font-size: 0.875em; display: block;">
          {% for error in form.fieldName.vars.errors %}
              <span class="d-block">{{ error.message }}</span>
          {% endfor %}
      </div>
  {% endif %}
  ```

## 3. Auto-Reopening Modals on Error
When a form fails validation, the page reloads. To ensure the user sees the errors, the modal must immediately pop back open.
- Add this inline script to the bottom of the modal's Twig file. 
- **CRITICAL:** Use `getOrCreateInstance` instead of `new bootstrap.Modal()` to prevent a bug where closing the modal leaves a permanent blurry `.modal-backdrop` div stuck on the screen.
  ```twig
  <script>
      document.addEventListener('DOMContentLoaded', function () {
          const modalEl = document.getElementById('myModalId');
          {% if form.vars.submitted and not form.vars.valid %}
              const myModal = bootstrap.Modal.getOrCreateInstance(modalEl);
              myModal.show();
          {% endif %}
      });
  </script>
  ```

## 4. Resetting "Create" Modals on Close
When a user closes a "Create/Add" modal, it should be wiped completely blank.
- Handle this in the global Javascript file (e.g., `FA_custom.js`).
- Attach to the `hidden.bs.modal` event.
- Forcefully clear all text/number/date inputs (`.val('')`), reset Select2 visually, call `.clear()` on Flatpickr instances, and `.remove()` the `.text-danger` divs. Do not use `form.reset()` as it may restore tainted data.

## 5. Restoring "Update" Modals on Close (The Twig Recall Method)
When an "Update" modal fails validation and the page reloads, the HTML is tainted with the user's bad `POST` data. If they close and reopen the modal, the bad data remains. You cannot use JS to reload the page to clear it, as that triggers a "Confirm Form Resubmission" browser warning.
- **The Solution:** Use an inline script inside the modal's Twig file to directly inject the pristine, cached PHP entity data back into the Javascript variables.
- **Decimal Formatting:** If restoring a `DECIMAL` database field into a numeric input, use Twig's `number_format(0, "", "")` to strip the `.00` decimals to prevent Euclidean division validator bugs.
  ```twig
  <script>
      document.addEventListener('DOMContentLoaded', function () {
          const updateModalEl = document.getElementById('updateModalId');
          if (updateModalEl) {
              updateModalEl.addEventListener('hidden.bs.modal', function () {
                  const form = document.getElementById('updateFormId');
                  if (form) {
                      form.reset(); // Clear active typing
                      
                      // Hardcode pristine DB data back into inputs via Twig
                      const amountInput = document.getElementById('amount_field_id');
                      if (amountInput) amountInput.value = '{{ entity.amount|number_format(0, "", "") }}';
                      
                      // Remove errors
                      $(form).find('.is-invalid').removeClass('is-invalid');
                      $(form).find('.text-danger.mt-1').remove();
                  }
              });
          }
      });
  </script>
  ```