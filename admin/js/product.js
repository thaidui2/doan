document.addEventListener('DOMContentLoaded', function() {
    // Khởi tạo Summernote
    $('.summernote').summernote({
        height: 300,
        toolbar: [
            ['style', ['style', 'bold', 'italic', 'underline', 'clear']],
            ['font', ['strikethrough', 'superscript', 'subscript']],
            ['fontsize', ['fontsize']],
            ['color', ['color']],
            ['para', ['ul', 'ol', 'paragraph']],
            ['table', ['table']],
            ['insert', ['link', 'picture']],
            ['view', ['fullscreen', 'codeview', 'help']]
        ],
        callbacks: {
            onImageUpload: function(files) {
                // Xử lý upload ảnh nếu cần
                alert('Upload ảnh trong editor chưa được hỗ trợ, vui lòng dùng link ảnh');
            }
        }
    });
    
    // Xử lý upload ảnh chính
    const mainImageContainer = document.getElementById('mainImageContainer');
    const mainImageInput = document.getElementById('hinhanh');
    const mainImagePreview = document.getElementById('mainImagePreview');
    const mainImagePlaceholder = document.getElementById('mainImagePlaceholder');
    
    if (mainImageContainer && mainImageInput) {
        mainImageContainer.addEventListener('click', function() {
            mainImageInput.click();
        });
        
        mainImageInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                
                // Kiểm tra kích thước file (max 5MB)
                if (file.size > 5 * 1024 * 1024) {
                    alert('Kích thước file quá lớn. Vui lòng chọn file nhỏ hơn 5MB.');
                    this.value = '';
                    return;
                }
                
                // Kiểm tra loại file
                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Định dạng file không hỗ trợ. Vui lòng chọn ảnh có định dạng JPG, PNG hoặc GIF.');
                    this.value = '';
                    return;
                }
                
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    mainImagePreview.src = e.target.result;
                    mainImagePreview.classList.remove('d-none');
                    mainImagePlaceholder.classList.add('d-none');
                }
                
                reader.readAsDataURL(file);
            }
        });
    }
    
    // Xử lý upload nhiều ảnh
    const extraImagesContainer = document.getElementById('extraImagesContainer');
    const extraImagesInput = document.getElementById('product_images');
    const extraImagesPreview = document.getElementById('extraImagesPreview');
    
    if (extraImagesContainer && extraImagesInput) {
        extraImagesContainer.addEventListener('click', function() {
            extraImagesInput.click();
        });
        
        extraImagesInput.addEventListener('change', function() {
            extraImagesPreview.innerHTML = '';
            
            if (this.files && this.files.length > 0) {
                // Giới hạn số lượng file
                if (this.files.length > 5) {
                    alert('Bạn chỉ có thể upload tối đa 5 ảnh.');
                    this.value = '';
                    return;
                }
                
                for (let i = 0; i < this.files.length; i++) {
                    const file = this.files[i];
                    
                    // Kiểm tra kích thước file (max 5MB)
                    if (file.size > 5 * 1024 * 1024) {
                        alert(`File "${file.name}" quá lớn. Vui lòng chọn file nhỏ hơn 5MB.`);
                        continue;
                    }
                    
                    // Kiểm tra loại file
                    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    if (!allowedTypes.includes(file.type)) {
                        alert(`File "${file.name}" không hỗ trợ. Vui lòng chọn ảnh có định dạng JPG, PNG hoặc GIF.`);
                        continue;
                    }
                    
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        const imgContainer = document.createElement('div');
                        imgContainer.className = 'position-relative';
                        
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.className = 'image-preview';
                        img.alt = 'Ảnh sản phẩm ' + (i + 1);
                        
                        imgContainer.appendChild(img);
                        extraImagesPreview.appendChild(imgContainer);
                    }
                    
                    reader.readAsDataURL(file);
                }
            }
        });
    }
    
    // Xử lý xóa ảnh hiện có
    const deleteImageButtons = document.querySelectorAll('.delete-image');
    
    deleteImageButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Đánh dấu ảnh để xóa
            const imageId = this.getAttribute('data-image-id');
            const checkbox = this.parentElement.querySelector('.delete-image-checkbox');
            
            if (checkbox) {
                checkbox.checked = true;
                this.parentElement.style.opacity = '0.5';
                this.style.backgroundColor = '#28a745';
                this.innerHTML = '<i class="fas fa-check"></i>';
                this.style.pointerEvents = 'none';
            }
        });
    });
    
    // Xử lý thêm/xóa biến thể
    const variantsContainer = document.getElementById('variants-container');
    const addVariantBtn = document.getElementById('add-variant');
    const variantTemplate = document.getElementById('variant-template');
    
    // Thêm biến thể mới
    if (addVariantBtn && variantTemplate) {
        // Add initial variant for product_add.php
        if (variantsContainer.querySelectorAll('.variant-row').length === 0) {
            addVariantRow();
        }
        
        addVariantBtn.addEventListener('click', function() {
            addVariantRow();
        });
        
        // Xử lý xóa biến thể
        variantsContainer.addEventListener('click', function(e) {
            if (e.target.closest('.remove-variant')) {
                e.preventDefault();
                
                const variantRow = e.target.closest('.variant-row');
                
                // Chỉ xóa nếu có nhiều hơn 1 biến thể
                if (variantsContainer.querySelectorAll('.variant-row').length > 1) {
                    variantRow.remove();
                } else {
                    alert('Phải có ít nhất một biến thể cho sản phẩm.');
                }
            }
        });
        
        // Xử lý hiển thị màu sắc trong dropdown
        variantsContainer.addEventListener('change', function(e) {
            if (e.target.classList.contains('variant-color')) {
                const selectedOption = e.target.options[e.target.selectedIndex];
                const colorCode = selectedOption.getAttribute('data-color');
                
                if (colorCode) {
                    selectedOption.style.backgroundColor = colorCode;
                    if (isLightColor(colorCode)) {
                        selectedOption.style.color = '#000';
                    } else {
                        selectedOption.style.color = '#fff';
                    }
                }
            }
        });
    }
    
    // Validate form trước khi submit
    const productForm = document.getElementById('productForm');
    
    if (productForm) {
        productForm.addEventListener('submit', function(e) {
            // Kiểm tra các trường bắt buộc
            const requiredFields = productForm.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            // Kiểm tra có ảnh chính chưa
            const mainImageInput = document.getElementById('hinhanh');
            const mainImageContainer = document.getElementById('mainImageContainer');
            
            if (mainImageInput && mainImageInput.hasAttribute('required') && (!mainImageInput.files || !mainImageInput.files[0])) {
                mainImageContainer.classList.add('border-danger');
                isValid = false;
            } else if (mainImageContainer) {
                mainImageContainer.classList.remove('border-danger');
            }
            
            // Kiểm tra các biến thể
            const variants = variantsContainer.querySelectorAll('.variant-row');
            let hasValidVariant = false;
            
            variants.forEach(variant => {
                const sizeSelect = variant.querySelector('.variant-size');
                const colorSelect = variant.querySelector('.variant-color');
                const quantityInput = variant.querySelector('.variant-quantity');
                
                if (sizeSelect.value && colorSelect.value && parseInt(quantityInput.value) >= 0) {
                    hasValidVariant = true;
                }
            });
            
            if (!hasValidVariant) {
                alert('Vui lòng thêm ít nhất một biến thể hợp lệ cho sản phẩm.');
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        });
    }
    
    // Các hàm hỗ trợ
    function addVariantRow() {
        const variantContent = variantTemplate.content.cloneNode(true);
        variantsContainer.appendChild(variantContent);
    }
    
    function isLightColor(color) {
        // Chuyển mã màu hex thành RGB
        let r, g, b;
        
        if (color.startsWith('#')) {
            color = color.substring(1);
            
            if (color.length === 3) {
                r = parseInt(color[0] + color[0], 16);
                g = parseInt(color[1] + color[1], 16);
                b = parseInt(color[2] + color[2], 16);
            } else if (color.length === 6) {
                r = parseInt(color.substring(0, 2), 16);
                g = parseInt(color.substring(2, 4), 16);
                b = parseInt(color.substring(4, 6), 16);
            } else {
                return true;
            }
            
            // Tính độ sáng (YIQ)
            const yiq = ((r * 299) + (g * 587) + (b * 114)) / 1000;
            
            // Nếu YIQ > 128, màu sáng
            return yiq > 128;
        }
        
        return true;
    }
});
