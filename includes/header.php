<?php
// Không echo hay html ở đây, chỉ code PHP thuần
// Kết nối CSDL, khởi tạo phiên làm việc, v.v.
if (!isset($conn)) {
    require_once(__DIR__ . '/../config/config.php');
}

// Thay vào đó, kiểm tra xem session đã được khởi động chưa
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Các chức năng khởi tạo khác

// Lấy số lượng sản phẩm trong giỏ hàng
function getCartItemCount($conn) {
    $session_id = session_id();
    $user_id = isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : null;
    
    if ($user_id) {
        $stmt = $conn->prepare("
            SELECT SUM(gct.soluong) as total
            FROM giohang g
            JOIN giohang_chitiet gct ON g.id_giohang = gct.id_giohang
            WHERE g.id_nguoidung = ?
        ");
        $stmt->bind_param("i", $user_id);
    } else {
        $stmt = $conn->prepare("
            SELECT SUM(gct.soluong) as total
            FROM giohang g
            JOIN giohang_chitiet gct ON g.id_giohang = gct.id_giohang
            WHERE g.session_id = ? AND g.id_nguoidung IS NULL
        ");
        $stmt->bind_param("s", $session_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    return $result['total'] ?? 0;
}

// Lấy các sản phẩm trong giỏ hàng để hiển thị dropdown
function getCartPreviewItems($conn) {
    $session_id = session_id();
    $user_id = isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : null;
    
    if ($user_id) {
        $stmt = $conn->prepare("
            SELECT gct.*, sp.tensanpham, sp.hinhanh, sp.trangthai, g.id_giohang
            FROM giohang g
            JOIN giohang_chitiet gct ON g.id_giohang = gct.id_giohang
            JOIN sanpham sp ON gct.id_sanpham = sp.id_sanpham
            WHERE g.id_nguoidung = ?
            ORDER BY gct.ngay_them DESC
            LIMIT 5
        ");
        $stmt->bind_param("i", $user_id);
    } else {
        $stmt = $conn->prepare("
            SELECT gct.*, sp.tensanpham, sp.hinhanh, sp.trangthai, g.id_giohang
            FROM giohang g
            JOIN giohang_chitiet gct ON g.id_giohang = gct.id_giohang
            JOIN sanpham sp ON gct.id_sanpham = sp.id_sanpham
            WHERE g.session_id = ? AND g.id_nguoidung IS NULL
            ORDER BY gct.ngay_them DESC
            LIMIT 5
        ");
        $stmt->bind_param("s", $session_id);
    }
    
    $stmt->execute();
    return $stmt->get_result();
}

// Lấy danh mục sản phẩm cho menu
function getCategories($conn) {
    $stmt = $conn->prepare("
        SELECT id_loai, tenloai, hinhanh 
        FROM loaisanpham 
        WHERE trangthai = 1 
        ORDER BY tenloai
    ");
    $stmt->execute();
    return $stmt->get_result();
}

// Calculate cart count
$cart_count = getCartItemCount($conn);
$categories = getCategories($conn);

if (!$categories || $categories->num_rows == 0) {
    error_log("Không tìm thấy danh mục nào hoặc lỗi kết nối cơ sở dữ liệu");
}

// Lấy thông tin giỏ hàng - lấy cart_id trước khi duyệt items
$cart_id = 0;
$cart_total = 0;

// Lấy giỏ hàng hiện tại của người dùng
$session_id = session_id();
$user_id = isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : null;

if ($user_id) {
    $cart_stmt = $conn->prepare("SELECT id_giohang, tong_tien FROM giohang WHERE id_nguoidung = ?");
    $cart_stmt->bind_param("i", $user_id);
} else {
    $cart_stmt = $conn->prepare("SELECT id_giohang, tong_tien FROM giohang WHERE session_id = ? AND id_nguoidung IS NULL");
    $cart_stmt->bind_param("s", $session_id);
}

$cart_stmt->execute();
$cart_result = $cart_stmt->get_result();

if ($cart_result->num_rows > 0) {
    $cart_info = $cart_result->fetch_assoc();
    $cart_id = $cart_info['id_giohang'];
    $cart_total = $cart_info['tong_tien'];
}

// Sau khi lấy được cart_id, mới lấy các items
$cart_items = getCartPreviewItems($conn);

// Lấy trang hiện tại để đánh dấu menu active
$current_page = basename($_SERVER['PHP_SELF']);
?>

<header class="sticky-top">
    <!-- Thông báo khuyến mãi -->
    <div class="announcement-bar py-2 bg-dark text-white">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div class="small d-none d-md-block">
                    <i class="bi bi-telephone-fill"></i> Hotline: 0123.456.789 | 
                    <i class="bi bi-envelope-fill"></i> Email: contact@bugshop.vn
                </div>
                <div class="marquee-container">
                    <div class="marquee-text">
                        <span style="color: #0d6efd"><i class="bi bi-tags-fill"></i> GIẢM GIÁ 20% CHO ĐƠN HÀNG ĐẦU TIÊN | MIỄN PHÍ GIAO HÀNG CHO ĐƠN TỪ 500K</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Thanh điều hướng chính -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm py-3">
        <div class="container">
            <!-- Logo -->
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <img src="images/logo.png" alt="Bug Shop Logo" height="40" class="d-inline-block align-text-top me-2">
                <span class="fw-bold text-primary">BUG SHOP</span>
            </a>
            
            <!-- Nút toggle cho mobile -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <!-- Menu chính -->
            <div class="collapse navbar-collapse" id="navbarMain">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'index.php' ? 'active fw-bold' : ''; ?>" href="index.php">
                            <i class="bi bi-house-door"></i> Trang chủ
                        </a>
                    </li>
                    
                    <!-- Dropdown Sản phẩm -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo $current_page == 'sanpham.php' ? 'active fw-bold' : ''; ?>" 
                           href="sanpham.php" id="navbarDropdown" data-bs-toggle="dropdown">
                            <i class="bi bi-grid"></i> Sản phẩm
                        </a>
                        <div class="dropdown-menu dropdown-menu-start categories-menu" aria-labelledby="navbarDropdown">
                            <div class="row p-2">
                                <div class="col-12">
                                    <a class="dropdown-item fw-bold bg-light rounded mb-2 py-2" href="sanpham.php">
                                        <i class="bi bi-grid-3x3-gap"></i> Tất cả sản phẩm
                                    </a>
                                </div>
                                
                                <?php while($category = $categories->fetch_assoc()): ?>
                                <div class="col-6">
                                    <a class="dropdown-item category-item d-flex align-items-center" 
                                       href="sanpham.php?loai=<?php echo $category['id_loai']; ?>">
                                        <?php if(!empty($category['hinhanh'])): ?>
                                        <img src="uploads/categories/<?php echo $category['hinhanh']; ?>" 
                                             class="category-thumbnail me-2" alt="<?php echo $category['tenloai']; ?>">
                                        <?php else: ?>
                                        <i class="bi bi-tag me-2"></i>
                                        <?php endif; ?>
                                        <?php echo $category['tenloai']; ?>
                                    </a>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    </li>
                    
                    <!-- Các liên kết khác -->
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'khuyenmai.php' ? 'active fw-bold' : ''; ?>" href="khuyenmai.php">
                            <i class="bi bi-ticket-perforated"></i> Khuyến mãi
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'lienhe.php' ? 'active fw-bold' : ''; ?>" href="lienhe.php">
                            <i class="bi bi-chat-dots"></i> Liên hệ
                        </a>
                    </li>
                </ul>
                
                <!-- Thanh tìm kiếm -->
                <form class="search-form d-flex me-2 position-relative" action="sanpham.php" method="get">
                    <input class="form-control search-input" type="search" name="search" placeholder="Tìm sản phẩm..." aria-label="Tìm kiếm">
                    <button class="btn btn-search position-absolute" type="submit">
                        <i class="bi bi-search"></i>
                    </button>
                </form>
                
                <!-- Các nút chức năng -->
                <div class="d-flex align-items-center">
                    <!-- Dropdown giỏ hàng -->
                    <div class="dropdown cart-dropdown me-2">
                        <a class="nav-link position-relative" href="#" role="button" id="cartDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-cart3 fs-5"></i>
                            <?php if ($cart_count > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger cart-count">
                                <?php echo $cart_count; ?>
                            </span>
                            <?php else: ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger cart-count" style="display: none;">0</span>
                            <?php endif; ?>
                        </a>
                        
                        <div class="dropdown-menu dropdown-menu-end cart-dropdown p-0" aria-labelledby="cartDropdown" style="width: 320px;">
                            <div class="p-3 border-bottom">
                                <h6 class="mb-0">Giỏ hàng của bạn (<?php echo $cart_count; ?>)</h6>
                            </div>
                            
                            <div class="cart-items overflow-auto" style="max-height: 320px;">
                                <?php if ($cart_count > 0): ?>
                                    <?php while($item = $cart_items->fetch_assoc()): ?>
                                    <a href="product-detail.php?id=<?php echo $item['id_sanpham']; ?>" class="dropdown-item p-3 border-bottom cart-item">
                                        <div class="d-flex align-items-center">
                                            <img src="<?php echo !empty($item['hinhanh']) ? 'uploads/products/' . $item['hinhanh'] : 'images/no-image.png'; ?>" 
                                                 alt="<?php echo $item['tensanpham']; ?>" 
                                                 class="img-thumbnail" style="width: 50px; height: 50px; object-fit: cover;">
                                            <div class="ms-3 flex-grow-1">
                                                <h6 class="mb-0 text-truncate" style="max-width: 150px;"><?php echo $item['tensanpham']; ?></h6>
                                                <div class="small text-muted">
                                                    <?php echo number_format($item['gia'], 0, ',', '.'); ?>₫ × <?php echo $item['soluong']; ?>
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <div class="fw-bold text-success"><?php echo number_format($item['thanh_tien'], 0, ',', '.'); ?>₫</div>
                                            </div>
                                        </div>
                                    </a>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="p-4 text-center">
                                        <i class="bi bi-cart-x fs-1 text-muted"></i>
                                        <p class="mt-2 mb-0">Giỏ hàng trống</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="p-3 border-top bg-light">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="fw-bold">Tổng tiền:</span>
                                    <span class="fw-bold text-danger">
                                        <?php echo number_format($cart_total, 0, ',', '.'); ?>₫
                                    </span>
                                </div>
                                <div class="d-grid gap-2">
                                    <a href="giohang.php" class="btn btn-primary btn-sm">
                                        <i class="bi bi-cart"></i> Xem giỏ hàng
                                    </a>
                                    <?php if ($cart_count > 0): ?>
                                    <a href="thanhtoan.php" class="btn btn-success btn-sm">
                                        <i class="bi bi-credit-card"></i> Thanh toán
                                    </a>
                                    <?php else: ?>
                                    <button class="btn btn-success btn-sm" disabled>
                                        <i class="bi bi-credit-card"></i> Thanh toán
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Phần đăng nhập/tài khoản -->
                    <div class="user-menu-wrapper">
                        <?php if(isset($_SESSION['user']) && $_SESSION['user']['logged_in'] === true): ?>
                            <div class="dropdown">
                                <button class="btn btn-outline-primary dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown">
                                    <i class="bi bi-person-circle"></i> <span class="d-none d-sm-inline"><?php echo $_SESSION['user']['username']; ?></span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="userDropdown">
                                    <li>
                                        <h6 class="dropdown-header">Xin chào, <?php echo $_SESSION['user']['tenuser']; ?>!</h6>
                                    </li>
                                    <li><a class="dropdown-item" href="taikhoan.php"><i class="bi bi-person me-2"></i>Tài khoản của tôi</a></li>
                                    <li><a class="dropdown-item" href="donhang.php"><i class="bi bi-receipt me-2"></i>Đơn hàng của tôi</a></li>
                                    <li><a class="dropdown-item" href="wishlist.php"><i class="bi bi-heart me-2"></i>Sản phẩm yêu thích</a></li>
                                    
                                    <?php 
                                    // Kiểm tra nếu là người bán thì hiển thị link đến kênh người bán
                                    $seller_check = $conn->prepare("SELECT loai_user FROM users WHERE id_user = ? AND loai_user = 1");
                                    $seller_check->bind_param("i", $_SESSION['user']['id']);
                                    $seller_check->execute();
                                    $is_seller = $seller_check->get_result()->num_rows > 0;
                                    
                                    if ($is_seller): 
                                    ?>
                                        <li><a class="dropdown-item" href="seller/trang-chu.php"><i class="bi bi-shop me-2"></i>Kênh người bán</a></li>
                                    <?php else: ?>
                                        <li><a class="dropdown-item" href="tro-thanh-nguoi-ban.php"><i class="bi bi-shop me-2"></i>Trở thành người bán</a></li>
                                    <?php endif; ?>
                                    
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Đăng xuất</a></li>
                                </ul>
                            </div>
                        <?php else: ?>
                            <div class="d-flex">
                                <a href="dangnhap.php" class="btn btn-outline-primary btn-sm me-2">
                                    <i class="bi bi-person"></i> <span class="d-none d-sm-inline">Đăng nhập</span>
                                </a>
                                <a href="dangky.php" class="btn btn-primary btn-sm">
                                    <span class="d-none d-sm-inline">Đăng ký</span>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- Thêm vào cuối file, trước phần đóng </header> -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Log ra tất cả các liên kết để kiểm tra
        console.log('Các liên kết trong header:');
        document.querySelectorAll('a').forEach(function(link) {
            console.log(link.href, link);
        });
        
        // Kiểm tra liên kết đăng nhập
        const loginLink = document.querySelector('a[href="dangnhap.php"]');
        if (loginLink) {
            loginLink.addEventListener('click', function(e) {
                console.log('Đã click vào liên kết đăng nhập');
            });
        } else {
            console.error('Không tìm thấy liên kết đăng nhập!');
        }
    });
    </script>
</header>

<!-- Thêm CSS cho header mới -->
<style>
/* Thanh thông báo */
.announcement-bar {
    font-size: 0.85rem;
}

.marquee-container {
    flex-grow: 1;
    overflow: hidden;
    text-align: center;
}

.marquee-text {
    display: inline-block;
    white-space: nowrap;
    animation: marquee 20s linear infinite;
}

@keyframes marquee {
    0% { transform: translateX(100%); }
    100% { transform: translateX(-100%); }
}

/* Menu điều hướng */
.navbar .nav-link {
    padding: 0.5rem 1rem;
    font-weight: 500;
    color: #333;
    position: relative;
    transition: all 0.3s ease;
}

.navbar .nav-link:hover {
    color: #0d6efd;
}

.navbar .nav-link.active {
    color: #0d6efd;
}

.navbar .nav-link.active::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 1rem;
    right: 1rem;
    height: 3px;
    background-color: #0d6efd;
    border-radius: 3px;
}

/* Dropdown danh mục sản phẩm */
.categories-menu {
    min-width: 400px;
}

.category-item {
    padding: 0.5rem;
    margin-bottom: 0.25rem;
    border-radius: 0.25rem;
    transition: all 0.2s ease;
}

.category-item:hover {
    background-color: #f8f9fa;
}

.category-thumbnail {
    width: 24px;
    height: 24px;
    object-fit: cover;
    border-radius: 3px;
}

/* Form tìm kiếm */
.search-form {
    position: relative;
    max-width: 250px;
}

.search-input {
    padding-right: 40px;
    border-radius: 20px;
    border: 1px solid #dee2e6;
}

.search-input:focus {
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15);
    border-color: #86b7fe;
}

.btn-search {
    background: transparent;
    border: none;
    right: 0;
    top: 0;
    height: 100%;
    color: #6c757d;
    transition: color 0.2s ease;
}

.btn-search:hover {
    color: #0d6efd;
}

/* Dropdown giỏ hàng */
.cart-dropdown .dropdown-menu {
    border-radius: 0.5rem;
}

.cart-item {
    transition: background-color 0.2s;
}

.cart-item:hover {
    background-color: #f8f9fa;
}

/* Đảm bảo các nút trong header có thể click được */
.user-menu-wrapper {
    position: relative;
    z-index: 1000;
}

.btn {
    position: relative;
    z-index: 1000;
}

/* Responsive */
@media (max-width: 767.98px) {
    .navbar-brand span {
        display: none;
    }
    
    .categories-menu {
        min-width: 300px;
    }
}

/* Thêm vào phần <head> của trang */
@keyframes cartUpdate {
    0% { transform: scale(1); }
    50% { transform: scale(1.5); background-color: #dc3545; }
    100% { transform: scale(1); }
}

.cart-update-animation {
    animation: cartUpdate 0.5s ease-in-out;
}
</style>