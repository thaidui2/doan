document.addEventListener('DOMContentLoaded', function() {
    // Auto dismiss alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
    
    // Enable tooltips
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
    
    // Customer search functionality
    const searchInput = document.getElementById('search');
    if (searchInput) {
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.closest('form').submit();
            }
        });
    }
    
    // Status filter change event
    const statusFilter = document.getElementById('status');
    if (statusFilter) {
        statusFilter.addEventListener('change', function() {
            this.closest('form').submit();
        });
    }
    
    // Sort filter change event
    const sortFilter = document.getElementById('sort');
    if (sortFilter) {
        sortFilter.addEventListener('change', function() {
            this.closest('form').submit();
        });
    }
});
