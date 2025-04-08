<?php
// Set page title
$page_title = 'Cài đặt hệ thống phân quyền';

// Start session
session_start();

// Include database connection
include('../config/config.php');

// Only allow access for Super Admins
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || $_SESSION['admin_level'] < 3) {
    die('<div style="text-align: center; margin-top: 100px; font-family: Arial, sans-serif;">
        <h1>Không có quyền truy cập</h1>
        <p>Chỉ Super Admin mới có quyền cài đặt hệ thống phân quyền.</p>
        <p><a href="index.php">Quay lại trang chủ</a></p>
    </div>');
}

// Check if setup has been run already
$check_roles = $conn->query("SHOW TABLES LIKE 'roles'");
$check_permissions = $conn->query("SHOW TABLES LIKE 'permissions'");

$setup_completed = false;
$error = false;
$message = "";

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup'])) {
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Create roles table
        $conn->query("
            CREATE TABLE IF NOT EXISTS `roles` (
              `id_role` int(11) NOT NULL AUTO_INCREMENT,
              `ten_role` varchar(100) NOT NULL,
              `mo_ta` text DEFAULT NULL,
              `ngay_tao` timestamp NOT NULL DEFAULT current_timestamp(),
              PRIMARY KEY (`id_role`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");
        
        // Create permissions table
        $conn->query("
            CREATE TABLE IF NOT EXISTS `permissions` (
              `id_permission` int(11) NOT NULL AUTO_INCREMENT,
              `ten_permission` varchar(100) NOT NULL,
              `ma_permission` varchar(100) NOT NULL,
              `mo_ta` text DEFAULT NULL,
              `nhom_permission` varchar(100) DEFAULT NULL,
              PRIMARY KEY (`id_permission`),
              UNIQUE KEY `ma_permission` (`ma_permission`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");
        
        // Create role_permissions table
        $conn->query("
            CREATE TABLE IF NOT EXISTS `role_permissions` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `id_role` int(11) NOT NULL,
              `id_permission` int(11) NOT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `role_permission_unique` (`id_role`,`id_permission`),
              KEY `id_permission` (`id_permission`),
              CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`id_role`) REFERENCES `roles` (`id_role`) ON DELETE CASCADE,
              CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`id_permission`) REFERENCES `permissions` (`id_permission`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");
        
        // Create admin_roles table
        $conn->query("
            CREATE TABLE IF NOT EXISTS `admin_roles` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `id_admin` int(11) NOT NULL,
              `id_role` int(11) NOT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `admin_role_unique` (`id_admin`,`id_role`),
              KEY `id_role` (`id_role`),
              CONSTRAINT `admin_roles_ibfk_1` FOREIGN KEY (`id_admin`) REFERENCES `admin` (`id_admin`) ON DELETE CASCADE,
              CONSTRAINT `admin_roles_ibfk_2` FOREIGN KEY (`id_role`) REFERENCES `roles` (`id_role`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");

        // Create admin_actions table for logging
        $conn->query("
            CREATE TABLE IF NOT EXISTS `admin_actions` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `admin_id` int(11) NOT NULL,
              `action_type` varchar(100) NOT NULL,
              `target_type` varchar(50) NOT NULL,
              `target_id` int(11) NOT NULL,
              `details` text DEFAULT NULL,
              `ip_address` varchar(45) DEFAULT NULL,
              `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `admin_id` (`admin_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        
        // Insert default roles
        $conn->query("INSERT INTO `roles` (`ten_role`, `mo_ta`) VALUES
            ('Super Admin', 'Toàn quyền trên hệ thống'),
            ('Shop Manager', 'Quản lý sản phẩm, đơn hàng, khách hàng'),
            ('Content Manager', 'Quản lý nội dung, danh mục, bình luận'),
            ('Support Staff', 'Hỗ trợ khách hàng, xử lý đơn hàng')
        ");
        
        // Insert default permissions
        $conn->query("INSERT INTO `permissions` (`ten_permission`, `ma_permission`, `mo_ta`, `nhom_permission`) VALUES
            -- Quyền quản lý sản phẩm
            ('Xem sản phẩm', 'product_view', 'Xem danh sách và chi tiết sản phẩm', 'products'),
            ('Thêm sản phẩm', 'product_add', 'Thêm sản phẩm mới', 'products'),
            ('Sửa sản phẩm', 'product_edit', 'Chỉnh sửa thông tin sản phẩm', 'products'),
            ('Xóa sản phẩm', 'product_delete', 'Xóa sản phẩm', 'products'),

            -- Quyền quản lý danh mục
            ('Xem danh mục', 'category_view', 'Xem danh sách danh mục', 'categories'),
            ('Thêm danh mục', 'category_add', 'Thêm danh mục mới', 'categories'),
            ('Sửa danh mục', 'category_edit', 'Chỉnh sửa thông tin danh mục', 'categories'),
            ('Xóa danh mục', 'category_delete', 'Xóa danh mục', 'categories'),

            -- Quyền quản lý đơn hàng
            ('Xem đơn hàng', 'order_view', 'Xem danh sách và chi tiết đơn hàng', 'orders'),
            ('Cập nhật trạng thái đơn hàng', 'order_update_status', 'Thay đổi trạng thái đơn hàng', 'orders'),
            ('Hủy đơn hàng', 'order_cancel', 'Hủy đơn hàng', 'orders'),
            ('Xóa đơn hàng', 'order_delete', 'Xóa đơn hàng khỏi hệ thống', 'orders'),

            -- Quyền quản lý khách hàng
            ('Xem khách hàng', 'customer_view', 'Xem danh sách và thông tin khách hàng', 'customers'),
            ('Thêm khách hàng', 'customer_add', 'Thêm khách hàng mới', 'customers'),
            ('Sửa khách hàng', 'customer_edit', 'Chỉnh sửa thông tin khách hàng', 'customers'),
            ('Xóa khách hàng', 'customer_delete', 'Xóa khách hàng', 'customers'),
            ('Khóa/mở khóa khách hàng', 'customer_toggle_status', 'Thay đổi trạng thái khóa của khách hàng', 'customers'),

            -- Quyền quản lý admin
            ('Xem admin', 'admin_view', 'Xem danh sách và thông tin admin', 'admins'),
            ('Thêm admin', 'admin_add', 'Thêm tài khoản admin mới', 'admins'),
            ('Sửa admin', 'admin_edit', 'Chỉnh sửa thông tin admin', 'admins'),
            ('Xóa admin', 'admin_delete', 'Xóa tài khoản admin', 'admins'),

            -- Quyền quản lý vai trò và phân quyền
            ('Xem vai trò và quyền hạn', 'role_view', 'Xem danh sách vai trò và quyền hạn', 'permissions'),
            ('Thêm vai trò', 'role_add', 'Thêm vai trò mới', 'permissions'),
            ('Sửa vai trò', 'role_edit', 'Chỉnh sửa thông tin vai trò', 'permissions'),
            ('Xóa vai trò', 'role_delete', 'Xóa vai trò', 'permissions'),
            ('Phân quyền', 'permission_assign', 'Phân quyền cho vai trò', 'permissions'),

            -- Quyền quản lý thống kê và báo cáo
            ('Xem thống kê', 'report_view', 'Xem các báo cáo thống kê', 'reports'),
            ('Xuất báo cáo', 'report_export', 'Xuất báo cáo', 'reports'),

            -- Quyền quản lý hệ thống
            ('Quản lý cài đặt', 'setting_manage', 'Quản lý cài đặt hệ thống', 'settings'),
            ('Xem nhật ký hoạt động', 'log_view', 'Xem nhật ký hoạt động của hệ thống', 'settings')
        ");
        
        // Assign permissions to roles
        
        // Super Admin - all permissions
        $result = $conn->query("SELECT id_role FROM roles WHERE ten_role = 'Super Admin'");
        $super_admin_role = $result->fetch_assoc();
        
        $result = $conn->query("SELECT id_permission FROM permissions");
        $stmt = $conn->prepare("INSERT INTO role_permissions (id_role, id_permission) VALUES (?, ?)");
        
        while ($permission = $result->fetch_assoc()) {
            $stmt->bind_param("ii", $super_admin_role['id_role'], $permission['id_permission']);
            $stmt->execute();
        }
        
        // Shop Manager
        $result = $conn->query("SELECT id_role FROM roles WHERE ten_role = 'Shop Manager'");
        $shop_manager_role = $result->fetch_assoc();
        
        $shop_manager_permissions = [
            'product_view', 'product_add', 'product_edit', 'product_delete',
            'category_view', 'category_add', 'category_edit', 'category_delete',
            'order_view', 'order_update_status', 'order_cancel',
            'customer_view', 'customer_edit', 'customer_toggle_status',
            'report_view'
        ];
        
        $placeholders = implode(',', array_fill(0, count($shop_manager_permissions), '?'));
        $stmt = $conn->prepare("
            SELECT id_permission FROM permissions WHERE ma_permission IN ($placeholders)
        ");
        $stmt->bind_param(str_repeat('s', count($shop_manager_permissions)), ...$shop_manager_permissions);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $stmt = $conn->prepare("INSERT INTO role_permissions (id_role, id_permission) VALUES (?, ?)");
        while ($permission = $result->fetch_assoc()) {
            $stmt->bind_param("ii", $shop_manager_role['id_role'], $permission['id_permission']);
            $stmt->execute();
        }
        
        // Content Manager
        $result = $conn->query("SELECT id_role FROM roles WHERE ten_role = 'Content Manager'");
        $content_manager_role = $result->fetch_assoc();
        
        $content_manager_permissions = [
            'product_view', 'product_add', 'product_edit',
            'category_view', 'category_add', 'category_edit'
        ];
        
        $placeholders = implode(',', array_fill(0, count($content_manager_permissions), '?'));
        $stmt = $conn->prepare("
            SELECT id_permission FROM permissions WHERE ma_permission IN ($placeholders)
        ");
        $stmt->bind_param(str_repeat('s', count($content_manager_permissions)), ...$content_manager_permissions);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $stmt = $conn->prepare("INSERT INTO role_permissions (id_role, id_permission) VALUES (?, ?)");
        while ($permission = $result->fetch_assoc()) {
            $stmt->bind_param("ii", $content_manager_role['id_role'], $permission['id_permission']);
            $stmt->execute();
        }
        
        // Support Staff
        $result = $conn->query("SELECT id_role FROM roles WHERE ten_role = 'Support Staff'");
        $support_staff_role = $result->fetch_assoc();
        
        $support_staff_permissions = [
            'product_view', 
            'order_view', 'order_update_status',
            'customer_view'
        ];
        
        $placeholders = implode(',', array_fill(0, count($support_staff_permissions), '?'));
        $stmt = $conn->prepare("
            SELECT id_permission FROM permissions WHERE ma_permission IN ($placeholders)
        ");
        $stmt->bind_param(str_repeat('s', count($support_staff_permissions)), ...$support_staff_permissions);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $stmt = $conn->prepare("INSERT INTO role_permissions (id_role, id_permission) VALUES (?, ?)");
        while ($permission = $result->fetch_assoc()) {
            $stmt->bind_param("ii", $support_staff_role['id_role'], $permission['id_permission']);
            $stmt->execute();
        }
        
        // Assign Super Admin role to all existing Super Admins
        $stmt = $conn->prepare("
            INSERT INTO admin_roles (id_admin, id_role)
            SELECT id_admin, ? FROM admin WHERE cap_bac = 3
        ");
        $stmt->bind_param("i", $super_admin_role['id_role']);
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        $setup_completed = true;
        $message = "Hệ thống phân quyền đã được cài đặt thành công!";
        
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        $error = true;
        $message = "Lỗi khi cài đặt hệ thống phân quyền: " . $e->getMessage();
    }
}

// Check if tables already exist
$tables_exist = $check_roles->num_rows > 0 && $check_permissions->num_rows > 0;

// Include a simplified header
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Bug Shop</title>
    <link rel="stylesheet" href="../node_modules/bootstrap/dist/css/bootstrap.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            padding-top: 2rem;
            background-color: #f8f9fa;
        }
        .install-wrapper {
            max-width: 800px;
            margin: 0 auto;
        }
        .step {
            position: relative;
            padding-left: 45px;
            margin-bottom: 20px;
        }
        .step-number {
            position: absolute;
            left: 0;
            top: 0;
            width: 35px;
            height: 35px;
            background-color: #0d6efd;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="install-wrapper">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0"><i class="bi bi-shield-lock me-2"></i> Cài đặt hệ thống phân quyền Bug Shop</h3>
                </div>
                <div class="card-body">
                    <?php if ($setup_completed): ?>
                        <div class="alert alert-success">
                            <h4 class="alert-heading"><i class="bi bi-check-circle me-2"></i> Cài đặt thành công!</h4>
                            <p><?php echo $message; ?></p>
                            <hr>
                            <p class="mb-0">Bạn có thể quản lý vai trò và phân quyền trong phần "Quản lý hệ thống" của trang quản trị.</p>
                            <div class="mt-3">
                                <a href="index.php" class="btn btn-primary">Quay về trang chủ</a>
                                <a href="roles.php" class="btn btn-outline-secondary ms-2">Quản lý vai trò</a>
                            </div>
                        </div>
                    <?php elseif ($error): ?>
                        <div class="alert alert-danger">
                            <h4 class="alert-heading"><i class="bi bi-x-circle me-2"></i> Cài đặt thất bại!</h4>
                            <p><?php echo $message; ?></p>
                            <hr>
                            <p class="mb-0">Vui lòng thử lại hoặc liên hệ với quản trị viên hệ thống.</p>
                            <div class="mt-3">
                                <a href="install_permissions.php" class="btn btn-primary">Thử lại</a>
                                <a href="index.php" class="btn btn-outline-secondary ms-2">Quay về trang chủ</a>
                            </div>
                        </div>
                    <?php elseif ($tables_exist): ?>
                        <div class="alert alert-warning">
                            <h4 class="alert-heading"><i class="bi bi-exclamation-triangle me-2"></i> Chú ý!</h4>
                            <p>Hệ thống phân quyền đã được cài đặt trước đó.</p>
                            <hr>
                            <p class="mb-0">Nếu bạn tiếp tục cài đặt, dữ liệu hiện tại sẽ bị xóa và tạo lại.</p>
                        </div>
                        <div class="mt-3">
                            <form method="post" class="d-inline">
                                <input type="hidden" name="setup" value="1">
                                <button type="submit" class="btn btn-danger" onclick="return confirm('Bạn có chắc chắn muốn cài đặt lại hệ thống phân quyền? Dữ liệu hiện tại sẽ bị xóa.')">
                                    <i class="bi bi-arrow-repeat me-2"></i> Cài đặt lại
                                </button>
                            </form>
                            <a href="index.php" class="btn btn-outline-secondary ms-2">Quay về trang chủ</a>
                        </div>
                    <?php else: ?>
                        <h5 class="mb-4">Chào mừng đến với hệ thống phân quyền Bug Shop!</h5>
                        
                        <p>Hệ thống phân quyền cho phép bạn:</p>
                        <ul>
                            <li>Tạo và quản lý các vai trò khác nhau trong hệ thống</li>
                            <li>Phân quyền chi tiết cho từng vai trò</li>
                            <li>Gán vai trò cho các nhân viên quản trị</li>
                            <li>Theo dõi hoạt động của các nhân viên trong hệ thống</li>
                        </ul>
                        
                        <div class="mt-4">
                            <h6 class="mb-3">Quá trình cài đặt sẽ thực hiện các bước sau:</h6>
                            
                            <div class="step">
                                <div class="step-number">1</div>
                                <h6>Tạo các bảng dữ liệu</h6>
                                <p class="text-muted">Tạo các bảng roles, permissions, role_permissions, admin_roles và admin_actions</p>
                            </div>
                            
                            <div class="step">
                                <div class="step-number">2</div>
                                <h6>Tạo các vai trò mặc định</h6>
                                <p class="text-muted">Super Admin, Shop Manager, Content Manager và Support Staff</p>
                            </div>
                            
                            <div class="step">
                                <div class="step-number">3</div>
                                <h6>Tạo các quyền hạn trong hệ thống</h6>
                                <p class="text-muted">Các quyền liên quan đến sản phẩm, danh mục, đơn hàng, khách hàng, quản trị viên, v.v.</p>
                            </div>
                            
                            <div class="step">
                                <div class="step-number">4</div>
                                <h6>Phân quyền cho các vai trò mặc định</h6>
                                <p class="text-muted">Gán các quyền phù hợp cho từng vai trò</p>
                            </div>
                        </div>
                        
                        <div class="mt-4 pt-3 border-top text-center">
                            <form method="post">
                                <input type="hidden" name="setup" value="1">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-shield-check me-2"></i> Bắt đầu cài đặt
                                </button>
                            </form>
                            <div class="mt-3">
                                <a href="index.php" class="btn btn-outline-secondary">Quay về trang chủ</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-light text-center text-muted">
                    <small>Bug Shop Admin - Hệ thống phân quyền v1.0</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="../node_modules/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
