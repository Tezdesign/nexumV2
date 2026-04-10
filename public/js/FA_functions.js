/**
 * Financial Analysis - Specific Functions
 * Consolidates all specific JS logic (like search, multi-delete)
 * away from the Twig files to keep them clean.
 */

document.addEventListener('DOMContentLoaded', function() {

    /**
     * ==========================================
     * MODAL BACKDROP FIX & SELECT2 BUG FIX
     * ==========================================
     * Moves modals to the body to prevent them from being trapped 
     * behind backdrops when inside containers with overflow: hidden.
     * Also repairs Select2 instances that break when their parent DOM node is moved.
     */
    document.addEventListener('show.bs.modal', function (event) {
        const modal = event.target;
        if (modal && modal.classList.contains('modal') && modal.parentNode !== document.body) {
            
            // Move the modal to the body to fix the z-index/backdrop bug
            document.body.appendChild(modal);
            
            // Fix Select2 instances that break when their parent node is moved in the DOM
            // We must destroy and re-initialize them so their event listeners reattach correctly
            if (typeof jQuery !== 'undefined') {
                const $modal = $(modal);
                const $selects = $modal.find('[data-toggle="select2"]');
                
                if ($selects.length > 0) {
                    $selects.each(function() {
                        const $this = $(this);
                        // If it was already initialized, destroy it first
                        if ($this.hasClass('select2-hidden-accessible')) {
                            $this.select2('destroy');
                        }
                        // Re-initialize with the correct dropdown parent so focus works perfectly
                        $this.select2({ dropdownParent: $modal });
                    });
                }
            }
        }
    });

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
                    // Cleanup any orphaned update modals that were moved to the body
                    document.querySelectorAll('body > .modal[id^="updateTransactionModal_"]').forEach(m => m.remove());

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

    /**
     * ==========================================
     * TRANSACTION DROPDOWN FILTER & NOUISLIDER
     * ==========================================
     */
    const filterCategory = document.getElementById('filter-category');
    const filterYear = document.getElementById('filter-date-year');
    const filterMonth = document.getElementById('filter-date-month');
    const filterDay = document.getElementById('filter-date-day');
    const btnApplyFilters = document.getElementById('btn-apply-filters');
    const btnClearFilters = document.getElementById('btn-clear-filters');
    const sliderElement = document.getElementById('transaction-amount-slider');

    if (sliderElement && btnApplyFilters && btnClearFilters) {

        // DELETE the dynamic maxProjectAmount calculation.
        // Force it to use safe, hardcoded numbers exactly like your Transaction page.

        // 1. Initialize NoUiSlider
        noUiSlider.create(sliderElement, {
            start: [0, 100000], // Safe, valid numbers
            connect: true,
            tooltips: [true, true],
            range: {
                'min': 0,
                'max': 100000 // Safe, valid numbers
            },
            format: wNumb({
                decimals: 0,
                prefix: '$'
            })
        });

        // Fix the 0px Dropdown Bug!
        document.getElementById('transactionFilterDropdown').addEventListener('shown.bs.dropdown', function () {
            sliderElement.noUiSlider.updateOptions({}, false);
        });

        // 2. Apply Filters Logic
        btnApplyFilters.addEventListener('click', function() {
            const selectedCategory = filterCategory.value;
            const selectedYear = filterYear.value;
            const selectedMonth = filterMonth.value;
            const selectedDay = filterDay.value;
            
            // Get raw slider values (remove the '$' prefix for math)
            const sliderValues = sliderElement.noUiSlider.get();
            const minAmount = parseFloat(sliderValues[0].replace('$', ''));
            const maxAmount = parseFloat(sliderValues[1].replace('$', ''));

            const allTxCards = document.querySelectorAll('.transaction-card');
            let visibleCount = 0;

            allTxCards.forEach(card => {
                let showCard = true;

                // Category Check
                if (selectedCategory !== 'ALL') {
                    const badge = card.querySelector('.badge');
                    if (!badge || badge.textContent.trim().toUpperCase() !== selectedCategory) {
                        showCard = false;
                    }
                }

                // Date Check
                const dateEl = card.querySelector('p.text-muted.mb-0.fs-12'); // The date paragraph
                if (dateEl && showCard) {
                    const dateText = dateEl.textContent.trim(); // Format: "2026-04-09"
                    const parts = dateText.split('-');
                    
                    if (parts.length === 3) {
                        const cardYear = parts[0];
                        const cardMonth = parts[1];
                        const cardDay = parts[2];

                        if (selectedYear !== 'ALL' && cardYear !== selectedYear) showCard = false;
                        if (selectedMonth !== 'ALL' && cardMonth !== selectedMonth) showCard = false;
                        if (selectedDay !== 'ALL' && cardDay !== selectedDay) showCard = false;
                    }
                }

                // Amount Check
                if (showCard) {
                    const costEl = card.querySelector('.text-danger');
                    if (costEl) {
                        // Extract just the number (e.g., "-$299.00" -> 299.00)
                        const costText = costEl.textContent.replace(/[^0-9.]/g, ''); 
                        const costValue = parseFloat(costText);
                        
                        if (!isNaN(costValue)) {
                            if (costValue < minAmount || costValue > maxAmount) {
                                showCard = false;
                            }
                        }
                    }
                }

                // Toggle Visibility
                if (showCard) {
                    card.classList.remove('d-none');
                    visibleCount++;
                } else {
                    card.classList.add('d-none');
                }
            });

            // Close the dropdown after applying
            const dropdownEl = document.getElementById('transactionFilterDropdown');
            if (dropdownEl) {
                const dropdownInstance = bootstrap.Dropdown.getInstance(dropdownEl);
                if (dropdownInstance) dropdownInstance.hide();
            }
        });

        // 3. Clear Filters Logic
        btnClearFilters.addEventListener('click', function() {
            filterCategory.value = 'ALL';
            filterYear.value = 'ALL';
            filterMonth.value = 'ALL';
            filterDay.value = 'ALL';
            sliderElement.noUiSlider.set([0, 10000]);

            const allTxCards = document.querySelectorAll('.transaction-card');
            allTxCards.forEach(card => card.classList.remove('d-none'));
        });
    }
});
