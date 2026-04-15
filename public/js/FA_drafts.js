document.addEventListener('DOMContentLoaded', () => {
    const draftModal = document.getElementById('createDraftModal');
    let quillInitialized = false;

    if (draftModal) {
        draftModal.addEventListener('shown.bs.modal', function () {
            const editorEl = document.getElementById('draft-description-editor');
            const hiddenInput = document.querySelector('.draft-description-hidden');
            
            if (editorEl && typeof Quill !== 'undefined' && !quillInitialized) {
                const quill = new Quill('#draft-description-editor', {
                    theme: 'bubble',
                    placeholder: 'Detailed description...'
                });
                
                // Sync Quill HTML content to the hidden input
                quill.on('text-change', function() {
                    if (hiddenInput) {
                        // If editor is effectively empty, clear the input
                        if (quill.getText().trim().length === 0) {
                            hiddenInput.value = '';
                        } else {
                            hiddenInput.value = quill.root.innerHTML;
                        }
                    }
                });
                
                // If hidden input already has content (e.g., validation failed), load it
                if (hiddenInput && hiddenInput.value) {
                    quill.root.innerHTML = hiddenInput.value;
                }

                quillInitialized = true;
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
