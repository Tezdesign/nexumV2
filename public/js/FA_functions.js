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
        
        // 1. Initialize NoUiSlider with Tooltips
        noUiSlider.create(sliderElement, {
            start: [0, 100000], // Sensible defaults; could be dynamic based on dataset
            connect: true,
            tooltips: [true, true], // Show floating tooltips above handles
            range: {
                'min': 0,
                'max': 100000
            },
            format: wNumb({
                decimals: 0,
                prefix: '$'
            })
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

    /**
     * ==========================================
     * TRANSACTION DROPDOWN FILTER & NOUISLIDER
     * ==========================================
     */


    const filterStatus = document.getElementById('filter-project-status');
    const filterName = document.getElementById('filter-project-name');
    const filterPMonth = document.getElementById('filter-project-month');
    const btnApplyPFilters = document.getElementById('btn-apply-project-filters');
    const btnClearPFilters = document.getElementById('btn-clear-project-filters');
    const sliderPElement = document.getElementById('project-amount-slider');

    if (sliderElement && btnApplyFilters && btnClearFilters) {

        // 2. Initialize NoUiSlider with Tooltips
        noUiSlider.create(sliderPElement, {
            start: [0, 100000], // Sensible defaults; adjust based on dataset
            connect: true,
            tooltips: [true, true], // Show floating tooltips above handles
            range: {
                'min': 0,
                'max': 100000
            },
            format: wNumb({
                decimals: 0,
                prefix: '$'
            })
        });

        // 3. Apply Filters Logic
        btnApplyPFilters.addEventListener('click', function() {
            const selectedStatus = filterStatus.value.toUpperCase();
            const selectedName = filterName.value;
            const selectedMonth = filterMonth.value;

            // Get raw slider values (remove the '$' prefix for math)
            const sliderPValues = sliderPElement.noUiSlider.get();
            const minPAmount = parseFloat(sliderValues[0].replace(/[^0-9.-]+/g, ""));
            const maxPAmount = parseFloat(sliderValues[1].replace(/[^0-9.-]+/g, ""));

            // IMPORTANT: Ensure your project items have the class 'project-card'
            const allProjectCards = document.querySelectorAll('.project-card');

            allProjectCards.forEach(card => {
                let showCard = true;

                // A. Status Check
                if (selectedStatus !== 'ALL') {
                    // Adjust selector to find your status badge (e.g., .badge)
                    const statusBadge = card.querySelector('.badge');
                    if (!statusBadge || statusBadge.textContent.trim().toUpperCase() !== selectedStatus) {
                        showCard = false;
                    }
                }

                // B. Project Name Check
                if (showCard && selectedName !== 'ALL') {
                    // Adjust selector to find your project title (e.g., h5 or .project-title)
                    const nameEl = card.querySelector('h5, .project-title');
                    if (!nameEl || nameEl.textContent.trim() !== selectedName) {
                        showCard = false;
                    }
                }

                // C. Month Check
                if (showCard && selectedMonth !== 'ALL') {
                    // Adjust selector to find your date text
                    const dateEl = card.querySelector('p.text-muted, .project-date');
                    if (dateEl) {
                        const dateText = dateEl.textContent.trim(); // Expecting format like "2026-04-09"
                        const parts = dateText.split('-');

                        if (parts.length >= 2) {
                            const cardMonth = parts[1]; // Grabs the "04" from "2026-04-09"
                            if (cardMonth !== selectedMonth) {
                                showCard = false;
                            }
                        }
                    }
                }

                // D. Amount Check (Total Budget)
                if (showCard) {
                    // Adjust selector to target where the budget is displayed
                    const budgetEl = card.querySelector('h4.fw-normal, .project-budget');
                    if (budgetEl) {
                        const budgetText = budgetEl.textContent.replace(/[^0-9.]/g, '');
                        const budgetValue = parseFloat(budgetText);

                        if (!isNaN(budgetValue)) {
                            if (budgetValue < minPAmount || budgetValue > maxPAmount) {
                                showCard = false;
                            }
                        }
                    }
                }

                // E. Toggle Visibility
                if (showCard) {
                    card.classList.remove('d-none');
                } else {
                    card.classList.add('d-none');
                }
            });

            // Close the dropdown after applying
            const dropdownEl = document.getElementById('projectFilterDropdown');
            if (dropdownEl) {
                const dropdownInstance = bootstrap.Dropdown.getInstance(dropdownEl);
                if (dropdownInstance) dropdownInstance.hide();
            }
        });

        // 4. Clear Filters Logic
        btnClearPFilters.addEventListener('click', function() {
            // Reset Selects
            filterStatus.value = 'ALL';
            filterName.value = 'ALL';
            filterPMonth.value = 'ALL';

            // Reset Slider
            sliderPElement.noUiSlider.set([0, 100000]);

            // Reveal all cards
            const allProjectCards = document.querySelectorAll('.project-card');
            allProjectCards.forEach(card => card.classList.remove('d-none'));
        });
    }

});
