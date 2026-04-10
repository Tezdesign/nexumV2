document.addEventListener('DOMContentLoaded', function() {
    /**
     * ==========================================
     * PROJECT DROPDOWN FILTER & NOUISLIDER
     * ==========================================
     */
    const filterStatus = document.getElementById('filter-project-status');
    const filterPName = document.getElementById('filter-project-name');
    const filterPMonth = document.getElementById('filter-project-month');
    const btnApplyPFilters = document.getElementById('btn-apply-project-filters');
    const btnClearPFilters = document.getElementById('btn-clear-project-filters');
    const sliderPElement = document.getElementById('project-amount-slider');
    const emptyState = document.getElementById('project-search-empty-state');

    if (sliderPElement && btnApplyPFilters && btnClearPFilters) {

        // 1. Initialize NoUiSlider
        noUiSlider.create(sliderPElement, {
            start: [0, 100000],
            connect: true,
            tooltips: [true, true],
            range: {
                'min': 0,
                'max': 100000
            },
            format: wNumb({
                decimals: 0,
                prefix: '$'
            })
        });

        // Force slider to redraw when dropdown opens so width isn't 0px
        const dropdownEl = document.getElementById('projectFilterDropdown');
        if (dropdownEl) {
            dropdownEl.addEventListener('shown.bs.dropdown', function () {
                sliderPElement.noUiSlider.updateOptions({}, false);
            });
        }

        // 2. Apply Filters Logic
        // 2. Apply Filters Logic
        btnApplyPFilters.addEventListener('click', function() {
            const selectedStatus = filterStatus.value.toUpperCase();
            const selectedName = filterPName ? filterPName.value.trim().toLowerCase() : 'all';
            const selectedMonth = filterPMonth.value;

            // Get raw slider values
            const sliderValues = sliderPElement.noUiSlider.get();
            const minAmount = parseFloat(sliderValues[0].replace(/[^0-9.-]+/g, ""));
            const maxAmount = parseFloat(sliderValues[1].replace(/[^0-9.-]+/g, ""));

            const allProjectCards = document.querySelectorAll('.project-card');
            let visibleCount = 0;

            allProjectCards.forEach(card => {
                let showCard = true;

                // 1. Status Check
                if (selectedStatus !== 'ALL') {
                    const statusBadge = card.querySelector('.badge');
                    if (!statusBadge || statusBadge.textContent.trim().toUpperCase() !== selectedStatus) {
                        showCard = false;
                    }
                }

                // 2. Project Name Check (More forgiving)
                if (showCard && selectedName !== 'all') {
                    // Check ALL text inside the card for the project name
                    const cardText = card.textContent.toLowerCase();
                    if (!cardText.includes(selectedName)) {
                        showCard = false;
                    }
                }

                // 3. Month Check (Looks for the specific "MM" anywhere near a calendar icon)
                if (showCard && selectedMonth !== 'ALL') {
                    const dateIcon = card.querySelector('.ti-calendar-event');
                    if (dateIcon && dateIcon.parentElement) {
                        const dateText = dateIcon.parentElement.textContent.trim();
                        // Looks for YYYY-MM-DD or MM/DD/YYYY
                        const dateMatch = dateText.match(/(\d{4})-(\d{2})-(\d{2})|(\d{2})\/(\d{2})\/(\d{4})/);

                        let cardMonth = null;
                        if (dateMatch && dateMatch[2]) { cardMonth = dateMatch[2]; } // YYYY-MM-DD
                        else if (dateMatch && dateMatch[4]) { cardMonth = dateMatch[4]; } // MM/DD/YYYY

                        if (cardMonth !== selectedMonth) {
                            showCard = false;
                        }
                    } else {
                        showCard = false;
                    }
                }

                // 4. Amount Check (FIXED: Handles 'k' and 'M')
                if (showCard) {
                    const budgetEls = card.querySelectorAll('h4.fw-normal, h5.fw-normal');
                    if (budgetEls.length > 0) {
                        const rawText = budgetEls[0].textContent.trim().toLowerCase();
                        let multiplier = 1;

                        // Check for thousands or millions
                        if (rawText.includes('k')) multiplier = 1000;
                        if (rawText.includes('m')) multiplier = 1000000;

                        // Strip characters, leaving only the decimal number
                        const cleanNumber = rawText.replace(/[^0-9.]/g, '');
                        const budgetValue = parseFloat(cleanNumber) * multiplier;

                        if (!isNaN(budgetValue)) {
                            if (budgetValue < minAmount || budgetValue > maxAmount) {
                                showCard = false;
                            }
                        }
                    }
                }

                // 5. Toggle Visibility safely (Handles both Grid and List views)
                let elementToHide = card;
                const parentCol = card.closest('.col');
                if (parentCol && parentCol.parentElement && parentCol.parentElement.id === 'project-grid-view') {
                    elementToHide = parentCol;
                }

                if (showCard) {
                    elementToHide.classList.remove('d-none');
                    visibleCount++;
                } else {
                    elementToHide.classList.add('d-none');
                }
            });

            // Handle empty state graphic
            if (emptyState) {
                if (visibleCount === 0 && allProjectCards.length > 0) {
                    emptyState.classList.remove('d-none');
                } else {
                    emptyState.classList.add('d-none');
                }
            }

            // Close the dropdown after applying
            const dropdownEl = document.getElementById('projectFilterDropdown');
            if (dropdownEl) {
                const dropdownInstance = bootstrap.Dropdown.getInstance(dropdownEl);
                if (dropdownInstance) dropdownInstance.hide();
            }
        });

        // 3. Clear Filters Logic
        btnClearPFilters.addEventListener('click', function() {
            if (filterStatus) filterStatus.value = 'ALL';
            if (filterPName) filterPName.value = 'ALL';
            if (filterPMonth) filterPMonth.value = 'ALL';
            
            if (sliderPElement && sliderPElement.noUiSlider) {
                sliderPElement.noUiSlider.set([0, 100000]);
            }

            const allProjectCards = document.querySelectorAll('.project-card');
            allProjectCards.forEach(card => {
                let elementToHide = card;
                const parentCol = card.closest('.col');
                if (parentCol && parentCol.parentElement && parentCol.parentElement.id === 'project-grid-view') {
                    elementToHide = parentCol;
                }
                elementToHide.classList.remove('d-none');
            });

            if (emptyState) {
                emptyState.classList.add('d-none');
            }
        });
    }
});