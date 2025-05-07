document.addEventListener('DOMContentLoaded', function() {
    // Hiệu ứng cho nút thêm vào giỏ hàng
    const addToCartButtons = document.querySelectorAll('.add-to-cart');
    
    addToCartButtons.forEach(button => {
        button.addEventListener('mouseenter', function() {
            this.innerHTML = '<i class="bi bi-cart-check me-2"></i> Thêm ngay';
        });
        
        button.addEventListener('mouseleave', function() {
            this.innerHTML = '<i class="bi bi-cart-plus me-2"></i> Thêm vào giỏ';
        });
        
        button.addEventListener('click', function() {
            const productId = this.getAttribute('data-product-id');
            addToCart(productId, 1);
        });
    });
    
    // Hiệu ứng cho nút yêu thích
    const wishlistButtons = document.querySelectorAll('.product-action button:first-child');
    
    wishlistButtons.forEach(button => {
        button.addEventListener('click', function() {
            const icon = this.querySelector('i');
            if (icon.classList.contains('bi-heart')) {
                icon.classList.remove('bi-heart');
                icon.classList.add('bi-heart-fill');
                this.classList.add('text-danger');
                
                // Hiệu ứng tim bay lên
                const heart = document.createElement('div');
                heart.classList.add('floating-heart');
                this.appendChild(heart);
                
                setTimeout(() => {
                    heart.remove();
                }, 1000);
            } else {
                icon.classList.remove('bi-heart-fill');
                icon.classList.remove('text-danger');
                icon.classList.add('bi-heart');
                this.classList.remove('text-danger');
            }
        });
    });
    
    // Xử lý nút sao chép mã giảm giá
    const copyButtons = document.querySelectorAll('.copy-code');
    copyButtons.forEach(button => {
        button.addEventListener('click', function() {
            const code = this.getAttribute('data-code');
            navigator.clipboard.writeText(code).then(() => {
                const originalText = this.textContent;
                this.textContent = 'Đã sao chép';
                this.classList.add('btn-success');
                this.classList.remove('btn-outline-primary');
                
                setTimeout(() => {
                    this.textContent = originalText;
                    this.classList.remove('btn-success');
                    this.classList.add('btn-outline-primary');
                }, 2000);
            });
        });
    });
    
    // Xử lý đếm ngược
    function updateCountdown() {
        const now = new Date();
        // Đặt thời gian kết thúc (ví dụ: ngày cuối tháng)
        const endOfMonth = new Date();
        endOfMonth.setMonth(endOfMonth.getMonth() + 1);
        endOfMonth.setDate(0);
        endOfMonth.setHours(23, 59, 59, 999);
        
        const diff = endOfMonth - now;
        
        if (diff <= 0) {
            document.getElementById('days').textContent = '00';
            document.getElementById('hours').textContent = '00';
            document.getElementById('minutes').textContent = '00';
            document.getElementById('seconds').textContent = '00';
            return;
        }
        
        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
        const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((diff % (1000 * 60)) / 1000);
        
        document.getElementById('days').textContent = days.toString().padStart(2, '0');
        document.getElementById('hours').textContent = hours.toString().padStart(2, '0');
        document.getElementById('minutes').textContent = minutes.toString().padStart(2, '0');
        document.getElementById('seconds').textContent = seconds.toString().padStart(2, '0');
    }
    
    // Cập nhật đếm ngược mỗi giây
    updateCountdown();
    setInterval(updateCountdown, 1000);
    
    // Hàm thêm vào giỏ hàng
    function addToCart(productId, quantity) {
        fetch('add_to_cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `product_id=${productId}&quantity=${quantity}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Hiển thị thông báo thành công
                const toast = document.createElement('div');
                toast.className = 'toast-notification success show';
                toast.innerHTML = `
                    <div class="toast-icon"><i class="bi bi-check-circle-fill"></i></div>
                    <div class="toast-message">Sản phẩm đã được thêm vào giỏ hàng!</div>
                `;
                document.body.appendChild(toast);
                
                // Cập nhật số lượng trên icon giỏ hàng
                if (document.querySelector('.cart-count')) {
                    document.querySelector('.cart-count').textContent = data.cart_count;
                }
                
                // Tự động xóa thông báo sau 3 giây
                setTimeout(() => {
                    toast.classList.remove('show');
                    setTimeout(() => {
                        toast.remove();
                    }, 300);
                }, 3000);
            } else {
                alert(data.message || 'Có lỗi xảy ra khi thêm sản phẩm vào giỏ hàng.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Đã xảy ra lỗi khi thêm sản phẩm vào giỏ hàng.');
        });
    }
});
