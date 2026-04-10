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


        const dropdownEl = document.getElementById('projectFilterDropdown');
        if (dropdownEl) {
            dropdownEl.addEventListener('shown.bs.dropdown', function () {
                sliderPElement.noUiSlider.updateOptions({}, false);
            });
        }

        btnApplyPFilters.addEventListener('click', function() {
            const selectedStatus = filterStatus.value.toUpperCase();
            const selectedName = filterPName ? filterPName.value.trim().toLowerCase() : 'all';
            const selectedMonth = filterPMonth.value;

            const sliderValues = sliderPElement.noUiSlider.get();
            const minAmount = parseFloat(sliderValues[0].replace(/[^0-9.-]+/g, ""));
            const maxAmount = parseFloat(sliderValues[1].replace(/[^0-9.-]+/g, ""));

            const allProjectCards = document.querySelectorAll('.project-card');
            let visibleCount = 0;

            allProjectCards.forEach(card => {
                let showCard = true;


                if (selectedStatus !== 'ALL') {
                    const statusBadge = card.querySelector('.badge');
                    if (!statusBadge || statusBadge.textContent.trim().toUpperCase() !== selectedStatus) {
                        showCard = false;
                    }
                }


                if (showCard && selectedName !== 'all') {

                    const cardText = card.textContent.toLowerCase();
                    if (!cardText.includes(selectedName)) {
                        showCard = false;
                    }
                }


                if (showCard && selectedMonth !== 'ALL') {
                    const dateIcon = card.querySelector('.ti-calendar-event');
                    if (dateIcon && dateIcon.parentElement) {
                        const dateText = dateIcon.parentElement.textContent.trim();

                        const dateMatch = dateText.match(/(\d{4})-(\d{2})-(\d{2})|(\d{2})\/(\d{2})\/(\d{4})/);

                        let cardMonth = null;
                        if (dateMatch && dateMatch[2]) { cardMonth = dateMatch[2]; }
                        else if (dateMatch && dateMatch[4]) { cardMonth = dateMatch[4]; }

                        if (cardMonth !== selectedMonth) {
                            showCard = false;
                        }
                    } else {
                        showCard = false;
                    }
                }


                if (showCard) {
                    const budgetEls = card.querySelectorAll('h4.fw-normal, h5.fw-normal');
                    if (budgetEls.length > 0) {
                        const rawText = budgetEls[0].textContent.trim().toLowerCase();
                        let multiplier = 1;


                        if (rawText.includes('k')) multiplier = 1000;
                        if (rawText.includes('m')) multiplier = 1000000;


                        const cleanNumber = rawText.replace(/[^0-9.]/g, '');
                        const budgetValue = parseFloat(cleanNumber) * multiplier;

                        if (!isNaN(budgetValue)) {
                            if (budgetValue < minAmount || budgetValue > maxAmount) {
                                showCard = false;
                            }
                        }
                    }
                }


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


            if (emptyState) {
                if (visibleCount === 0 && allProjectCards.length > 0) {
                    emptyState.classList.remove('d-none');
                } else {
                    emptyState.classList.add('d-none');
                }
            }


            const dropdownEl = document.getElementById('projectFilterDropdown');
            if (dropdownEl) {
                const dropdownInstance = bootstrap.Dropdown.getInstance(dropdownEl);
                if (dropdownInstance) dropdownInstance.hide();
            }
        });
    }
});