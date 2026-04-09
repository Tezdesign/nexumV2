document.addEventListener('DOMContentLoaded', function () {
    // 1. Initialize Components
    if ($('[data-toggle="select2"]').length) {
        $('[data-toggle="select2"]').each(function() {
            let parentModal = $(this).closest('.modal');
            if (parentModal.length) {
                $(this).select2({ dropdownParent: parentModal });
            } else {
                $(this).select2();
            }
        });
    }

    let startPicker = null;
    let endPicker = null;

    if ($('[data-provider="flatpickr"]').length) {
        const startInput = document.getElementById('budget_profile_start_date');
        const endInput = document.getElementById('budget_profile_end_date');

        if (startInput) {
            startPicker = flatpickr(startInput, { dateFormat: "Y-m-d" });
        }
        if (endInput) {
            endPicker = flatpickr(endInput, { dateFormat: "Y-m-d" });
        }
    }

    // 2. UI Toggle Logic: Standard vs Custom Period
    const radioStandard = document.getElementById('periodStandard');
    const radioCustom = document.getElementById('periodCustom');
    const fiscalYearSelect = document.getElementById('budget_profile_fiscal_year');
    const startDateInput = document.getElementById('budget_profile_start_date');
    const endDateInput = document.getElementById('budget_profile_end_date');

    function toggleDateMode() {
        if (!radioStandard || !radioCustom) return;

        if (radioStandard.checked) {
            // Standard: Enable Fiscal Year dropdown, Disable Flatpickr popups & make inputs readonly
            if (fiscalYearSelect) {
                $(fiscalYearSelect).prop('disabled', false).trigger('change.select2');
            }
            if (startDateInput) startDateInput.setAttribute('readonly', 'readonly');
            if (endDateInput) endDateInput.setAttribute('readonly', 'readonly');
            if (startPicker) startPicker.set('clickOpens', false);
            if (endPicker) endPicker.set('clickOpens', false);

            // Re-trigger standard calculation
            if (fiscalYearSelect) {
                $(fiscalYearSelect).trigger('change');
            }
        } else {
            // Custom: Keep Fiscal Year dropdown ENABLED so it submits, but don't auto-fill dates from it!
            if (fiscalYearSelect) {
                $(fiscalYearSelect).prop('disabled', false).trigger('change.select2');
            }
            if (startDateInput) startDateInput.removeAttribute('readonly');
            if (endDateInput) endDateInput.removeAttribute('readonly');
            if (startPicker) startPicker.set('clickOpens', true);
            if (endPicker) endPicker.set('clickOpens', true);
        }
    }

    if(radioStandard && radioCustom) {
        radioStandard.addEventListener('change', toggleDateMode);
        radioCustom.addEventListener('change', toggleDateMode);

        // If editing an existing profile with non-standard dates, we might want to check Custom mode automatically.
        // For now, run on load.
        toggleDateMode(); 
    }

    // 3. Auto-Calculate Standard Dates based on Fiscal Year Dropdown
    if (fiscalYearSelect) {
        $(fiscalYearSelect).on('change', function() {
            // ONLY auto-fill if Standard is checked! If Custom is checked, let them do what they want.
            if (radioStandard && !radioStandard.checked) return;

            const year = $(this).val();
            if (year) {
                if (startPicker) startPicker.setDate(year + '-01-01', true);
                else if (startDateInput) startDateInput.value = year + '-01-01';

                if (endPicker) endPicker.setDate(year + '-12-31', true);
                else if (endDateInput) endDateInput.value = year + '-12-31';
            } else {
                if (startPicker) startPicker.clear();
                else if (startDateInput) startDateInput.value = '';

                if (endPicker) endPicker.clear();
                else if (endDateInput) endDateInput.value = '';
            }
        });
    }

    // 5. Clean Modal on Close
    const createModalElement = document.getElementById('createProfileModal');
    const updateModalElement = document.getElementById('updateProfileModal');
    const createProjectBudgetModal = document.getElementById('createProjectBudgetModal');

    function attachModalCloseListener(modalElement, formId) {
        if (modalElement) {
            modalElement.addEventListener('hidden.bs.modal', function () {
                const form = document.getElementById(formId);
                if (form) {
                    // Do a normal visual reset for a clean form
                    if (formId === 'createProfileForm' || formId === 'createProjectBudgetForm') {
                        $(form).find('input[type="text"], input[type="number"], input[type="date"]').val('');
                        $(form).find('select').val('').trigger('change.select2');

                        const flatpickrs = document.querySelectorAll('[data-provider="flatpickr"]');
                        flatpickrs.forEach(fp => {
                            if (fp._flatpickr) {
                                fp._flatpickr.clear();
                            }
                        });
                    }

                    $(form).find('.is-invalid').removeClass('is-invalid');
                    $(form).find('.text-danger.mt-1').remove();
                }
            });
        }
    }

    attachModalCloseListener(createModalElement, 'createProfileForm');
    attachModalCloseListener(updateModalElement, 'updateProfileForm');
    attachModalCloseListener(createProjectBudgetModal, 'createProjectBudgetForm');
});
