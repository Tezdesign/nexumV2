document.addEventListener('DOMContentLoaded', function () {
    /**
     * BUDGET PROFILE DROPDOWN FILTER & NOUISLIDER
     * ==========================================
     */
    const filterStatus = document.getElementById('filter-profile-status');
    const filterPeriod = document.getElementById('filter-profile-period');
    const filterCurrency = document.getElementById('filter-profile-currency');
    const btnApplyFilters = document.getElementById('btn-apply-profile-filters');
    const btnClearFilters = document.getElementById('btn-clear-profile-filters');
    const sliderElement = document.getElementById('profile-amount-slider');
    const emptyState = document.getElementById('profile-search-empty-state');

    console.log('Slider:', sliderElement);
    console.log('Apply Btn:', btnApplyFilters);
    console.log('Clear Btn:', btnClearFilters);
    
    if (sliderElement && btnApplyFilters && btnClearFilters) {
        

        noUiSlider.create(sliderElement, {
            start: [0, 1000000],
            connect: true,
            tooltips: [true, true],
            range: {
                'min': 0,
                'max': 1000000
            },
            format: wNumb({
                decimals: 0,
                prefix: '$'
            })
        });


        const dropdownEl = document.getElementById('profileFilterDropdown');
        if (dropdownEl) {
            dropdownEl.addEventListener('shown.bs.dropdown', function () {
                sliderElement.noUiSlider.updateOptions({}, false);
            });
        }


        btnApplyFilters.addEventListener('click', function() {
            const selectedStatus = filterStatus.value.toUpperCase();
            const selectedPeriod = filterPeriod.value.toUpperCase();
            const selectedCurrency = filterCurrency.value.toUpperCase();
            

            const sliderValues = sliderElement.noUiSlider.get();
            const minAmount = parseFloat(sliderValues[0].replace(/[^0-9.-]+/g, ''));
            const maxAmount = parseFloat(sliderValues[1].replace(/[^0-9.-]+/g, ''));


            const isMaxUnbounded = (maxAmount >= 1000000);

            const allProfileCards = document.querySelectorAll('.profile-card');
            let visibleCount = 0;

            allProfileCards.forEach(card => {
                let showCard = true;


                if (selectedStatus !== 'ALL') {
                    const badge = card.querySelector('.badge');
                    if (!badge || badge.textContent.trim().toUpperCase() !== selectedStatus) {
                        showCard = false;
                    }
                }


                if (showCard && selectedCurrency !== 'ALL') {
                    const currencyTextEl = Array.from(card.querySelectorAll('.text-muted.fs-13')).find(el => el.textContent.includes('Base Currency:'));
                    if (currencyTextEl) {
                        const currencyText = currencyTextEl.textContent.replace('Base Currency:', '').trim().toUpperCase();
                        if (currencyText !== selectedCurrency) {
                            showCard = false;
                        }
                    } else {
                        showCard = false;
                    }
                }


                if (showCard && selectedPeriod !== 'ALL') {
                    const dateIcon = card.querySelector('.ti-calendar-event');
                    if (dateIcon) {
                        const dateText = dateIcon.parentElement.textContent.trim();
                        const match = dateText.match(/(\d{4})-(\d{2})-(\d{2})\s+to\s+(\d{4})-(\d{2})-(\d{2})/);
                        if (match) {
                            const startMonth = match[2];
                            const startDay = match[3];
                            const endMonth = match[5];
                            const endDay = match[6];
                            
                            const isStandard = (startMonth === '01' && startDay === '01' && endMonth === '12' && endDay === '31');
                            
                            if (selectedPeriod === 'STANDARD' && !isStandard) {
                                showCard = false;
                            } else if (selectedPeriod === 'CUSTOM' && isStandard) {
                                showCard = false;
                            }
                        } else {
                            showCard = false;
                        }
                    } else {
                        showCard = false;
                    }
                }


                if (showCard) {
                    const costEls = card.querySelectorAll('.fw-normal');
                    if (costEls.length > 0) {
                        const costEl = costEls[0]; // Disposable budget is first
                        const costText = costEl.textContent.replace(/[^0-9.]/g, ''); 
                        const costValue = parseFloat(costText);
                        
                        if (!isNaN(costValue)) {
                            // Apply unbounded logic if max slider value is selected
                            if (costValue < minAmount || (!isMaxUnbounded && costValue > maxAmount)) {
                                showCard = false;
                            }
                        }
                    }
                }


                let elementToHide = card;
                const parentCol = card.closest('.col');
                if (parentCol && parentCol.parentElement && parentCol.parentElement.id === 'profile-grid-container') {
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
                if (visibleCount === 0) {
                    emptyState.classList.remove('d-none');
                } else {
                    emptyState.classList.add('d-none');
                }
            }


            if (dropdownEl) {
                const dropdownInstance = bootstrap.Dropdown.getInstance(dropdownEl);
                if (dropdownInstance) dropdownInstance.hide();
            }
        });


        btnClearFilters.addEventListener('click', function() {
            filterStatus.value = 'ALL';
            filterPeriod.value = 'ALL';
            filterCurrency.value = 'ALL';
            sliderElement.noUiSlider.set([0, 1000000]);

            const allProfileCards = document.querySelectorAll('.profile-card');
            allProfileCards.forEach(card => {
                let elementToHide = card;
                const parentCol = card.closest('.col');
                if (parentCol && parentCol.parentElement && parentCol.parentElement.id === 'profile-grid-container') {
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