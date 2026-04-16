document.addEventListener('DOMContentLoaded', () => {
    const draftModal = document.getElementById('createDraftModal');
    let quillInitialized = false;
    let createQuill = null;

    if (draftModal) {
        draftModal.addEventListener('shown.bs.modal', function () {
            const editorEl = document.getElementById('draft-description-editor');
            // FIX: Scope the selector to the modal so it doesn't bleed into update modals
            const hiddenInput = draftModal.querySelector('.draft-description-hidden');
            
            if (editorEl && typeof Quill !== 'undefined' && !quillInitialized) {
                createQuill = new Quill('#draft-description-editor', {
                    theme: 'bubble',
                    placeholder: 'Detailed description...'
                });
                
                // Sync Quill HTML content to the hidden input
                createQuill.on('text-change', function() {
                    if (hiddenInput) {
                        // If editor is effectively empty, clear the input
                        if (createQuill.getText().trim().length === 0) {
                            hiddenInput.value = '';
                        } else {
                            hiddenInput.value = createQuill.root.innerHTML;
                        }
                    }
                });
                
                // If hidden input already has content (e.g., validation failed), load it
                if (hiddenInput && hiddenInput.value) {
                    createQuill.root.innerHTML = hiddenInput.value;
                }

                quillInitialized = true;
            }
        });

        // FIX: Flush the form and Quill when the create modal is canceled/closed
        draftModal.addEventListener('hidden.bs.modal', function () {
            const form = document.getElementById('createDraftForm');
            if (form) {
                form.reset();
                // Clear validation errors
                if (typeof jQuery !== 'undefined') {
                    $(form).find('.is-invalid').removeClass('is-invalid');
                    $(form).find('.text-danger').remove();
                    $(form).find('.invalid-feedback').remove();
                    // Reset select2
                    $(form).find('.select2').val('').trigger('change.select2');
                } else {
                    form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
                    form.querySelectorAll('.text-danger, .invalid-feedback').forEach(el => el.remove());
                }
            }
            if (createQuill) {
                createQuill.root.innerHTML = '';
            }
            const hiddenInput = draftModal.querySelector('.draft-description-hidden');
            if (hiddenInput) {
                hiddenInput.value = '';
            }
        });
    }

    const searchInput = document.getElementById('draftSearchInput');
    const draftsContainer = document.getElementById('draftsListContainer');

    if (!searchInput || !draftsContainer) {
        return;
    }

    const draftItems = draftsContainer.querySelectorAll('.nexum-draft-item');

    // Add collapse toggle listeners for animation state
    document.addEventListener('show.bs.collapse', function (e) {
        if (e.target.classList.contains('draft-collapse-container')) {
            const item = e.target.closest('.nexum-draft-item');
            if (item) item.classList.add('is-expanded');
        }
    });

    document.addEventListener('hide.bs.collapse', function (e) {
        if (e.target.classList.contains('draft-collapse-container')) {
            const item = e.target.closest('.nexum-draft-item');
            if (item) item.classList.remove('is-expanded');
        }
    });

    searchInput.addEventListener('input', (e) => {
        const term = e.target.value.trim().toLowerCase();

        draftItems.forEach(item => {
            const content = item.getAttribute('data-search-content') || '';
            
            if (term === '' || content.includes(term)) {
                item.style.display = '';
            } else {
                item.style.display = 'none';
            }
        });
    });
});

/* =========================================================================
   UPDATE DRAFT MODALS LOGIC (Dynamic injection support for Turbo Frames)
   ========================================================================= */

// Use event delegation to handle dynamically loaded update modals
document.addEventListener('shown.bs.modal', function (e) {
    const modalEl = e.target;

    // Only process update draft modals
    if (!modalEl.classList.contains('nexum-update-draft-modal')) return;

    const draftId = modalEl.getAttribute('data-draft-id');
    if (!draftId) return;

    const editorId = 'draft-description-editor-' + draftId;
    const hiddenInputId = 'draft_desc_hidden_' + draftId;
    
    const editorEl = document.getElementById(editorId);
    const hiddenInput = document.getElementById(hiddenInputId);

    // Initialize Quill specifically for this update modal if not already done
    if (editorEl && typeof Quill !== 'undefined' && !modalEl._quillInstance) {
        const quill = new Quill('#' + editorId, {
            theme: 'bubble',
            placeholder: 'Detailed description...'
        });
        
        modalEl._quillInstance = quill;

        // Sync Quill HTML content to the hidden input
        quill.on('text-change', function() {
            if (hiddenInput) {
                if (quill.getText().trim().length === 0) {
                    hiddenInput.value = '';
                } else {
                    hiddenInput.value = quill.root.innerHTML;
                }
            }
        });
        
        // Load original content into Quill
        if (hiddenInput && hiddenInput.value) {
            quill.root.innerHTML = hiddenInput.value;
        }
    }
});

document.addEventListener('hidden.bs.modal', function (e) {
    const modalEl = e.target;
    
    if (!modalEl.classList.contains('nexum-update-draft-modal')) return;
    
    const draftId = modalEl.getAttribute('data-draft-id');
    const form = document.getElementById('updateDraftForm_' + draftId);
    
    if (form) {
        form.reset();

        // Restore original data from data attributes (populated via Twig)
        const subjectInput = document.getElementById('draft_subject_' + draftId);
        if (subjectInput) subjectInput.value = modalEl.getAttribute('data-original-subject') || '';
        
        const catSelect = document.getElementById('draft_cat_' + draftId);
        if (catSelect) {
            const originalCat = modalEl.getAttribute('data-original-category') || '';
            if (typeof jQuery !== 'undefined') {
                $(catSelect).val(originalCat).trigger('change.select2');
            } else {
                catSelect.value = originalCat;
            }
        }

        const amountInput = document.getElementById('draft_amount_' + draftId);
        if (amountInput) amountInput.value = modalEl.getAttribute('data-original-amount') || '';

        const budgetSelect = document.getElementById('draft_budget_' + draftId);
        if (budgetSelect) {
            const originalBudget = modalEl.getAttribute('data-original-budget') || '';
            if (typeof jQuery !== 'undefined') {
                $(budgetSelect).val(originalBudget).trigger('change.select2');
            } else {
                budgetSelect.value = originalBudget;
            }
        }

        const descHidden = document.getElementById('draft_desc_hidden_' + draftId);
        const originalDesc = modalEl.getAttribute('data-original-description') || '';
        
        if (descHidden) descHidden.value = originalDesc;
        if (modalEl._quillInstance) {
            modalEl._quillInstance.root.innerHTML = originalDesc;
        }

        // Clear validation UI
        if (typeof jQuery !== 'undefined') {
            $(form).find('.is-invalid').removeClass('is-invalid');
            $(form).find('.text-danger').remove();
            $(form).find('.invalid-feedback').remove();
        } else {
            form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
            form.querySelectorAll('.text-danger, .invalid-feedback').forEach(el => el.remove());
        }
    }
});

// Auto-open modal if there are errors passed from Symfony flash bag
document.addEventListener('turbo:load', checkAndOpenErrorModals);
document.addEventListener('DOMContentLoaded', checkAndOpenErrorModals);

function checkAndOpenErrorModals() {
    const errorModals = document.querySelectorAll('.nexum-update-draft-modal[data-has-errors="true"]');
    errorModals.forEach(modalEl => {
        // Move to body if needed to avoid z-index issues
        if (modalEl.parentNode !== document.body) {
            document.body.appendChild(modalEl);
        }
        
        // Use timeout to ensure DOM is fully ready for Bootstrap
        setTimeout(() => {
            if (typeof bootstrap !== 'undefined') {
                const bootstrapModal = bootstrap.Modal.getOrCreateInstance(modalEl);
                bootstrapModal.show();
            }
        }, 100);
    });
}