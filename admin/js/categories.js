document.addEventListener('DOMContentLoaded', function () {
    // Auto dismiss alerts after 5 seconds
    setTimeout(function () {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function (alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);

    // Define the category modal element
    const categoryModalElement = document.getElementById('categoryModal');

    // Hàm để reset form khi thêm mới
    function resetCategoryForm() {
        console.log('Resetting category form');
        const form = document.getElementById('categoryForm');
        if (form) {
            form.reset();

            // Xóa hidden input id nếu có
            const hiddenIdInput = form.querySelector('input[name="id"]');
            if (hiddenIdInput) {
                hiddenIdInput.remove();
            }

            // Đặt tiêu đề modal là "Thêm danh mục mới"
            const modalTitle = document.querySelector('#categoryModalLabel');
            if (modalTitle) {
                modalTitle.textContent = 'Thêm danh mục mới';
            }

            // Set nút submit thành "Thêm mới"
            const submitButton = document.querySelector('#submitCategoryButton');
            if (submitButton) {
                submitButton.textContent = 'Thêm mới';
            }

            // Xóa hình ảnh hiển thị nếu có
            const imageContainer = form.querySelector('.card.p-2.text-center');
            if (imageContainer) {
                imageContainer.innerHTML = `
                    <div class="py-4 text-muted">
                        <i class="fas fa-image fa-4x mb-2"></i>
                        <p>Chưa có hình ảnh</p>
                    </div>
                    <input type="file" class="form-control" id="hinhanh" name="hinhanh" accept="image/*">
                    <small class="text-muted mt-1">Để trống nếu không muốn thay đổi hình ảnh</small>
                `;
            }
        }
    }    // Add click event handler for all buttons that should trigger the modal
    document.querySelectorAll('button[data-bs-target="#categoryModal"]').forEach(function (button) {
        button.addEventListener('click', function (event) {
            // Kiểm tra nếu nút này không phải là nút sửa (có href với edit=)
            const isAddButton = button.id === 'addCategoryButton' || button.id === 'addNewCategoryButton' ||
                !button.getAttribute('href') || !button.getAttribute('href').includes('edit=');

            if (isAddButton) {
                // Reset form khi là thêm mới
                resetCategoryForm();
            }

            try {
                // Try to use Bootstrap's built-in modal functionality
                const categoryModal = new bootstrap.Modal(categoryModalElement);
                categoryModal.show();
            } catch (error) {
                console.error('Error showing modal:', error);
                // Fallback: manually add the necessary classes for displaying the modal
                categoryModalElement.classList.add('show');
                categoryModalElement.style.display = 'block';
                document.body.classList.add('modal-open');

                // Create backdrop if it doesn't exist
                let backdrop = document.querySelector('.modal-backdrop');
                if (!backdrop) {
                    backdrop = document.createElement('div');
                    backdrop.classList.add('modal-backdrop', 'show');
                    document.body.appendChild(backdrop);
                }
            }
        });
    });    // Thêm event listener cho sự kiện hiển thị modal
    if (categoryModalElement) {
        categoryModalElement.addEventListener('show.bs.modal', function (event) {
            // Kiểm tra nếu modal được kích hoạt bởi nút "Thêm danh mục"
            const button = event.relatedTarget;
            if (button && (button.id === 'addCategoryButton' || button.id === 'addNewCategoryButton' || !button.classList.contains('edit-button'))) {
                resetCategoryForm();
            }
        });
    }

    // Show modal if edit parameter is present
    // Kiểm tra cả biến editMode từ PHP và tham số trong URL
    if (typeof editMode !== 'undefined' && editMode === true) {
        try {
            const categoryModal = new bootstrap.Modal(categoryModalElement);
            categoryModal.show();
        } catch (error) {
            console.error('Error showing edit modal:', error);
        }
    } else {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('edit')) {
            try {
                const categoryModal = new bootstrap.Modal(categoryModalElement);
                categoryModal.show();
            } catch (error) {
                console.error('Error showing edit modal from URL params:', error);
            }
        }
    }
    // Generate slug from name
    const nameInput = document.getElementById('ten');
    const slugInput = document.getElementById('slug');

    if (nameInput && slugInput) {
        nameInput.addEventListener('keyup', function () {
            if (!slugInput.value) {
                slugInput.value = createSlug(nameInput.value);
            }
        });
    }

    // Add click event listener for the "Add Category" button
    const addCategoryButtons = document.querySelectorAll('button[data-bs-target="#categoryModal"]');
    addCategoryButtons.forEach(button => {
        button.addEventListener('click', function (e) {
            // Debug log
            console.log('Add Category button clicked');

            // Check if we have Bootstrap available
            if (typeof bootstrap === 'undefined') {
                console.error('Bootstrap is not loaded properly');
                // Load Bootstrap manually if needed
                const script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js';
                document.head.appendChild(script);
                script.onload = function () {
                    console.log('Bootstrap loaded manually');
                    const categoryModal = new bootstrap.Modal(document.getElementById('categoryModal'));
                    categoryModal.show();
                };
            }
        });
    });    // Form validation before submit
    const categoryForm = document.querySelector('#categoryModal form');
    if (categoryForm) {
        categoryForm.addEventListener('submit', function (e) {
            const categoryName = document.getElementById('ten').value.trim();
            if (!categoryName) {
                e.preventDefault();
                alert('Vui lòng nhập tên danh mục');
                return false;
            }

            console.log('Form is being submitted');
            return true;
        });

        // Reset form khi modal bị đóng
        categoryModalElement.addEventListener('hidden.bs.modal', function () {
            // Nếu không có tham số edit trong URL, reset form
            const urlParams = new URLSearchParams(window.location.search);
            if (!urlParams.has('edit')) {
                resetCategoryForm();
            }
        });
    }

    // Add event listeners for modal close buttons
    document.querySelectorAll('#categoryModal .btn-close, #categoryModal .btn-secondary').forEach(button => {
        button.addEventListener('click', function () {
            try {
                const categoryModal = bootstrap.Modal.getInstance(document.getElementById('categoryModal'));
                if (categoryModal) {
                    categoryModal.hide();
                } else {
                    // Manual fallback
                    document.getElementById('categoryModal').classList.remove('show');
                    document.getElementById('categoryModal').style.display = 'none';
                    document.body.classList.remove('modal-open');
                    const backdrop = document.querySelector('.modal-backdrop');
                    if (backdrop) backdrop.remove();
                }
            } catch (error) {
                console.error('Error hiding modal:', error);
            }
        });
    });

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
        filter.addEventListener('change', function () {
            this.closest('form').submit();
        });
    });

    // Debug function to check for Bootstrap issues
    console.log('Bootstrap version loaded:', typeof bootstrap !== 'undefined' ? 'Yes' : 'No');
});
