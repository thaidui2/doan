<?php
// Define the current page - should be set before including this file
$current_page = $current_page ?? '';
?>
<!-- Sidebar -->
<div class="col-md-2 col-lg-2 px-0 sidebar">
    <div class="sidebar-brand">
        <i class="fas fa-bug me-2"></i> Bug Shop
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
            <a class="nav-link <?php echo ($current_page == 'reviews') ? 'active' : ''; ?>" href="reviews.php">
                <i class="fas fa-fw fa-star me-2"></i>
                Đánh giá
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'promotions') ? 'active' : ''; ?>" href="promotions.php">
                <i class="fas fa-fw fa-tag me-2"></i>
                Khuyến mãi
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($current_page == 'settings') ? 'active' : ''; ?>" href="settings.php">
                <i class="fas fa-fw fa-cog me-2"></i>
                Cài đặt
            </a>
        </li>
        <hr class="sidebar-divider my-2">
        <li class="nav-item">
            <a class="nav-link" href="logout.php">
                <i class="fas fa-fw fa-sign-out-alt me-2"></i>
                Đăng xuất
            </a>
        </li>
    </ul>
</div>
