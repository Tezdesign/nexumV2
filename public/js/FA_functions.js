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

    /**
     * ==========================================
     * AI ANALYSIS TAB LOGIC
     * ==========================================
     */
    const btnGenerateAi = document.getElementById('btn-generate-ai-analysis');
    const btnRerunAi = document.getElementById('btn-rerun-ai');
    const aiActionArea = document.getElementById('ai-generate-action');
    const aiLoadingState = document.getElementById('ai-loading-state');
    const aiResultContainer = document.getElementById('ai-result-container');
    const aiAnalysisContent = document.getElementById('ai-analysis-content');
    const aiUserContext = document.getElementById('ai-user-context');
    
    // Result Elements
    const resVariance = document.getElementById('res-variance');
    const resVarianceSub = document.getElementById('res-variance-sub');
    const resTotal = document.getElementById('res-total');
    const resDate = document.getElementById('res-date');
    const resRiskBadge = document.getElementById('res-risk-badge');
    const resProbRing = document.getElementById('res-prob-ring');
    const resProbText = document.getElementById('res-prob-text');

    if (btnGenerateAi) {
        btnGenerateAi.addEventListener('click', function() {
            const projectId = this.getAttribute('data-project-id');
            const contextText = aiUserContext ? aiUserContext.value.trim() : '';
            
            // UI State Transition: Hide button, show loading
            aiActionArea.classList.add('d-none');
            aiLoadingState.classList.remove('d-none');
            
            // Fire the AJAX POST request
            fetch(`/apps-financial-analysis/budget/${projectId}/analyze`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ userContext: contextText })
            })
            .then(response => response.json())
            .then(data => {
                // UI State Transition: Hide loading, show result container
                aiLoadingState.classList.add('d-none');
                aiResultContainer.classList.remove('d-none');
                
                if (data.status === 'success') {
                    try {
                        const aiData = JSON.parse(data.analysis);
                        
                        // Populate Text Fields
                        if (resVariance) resVariance.innerText = aiData.variance_amount || 'N/A';
                        if (resVarianceSub) resVarianceSub.innerText = aiData.variance_status || 'N/A';
                        if (resTotal) resTotal.innerText = aiData.projected_total || 'N/A';
                        if (resDate) resDate.innerText = aiData.inflection_date || 'N/A';

                        // Populate Risk Badge
                        if (resRiskBadge) {
                            resRiskBadge.innerText = (aiData.risk_level || 'Unknown').toUpperCase() + ' RISK';
                            resRiskBadge.className = 'badge rounded-pill px-2 py-1 fs-12 ';
                            if (aiData.risk_level.toLowerCase().includes('high')) {
                                resRiskBadge.classList.add('bg-danger');
                            } else if (aiData.risk_level.toLowerCase().includes('medium')) {
                                resRiskBadge.classList.add('bg-warning');
                            } else {
                                resRiskBadge.classList.add('bg-success');
                            }
                        }

                        // Animate SVG Progress Ring
                        const probability = parseInt(aiData.success_probability) || 0;
                        if (resProbRing && resProbText) {
                            // The radius is 60 as defined in the SVG
                            const circumference = 60 * 2 * Math.PI; // approx 376.99
                            const offset = circumference - (probability / 100) * circumference;
                            
                            // Reset animation explicitly
                            resProbRing.style.transition = 'none';
                            resProbRing.style.strokeDashoffset = '376.99';
                            
                            // Force reflow
                            void resProbRing.offsetWidth; 
                            
                            // Trigger animation
                            resProbRing.style.transition = 'stroke-dashoffset 1s ease-in-out';
                            resProbRing.style.strokeDashoffset = offset;
                            
                            resProbText.innerText = probability + '%';
                            
                            // Color code the ring based on probability
                            if (probability < 40) {
                                resProbRing.setAttribute('stroke', '#fa5c7c'); // red
                            } else if (probability < 70) {
                                resProbRing.setAttribute('stroke', '#f9c851'); // yellow
                            } else {
                                resProbRing.setAttribute('stroke', '#10c469'); // green
                            }
                        }

                        // Inject the Markdown response. 
                        let formattedText = aiData.recommended_solutions || 'No detailed recommendations provided.';
                        
                        // Basic Markdown Parsing
                        formattedText = formattedText.replace(/^### (.*$)/gim, '<h5 class="text-primary mt-3 mb-2">$1</h5>');
                        formattedText = formattedText.replace(/^## (.*$)/gim, '<h4 class="mt-4 mb-3">$1</h4>');
                        formattedText = formattedText.replace(/\*\*(.*)\*\*/gim, '<strong>$1</strong>');
                        formattedText = formattedText.replace(/^\- (.*$)/gim, '<li class="mb-1">$1</li>');
                        formattedText = formattedText.replace(/\n/g, '<br>');
                        
                        aiAnalysisContent.innerHTML = formattedText;
                        
                    } catch (e) {
                        console.error('Failed to parse AI JSON:', e, data.analysis);
                        aiAnalysisContent.innerHTML = `<div class="alert alert-danger">Error interpreting AI response. Expected JSON. <br><br>Raw response:<br> ${data.analysis}</div>`;
                    }
                } else {
                    aiAnalysisContent.innerHTML = '<div class="alert alert-danger">Failed to generate analysis.</div>';
                }
            })
            .catch(error => {
                aiLoadingState.classList.add('d-none');
                aiResultContainer.classList.remove('d-none');
                aiAnalysisContent.innerHTML = `<div class="alert alert-danger"><h5 class="alert-heading">Connection Error</h5><p>${error.message}</p></div>`;
                console.error('AI Error:', error);
            });
        });
    }

    if (btnRerunAi) {
        btnRerunAi.addEventListener('click', function() {
            // Hide result, show action area
            aiResultContainer.classList.add('d-none');
            aiActionArea.classList.remove('d-none');
            
            // Clear content & reset ring
            aiAnalysisContent.innerHTML = '';
            if (resVariance) resVariance.innerText = '--';
            if (resTotal) resTotal.innerText = '--';
            if (resDate) resDate.innerText = '--';
            if (resProbRing) resProbRing.style.strokeDashoffset = '376.99';
            if (resProbText) resProbText.innerText = '--%';
        });
    }

});

/**
 * ==========================================
 * CURRENCY EXCHANGE SYSTEM
 * ==========================================
 */
function formatKpiNumber(num) {
    if (num >= 1000000 || num <= -1000000) {
        return (num / 1000000).toFixed(2) + 'M';
    }
    if (num >= 1000 || num <= -1000) {
        return (num / 1000).toFixed(1) + 'k';
    }
    return num.toFixed(2);
}

function initializeCurrencyExchange(selectorId, profileId, defaultCurrency) {
    const $currencySelect = $(selectorId);
    if ($currencySelect.length === 0) return;

    $.ajax({
        url: '/apps-financial-analysis/profile/' + profileId + '/currency-rates',
        type: 'GET',
        success: function(response) {
            if (response.results && response.results.length > 0) {
                $currencySelect.select2({
                    data: response.results,
                    dropdownParent: $('body'),
                    width: '100px',
                    placeholder: 'Change Currency...',
                    templateResult: function (state) {
                        if (!state.id) return state.text;
                        return $('<span>' + state.text + ' (Rate: ' + parseFloat(state.rate).toFixed(3) + ')</span>');
                    },
                    templateSelection: function (state) {
                        if (!state.id) return state.text;
                        return state.id;
                    }
                });

                const applyConversion = function(selectedCurrencyCode, rate) {
                    let newSymbol = selectedCurrencyCode;
                    if (selectedCurrencyCode === 'USD') newSymbol = '$';
                    else if (selectedCurrencyCode === 'EUR') newSymbol = '€';
                    else if (selectedCurrencyCode === 'GBP') newSymbol = '£';
                    else if (selectedCurrencyCode === 'JPY') newSymbol = '¥';
                    else newSymbol = selectedCurrencyCode + ' ';

                    // Update generic currency-value elements and legacy kpi-value elements
                    $('.kpi-value, .currency-value').each(function() {
                        const rawValue = parseFloat($(this).attr('data-raw-value'));
                        if (!isNaN(rawValue)) {
                            const convertedValue = rawValue * rate;
                            const isKpi = $(this).hasClass('kpi-value');
                            
                            if (isKpi) {
                                const formattedString = formatKpiNumber(convertedValue);
                                $(this).find('.kpi-symbol').text(newSymbol);
                                $(this).find('.kpi-number').text(formattedString);
                            } else {
                                const formattedString = formatKpiNumber(convertedValue);
                                $(this).find('.currency-symbol').text(newSymbol);
                                $(this).find('.currency-number').text(formattedString);
                            }
                        }
                    });

                    // Update Charts if the function exists
                    if (typeof window.updateChartCurrencies === 'function') {
                        window.updateChartCurrencies(rate, newSymbol);
                    }
                };

                const storedCurrency = localStorage.getItem('fa_preferred_currency');
                let initialCurrency = defaultCurrency;
                let initialRate = 1;

                if (storedCurrency && storedCurrency !== defaultCurrency && $currencySelect.find("option[value='" + storedCurrency + "']").length) {
                    initialCurrency = storedCurrency;
                }

                if ($currencySelect.find("option[value='" + initialCurrency + "']").length) {
                    $currencySelect.val(initialCurrency).trigger('change');
                    
                    if (initialCurrency !== defaultCurrency) {
                        response.results.forEach(function(group) {
                            if (group.children) {
                                group.children.forEach(function(child) {
                                    if (child.id === initialCurrency) {
                                        initialRate = parseFloat(child.rate);
                                    }
                                });
                            }
                        });
                        applyConversion(initialCurrency, initialRate);
                    }
                }

                $currencySelect.on('select2:select', function (e) {
                    const data = e.params.data;
                    const rate = parseFloat(data.rate);
                    const selectedCurrencyCode = data.id;

                    localStorage.setItem('fa_preferred_currency', selectedCurrencyCode);
                    applyConversion(selectedCurrencyCode, rate);
                });

                $('#btn-reset-currency').on('click', function(e) {
                    e.preventDefault();
                    localStorage.removeItem('fa_preferred_currency');
                    $currencySelect.val(defaultCurrency).trigger('change');
                    applyConversion(defaultCurrency, 1);
                });
            } else {
                $currencySelect.html('<option disabled>API Error / Unavailable</option>');
            }
        },
        error: function() {
            $currencySelect.html('<option disabled>API Error</option>');
        }
    });
}

