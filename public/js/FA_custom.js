document.addEventListener('DOMContentLoaded', function () {
    // 1. Initialize Components
    if ($('[data-toggle="select2"]').length) {
        $('[data-toggle="select2"]').select2({
            dropdownParent: $('#createProfileModal') // Fix for Select2 inside Bootstrap Modals
        });
    }

    if ($('[data-provider="flatpickr"]').length) {
        $('[data-provider="flatpickr"]').flatpickr({ dateFormat: "Y-m-d" });
    }

    // 5. Clean Modal on Close
    const modalElement = document.getElementById('createProfileModal');
    if (modalElement) {
        modalElement.addEventListener('hidden.bs.modal', function () {
            // Reset form fields
            const form = document.getElementById('createProfileForm');
            if (form) {
                // Manually clear all text and number inputs (form.reset() just restores the bad data after a failed submit)
                $(form).find('input[type="text"], input[type="number"], input[type="date"]').val('');
                
                // Reset select2 dropdowns visually and functionally
                $(form).find('select').val('').trigger('change.select2');
                
                // Clear flatpickr inputs securely
                const flatpickrs = document.querySelectorAll('[data-provider="flatpickr"]');
                flatpickrs.forEach(fp => {
                    if (fp._flatpickr) {
                        fp._flatpickr.clear();
                    }
                });
                
                // Remove any server-side validation messages and styling
                $(form).find('.is-invalid').removeClass('is-invalid');
                $(form).find('.invalid-feedback').remove();
                $(form).find('.text-danger.mt-1').remove();
                
                // Hide the global alert if it exists
                const alertBox = form.querySelector('.alert-danger');
                if (alertBox) alertBox.style.display = 'none';
            }
        });
    }
});
