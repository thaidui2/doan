<?php
// Thứ tự include quan trọng để tránh lỗi khai báo hàm trùng lặp
include_once('includes/functions.php');       // Chứa hàm hasPermission() chính thức
include_once('includes/admin_helpers.php');   // Sẽ kiểm tra và bỏ qua nếu hàm đã tồn tại
include_once('includes/permissions.php'); 
?>
<nav id="sidebar" class="col-md-3 col-lg-2 d-md-block bg-dark sidebar collapse">
    <div class="sidebar-sticky pt-3">
        
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo isActiveMenu('index.php') ? 'active' : ''; ?>" href="index.php">
                    <i class="bi bi-house-door"></i>
                    Tổng quan
                </a>
            </li>
            
            <?php if (hasPermission('order_view')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo isActiveMenu(['orders.php', 'order-detail.php']) ? 'active' : ''; ?>" href="orders.php">
                    <i class="bi bi-cart3"></i>
                    Quản lý đơn hàng
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (hasPermission('product_view')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo isActiveMenu(['products.php', 'add_product.php', 'edit_product.php']) ? 'active' : ''; ?>" href="products.php">
                    <i class="bi bi-box"></i>
                    Quản lý sản phẩm
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (hasPermission('category_view')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo isActiveMenu('categories.php') ? 'active' : ''; ?>" href="categories.php">
                    <i class="bi bi-tags"></i>
                    Quản lý danh mục
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (hasPermission('promo_view')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo isActiveMenu(['khuyen-mai.php', 'them-khuyen-mai.php', 'chinh-sua-khuyen-mai.php']) ? 'active' : ''; ?>" href="khuyen-mai.php">
                    <i class="bi bi-ticket-perforated"></i>
                    Quản lý khuyến mãi
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (hasPermission('customer_view')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo isActiveMenu(['customers.php', 'customer-detail.php', 'add_customer.php', 'edit_customer.php']) ? 'active' : ''; ?>" href="customers.php">
                    <i class="bi bi-people"></i>
                    Quản lý khách hàng
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (hasPermission('order_view')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo isActiveMenu('reviews.php') ? 'active' : ''; ?>" href="reviews.php">
                    <i class="bi bi-star"></i>
                    Quản lý đánh giá
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (hasPermission('report_view')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo isActiveMenu('order-report.php') ? 'active' : ''; ?>" href="order-report.php">
                    <i class="bi bi-graph-up"></i>
                    Báo cáo doanh thu
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (hasPermission('setting_manage')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo isActiveMenu('settings.php') ? 'active' : ''; ?>" href="settings.php">
                    <i class="bi bi-gear"></i>
                    Cài đặt
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (hasPermission('admin_view') || hasPermission('role_view')): ?>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="collapse" href="#adminSubmenu" role="button" aria-expanded="<?php echo isActiveMenu(['admins.php', 'add_admin.php', 'edit_admin.php', 'roles.php', 'permissions.php']) ? 'true' : 'false'; ?>" aria-controls="adminSubmenu">
                    <i class="bi bi-person-lock"></i>
                    Quản lý hệ thống
                    <i class="bi bi-chevron-down ms-auto"></i>
                </a>
                <div class="collapse <?php echo isActiveMenu(['admins.php', 'add_admin.php', 'edit_admin.php', 'roles.php', 'permissions.php']) ? 'show' : ''; ?>" id="adminSubmenu">
                    <ul class="nav flex-column ms-3">
                        <?php if (hasPermission('admin_view')): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo isActiveMenu(['admins.php', 'add_admin.php', 'edit_admin.php']) ? 'active' : ''; ?>" href="admins.php">
                                <i class="bi bi-people-fill"></i> Quản lý nhân viên
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (hasPermission('role_view')): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo isActiveMenu('roles.php') ? 'active' : ''; ?>" href="roles.php">
                                <i class="bi bi-person-badge"></i> Vai trò
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (hasPermission('log_view')): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo isActiveMenu('activity-logs.php') ? 'active' : ''; ?>" href="activity-logs.php">
                                <i class="bi bi-journal-text"></i> Nhật ký hoạt động
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </li>
            <?php endif; ?>
            
            <hr class="bg-secondary my-3">
            
            <li class="nav-item">
                <a class="nav-link" href="logout.php">
                    <i class="bi bi-box-arrow-right"></i>
                    Đăng xuất
                </a>
            </li>
        </ul>
    </div>
</nav>
