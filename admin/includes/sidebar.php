<?php
// Define the current page - should be set before including this file
$current_page = $current_page ?? '';

// Get current user information
$user_name = $_SESSION['admin_name'] ?? 'Admin';

// Start output buffering to prevent any unwanted whitespace
ob_start();
?>
<!-- Sidebar -->
<div class="col-md-2 col-lg-2 px-0 sidebar">
    <div class="sidebar-brand">
        <i class="fas fa-bug me-2"></i> Bug Shop
    </div>
    <div class="sidebar-user-info px-3 py-2 text-white">
        <i class="fas fa-user-circle me-1"></i> Xin chào, <strong><?php echo htmlspecialchars($user_name); ?></strong>
    </div>
    <hr class="sidebar-divider my-2">
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'dashboard') ? 'active' : ''; ?>" href="dashboard.php">
                <i class="fas fa-fw fa-tachometer-alt me-2"></i>
                Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'products') ? 'active' : ''; ?>" href="products.php">
                <i class="fas fa-fw fa-box me-2"></i>
                Sản phẩm
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'categories') ? 'active' : ''; ?>" href="categories.php">
                <i class="fas fa-fw fa-list me-2"></i>
                Danh mục
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'sizes') ? 'active' : ''; ?>" href="sizes.php">
                <i class="fas fa-fw fa-ruler me-2"></i>
                Kích thước
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'colors') ? 'active' : ''; ?>" href="colors.php">
                <i class="fas fa-fw fa-palette me-2"></i>
                Màu sắc
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'brands') ? 'active' : ''; ?>" href="brands.php">
                <i class="fas fa-fw fa-copyright me-2"></i>
                Thương hiệu
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'orders') ? 'active' : ''; ?>" href="orders.php">
                <i class="fas fa-fw fa-shopping-cart me-2"></i>
                Đơn hàng
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'customers') ? 'active' : ''; ?>" href="customers.php">
                <i class="fas fa-fw fa-users me-2"></i>
                Khách hàng
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'staff') ? 'active' : ''; ?>" href="staff.php">
                <i class="fas fa-fw fa-user-tie me-2"></i>
                Quản lý nhân viên
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'reviews') ? 'active' : ''; ?>" href="reviews.php">
                <i class="fas fa-fw fa-star me-2"></i>
                Đánh giá
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'returns') ? 'active' : ''; ?>" href="returns.php">
                <i class="fas fa-fw fa-exchange-alt me-2"></i>
                Hoàn trả
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'promotions') ? 'active' : ''; ?>" href="promotions.php">
                <i class="fas fa-fw fa-tag me-2"></i>
                Khuyến mãi
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="logout.php">
                <i class="fas fa-fw fa-sign-out-alt me-2"></i>
                Đăng xuất
            </a>
        </li>
    </ul>
</div>
<?php
// No PHP closing tag at the end of the file to prevent accidental whitespace
// Output buffering will be flushed automatically at the end of script execution
