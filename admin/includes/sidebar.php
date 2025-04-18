<?php
// Thứ tự include quan trọng để tránh lỗi khai báo hàm trùng lặp
include_once('includes/functions.php');       // Chứa hàm hasPermission() chính thức
include_once('includes/admin_helpers.php');   // Sẽ kiểm tra và bỏ qua nếu hàm đã tồn tại
include_once('includes/permissions.php'); 
?>
<?php
// Hàm kiểm tra sự tồn tại của bảng - chỉ định nghĩa một lần
if (!function_exists('tableExists')) {
    function tableExists($conn, $table_name) {
        $result = $conn->query("SHOW TABLES LIKE '$table_name'");
        return $result->num_rows > 0;
    }
}

// Chuẩn bị số lượng thông báo
$notification_count = 0;

// Đếm số lượng đơn hàng mới chờ xử lý
$new_orders = 0;
$new_orders_query = $conn->query("SELECT COUNT(*) as count FROM donhang WHERE trang_thai_don_hang = 1");
if ($new_orders_query) {
    $new_orders = $new_orders_query->fetch_assoc()['count'] ?? 0;
    $notification_count += $new_orders;
}

// Đếm số lượng đánh giá mới
$new_reviews = 0;
$reviews_query = $conn->query("SELECT COUNT(*) as count FROM danhgia WHERE trang_thai = 0");
if ($reviews_query) {
    $new_reviews = $reviews_query->fetch_assoc()['count'] ?? 0;
    $notification_count += $new_reviews;
}

// Đếm số lượng yêu cầu hoàn trả (nếu có)
$pending_returns = 0;

// Đếm các yêu cầu liên hệ mới (nếu có)
$contact_requests = 0;

// Ensure admin_level is defined
if (!isset($admin_level)) {
    $admin_level = isset($_SESSION['admin_level']) ? $_SESSION['admin_level'] : 0;
}
?>

<!-- Sidebar -->
<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
    <div class="position-sticky pt-3">
        <!-- Dashboard -->
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo (isActiveMenu('index.php')) ? 'active' : ''; ?>" href="index.php">
                    <i class="bi bi-speedometer2 me-2"></i> Dashboard
                </a>
            </li>
        </ul>

        <!-- Products -->
        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>Sản phẩm</span>
        </h6>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'orders.php' || $current_page == 'order-detail.php') ? 'active' : ''; ?>" href="orders.php">
                    <i class="bi bi-cart me-2"></i> Đơn hàng
                    <?php if ($new_orders > 0): ?>
                        <span class="badge bg-danger rounded-pill"><?php echo $new_orders; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <!-- Sản phẩm -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'products.php' || $current_page == 'add_product.php' || $current_page == 'edit_product.php') ? 'active' : ''; ?>" href="products.php">
                    <i class="bi bi-box me-2"></i> Sản phẩm
                </a>
            </li>
            
            <!-- Danh mục -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'categories.php') ? 'active' : ''; ?>" href="categories.php">
                    <i class="bi bi-tags me-2"></i> Danh mục
                </a>
            </li>
            
            <!-- Thuộc tính sản phẩm -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'attributes.php') ? 'active' : ''; ?>" href="attributes.php">
                    <i class="bi bi-sliders me-2"></i> Thuộc tính
                </a>
            </li>
            
            <!-- Thương hiệu -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'brands.php') ? 'active' : ''; ?>" href="brands.php">
                    <i class="bi bi-award me-2"></i> Thương hiệu
                </a>
            </li>
            
            <!-- Khách hàng -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'customers.php' || $current_page == 'customer-detail.php') ? 'active' : ''; ?>" href="customers.php">
                    <i class="bi bi-people me-2"></i> Khách hàng
                </a>
            </li>
            
            <!-- Đánh giá -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'reviews.php') ? 'active' : ''; ?>" href="reviews.php">
                    <i class="bi bi-star me-2"></i> Đánh giá
                    <?php if ($new_reviews > 0): ?>
                        <span class="badge bg-danger rounded-pill"><?php echo $new_reviews; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <!-- Khuyến mãi -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'khuyen-mai.php' || $current_page == 'them-khuyen-mai.php') ? 'active' : ''; ?>" href="khuyen-mai.php">
                    <i class="bi bi-percent me-2"></i> Khuyến mãi
                </a>
            </li>
            
            <!-- Báo cáo bán hàng -->
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'order-report.php') ? 'active' : ''; ?>" href="order-report.php">
                    <i class="bi bi-bar-chart me-2"></i> Báo cáo bán hàng
                </a>
            </li>

            <?php if ($admin_level == 2): // Chỉ hiển thị phần Cài đặt cho admin cấp cao ?>
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>" href="settings.php">
                    <i class="bi bi-gear me-2"></i> Cài đặt
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'admins.php') ? 'active' : ''; ?>" href="admins.php">
                    <i class="bi bi-person-badge me-2"></i> Quản lý người dùng
                </a>
            </li>
            <?php endif; ?>
            
            <!-- Đăng xuất -->
            <li class="nav-item">
                <a class="nav-link" href="logout.php">
                    <i class="bi bi-box-arrow-right me-2"></i> Đăng xuất
                </a>
            </li>
        </ul>
        
        <!-- Admin-only section -->
        <?php if ($admin_level >= 2): ?>
        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>Quản trị hệ thống</span>
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>" href="settings.php">
                    <i class="bi bi-gear me-2"></i> Cài đặt
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo ($current_page == 'admins.php') ? 'active' : ''; ?>" href="admins.php">
                    <i class="bi bi-person-badge me-2"></i> Quản lý người dùng
                </a>
            </li>
        </ul>
        <?php endif; ?>
    </div>
</nav>
