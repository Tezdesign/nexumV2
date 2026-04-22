document.addEventListener('DOMContentLoaded', function() {
    let html5QrcodeScanner;

    const qrScannerModalEl = document.getElementById('qrScannerModal');
    if (qrScannerModalEl) {
        qrScannerModalEl.addEventListener('shown.bs.modal', function () {
            // Check if it's already initialized to prevent multiple instances
            if (!html5QrcodeScanner) {
                html5QrcodeScanner = new Html5QrcodeScanner(
                    "qr-reader", { fps: 10, qrbox: 250 }
                );
                html5QrcodeScanner.render(onScanSuccess, onScanFailure);
            }
        });

        qrScannerModalEl.addEventListener('hidden.bs.modal', function () {
            if (html5QrcodeScanner) {
                html5QrcodeScanner.clear().then(() => {
                    html5QrcodeScanner = null; // Reset so it can be re-opened
                }).catch(error => console.error("Failed to clear scanner.", error));
            }
        });
    }

    function onScanSuccess(decodedText, decodedResult) {
        // 1. Stop scanner and hide QR modal
        if (html5QrcodeScanner) {
             html5QrcodeScanner.clear();
             html5QrcodeScanner = null;
        }
        
        const qrModal = bootstrap.Modal.getInstance(document.getElementById('qrScannerModal'));
        if(qrModal) qrModal.hide();

        try {
            // 2. Parse JSON
            const data = JSON.parse(decodedText);
            
            // 3. Pre-fill Create Transaction Form (adjust IDs based on form generation)
            if(data.reference) document.getElementById('transaction_reference').value = data.reference;
            if(data.cost) document.getElementById('transaction_cost').value = data.cost;
            if(data.expense_category) {
                const categorySelect = document.getElementById('transaction_expense_category');
                if (categorySelect) {
                    categorySelect.value = data.expense_category;
                    // Trigger Select2 update if applicable
                    if (typeof jQuery !== 'undefined' && $(categorySelect).data('select2')) {
                        $(categorySelect).trigger('change');
                    }
                }
            }
            if(data.description) document.getElementById('transaction_description').value = data.description;
            if(data.date_stamp) document.getElementById('transaction_date_stamp').value = data.date_stamp;

            // 4. Open Create Transaction Modal
            setTimeout(() => { // slight delay to allow the first modal to fully close
                const createModalEl = document.getElementById('createTransactionModal');
                if (createModalEl) {
                    const createModal = new bootstrap.Modal(createModalEl);
                    createModal.show();
                }
            }, 300);
            
        } catch (e) {
            alert("Invalid QR Code format. Expected JSON payload.");
            console.error("QR Parse Error:", e, "Raw Data:", decodedText);
        }
    }

    function onScanFailure(error) {
        // handle scan failure, usually better to ignore and keep scanning
    }
});
