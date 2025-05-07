// Add basic debugging and helper functions for the product detail page
document.addEventListener('DOMContentLoaded', function() {
    console.log('Product detail page script loaded');
    
    // Debug information
    const productId = document.getElementById('current-product-id')?.value;
    console.log('Current product ID:', productId);
    
    // Image zoom functionality
    const zoomContainer = document.getElementById('image-zoom-container');
    const mainImage = document.getElementById('main-product-image');
    const zoomLens = document.getElementById('zoom-lens');
    const zoomResult = document.getElementById('zoom-result');
    
    if (zoomContainer && mainImage && zoomLens && zoomResult) {
        // Biến lưu trạng thái zoom
        let zoomActive = false;
        let zoomScale = 2; // Mức độ zoom mặc định
        let isFullScreen = false;
        
        // Tạo nút đóng fullscreen
        const closeButton = document.createElement('div');
        closeButton.className = 'fullscreen-close d-none';
        closeButton.innerHTML = '<i class="bi bi-x"></i>';
        document.body.appendChild(closeButton);
        
        // Lấy các nút điều khiển zoom
        const zoomInBtn = zoomContainer.querySelector('.zoom-in-btn');
        const zoomOutBtn = zoomContainer.querySelector('.zoom-out-btn');
        const fullScreenBtn = zoomContainer.querySelector('.fullscreen-btn');
        const zoomIndicator = zoomContainer.querySelector('.zoom-indicator');
        
        // Hiện thông tin zoom level
        function updateZoomIndicator() {
            if (zoomIndicator) {
                const zoomLevelText = document.getElementById('zoom-level');
                if (zoomLevelText) {
                    zoomLevelText.textContent = `Zoom ${zoomScale}x`;
                }
                zoomIndicator.classList.remove('d-none');
                
                // Tự động ẩn sau 2 giây
                setTimeout(() => {
                    if (!zoomActive && !isFullScreen) {
                        zoomIndicator.classList.add('d-none');
                    }
                }, 2000);
            }
        }
        
        // Xử lý sự kiện mouseover
        mainImage.addEventListener('mouseover', function() {
            // Chỉ áp dụng cho màn hình lớn và khi không ở chế độ toàn màn hình
            if (window.innerWidth < 768 || isFullScreen) return;
            
            zoomLens.classList.remove('d-none');
            zoomResult.classList.remove('d-none');
            zoomIndicator.classList.remove('d-none');
            zoomActive = true;
            
            // Thiết lập ban đầu cho zoom result
            zoomResult.style.backgroundImage = `url(${mainImage.src})`;
        });
        
        // Xử lý sự kiện mouseout
        mainImage.addEventListener('mouseout', function() {
            if (isFullScreen) return;
            
            zoomLens.classList.add('d-none');
            zoomResult.classList.add('d-none');
            
            // Ẩn thông tin zoom sau một khoảng thời gian
            setTimeout(() => {
                if (!zoomActive) {
                    zoomIndicator.classList.add('d-none');
                }
            }, 1000);
            
            zoomActive = false;
        });
        
        // Xử lý sự kiện mousemove
        mainImage.addEventListener('mousemove', function(e) {
            if (!zoomActive) return;
            
            // Lấy kích thước và vị trí của ảnh
            const rect = mainImage.getBoundingClientRect();
            
            // Tính toán vị trí chuột tương đối so với ảnh
            let x = e.clientX - rect.left;
            let y = e.clientY - rect.top;
            
            // Giới hạn vị trí của lens để không vượt ra ngoài ảnh
            const lensHalfWidth = zoomLens.offsetWidth / 2;
            const lensHalfHeight = zoomLens.offsetHeight / 2;
            
            if (x < lensHalfWidth) x = lensHalfWidth;
            if (x > rect.width - lensHalfWidth) x = rect.width - lensHalfWidth;
            if (y < lensHalfHeight) y = lensHalfHeight;
            if (y > rect.height - lensHalfHeight) y = rect.height - lensHalfHeight;
            
            // Di chuyển lens theo chuột
            zoomLens.style.left = `${x - lensHalfWidth}px`;
            zoomLens.style.top = `${y - lensHalfHeight}px`;
            
            // Tính toán tỷ lệ zoom
            const cx = rect.width / zoomLens.offsetWidth * zoomScale;
            const cy = rect.height / zoomLens.offsetHeight * zoomScale;
            
            // Tính toán vị trí background trong kết quả zoom
            const backgroundPositionX = -((x * cx) / zoomScale - zoomResult.offsetWidth / 2);
            const backgroundPositionY = -((y * cy) / zoomScale - zoomResult.offsetHeight / 2);
            
            // Cập nhật kết quả zoom
            zoomResult.style.backgroundImage = `url(${mainImage.src})`;
            zoomResult.style.backgroundPosition = `${backgroundPositionX}px ${backgroundPositionY}px`;
            zoomResult.style.backgroundSize = `${rect.width * cx / zoomScale}px ${rect.height * cy / zoomScale}px`;
        });
        
        // Xử lý nút zoom in
        if (zoomInBtn) {
            zoomInBtn.addEventListener('click', function() {
                if (zoomScale < 4) {
                    zoomScale += 0.5;
                    updateZoomIndicator();
                }
            });
        }
        
        // Xử lý nút zoom out
        if (zoomOutBtn) {
            zoomOutBtn.addEventListener('click', function() {
                if (zoomScale > 1) {
                    zoomScale -= 0.5;
                    updateZoomIndicator();
                }
            });
        }
        
        // Xử lý nút fullscreen
        if (fullScreenBtn) {
            fullScreenBtn.addEventListener('click', function() {
                toggleFullScreen();
            });
        }
        
        // Xử lý đóng fullscreen
        closeButton.addEventListener('click', function() {
            if (isFullScreen) {
                toggleFullScreen();
            }
        });
        
        // Xử lý phím ESC để thoát fullscreen
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && isFullScreen) {
                toggleFullScreen();
            }
        });
        
        // Hàm bật/tắt chế độ toàn màn hình
        function toggleFullScreen() {
            isFullScreen = !isFullScreen;
            
            if (isFullScreen) {
                document.body.style.overflow = 'hidden';
                document.body.classList.add('fullscreen-mode');
                zoomContainer.classList.add('position-fixed', 'top-0', 'start-0', 'w-100', 'h-100', 'd-flex', 'align-items-center', 'justify-content-center', 'bg-dark');
                closeButton.classList.remove('d-none');
                zoomLens.classList.add('d-none');
                zoomResult.classList.add('d-none');
            } else {
                document.body.style.overflow = '';
                document.body.classList.remove('fullscreen-mode');
                zoomContainer.classList.remove('position-fixed', 'top-0', 'start-0', 'w-100', 'h-100', 'd-flex', 'align-items-center', 'justify-content-center', 'bg-dark');
                closeButton.classList.add('d-none');
            }
        }
        
        // Zoom với bánh xe chuột
        mainImage.addEventListener('wheel', function(e) {
            if (zoomActive || isFullScreen) {
                e.preventDefault();
                
                // Zoom in khi cuộn lên, zoom out khi cuộn xuống
                if (e.deltaY < 0 && zoomScale < 4) {
                    zoomScale += 0.25;
                } else if (e.deltaY > 0 && zoomScale > 1) {
                    zoomScale -= 0.25;
                }
                
                // Giới hạn mức zoom
                zoomScale = Math.min(Math.max(zoomScale, 1), 4);
                updateZoomIndicator();
            }
        }, { passive: false });
        
        // Khởi tạo hiển thị mức độ zoom
        updateZoomIndicator();
    } else {
        console.warn('Các phần tử zoom không tồn tại trong trang');
    }
    
    // Xử lý chuyển đổi hình ảnh khi click vào ảnh nhỏ
    const thumbnails = document.querySelectorAll('.thumbnail-image');

    if (thumbnails.length > 0 && mainImage) {
        thumbnails.forEach(thumbnail => {
            thumbnail.addEventListener('click', function() {
                // Đổi ảnh chính
                mainImage.src = this.src;
                
                // Đánh dấu thumbnail đang active
                thumbnails.forEach(thumb => thumb.parentElement.classList.remove('active'));
                this.parentElement.classList.add('active');
            });
        });
    }
    
    // Xử lý chuyển ảnh theo màu sắc
    const colorOptions = document.querySelectorAll('.color-option');
    if (colorOptions.length > 0) {
        colorOptions.forEach(option => {
            option.addEventListener('click', function() {
                if (this.dataset.disabled === 'true') return;
                
                // Đánh dấu màu đang chọn
                colorOptions.forEach(opt => opt.classList.remove('active'));
                this.classList.add('active');
                
                // Hiển thị tên màu đã chọn
                const selectedColorValue = document.querySelector('.selected-color-value');
                if (selectedColorValue) {
                    selectedColorValue.textContent = this.dataset.colorName || 'Chưa chọn';
                    if (this.dataset.colorCode) {
                        selectedColorValue.innerHTML += ` <span class="color-preview" style="background-color: ${this.dataset.colorCode}"></span>`;
                    }
                }
                
                // Cập nhật thông tin tồn kho nếu cả size và màu đã được chọn
                updateStockInfo();
            });
        });
    }
    
    // Xử lý nút kích thước
    const sizeButtons = document.querySelectorAll('.size-btn');
    if (sizeButtons.length > 0) {
        sizeButtons.forEach(button => {
            button.addEventListener('click', function() {
                // Đánh dấu kích thước đang chọn
                sizeButtons.forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');
                
                // Hiển thị tên kích thước đã chọn
                const selectedSizeValue = document.querySelector('.selected-size-value');
                if (selectedSizeValue) {
                    selectedSizeValue.textContent = this.dataset.sizeName || 'Chưa chọn';
                }
                
                // Cập nhật thông tin tồn kho nếu cả size và màu đã được chọn
                updateStockInfo();
            });
        });
    }
    
    // Hàm cập nhật thông tin tồn kho
    function updateStockInfo() {
        const selectedSize = document.querySelector('.size-btn.active');
        const selectedColor = document.querySelector('.color-option.active');
        const stockInfo = document.getElementById('variant-stock-info');
    
        if (selectedSize && selectedColor && stockInfo) {
            const sizeId = selectedSize.dataset.sizeId;
            const colorId = selectedColor.dataset.colorId;
            
            // Lấy dữ liệu tồn kho từ dataset
            const variantStockData = JSON.parse(document.getElementById('variant-stock-data').textContent);
            
            if (variantStockData[sizeId] && variantStockData[sizeId][colorId] !== undefined) {
                const stock = variantStockData[sizeId][colorId];
                stockInfo.classList.remove('d-none', 'stock-high', 'stock-medium', 'stock-low');
                
                if (stock > 10) {
                    stockInfo.classList.add('stock-high');
                    stockInfo.innerHTML = `<i class="bi bi-check-circle-fill text-success me-2"></i> Còn hàng (${stock} sản phẩm)`;
                } else if (stock > 5) {
                    stockInfo.classList.add('stock-medium');
                    stockInfo.innerHTML = `<i class="bi bi-info-circle-fill text-warning me-2"></i> Còn ${stock} sản phẩm`;
                } else if (stock > 0) {
                    stockInfo.classList.add('stock-low');
                    stockInfo.innerHTML = `<i class="bi bi-exclamation-triangle-fill text-danger me-2"></i> Chỉ còn ${stock} sản phẩm`;
                } else {
                    stockInfo.classList.add('stock-low');
                    stockInfo.innerHTML = `<i class="bi bi-x-circle-fill text-danger me-2"></i> Hết hàng`;
                }
                
                // Cập nhật số lượng tối đa có thể mua
                const quantityInput = document.getElementById('quantity');
                if (quantityInput) {
                    quantityInput.max = stock;
                    if (parseInt(quantityInput.value) > stock) {
                        quantityInput.value = stock > 0 ? stock : 1;
                    }
                }
                
                // Update the total stock display
                const totalStockDisplay = document.getElementById('total-stock-display');
                if (totalStockDisplay) {
                    totalStockDisplay.textContent = stock;
                }
            }
        }
    }
    
    // Xử lý nút tăng/giảm số lượng
    const decreaseBtn = document.getElementById('decreaseBtn');
    const increaseBtn = document.getElementById('increaseBtn');
    const quantityInput = document.getElementById('quantity');
    
    if (decreaseBtn && increaseBtn && quantityInput) {
        decreaseBtn.addEventListener('click', function() {
            const currentValue = parseInt(quantityInput.value);
            if (currentValue > 1) {
                quantityInput.value = currentValue - 1;
            }
        });
        
        increaseBtn.addEventListener('click', function() {
            const currentValue = parseInt(quantityInput.value);
            const maxValue = parseInt(quantityInput.max);
            if (currentValue < maxValue) {
                quantityInput.value = currentValue + 1;
            }
        });
    }
    
    // Thêm vào cuối script để đảm bảo chức năng Mua ngay vẫn hoạt động
    const buyNowBtn = document.getElementById('buyNowBtn');
    if (buyNowBtn) {
        buyNowBtn.addEventListener('click', function() {
            console.log('Buy Now button clicked'); // Debug logging
            const selection = validateSelection();
            if (!selection) return;
            console.log('Selection validated:', selection); // Debug the selection values
            
            // Visual feedback - show loading state
            buyNowBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Đang xử lý...';
            buyNowBtn.disabled = true;
            
            // Tạo form ẩn để submit dữ liệu - Cách tiếp cận mới, đơn giản hơn
            let formHtml = `
            <form id="buyNowForm" action="ajax/mua_ngay.php" method="POST" style="display:none">
                <input type="hidden" name="productId" value="${selection.productId}">
                <input type="hidden" name="quantity" value="${selection.quantity}">
                <input type="hidden" name="sizeId" value="${selection.sizeId || ''}">
                <input type="hidden" name="colorId" value="${selection.colorId || ''}">
            </form>
            `;
            // Thêm form vào body
            document.body.insertAdjacentHTML('beforeend', formHtml);
            // Lấy và gửi form
            const form = document.getElementById('buyNowForm');
            // Thêm delay nhỏ để đảm bảo DOM được cập nhật
            setTimeout(() => {
                try {
                    form.submit();
                } catch (error) {
                    console.error('Form submission error:', error);
                    buyNowBtn.innerHTML = 'Mua ngay';
                    buyNowBtn.disabled = false;
                    showToast('Có lỗi xảy ra khi xử lý yêu cầu', 'danger');
                }
            }, 100);
        });
    }
    
    // Hàm kiểm tra lựa chọn - Enhanced validation with better product ID handling
    function validateSelection() {
        const selectedSize = document.querySelector('.size-btn.active');
        const selectedColor = document.querySelector('.color-option.active');
        const quantity = document.getElementById('quantity').value;
        
        // Kiểm tra đã chọn size chưa nếu có size để chọn
        const sizeOptions = document.getElementById('size-options');
        if (sizeOptions && sizeOptions.children.length > 0 && !selectedSize) {
            showToast('Vui lòng chọn kích thước', 'warning');
            return null;
        }
        
        // Kiểm tra đã chọn màu chưa nếu có màu để chọn
        const colorSelector = document.querySelector('.color-selector');
        if (colorSelector && colorSelector.children.length > 0 && !selectedColor) {
            showToast('Vui lòng chọn màu sắc', 'warning');
            return null;
        }
        
        // Kiểm tra số lượng hợp lệ
        if (!quantity || parseInt(quantity) < 1) {
            showToast('Vui lòng chọn số lượng hợp lệ', 'warning');
            return null;
        }
        
        // Try multiple ways to get the product ID
        // 1. First try to get from hidden input
        let productId = parseInt(document.getElementById('current-product-id')?.value, 10);
        
        // 2. If that fails, try to get from URL
        if (!productId || isNaN(productId) || productId <= 0) {
            console.log("Invalid product ID from hidden field, trying URL");
            productId = getProductIdFromUrl();
        }
        
        console.log("Final product ID to be sent:", productId);
        
        if (!productId || productId <= 0) {
            console.error("Could not determine valid product ID");
            showToast('ID sản phẩm không hợp lệ', 'danger');
            return null;
        }
        
        // Create properly formatted JSON data
        return {
            productId: productId,
            quantity: parseInt(quantity),
            sizeId: selectedSize ? parseInt(selectedSize.dataset.sizeId) : null,
            colorId: selectedColor ? parseInt(selectedColor.dataset.colorId) : null
        };
    }

    // Add a JavaScript function to get product ID from URL as fallback
    function getProductIdFromUrl() {
        const urlParams = new URLSearchParams(window.location.search);
        const idFromUrl = parseInt(urlParams.get('id'), 10);
        return !isNaN(idFromUrl) && idFromUrl > 0 ? idFromUrl : null;
    }
    
    const addToCartBtn = document.getElementById('addToCartBtn');
    if (addToCartBtn) {
        addToCartBtn.addEventListener('click', function() {
            console.log('Add to cart button clicked');
            
            // Enhanced debugging
            const urlId = getProductIdFromUrl();
            console.log('Product ID from URL:', urlId);
            
            const productIdEl = document.getElementById('current-product-id');
            console.log('Product ID element:', productIdEl);
            console.log('Product ID value from element:', productIdEl ? productIdEl.value : 'not found');
            
            const selection = validateSelection();
            if (!selection) {
                return; // Hàm validateSelection đã hiển thị thông báo lỗi
            }
            
            console.log('Sending data to server:', selection); // Debug log
            
            // Hiển thị spinner hoặc thông báo đang xử lý
            addToCartBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Đang xử lý...';
            addToCartBtn.disabled = true;
            
            // Gửi dữ liệu tới server
            fetch('ajax/them_vao_gio.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(selection)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                console.log('Response:', data);
                
                // Khôi phục nút
                addToCartBtn.innerHTML = '<i class="bi bi-cart-plus"></i> Thêm vào giỏ';
                addToCartBtn.disabled = false;
                
                if (data.success) {
                    // Cập nhật số lượng trong giỏ hàng hiển thị trên header
                    updateCartCountDisplay(data.cartCount);
                    // Hiển thị thông báo thành công
                    showToast(data.message, 'success');
                } else {
                    // Hiển thị thông báo lỗi
                    showToast(data.message || 'Có lỗi xảy ra khi thêm vào giỏ hàng', 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                addToCartBtn.innerHTML = '<i class="bi bi-cart-plus"></i> Thêm vào giỏ';
                addToCartBtn.disabled = false;
                showToast('Lỗi kết nối đến máy chủ', 'danger');
            });
        });
    }
    
    // Thêm xử lý cho các nút "Thêm vào giỏ" của sản phẩm liên quan
    document.querySelectorAll('.add-to-cart-btn').forEach(button => {
        // Bỏ qua nút chính vì đã xử lý bên trên
        if (button.id === 'addToCartBtn') return;
        
        button.addEventListener('click', function() {
            // Lấy ID sản phẩm từ data attribute
            const productId = this.getAttribute('data-product-id');
            if (!productId) {
                showToast('Không tìm thấy thông tin sản phẩm', 'danger');
                return;
            }
            
            console.log('Adding related product to cart:', productId);
            
            // Hiển thị spinner
            const originalText = this.innerHTML;
            this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Đang xử lý...';
            this.disabled = true;
            
            // Gửi request thêm vào giỏ hàng
            fetch('ajax/them_vao_gio.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    productId: parseInt(productId),
                    quantity: 1,
                    sizeId: null,
                    colorId: null
                })
            })
            .then(response => response.json())
            .then(data => {
                // Khôi phục nút
                this.innerHTML = originalText;
                this.disabled = false;
                
                if (data.success) {
                    // Cập nhật số lượng trong giỏ hàng
                    updateCartCountDisplay(data.cartCount);
                    showToast('Đã thêm sản phẩm vào giỏ hàng!', 'success');
                } else {
                    showToast(data.message || 'Có lỗi xảy ra khi thêm vào giỏ hàng', 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                this.innerHTML = originalText;
                this.disabled = false;
                showToast('Lỗi kết nối đến máy chủ', 'danger');
            });
        });
    });
    
    // Hàm tiện ích để cập nhật số lượng giỏ hàng trên giao diện
    function updateCartCountDisplay(count) {
        const cartCountElement = document.getElementById('cartCount');
        if (cartCountElement && count !== undefined) {
            cartCountElement.textContent = count;
            // Hiệu ứng nhấp nháy
            cartCountElement.classList.add('cart-update-animation');
            setTimeout(() => {
                cartCountElement.classList.remove('cart-update-animation');
            }, 1000);
        }
    }

    // Toast notification function
    function showToast(message, type = 'info') {
        // Kiểm tra nếu chưa có container
        let toastContainer = document.getElementById('toastContainer');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'toastContainer';
            toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            toastContainer.style.zIndex = '1050';
            document.body.appendChild(toastContainer);
        }
        
        const toastId = 'toast-' + Date.now();
        const toastHTML = `
            <div class="toast" role="alert" aria-live="assertive" aria-atomic="true" id="${toastId}">
                <div class="toast-header">
                    <strong class="me-auto">Thông báo</strong>
                    <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body ${type === 'success' ? 'bg-success' : type === 'danger' ? 'bg-danger' : type === 'warning' ? 'bg-warning' : 'bg-info'} text-white">
                    ${message}
                </div>
            </div>
        `;
        
        toastContainer.insertAdjacentHTML('beforeend', toastHTML);
        const toastElement = document.getElementById(toastId);
        const toast = new bootstrap.Toast(toastElement, { autohide: true, delay: 3000 });
        toast.show();
    }
});
