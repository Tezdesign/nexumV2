    /**
     * ==========================================
     * PROJECT BUDGET DROPDOWN FILTER & NOUISLIDER
     * ==========================================
     */
    const filterProjectName = document.getElementById('filter-project-name');
    const filterProjectMonth = document.getElementById('filter-project-month');
    const btnApplyProjectFilters = document.getElementById('btn-apply-project-filters');
    const btnClearProjectFilters = document.getElementById('btn-clear-project-filters');
    const projectSliderElement = document.getElementById('project-amount-slider');

    if (projectSliderElement && btnApplyProjectFilters && btnClearProjectFilters) {

        // Carbon copy of transaction slider
        noUiSlider.create(projectSliderElement, {
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

        btnApplyProjectFilters.addEventListener('click', function() {
            const name = filterProjectName ? filterProjectName.value : 'ALL';
            const month = filterProjectMonth ? filterProjectMonth.value : 'ALL';

            const sliderValues = projectSliderElement.noUiSlider.get();
            const minAmount = parseFloat(sliderValues[0].replace(/[^0-9.-]+/g, ""));
            const maxAmount = parseFloat(sliderValues[1].replace(/[^0-9.-]+/g, ""));

            const allCards = document.querySelectorAll('.project-card');
            let visibleCount = 0;

            allCards.forEach(card => {
                const elementToHide = card.closest('.col') ? card.closest('.col') : card;
                let showCard = true;

                // Name Check (The Linked Project name)
                if (showCard && name !== 'ALL') {
                    const nameEl = card.querySelector('p.text-muted.fs-13.mb-3') || card.querySelector('p.text-muted.mb-0.fs-13'); 
                    if (!nameEl || nameEl.textContent.trim() !== name) {
                        showCard = false;
                    }
                }

                // Month Check
                if (showCard && month !== 'ALL') {
                    let foundMonth = false;
                    const paragraphs = card.querySelectorAll('p.text-muted');
                    paragraphs.forEach(p => {
                        if (p.textContent.includes('Due:') || (p.querySelector('.ti-calendar-event') && p.textContent.trim().match(/\d{4}-\d{2}-\d{2}/))) {
                            const dateMatch = p.textContent.match(/\d{4}-(\d{2})-\d{2}/);
                            if (dateMatch && dateMatch[1] === month) {
                                foundMonth = true;
                            }
                        }
                    });
                    if (!foundMonth) {
                        showCard = false;
                    }
                }

                // Amount Check (Total Budget)
                if (showCard) {
                    const headers = card.querySelectorAll('h4.fw-normal, h5.fw-normal');
                    let budgetValue = null;
                    if (headers.length > 0) {
                        const costText = headers[0].textContent.replace(/[^0-9.]/g, ''); 
                        budgetValue = parseFloat(costText);
                    }
                    if (budgetValue !== null && !isNaN(budgetValue)) {
                        if (budgetValue < minAmount || budgetValue > maxAmount) {
                            showCard = false;
                        }
                    }
                }

                // Toggle Visibility
                if (showCard) {
                    elementToHide.classList.remove('d-none');
                    visibleCount++;
                } else {
                    elementToHide.classList.add('d-none');
                }
            });

            // Handle "No results found"
            const projectEmptyState = document.getElementById('project-search-empty-state');
            if (projectEmptyState) {
                if (visibleCount === 0 && allCards.length > 0) {
                    projectEmptyState.classList.remove('d-none');
                } else {
                    projectEmptyState.classList.add('d-none');
                }
            }

            // Close the dropdown
            const dropdownEl = document.getElementById('projectFilterDropdown');
            if (dropdownEl) {
                const dropdownInstance = bootstrap.Dropdown.getInstance(dropdownEl);
                if (dropdownInstance) dropdownInstance.hide();
            }
        });

        btnClearProjectFilters.addEventListener('click', function() {
            if (filterProjectName) filterProjectName.value = 'ALL';
            if (filterProjectMonth) filterProjectMonth.value = 'ALL';
            projectSliderElement.noUiSlider.set([0, 100000]);

            const allCards = document.querySelectorAll('.project-card');
            allCards.forEach(card => {
                const elementToHide = card.closest('.col') ? card.closest('.col') : card;
                elementToHide.classList.remove('d-none');
            });
            const projectEmptyState = document.getElementById('project-search-empty-state');
            if(projectEmptyState) projectEmptyState.classList.add('d-none');
        });
    }

    /**
     * ==========================================
     * BUDGET PROFILE DROPDOWN FILTER & NOUISLIDER
     * ==========================================
     */
    const filterProfilePeriod = document.getElementById('filter-profile-period');
    const filterProfileCurrency = document.getElementById('filter-profile-currency');
    const btnApplyProfileFilters = document.getElementById('btn-apply-profile-filters');
    const btnClearProfileFilters = document.getElementById('btn-clear-profile-filters');
    const profileSliderElement = document.getElementById('profile-amount-slider');

    if (profileSliderElement && btnApplyProfileFilters && btnClearProfileFilters) {

        // Carbon copy of transaction slider
        noUiSlider.create(profileSliderElement, {
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

        btnApplyProfileFilters.addEventListener('click', function() {
            const period = filterProfilePeriod ? filterProfilePeriod.value : 'ALL';
            const currency = filterProfileCurrency ? filterProfileCurrency.value : 'ALL';

            const sliderValues = profileSliderElement.noUiSlider.get();
            const minAmount = parseFloat(sliderValues[0].replace(/[^0-9.-]+/g, ""));
            const maxAmount = parseFloat(sliderValues[1].replace(/[^0-9.-]+/g, ""));

            const allCards = document.querySelectorAll('.profile-card');
            let visibleCount = 0;

            allCards.forEach(card => {
                const elementToHide = card.closest('.col') ? card.closest('.col') : card;
                let showCard = true;

                // Period Type Check (Standard vs Custom)
                if (period !== 'ALL' && showCard) {
                    let isStandard = false;
                    const dateParagraphs = card.querySelectorAll('p.text-muted.mb-0.fs-12');
                    dateParagraphs.forEach(p => {
                        if (p.textContent.includes(' to ')) {
                            const dateMatch = p.textContent.match(/(\d{4}-01-01) to (\d{4}-12-31)/);
                            if (dateMatch && dateMatch[1] && dateMatch[2]) {
                                isStandard = true;
                            }
                        }
                    });

                    if (period === 'STANDARD' && !isStandard) showCard = false;
                    if (period === 'CUSTOM' && isStandard) showCard = false;
                }

                // Currency Check
                if (currency !== 'ALL' && showCard) {
                    const currEl = card.querySelector('p.text-muted.fs-13.mb-3'); 
                    if (currEl && !currEl.textContent.includes(currency)) {
                        showCard = false;
                    }
                }

                // Amount Check (Disposable Budget)
                if (showCard) {
                    const headers = card.querySelectorAll('h4.fw-normal.fs-14.m-0');
                    let budgetValue = null;
                    if (headers.length > 0) {
                        const costText = headers[0].textContent.replace(/[^0-9.]/g, ''); 
                        budgetValue = parseFloat(costText);
                    }
                    if (budgetValue !== null && !isNaN(budgetValue)) {
                        if (budgetValue < minAmount || budgetValue > maxAmount) {
                            showCard = false;
                        }
                    }
                }

                // Toggle Visibility
                if (showCard) {
                    elementToHide.classList.remove('d-none');
                    visibleCount++;
                } else {
                    elementToHide.classList.add('d-none');
                }
            });

            // Handle "No results found"
            const profileEmptyState = document.getElementById('profile-search-empty-state');
            if (profileEmptyState) {
                if (visibleCount === 0 && allCards.length > 0) {
                    profileEmptyState.classList.remove('d-none');
                } else {
                    profileEmptyState.classList.add('d-none');
                }
            }

            // Close the dropdown
            const dropdownEl = document.getElementById('profileFilterDropdown');
            if (dropdownEl) {
                const dropdownInstance = bootstrap.Dropdown.getInstance(dropdownEl);
                if (dropdownInstance) dropdownInstance.hide();
            }
        });

        btnClearProfileFilters.addEventListener('click', function() {
            if (filterProfilePeriod) filterProfilePeriod.value = 'ALL';
            if (filterProfileCurrency) filterProfileCurrency.value = 'ALL';
            profileSliderElement.noUiSlider.set([0, 100000]);

            const allCards = document.querySelectorAll('.profile-card');
            allCards.forEach(card => {
                const elementToHide = card.closest('.col') ? card.closest('.col') : card;
                elementToHide.classList.remove('d-none');
            });
            const profileEmptyState = document.getElementById('profile-search-empty-state');
            if(profileEmptyState) profileEmptyState.classList.add('d-none');
        });
    }

});