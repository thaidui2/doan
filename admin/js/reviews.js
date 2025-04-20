document.addEventListener('DOMContentLoaded', function() {
    // Auto dismiss alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
    
    // Auto-submit form when select elements change
    const autoSubmitFilters = document.querySelectorAll('#status, #rating, #product, #customer, #sort');
    autoSubmitFilters.forEach(filter => {
        filter.addEventListener('change', function() {
            this.closest('form').submit();
        });
    });
    
    // Enhanced image viewer for review images
    const reviewImages = document.querySelectorAll('.review-image');
    reviewImages.forEach(img => {
        // Skip images with errors
        if (img.classList.contains('img-error')) {
            return;
        }
        
        img.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Get the full size image path from parent link
            const fullSizeImageSrc = this.closest('a').getAttribute('href');
            
            // Create modal element with enhanced styling
            const modal = document.createElement('div');
            modal.classList.add('modal', 'fade');
            modal.setAttribute('tabindex', '-1');
            modal.style.backgroundColor = 'rgba(0, 0, 0, 0.85)';
            
            modal.innerHTML = `
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content bg-dark text-light border-0">
                        <div class="modal-header border-secondary">
                            <h5 class="modal-title text-light">Xem ảnh đánh giá</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body text-center p-0 position-relative">
                            <div class="d-flex align-items-center justify-content-center" style="min-height: 300px;">
                                <div class="spinner-border text-light my-5" role="status" id="imageLoader">
                                    <span class="visually-hidden">Đang tải...</span>
                                </div>
                                <img src="${fullSizeImageSrc}" class="img-fluid" alt="Review Image" style="max-height: 80vh; display: none;"
                                     onload="this.style.display='block'; document.getElementById('imageLoader').style.display='none';"
                                     onerror="this.onerror=null; this.src='../assets/img/no-image.png'; document.getElementById('imageLoader').style.display='none'; this.style.display='block';">
                            </div>
                        </div>
                        <div class="modal-footer border-secondary">
                            <a href="${fullSizeImageSrc}" class="btn btn-sm btn-outline-light" target="_blank" download>
                                <i class="fas fa-download me-1"></i> Tải xuống ảnh
                            </a>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            // Show the modal
            const modalInstance = new bootstrap.Modal(modal);
            modalInstance.show();
            
            // Remove from DOM after hiding
            modal.addEventListener('hidden.bs.modal', function() {
                document.body.removeChild(modal);
            });
        });
    });
    
    // Add animation effects to review items
    const reviewItems = document.querySelectorAll('.review-item');
    reviewItems.forEach((item, index) => {
        // Add subtle entrance animation with delay based on position
        item.style.opacity = '0';
        item.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            item.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            item.style.opacity = '1';
            item.style.transform = 'translateY(0)';
        }, 100 + (index * 50)); // Staggered animation
    });
    
    // Confirm actions with improved dialog
    const actionButtons = document.querySelectorAll('a[href*="action="]');
    actionButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            const action = this.getAttribute('href').includes('action=delete') ? 'xóa' : 
                          (this.getAttribute('href').includes('action=hide') ? 'ẩn' : 'hiển thị');
            
            let confirmMessage = `Bạn có chắc chắn muốn ${action} đánh giá này?`;
            if (action === 'xóa') {
                confirmMessage += ' Hành động này không thể hoàn tác!';
            }
                          
            if (!confirm(confirmMessage)) {
                e.preventDefault();
                return false;
            }
        });
    });
});
