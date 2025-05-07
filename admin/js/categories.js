document.addEventListener('DOMContentLoaded', function() {
    // Auto dismiss alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
    
    // Show modal if edit parameter is present
    // Kiểm tra cả biến editMode từ PHP và tham số trong URL
    if (typeof editMode !== 'undefined' && editMode === true) {
        const categoryModal = new bootstrap.Modal(document.getElementById('categoryModal'));
        categoryModal.show();
    } else {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('edit')) {
            const categoryModal = new bootstrap.Modal(document.getElementById('categoryModal'));
            categoryModal.show();
        }
    }
    
    // Generate slug from name
    const nameInput = document.getElementById('ten');
    const slugInput = document.getElementById('slug');
    
    if (nameInput && slugInput) {
        nameInput.addEventListener('keyup', function() {
            if (!slugInput.value) {
                slugInput.value = createSlug(nameInput.value);
            }
        });
    }
    
    function createSlug(text) {
        return text.toLowerCase()
            .replace(/[áàảãạăắằẳẵặâấầẩẫậ]/g, 'a')
            .replace(/[éèẻẽẹêếềểễệ]/g, 'e')
            .replace(/[íìỉĩị]/g, 'i')
            .replace(/[óòỏõọôốồổỗộơớờởỡợ]/g, 'o')
            .replace(/[úùủũụưứừửữự]/g, 'u')
            .replace(/[ýỳỷỹỵ]/g, 'y')
            .replace(/đ/g, 'd')
            .replace(/\s+/g, '-')
            .replace(/[^\w\-]+/g, '')
            .replace(/\-\-+/g, '-')
            .replace(/^-+/, '')
            .replace(/-+$/, '');
    }
    
    // Auto submit form when select elements change
    const autoSubmitFilters = document.querySelectorAll('#status, #sort, #parent');
    autoSubmitFilters.forEach(filter => {
        filter.addEventListener('change', function() {
            this.closest('form').submit();
        });
    });
});
