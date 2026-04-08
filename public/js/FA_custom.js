document.addEventListener('DOMContentLoaded', function () {
    // 1. Initialize Components
    if ($('[data-toggle="select2"]').length) {
        $('[data-toggle="select2"]').select2({
            dropdownParent: $('#createProfileModal') // Fix for Select2 inside Bootstrap Modals
        });
    }

    if ($('[data-provider="flatpickr"]').length) {
        $('[data-provider="flatpickr"]').flatpickr();
    }

    // 2. Form Logic: Standard vs Custom Period
    const radioStandard = document.getElementById('periodStandard');
    const radioCustom = document.getElementById('periodCustom');
    const standardWrapper = document.getElementById('standardYearWrapper');
    const customWrapper = document.getElementById('customDatesWrapper');
    
    const standardYearSelect = document.getElementById('standardYearSelect');
    const hiddenFiscalYearInput = document.getElementById('budget_profile_fiscal_year');
    const startDateInput = document.getElementById('budget_profile_start_date');
    const endDateInput = document.getElementById('budget_profile_end_date');

    function toggleDateMode() {
        if (radioStandard.checked) {
            standardWrapper.style.display = 'flex';
            customWrapper.style.display = 'none';
            // Re-trigger standard calculation
            if (standardYearSelect) {
                standardYearSelect.dispatchEvent(new Event('change'));
            }
        } else {
            standardWrapper.style.display = 'none';
            customWrapper.style.display = 'flex';
            // Clear fields to let user select
            if (startDateInput) startDateInput.value = '';
            if (endDateInput) endDateInput.value = '';
            if (hiddenFiscalYearInput) hiddenFiscalYearInput.value = '';
        }
    }

    if(radioStandard && radioCustom) {
        radioStandard.addEventListener('change', toggleDateMode);
        radioCustom.addEventListener('change', toggleDateMode);
    }

    // 3. Auto-Calculate Standard Dates
    if(standardYearSelect) {
        standardYearSelect.addEventListener('change', function() {
            if (!radioStandard.checked) return;
            
            const year = this.value;
            if (year) {
                if (hiddenFiscalYearInput) hiddenFiscalYearInput.value = year;
                if (startDateInput) startDateInput.value = year + '-01-01';
                if (endDateInput) endDateInput.value = year + '-12-31';
            } else {
                if (hiddenFiscalYearInput) hiddenFiscalYearInput.value = '';
                if (startDateInput) startDateInput.value = '';
                if (endDateInput) endDateInput.value = '';
            }
        });
    }


    if(endDateInput) {
        endDateInput.addEventListener('change', function() {
            if (!radioCustom.checked) return;
            
            const dateVal = this.value;
            if (dateVal) {
                const year = dateVal.split('-')[0];
                if (hiddenFiscalYearInput) hiddenFiscalYearInput.value = year;
            }
        });
    }


    const form = document.querySelector('.needs-validation');
    if(form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    }
});