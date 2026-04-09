/**
 * Financial Analysis - Specific Functions
 * Consolidates all specific JS logic (like search, multi-delete)
 * away from the Twig files to keep them clean.
 */

document.addEventListener('DOMContentLoaded', function() {

    /**
     * ==========================================
     * TRANSACTION TAB LOGIC
     * ==========================================
     */
    const toggleBtn = document.getElementById('multiDeleteToggleBtn');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    const listContainer = document.getElementById('transaction-list-container');
    const multiDeleteContainer = document.getElementById('multiDeleteContainer');
    const deleteCountBadge = document.getElementById('deleteCountBadge');
    
    // Confirmation Modal Elements
    const deleteModalEl = document.getElementById('deleteTransactionsModal');
    const modalDeleteCount = document.getElementById('modalDeleteCount');
    const bulkDeleteInput = document.getElementById('bulkDeleteInput');

    // 1. Multi-Delete Toggle Logic
    if (toggleBtn && listContainer && multiDeleteContainer) {
        toggleBtn.addEventListener('click', function() {
            listContainer.classList.toggle('show-checkboxes');
            multiDeleteContainer.classList.toggle('show-checkboxes-mode');
            
            if (listContainer.classList.contains('show-checkboxes')) {
                toggleBtn.classList.replace('btn-outline-danger', 'btn-danger');
            } else {
                toggleBtn.classList.replace('btn-danger', 'btn-outline-danger');
                const checkboxes = listContainer.querySelectorAll('.transaction-checkbox');
                checkboxes.forEach(cb => cb.checked = false);
                if (deleteCountBadge) deleteCountBadge.textContent = '0';
            }
        });

        // Action Bar Confirm Click
        if (confirmDeleteBtn) {
            confirmDeleteBtn.addEventListener('click', function() {
                const checkedBoxes = listContainer.querySelectorAll('.transaction-checkbox:checked');
                if (checkedBoxes.length === 0) {
                    alert('Please select at least one transaction to delete.');
                    return;
                }

                const ids = Array.from(checkedBoxes).map(cb => cb.value);
                
                // Populate and Show Confirmation Modal
                if (modalDeleteCount) modalDeleteCount.textContent = ids.length;
                if (bulkDeleteInput) bulkDeleteInput.value = ids.join(',');
                
                if (deleteModalEl) {
                    const myModal = bootstrap.Modal.getOrCreateInstance(deleteModalEl);
                    myModal.show();
                }
            });
        }

        // Listen to checkbox changes to update count badge
        listContainer.addEventListener('change', function(e) {
            if (e.target.classList.contains('transaction-checkbox')) {
                const checkedCount = listContainer.querySelectorAll('.transaction-checkbox:checked').length;
                if (deleteCountBadge) {
                    deleteCountBadge.textContent = checkedCount;
                }
            }
        });
    }

    // 2. Pure AJAX Live Search for Transactions
    const searchInput = document.getElementById('transaction-search-input');
    let searchTimeout = null;

    if (searchInput && listContainer) {
        searchInput.addEventListener('input', function(e) {
            clearTimeout(searchTimeout);
            const term = e.target.value.trim();

            searchTimeout = setTimeout(() => {
                const url = new URL(window.location.href);
                url.searchParams.set('q', term);

                fetch(url.toString(), {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.text())
                .then(html => {
                    // Handle SimpleBar dynamically wrapping content
                    const simplebarContent = listContainer.querySelector('.simplebar-content');
                    if (simplebarContent) {
                        simplebarContent.innerHTML = html;
                    } else {
                        listContainer.innerHTML = html;
                    }
                    
                    // Re-bind delete count badge after DOM refresh
                    if (deleteCountBadge) {
                        const checkedCount = listContainer.querySelectorAll('.transaction-checkbox:checked').length;
                        deleteCountBadge.textContent = checkedCount;
                    }
                })
                .catch(error => {
                    console.error('Error fetching transactions:', error);
                });
            }, 300);
        });
    }

});
