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

    /**
     * ==========================================
     * PROJECT BUDGETS VIEW TOGGLE & SEARCH
     * ==========================================
     */
    const btnGridView = document.getElementById('btn-grid-view');
    const btnListView = document.getElementById('btn-list-view');
    const gridViewContainer = document.getElementById('project-grid-view');
    const listViewContainer = document.getElementById('project-list-view');

    // 1. Grid/List View Toggling (Vanilla JS)
    if (btnGridView && btnListView && gridViewContainer && listViewContainer) {
        const activeStyle = 'background-color: rgba(91, 105, 188, 0.15); color: var(--ct-secondary, #5b69bc); border-color: transparent;';
        const inactiveStyle = 'background-color: transparent; color: var(--ct-secondary, #5b69bc); border-color: rgba(91, 105, 188, 0.15);';

        btnGridView.addEventListener('click', function() {
            btnGridView.setAttribute('style', activeStyle);
            btnListView.setAttribute('style', inactiveStyle);
            gridViewContainer.classList.remove('d-none');
            listViewContainer.classList.add('d-none');
        });

        btnListView.addEventListener('click', function() {
            btnListView.setAttribute('style', activeStyle);
            btnGridView.setAttribute('style', inactiveStyle);
            listViewContainer.classList.remove('d-none');
            gridViewContainer.classList.add('d-none');
        });
    }

    // 2. Pure Vanilla JS Live Search (No AJAX required for projects)
    const projectSearchInput = document.getElementById('project-search-input');
    const projectEmptyState = document.getElementById('project-search-empty-state');

    if (projectSearchInput && (gridViewContainer || listViewContainer)) {
        projectSearchInput.addEventListener('input', function(e) {
            const term = e.target.value.toLowerCase().trim();
            const allCards = document.querySelectorAll('.project-card');
            let visibleCount = 0;
            
            allCards.forEach(card => {
                // Determine if we are filtering the grid column wrapper or the list card itself
                const elementToHide = card.closest('.col') ? card.closest('.col') : card;
                
                const text = card.textContent.toLowerCase();
                if (text.includes(term)) {
                    elementToHide.classList.remove('d-none');
                    visibleCount++;
                } else {
                    elementToHide.classList.add('d-none');
                }
            });

            // Handle "No results found" dynamic state safely
            if (projectEmptyState) {
                if (visibleCount === 0 && allCards.length > 0) {
                    projectEmptyState.classList.remove('d-none');
                } else {
                    projectEmptyState.classList.add('d-none');
                }
            }
        });
    }

    /**
     * ==========================================
     * FISCAL BUDGET PROFILES LIVE SEARCH
     * ==========================================
     */
    const profileSearchInput = document.getElementById('profile-search-input');
    const profileGridContainer = document.getElementById('profile-grid-container');
    const profileEmptyState = document.getElementById('profile-search-empty-state');

    if (profileSearchInput && profileGridContainer) {
        profileSearchInput.addEventListener('input', function(e) {
            const term = e.target.value.toLowerCase().trim();
            const allCards = profileGridContainer.querySelectorAll('.profile-card');
            let visibleCount = 0;
            
            allCards.forEach(card => {
                const elementToHide = card.closest('.col') ? card.closest('.col') : card;
                
                // Only search within the title (h4) of the profile card
                const titleElement = card.querySelector('h4');
                const text = titleElement ? titleElement.textContent.toLowerCase() : '';
                
                if (text.includes(term)) {
                    elementToHide.classList.remove('d-none');
                    visibleCount++;
                } else {
                    elementToHide.classList.add('d-none');
                }
            });

            // Handle "No results found" dynamic state safely
            if (profileEmptyState) {
                if (visibleCount === 0 && allCards.length > 0) {
                    profileEmptyState.classList.remove('d-none');
                } else {
                    profileEmptyState.classList.add('d-none');
                }
            }
        });
    }

});
