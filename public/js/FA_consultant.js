document.addEventListener('DOMContentLoaded', function () {
    const interfaceContainer = document.getElementById('consultant-draft-interface');
    if (!interfaceContainer) return;

    const ajaxUrl = interfaceContainer.getAttribute('data-ajax-url');
    const filterTabs = document.querySelectorAll('.consultant-filter-tab');
    const listContainer = document.getElementById('consultant-draft-list');
    const evalPanel = document.getElementById('consultant-eval-panel');
    const template = document.getElementById('eval-panel-template');

    let activeDraftId = null;

    // 1. Initial Load (Flawed tab by default)
    loadDrafts('flawed');

    // 2. Filter Tab Clicks
    filterTabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Update active styling
            filterTabs.forEach(t => t.classList.remove('active', 'bg-white', 'shadow-sm'));
            this.classList.add('active', 'bg-white', 'shadow-sm');

            const filter = this.getAttribute('data-filter');
            loadDrafts(filter);
        });
    });

    // 3. Load Drafts via AJAX
    function loadDrafts(filter) {
        listContainer.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';
        
        fetch(`${ajaxUrl}?filter=${filter}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.text())
        .then(html => {
            listContainer.innerHTML = html;
            resetEvalPanel();
            attachRowListeners();
        })
        .catch(err => {
            console.error('Failed to load drafts:', err);
            listContainer.innerHTML = '<div class="text-center py-4 text-danger"><i class="ri-error-warning-line fs-1"></i><p>Error loading drafts.</p></div>';
        });
    }

    // 4. Attach Click Listeners to Rows
    function attachRowListeners() {
        const rows = listContainer.querySelectorAll('.draft-selectable-row');
        rows.forEach(row => {
            row.addEventListener('click', function() {
                // Remove active styling from all rows
                rows.forEach(r => {
                    r.classList.remove('bg-light', 'border-primary', 'shadow-sm');
                    r.style.borderRight = ''; // Reset border
                });

                // Add active styling to clicked row (Visual Merge)
                this.classList.add('bg-light', 'border-primary', 'shadow-sm');
                // The visual merge trick: remove right border so it bleeds into the panel
                this.style.borderRight = '3px solid transparent'; 
                
                const draftId = this.getAttribute('data-draft-id');
                const evalDataStr = this.getAttribute('data-eval');
                
                activeDraftId = draftId;
                
                let evalData = {};
                try {
                    evalData = evalDataStr ? JSON.parse(evalDataStr) : {};
                } catch(e) {}

                populateEvalPanel(draftId, evalData);
            });
        });
    }

    // 5. Populate the 40% Evaluation Panel
    function populateEvalPanel(draftId, evalData) {
        if (!template) return;
        
        // Clone template content
        const clone = template.content.cloneNode(true);
        const cardBody = evalPanel.querySelector('.card-body');
        
        // Prepare container
        cardBody.innerHTML = '';
        cardBody.classList.remove('align-items-center', 'justify-content-center', 'text-center');
        cardBody.classList.add('text-start');
        cardBody.appendChild(clone);

        const statusBadge = cardBody.querySelector('.badge-status');
        const contentArea = cardBody.querySelector('.eval-content');
        
        const finalDecision = evalData.final_decision || 'PENDING';
        
        // Style status badge
        if (finalDecision === 'APPROVED' || finalDecision === 'PASS') {
            statusBadge.className = 'badge border text-success border-success bg-success-subtle';
        } else if (finalDecision === 'REJECTED') {
            statusBadge.className = 'badge border text-danger border-danger bg-danger-subtle';
        } else {
            statusBadge.className = 'badge border text-warning border-warning bg-warning-subtle';
        }
        statusBadge.textContent = finalDecision;

        // Build HTML for tests
        let html = '';
        if (evalData.tests) {
            // Budget Capacity Test
            const budget = evalData.tests.budget_capacity;
            if (budget) {
                const icon = budget.status === 'Pass' ? '<i class="ri-checkbox-circle-line text-success"></i>' : '<i class="ri-error-warning-line text-danger"></i>';
                html += `<div class="mb-3 border rounded p-2 bg-white shadow-sm">
                            <div class="fw-bold mb-1">${icon} Budget Capacity Test</div>
                            <div class="text-muted">${budget.message || 'No details'}</div>
                         </div>`;
            }

            // Statistical Anomaly Test
            const stats = evalData.tests.statistical_anomaly;
            if (stats) {
                const icon = stats.status === 'Pass' ? '<i class="ri-checkbox-circle-line text-success"></i>' : '<i class="ri-error-warning-line text-danger"></i>';
                const zscoreText = stats.z_score !== null ? ` (Z-Score: ${stats.z_score})` : '';
                html += `<div class="mb-3 border rounded p-2 bg-white shadow-sm">
                            <div class="fw-bold mb-1">${icon} Statistical Anomaly Test</div>
                            <div class="text-muted">${stats.reason || 'No details'}${zscoreText}</div>
                         </div>`;
            }

            // Duplicate Check
            const dupes = evalData.tests.duplicate_check;
            if (dupes) {
                const icon = dupes.status === 'Pass' ? '<i class="ri-checkbox-circle-line text-success"></i>' : '<i class="ri-error-warning-line text-danger"></i>';
                html += `<div class="mb-3 border rounded p-2 bg-white shadow-sm">
                            <div class="fw-bold mb-1">${icon} Duplicate Check</div>
                            <div class="text-muted">${dupes.message || 'No details'}</div>
                         </div>`;
            }
            
            // Prior Rejection Reason
            if (evalData.rejection_data) {
                html += `<div class="mt-3 pt-3 border-top border-dashed">
                            <h6 class="mb-1 text-danger"><i class="ri-close-circle-line me-1"></i>Previous Rejection Reason</h6>
                            <p class="mb-0 text-muted">${evalData.rejection_data.reason}</p>
                         </div>`;
            }

        } else {
            html = '<p class="text-muted">No evaluation data available for this draft.</p>';
        }

        contentArea.innerHTML = html;

        // Action Handlers
        const btnApprove = cardBody.querySelector('.btn-approve-draft');
        if (btnApprove) {
            btnApprove.addEventListener('click', () => submitConsultantAction(draftId, 'approve'));
        }

        const btnReject = cardBody.querySelector('.btn-reject-draft');
        if (btnReject) {
            // Rejection uses a modal to get reason, so we just set the target ID on a global variable
            window.consultantDraftRejectId = draftId;
        }
    }

    function resetEvalPanel() {
        activeDraftId = null;
        evalPanel.querySelector('.card-body').innerHTML = `
            <div class="d-flex flex-column align-items-center justify-content-center text-center h-100 p-4">
                <i class="ri-file-search-line fs-1 text-muted mb-2"></i>
                <h5 class="text-muted">Select a Draft</h5>
                <p class="text-muted small mb-0">Click on a draft row to view its evaluation details and take action.</p>
            </div>
        `;
    }

    function submitConsultantAction(draftId, action, reason = '') {
        const endpoint = `/apps-financial-analysis/consultant/draft/${draftId}/${action}`;
        
        fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ reason: reason })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Reload current tab
                const activeTab = document.querySelector('.consultant-filter-tab.active');
                if (activeTab) loadDrafts(activeTab.getAttribute('data-filter'));
                
                // Hide modal if rejecting
                if (action === 'reject' && typeof bootstrap !== 'undefined') {
                    const modalEl = document.getElementById('consultantRejectModal');
                    if (modalEl) bootstrap.Modal.getInstance(modalEl).hide();
                }
            } else {
                alert('Action failed: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(err => {
            console.error('Action failed:', err);
            alert('A network error occurred.');
        });
    }

    // Global reject handler for the modal submit button
    const submitRejectBtn = document.getElementById('submit-consultant-reject');
    if (submitRejectBtn) {
        submitRejectBtn.addEventListener('click', () => {
            const reasonInput = document.getElementById('consultant-reject-reason');
            const reason = reasonInput ? reasonInput.value.trim() : '';
            if (window.consultantDraftRejectId) {
                submitConsultantAction(window.consultantDraftRejectId, 'reject', reason);
            }
        });
    }
});