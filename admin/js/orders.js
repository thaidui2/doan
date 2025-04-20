document.addEventListener('DOMContentLoaded', function() {
    // Initialize date pickers
    if (typeof flatpickr !== 'undefined') {
        flatpickr('.datepicker', {
            dateFormat: 'Y-m-d',
            allowInput: true
        });
    }

    // Auto dismiss alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);

    // Handle order status update modal
    const updateStatusButtons = document.querySelectorAll('.btn-update-status');
    const cancelWarning = document.getElementById('cancelWarning');
    const newStatusSelect = document.getElementById('new_status');
    
    updateStatusButtons.forEach(button => {
        button.addEventListener('click', function() {
            const orderId = this.getAttribute('data-order-id');
            const orderCode = this.getAttribute('data-order-code');
            const currentStatus = parseInt(this.getAttribute('data-current-status'));
            
            // Set values in modal
            document.getElementById('modal_order_id').value = orderId;
            document.getElementById('modal_order_code').textContent = orderCode;
            
            // Set the current status as selected
            const statusSelect = document.getElementById('new_status');
            
            // Reset the options
            for (let i = 0; i < statusSelect.options.length; i++) {
                statusSelect.options[i].disabled = false;
            }
            
            // Set the current status
            if (statusSelect.options[currentStatus]) {
                statusSelect.value = currentStatus;
            }
            
            // Disable inappropriate transitions based on current status
            // Can't go back to "pending" status from "completed" or "canceled"
            if (currentStatus === 4 || currentStatus === 5) {
                statusSelect.querySelector('option[value="1"]').disabled = true;
            }
            
            // Can't go back to "pending" or "confirmed" from "shipping"
            if (currentStatus === 3) {
                statusSelect.querySelector('option[value="1"]').disabled = true;
            }
            
            // If order is canceled, only allow setting it back to pending
            if (currentStatus === 5) {
                statusSelect.querySelector('option[value="2"]').disabled = true;
                statusSelect.querySelector('option[value="3"]').disabled = true;
                statusSelect.querySelector('option[value="4"]').disabled = true;
            }
        });
    });

    // Show warning when selecting "canceled" status
    if (newStatusSelect) {
        newStatusSelect.addEventListener('change', function() {
            if (this.value === '5') {
                cancelWarning.style.display = 'block';
            } else {
                cancelWarning.style.display = 'none';
            }
        });
    }

    // Filter auto-submission on select changes
    const autoSubmitFilters = document.querySelectorAll('#status, #payment_status, #payment_method, #sort');
    autoSubmitFilters.forEach(filter => {
        filter.addEventListener('change', function() {
            this.closest('form').submit();
        });
    });
});
