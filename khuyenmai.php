<?php
session_start();

// Kiểm tra đăng nhập
$user_logged_in = false;
$username = '';

if (isset($_SESSION['user_username'])) {
    $user_logged_in = true;
    $username = $_SESSION['user_username'];
}

if (isset($_SESSION['user']['logged_in']) && $_SESSION['user']['logged_in'] === true) {
    $user_logged_in = true;
    $username = $_SESSION['user']['username'];
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Khuyến Mãi - Bug Shop</title>
    <link rel="stylesheet" href="node_modules\bootstrap\dist\css\bootstrap.css">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/index.css">
</head>

<body>
    <?php require_once('includes/header.php'); ?>
    
    <main>
        <!-- Banner Khuyến Mãi -->
        <section class="promotion-banner">
            <div class="container-fluid p-0">
                <div class="position-relative">
                    <img src="images/banner/promotion_banner.jpg" class="w-100" alt="Khuyến Mãi" onerror="this.src='images/banner/banner_giam_gia.webp';">
                    <div class="position-absolute top-50 start-50 translate-middle text-center text-white">
                        <h1 class="display-4 fw-bold">KHUYẾN MÃI HOT</h1>
                        <p class="fs-5">Săn ngay những sản phẩm giảm giá hấp dẫn!</p>
                    </div>
                </div>
            </div>
        </section>
        
        <!-- Sản phẩm khuyến mãi -->
        <section class="promotion-products py-5 bg-light">
            <div class="container">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="section-title position-relative">
                        <span class="bg-light pe-3">Sản Phẩm Khuyến Mãi</span>
                        <div class="title-line"></div>
                    </h2>
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="sortDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            Sắp xếp theo
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="sortDropdown">
                            <li><a class="dropdown-item" href="?sort=discount_desc">% Giảm giá cao nhất</a></li>
                            <li><a class="dropdown-item" href="?sort=price_asc">Giá tăng dần</a></li>
                            <li><a class="dropdown-item" href="?sort=price_desc">Giá giảm dần</a></li>
                            <li><a class="dropdown-item" href="?sort=new">Mới nhất</a></li>
                        </ul>
                    </div>
                </div>
                
                <div class="row g-4" id="promotion-products">
                    <?php
                    // Kết nối database
                    $servername = "localhost";
                    $username = "root";
                    $password = "";
                    $dbname = "shop_vippro";
                    
                    $conn = new mysqli($servername, $username, $password, $dbname);
                    
                    if ($conn->connect_error) {
                        die("Kết nối thất bại: " . $conn->connect_error);
                    }
                    
                    $conn->set_charset("utf8mb4");
                    
                    // Xử lý sắp xếp
                    $sort = isset($_GET['sort']) ? $_GET['sort'] : 'discount_desc';
                    
                    // Thiết lập ORDER BY dựa trên tham số sắp xếp
                    $order_by = "";
                    switch($sort) {
                        case 'price_asc':
                            $order_by = "ORDER BY s.gia ASC";
                            break;
                        case 'price_desc':
                            $order_by = "ORDER BY s.gia DESC";
                            break;
                        case 'new':
                            $order_by = "ORDER BY s.ngaytao DESC";
                            break;
                        case 'discount_desc':
                        default:
                            $order_by = "ORDER BY discount_percent DESC";
                            break;
                    }
                    
                    // Truy vấn sản phẩm khuyến mãi (sản phẩm có giagoc > gia)
                    $sql = "SELECT s.*, l.tenloai, AVG(dg.diemdanhgia) as diem_trung_binh,
                            (1 - s.gia/s.giagoc) * 100 as discount_percent
                            FROM sanpham s 
                            LEFT JOIN danhgia dg ON s.id_sanpham = dg.id_sanpham 
                            LEFT JOIN loaisanpham l ON s.id_loai = l.id_loai
                            WHERE s.trangthai = 1 AND s.giagoc > s.gia AND s.giagoc > 0
                            GROUP BY s.id_sanpham 
                            $order_by";
                    
                    $result = $conn->query($sql);
                    
                    // Kiểm tra và hiển thị sản phẩm
                    if ($result && $result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            // Tính phần trăm giảm giá
                            $discount_percent = round(100 - ($row['gia'] / $row['giagoc'] * 100));
                            
                            // Xử lý đường dẫn hình ảnh
                            if (!empty($row['hinhanh']) && file_exists('uploads/products/' . $row['hinhanh'])) {
                                $img_path = 'uploads/products/' . $row['hinhanh'];
                            } else {
                                // Kiểm tra hình ảnh từ bảng mausac_hinhanh
                                $stmt = $conn->prepare("SELECT hinhanh FROM mausac_hinhanh WHERE id_sanpham = ? LIMIT 1");
                                $stmt->bind_param("i", $row['id_sanpham']);
                                $stmt->execute();
                                $result_img = $stmt->get_result();
                                
                                if ($result_img->num_rows > 0) {
                                    $img_row = $result_img->fetch_assoc();
                                    if (file_exists('uploads/colors/' . $img_row['hinhanh'])) {
                                        $img_path = 'uploads/colors/' . $img_row['hinhanh'];
                                    } else {
                                        $img_path = 'images/no-image.jpg';
                                    }
                                } else {
                                    $img_path = 'images/no-image.jpg';
                                }
                            }
                            
                            // Xử lý điểm đánh giá
                            $rating = round($row['diem_trung_binh']);
                            if (is_null($rating)) $rating = 0;
                    ?>
                            <div class="col-6 col-md-3 product-item">
                                <div class="card product-card h-100">
                                    <div class="product-badge-container">
                                        <div class="product-badge bg-danger text-white">
                                            <i class="bi bi-tags-fill me-1"></i>-<?php echo $discount_percent; ?>%
                                        </div>
                                        <?php if ($row['noibat'] == 1): ?>
                                        <div class="product-badge bg-primary text-white">
                                            <i class="bi bi-star-fill me-1"></i>HOT
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <a href="product-detail.php?id=<?php echo $row['id_sanpham']; ?>" class="product-img-container">
                                        <img src="<?php echo $img_path; ?>" class="card-img-top product-img" alt="<?php echo htmlspecialchars($row['tensanpham']); ?>" 
                                             onerror="this.onerror=null; this.src='images/no-image.jpg';">
                                        <div class="overlay-effect"></div>
                                    </a>
                                    <div class="sale-ribbon">
                                        <span>SALE</span>
                                    </div>
                                    <div class="product-action">
                                        <button class="btn btn-light btn-sm rounded-circle" title="Yêu thích">
                                            <i class="bi bi-heart"></i>
                                        </button>
                                        <button class="btn btn-light btn-sm rounded-circle quick-view" data-id="<?php echo $row['id_sanpham']; ?>" title="Xem nhanh">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button class="btn btn-light btn-sm rounded-circle add-to-cart" data-product-id="<?php echo $row['id_sanpham']; ?>" title="Thêm vào giỏ hàng">
                                            <i class="bi bi-cart-plus"></i>
                                        </button>
                                    </div>
                                    <div class="card-body">
                                        <div class="product-category"><?php echo htmlspecialchars($row['tenloai'] ?? ''); ?></div>
                                        <h5 class="card-title product-title">
                                            <a href="product-detail.php?id=<?php echo $row['id_sanpham']; ?>" class="text-decoration-none text-dark">
                                                <?php echo htmlspecialchars($row['tensanpham']); ?>
                                            </a>
                                        </h5>
                                        <div class="rating">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="bi bi-star<?php echo ($i <= $rating) ? '-fill' : ''; ?> text-warning"></i>
                                            <?php endfor; ?>
                                            <span class="ms-1 text-muted small">(<?php echo $row['soluong_danhgia'] ?? 0; ?>)</span>
                                        </div>
                                        <div class="price-wrapper">
                                            <span class="text-danger fw-bold"><?php echo number_format($row['gia'], 0, ',', '.'); ?>₫</span>
                                            <small class="text-decoration-line-through text-muted ms-2"><?php echo number_format($row['giagoc'], 0, ',', '.'); ?>₫</small>
                                        </div>
                                    </div>
                                    <div class="card-footer">
                                        <button class="btn btn-primary w-100 add-to-cart" data-product-id="<?php echo $row['id_sanpham']; ?>">
                                            <i class="bi bi-cart-plus me-2"></i> Thêm vào giỏ
                                        </button>
                                    </div>
                                </div>
                            </div>
                    <?php
                        }
                    } else {
                        echo '<div class="col-12 text-center py-5">
                                <div class="empty-state">
                                    <i class="bi bi-tag-fill display-4 text-muted"></i>
                                    <h4 class="mt-3">Không có sản phẩm khuyến mãi</h4>
                                    <p class="text-muted">Hiện chưa có sản phẩm nào đang giảm giá.</p>
                                </div>
                              </div>';
                    }
                    
                    $conn->close();
                    ?>
                </div>
                
                <!-- Phân trang (nếu cần) -->
                <nav class="mt-5">
                    <ul class="pagination justify-content-center">
                        <li class="page-item disabled">
                            <a class="page-link" href="#" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        <li class="page-item active"><a class="page-link" href="#">1</a></li>
                        <li class="page-item"><a class="page-link" href="#">2</a></li>
                        <li class="page-item"><a class="page-link" href="#">3</a></li>
                        <li class="page-item">
                            <a class="page-link" href="#" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        </section>

        <!-- Countdown khuyến mãi -->
        <section class="countdown-section py-5">
            <div class="container">
                <div class="row">
                    <div class="col-lg-6">
                        <div class="flash-sale-card p-4 text-center text-lg-start">
                            <h3 class="mb-3">Flash Sale Sắp Kết Thúc!</h3>
                            <p>Nhanh tay mua ngay kẻo lỡ! Giảm giá đặc biệt lên đến 50%.</p>
                            
                            <div class="countdown d-flex justify-content-center justify-content-lg-start" id="countdown">
                                <div class="countdown-item">
                                    <span id="days">00</span>
                                    <span class="countdown-label">Ngày</span>
                                </div>
                                <div class="countdown-item">
                                    <span id="hours">00</span>
                                    <span class="countdown-label">Giờ</span>
                                </div>
                                <div class="countdown-item">
                                    <span id="minutes">00</span>
                                    <span class="countdown-label">Phút</span>
                                </div>
                                <div class="countdown-item">
                                    <span id="seconds">00</span>
                                    <span class="countdown-label">Giây</span>
                                </div>
                            </div>
                            
                            <a href="#promotion-products" class="btn btn-danger mt-3">Mua Ngay</a>
                        </div>
                    </div>
                    <div class="col-lg-6 mt-4 mt-lg-0">
                        <div class="promo-code-card p-4">
                            <h3 class="mb-3">Mã Giảm Giá</h3>
                            <p>Sử dụng mã giảm giá sau để nhận thêm ưu đãi:</p>
                            
                            <div class="coupon-container mb-3">
                                <div class="coupon d-flex align-items-center justify-content-between">
                                    <div>
                                        <span class="coupon-code">SUMMER2025</span>
                                        <small class="d-block">Giảm 10% cho đơn hàng trên 500K</small>
                                    </div>
                                    <button class="btn btn-sm btn-outline-primary copy-code" data-code="SUMMER2025">Sao chép</button>
                                </div>
                            </div>
                            
                            <div class="coupon-container">
                                <div class="coupon d-flex align-items-center justify-content-between">
                                    <div>
                                        <span class="coupon-code">NEWUSER</span>
                                        <small class="d-block">Giảm 50K cho khách hàng mới</small>
                                    </div>
                                    <button class="btn btn-sm btn-outline-primary copy-code" data-code="NEWUSER">Sao chép</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>
    
    <?php include('includes/footer.php'); ?>
    
    <script>
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
    </script>
    
    <style>
    /* Banner Khuyến Mãi */
    .promotion-banner {
        margin-bottom: 30px;
    }
    
    .promotion-banner img {
        max-height: 400px;
        object-fit: cover;
    }
    
    /* Sale Ribbon */
    .sale-ribbon {
        position: absolute;
        top: 0;
        right: 0;
        width: 100px;
        height: 100px;
        overflow: hidden;
        z-index: 5;
    }
    
    .sale-ribbon span {
        position: absolute;
        display: block;
        width: 160px;
        padding: 8px 0;
        background-color: #ff3e3e;
        box-shadow: 0 5px 10px rgba(0, 0, 0, 0.1);
        color: #fff;
        font-size: 14px;
        font-weight: 700;
        text-shadow: 0 1px 1px rgba(0, 0, 0, 0.2);
        text-transform: uppercase;
        text-align: center;
        transform: rotate(45deg);
        top: 20px;
        right: -40px;
    }
    
    /* Flash Sale Section */
    .countdown-section {
        background-color: #f8f9fa;
    }
    
    .flash-sale-card {
        background: linear-gradient(45deg, #ff416c, #ff4b2b);
        color: white;
        border-radius: 10px;
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    }
    
    .promo-code-card {
        background-color: white;
        border-radius: 10px;
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
    }
    
    .countdown {
        gap: 15px;
        margin: 20px 0;
    }
    
    .countdown-item {
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        width: 60px;
        height: 60px;
        background-color: rgba(255, 255, 255, 0.2);
        border-radius: 8px;
    }
    
    .countdown-item span:first-child {
        font-size: 22px;
        font-weight: bold;
    }
    
    .countdown-label {
        font-size: 12px;
    }
    
    /* Coupon Styles */
    .coupon-container {
        margin-bottom: 15px;
    }
    
    .coupon {
        padding: 12px;
        background-color: #f8f9fa;
        border: 1px dashed #dee2e6;
        border-radius: 6px;
    }
    
    .coupon-code {
        font-weight: bold;
        font-size: 16px;
        letter-spacing: 1px;
        color: #0d6efd;
    }
    
    /* Hiệu ứng tim bay lên khi thích sản phẩm */
    @keyframes float-up {
        0% { opacity: 1; transform: translate(-50%, -50%) scale(1); }
        100% { opacity: 0; transform: translate(-50%, -200%) scale(1.5); }
    }
    
    .floating-heart {
        position: absolute;
        top: 50%;
        left: 50%;
        width: 20px;
        height: 20px;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%23dc3545' class='bi bi-heart-fill' viewBox='0 0 16 16'%3E%3Cpath fill-rule='evenodd' d='M8 1.314C12.438-3.248 23.534 4.735 8 15-7.534 4.736 3.562-3.248 8 1.314'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: center;
        background-size: contain;
        z-index: 100;
        animation: float-up 1s forwards;
        pointer-events: none;
    }
    
    /* Toast Notification */
    .toast-notification {
        position: fixed;
        bottom: 20px;
        right: 20px;
        min-width: 300px;
        background-color: white;
        box-shadow: 0 5px 15px rgba(0,0,0,0.15);
        border-radius: 4px;
        padding: 12px 15px;
        display: flex;
        align-items: center;
        z-index: 9999;
        transform: translateY(100px);
        opacity: 0;
        transition: all 0.3s ease;
    }
    
    .toast-notification.show {
        transform: translateY(0);
        opacity: 1;
    }
    
    .toast-notification.success {
        border-left: 4px solid #28a745;
    }
    
    .toast-icon {
        margin-right: 12px;
        font-size: 20px;
    }
    
    .toast-notification.success .toast-icon {
        color: #28a745;
    }
    
    .toast-message {
        flex: 1;
        font-size: 14px;
    }
    
    /* Responsive Adjustments */
    @media (max-width: 767.98px) {
        .countdown-item {
            width: 50px;
            height: 50px;
        }
        
        .countdown-item span:first-child {
            font-size: 18px;
        }
        
        .countdown-label {
            font-size: 10px;
        }
    }
    </style>
</body>
</html>