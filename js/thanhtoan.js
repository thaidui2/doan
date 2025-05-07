document.addEventListener('DOMContentLoaded', function() {
    console.log("Trang thanh toán đã tải xong");
    
    // Kiểm tra phương thức thanh toán cho người dùng chưa đăng nhập
    const isLoggedIn = document.body.dataset.userLoggedIn === 'true';
    console.log("Login status:", isLoggedIn); // Debug login status
    const codRadio = document.getElementById('cod');
    
    // Ngăn chặn người dùng chưa đăng nhập chọn COD
    if (!isLoggedIn && codRadio) {
        codRadio.disabled = true;
        codRadio.parentElement.classList.add('disabled');
        const label = document.querySelector('label[for="cod"]');
        if (label) {
            label.title = "Vui lòng đăng nhập để sử dụng COD";
        }
    }
    
    // Kiểm tra khi submit form
    document.getElementById('checkout-form').addEventListener('submit', function(e) {
        const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
        if (!isLoggedIn && paymentMethod && paymentMethod.value === 'cod') {
            e.preventDefault();
            alert('Bạn cần đăng nhập để sử dụng phương thức thanh toán COD');
        }
    });

    // Xử lý khi submit form
    const form = document.getElementById('checkout-form');
    form.addEventListener('submit', function(e) {
        // Kiểm tra các trường bắt buộc
        let isValid = true;
        const requiredFields = form.querySelectorAll('[required]');
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                field.classList.add('is-invalid');
            } else {
                field.classList.remove('is-invalid');
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            alert('Vui lòng điền đầy đủ các trường bắt buộc');
            return;
        }
        
        // Thêm các trường hidden chứa thông tin địa chỉ
        const addressData = window.getSelectedAddressData();
        
        if (addressData.provinceName) {
            const provinceNameInput = document.createElement('input');
            provinceNameInput.type = 'hidden';
            provinceNameInput.name = 'province_name';
            provinceNameInput.value = addressData.provinceName;
            this.appendChild(provinceNameInput);
        }
        
        if (addressData.districtName) {
            const districtNameInput = document.createElement('input');
            districtNameInput.type = 'hidden';
            districtNameInput.name = 'district_name';
            districtNameInput.value = addressData.districtName;
            this.appendChild(districtNameInput);
        }
        
        if (addressData.wardName) {
            const wardNameInput = document.createElement('input');
            wardNameInput.type = 'hidden';
            wardNameInput.name = 'ward_name';
            wardNameInput.value = addressData.wardName;
            this.appendChild(wardNameInput);
        }
        
        // Thêm trường hidden chứa địa chỉ đầy đủ
        const fullAddressInput = document.createElement('input');
        fullAddressInput.type = 'hidden';
        fullAddressInput.name = 'full_address';
        fullAddressInput.value = addressData.fullAddress;
        this.appendChild(fullAddressInput);
    });

    // Validation cho số điện thoại
    const phoneInput = document.getElementById('phone');
    if (phoneInput) {
        phoneInput.addEventListener('input', function() {
            // Chỉ cho phép nhập số
            this.value = this.value.replace(/[^0-9]/g, '');
            
            // Giới hạn độ dài
            if (this.value.length > 10) {
                this.value = this.value.slice(0, 10);
            }
        });
    }
    
    // Hiển thị phương thức thanh toán đã chọn
    const paymentMethods = document.querySelectorAll('.payment-method-radio');
    paymentMethods.forEach(method => {
        method.addEventListener('change', function() {
            const selectedMethod = document.querySelector('.payment-method-selected');
            if (selectedMethod) {
                selectedMethod.classList.remove('payment-method-selected');
            }
            
            if (this.checked) {
                this.parentNode.querySelector('.payment-method-label').classList.add('payment-method-selected');
            }
        });
    });
    
    // Kích hoạt phương thức thanh toán mặc định
    const defaultPaymentMethod = document.getElementById('cod');
    if (defaultPaymentMethod) {
        defaultPaymentMethod.checked = true;
        defaultPaymentMethod.parentNode.querySelector('.payment-method-label').classList.add('payment-method-selected');
    }

    // Thay đổi từ
    const vnpayPayment = document.getElementById('vnpay');

    // Xử lý mã giảm giá
    const promoCodeInput = document.getElementById('promo-code');
    const applyPromoButton = document.getElementById('apply-promo');
    const promoMessageElement = document.getElementById('promo-message');
    const discountRow = document.getElementById('discount-row');
    const discountAmountElement = document.getElementById('discount-amount');
    const grandTotalElement = document.getElementById('grand-total');
    const promoCodeInputHidden = document.getElementById('promo-code-input');
    const discountAmountInputHidden = document.getElementById('discount-amount-input');
    const discountIdInputHidden = document.getElementById('discount-id-input');

    // Lấy thông tin tổng tiền từ dữ liệu được nhúng vào trang
    const subtotal = parseFloat(document.getElementById('subtotal-value').value || 0);
    // Phí vận chuyển
    const shippingFee = parseFloat(document.getElementById('shipping-fee-value').value || 30000);
    // Tổng tiền hiện tại (chưa có giảm giá)
    let currentTotal = subtotal + shippingFee;
    // Số tiền giảm giá
    let discountAmount = 0;

    applyPromoButton.addEventListener('click', function() {
        const code = promoCodeInput.value.trim();
        
        if (!code) {
            promoMessageElement.innerHTML = '<span class="text-danger">Vui lòng nhập mã giảm giá</span>';
            return;
        }
        
        // Hiển thị thông báo đang xử lý
        promoMessageElement.innerHTML = '<span class="text-muted"><i class="bi bi-hourglass-split me-2"></i>Đang kiểm tra...</span>';
        
        // Lấy thông tin sản phẩm trong giỏ hàng từ dữ liệu được nhúng vào trang
        const cartItems = JSON.parse(document.getElementById('checkout-items-data').value || '[]');
        
        // Gửi request kiểm tra mã giảm giá
        fetch('apply_promo_code.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                code: code,
                total: subtotal,
                cart_items: JSON.stringify(cartItems)
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Cập nhật UI khi thành công
                promoMessageElement.innerHTML = `<span class="text-success"><i class="bi bi-check-circle me-1"></i>${data.message}</span>`;
                
                // Hiển thị dòng giảm giá
                discountRow.style.display = 'flex';
                discountAmount = data.discount_amount;
                
                // Cập nhật số tiền giảm giá và tổng tiền
                discountAmountElement.textContent = `-${data.formatted_discount}₫`;
                grandTotalElement.textContent = `${data.formatted_total}₫`;
                
                // Cập nhật các input hidden
                promoCodeInputHidden.value = code;
                discountAmountInputHidden.value = discountAmount;
                discountIdInputHidden.value = data.discount_id;
                
                // Vô hiệu hóa input và nút áp dụng
                promoCodeInput.disabled = true;
                applyPromoButton.disabled = true;
                
                // Thêm nút hủy mã giảm giá
                const cancelButton = document.createElement('button');
                cancelButton.className = 'btn btn-sm btn-outline-danger ms-2';
                cancelButton.innerHTML = '<i class="bi bi-x-circle"></i> Hủy mã';
                cancelButton.onclick = function(e) {
                    e.preventDefault();
                    // Reset lại tất cả
                    discountRow.style.display = 'none';
                    grandTotalElement.textContent = `${new Intl.NumberFormat('vi-VN').format(currentTotal)}₫`;
                    promoMessageElement.innerHTML = '';
                    promoCodeInput.value = '';
                    promoCodeInput.disabled = false;
                    applyPromoButton.disabled = false;
                    
                    // Reset các input hidden
                    promoCodeInputHidden.value = '';
                    discountAmountInputHidden.value = '0';
                    discountIdInputHidden.value = '';
                    
                    // Xóa nút hủy
                    this.remove();
                };
                promoMessageElement.appendChild(cancelButton);
            } else {
                // Hiển thị thông báo lỗi
                promoMessageElement.innerHTML = `<span class="text-danger"><i class="bi bi-exclamation-circle me-1"></i>${data.message}</span>`;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            promoMessageElement.innerHTML = '<span class="text-danger">Có lỗi xảy ra khi kiểm tra mã giảm giá</span>';
        });
    });
});
