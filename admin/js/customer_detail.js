document.addEventListener('DOMContentLoaded', function() {
    // Auto dismiss alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);

    // Handle form submissions with confirmations
    const confirmForms = document.querySelectorAll('form[id^="form"]');
    confirmForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const confirmMessage = form.querySelector('button[type="submit"]').getAttribute('data-confirm') || 
                                  'Bạn có chắc chắn muốn thực hiện hành động này?';
            
            if (!confirm(confirmMessage)) {
                e.preventDefault();
                return false;
            }
        });
    });
    
    // Handle image preview enlargement
    const reviewImages = document.querySelectorAll('.review-image');
    reviewImages.forEach(img => {
        img.addEventListener('click', function(e) {
            e.preventDefault();
            const url = this.getAttribute('src');
            
            // Create modal for image preview
            const modal = document.createElement('div');
            modal.classList.add('modal', 'fade');
            modal.setAttribute('tabindex', '-1');
            modal.innerHTML = `
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Xem ảnh</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body text-center">
                            <img src="${url}" class="img-fluid" alt="Preview">
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            const modalInstance = new bootstrap.Modal(modal);
            modalInstance.show();
            
            // Remove modal from DOM after it's hidden
            modal.addEventListener('hidden.bs.modal', function () {
                document.body.removeChild(modal);
            });
        });
    });
});
