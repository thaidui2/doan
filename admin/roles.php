<?php
// Set page title
$page_title = 'Quản lý cấp bậc';

// Include header
include('includes/header.php');

// Include database connection
include('../config/config.php');

// Check if user has permission
checkPermissionRedirect('admin_view');

// Các mức cấp bậc trong hệ thống
$role_levels = [
    1 => [
        'name' => 'Quản lý',
        'description' => 'Người quản lý có quyền hạn cơ bản trong hệ thống'
    ],
    2 => [
        'name' => 'Admin cấp cao',
        'description' => 'Admin cấp cao có quyền hạn cao hơn quản lý thông thường'
    ]
];

// Đếm số lượng admin mỗi cấp bậc
$counts = [];
foreach (array_keys($role_levels) as $level) {
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM admin WHERE cap_bac = ?");
    $stmt->bind_param("i", $level);
    $stmt->execute();
    $result = $stmt->get_result();
    $counts[$level] = $result->fetch_assoc()['total'];
}

// Hiển thị danh sách admin theo cấp bậc nếu yêu cầu
$show_level = isset($_GET['level']) ? (int)$_GET['level'] : null;
$admins_list = [];

if ($show_level !== null && array_key_exists($show_level, $role_levels)) {
    $stmt = $conn->prepare("
        SELECT id_admin, taikhoan, ten_admin, email, trang_thai, ngay_tao, lan_dang_nhap_cuoi 
        FROM admin 
        WHERE cap_bac = ? 
        ORDER BY ten_admin ASC
    ");
    $stmt->bind_param("i", $show_level);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $admins_list[] = $row;
    }
}

// Handle delete admin role level (just reset to lowest level)
if (isset($_POST['reset_level']) && $_SESSION['admin_level'] >= 2) {
    $admin_id = (int)$_POST['admin_id'];
    $new_level = 1; // Reset to lowest level (Quản lý)
    
    // Prevent changing own level or super admin level
    $check = $conn->prepare("SELECT cap_bac FROM admin WHERE id_admin = ?");
    $check->bind_param("i", $admin_id);
    $check->execute();
    $admin_data = $check->get_result()->fetch_assoc();
    
    if ($admin_id != $_SESSION['admin_id']) {
        $stmt = $conn->prepare("UPDATE admin SET cap_bac = ? WHERE id_admin = ?");
        $stmt->bind_param("ii", $new_level, $admin_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'Đã cập nhật cấp bậc cho nhân viên thành công!';
            
            // Add admin action log if table exists
            $table_check = $conn->query("SHOW TABLES LIKE 'admin_actions'");
            if ($table_check->num_rows > 0) {
                $admin_id_current = $_SESSION['admin_id'];
                $action = 'update';
                $target_type = 'admin_role';
                $details = "Cập nhật cấp bậc cho admin #$admin_id từ " . 
                           ($admin_data['cap_bac'] == 2 ? 'Admin cấp cao' : 'Quản lý') . 
                           " thành Quản lý";
                $ip = $_SERVER['REMOTE_ADDR'];
                
                $log_stmt = $conn->prepare("
                    INSERT INTO admin_actions (admin_id, action_type, target_type, target_id, details, ip_address) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                
                $log_stmt->bind_param("ississ", $admin_id_current, $action, $target_type, $admin_id, $details, $ip);
                $log_stmt->execute();
            }
        } else {
            $_SESSION['error_message'] = 'Không thể cập nhật cấp bậc: ' . $conn->error;
        }
        
        // Redirect to refresh the page
        header("Location: roles.php" . ($show_level ? "?level=$show_level" : ""));
        exit();
    } else {
        $_SESSION['error_message'] = 'Không thể thay đổi cấp bậc của chính mình!';
    }
}

?>

<!-- Include sidebar -->
<?php include('includes/sidebar.php'); ?>

<!-- Main content -->
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Trang chủ</a></li>
            <li class="breadcrumb-item active" aria-current="page">Quản lý cấp bậc</li>
        </ol>
    </nav>
    
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Quản lý cấp bậc</h1>
    </div>
    
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['success_message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['error_message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>
    
    <div class="row row-cols-1 row-cols-md-2 mb-4 g-4">
        <?php foreach ($role_levels as $level => $role): ?>
        <div class="col">
            <div class="card h-100">
                <div class="card-header bg-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0"><?php echo htmlspecialchars($role['name']); ?></h5>
                        <span class="badge bg-primary rounded-pill"><?php echo $counts[$level] ?? 0; ?> thành viên</span>
                    </div>
                </div>
                <div class="card-body">
                    <p class="card-text"><?php echo htmlspecialchars($role['description']); ?></p>
                    
                    <h6 class="fw-bold mb-3">Quyền hạn</h6>
                    <ul class="list-group mb-3">
                        <?php if ($level == 1): // Quản lý ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Quản lý sản phẩm cơ bản</span>
                            <i class="bi bi-check-circle-fill text-success"></i>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Quản lý đơn hàng cơ bản</span>
                            <i class="bi bi-check-circle-fill text-success"></i>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Quản lý danh mục</span>
                            <i class="bi bi-check-circle-fill text-success"></i>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Quản lý admin khác</span>
                            <i class="bi bi-x-circle-fill text-danger"></i>
                        </li>
                        <?php elseif ($level == 2): // Admin cấp cao ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Toàn quyền quản lý sản phẩm</span>
                            <i class="bi bi-check-circle-fill text-success"></i>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Toàn quyền quản lý đơn hàng</span>
                            <i class="bi bi-check-circle-fill text-success"></i>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Quản lý danh mục và thương hiệu</span>
                            <i class="bi bi-check-circle-fill text-success"></i>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Quản lý nhân viên</span>
                            <i class="bi bi-check-circle-fill text-success"></i>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Thống kê báo cáo</span>
                            <i class="bi bi-check-circle-fill text-success"></i>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="card-footer bg-white">
                    <a href="?level=<?php echo $level; ?>" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-people-fill"></i> Xem danh sách thành viên
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <?php if ($show_level !== null && array_key_exists($show_level, $role_levels)): ?>
    <div class="card mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="card-title mb-0">Danh sách thành viên cấp bậc: <?php echo htmlspecialchars($role_levels[$show_level]['name']); ?></h5>
        </div>
        <div class="card-body">
            <?php if (empty($admins_list)): ?>
            <p class="text-muted mb-0">Không có thành viên nào với cấp bậc này.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tên đăng nhập</th>
                            <th>Họ tên</th>
                            <th>Email</th>
                            <th>Trạng thái</th>
                            <th>Ngày tạo</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($admins_list as $admin): ?>
                        <tr>
                            <td><?php echo $admin['id_admin']; ?></td>
                            <td><?php echo htmlspecialchars($admin['taikhoan']); ?></td>
                            <td><?php echo htmlspecialchars($admin['ten_admin']); ?></td>
                            <td><?php echo htmlspecialchars($admin['email'] ?: 'N/A'); ?></td>
                            <td>
                                <?php if ($admin['trang_thai']): ?>
                                <span class="badge bg-success">Hoạt động</span>
                                <?php else: ?>
                                <span class="badge bg-danger">Bị khóa</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($admin['ngay_tao'])); ?></td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="view_admin.php?id=<?php echo $admin['id_admin']; ?>" class="btn btn-outline-primary" title="Xem chi tiết">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    
                                    <?php if ($_SESSION['admin_level'] >= 2 && $admin['id_admin'] != $_SESSION['admin_id'] && $show_level == 2): ?>
                                    <button type="button" class="btn btn-outline-warning reset-role" 
                                            data-id="<?php echo $admin['id_admin']; ?>" 
                                            data-name="<?php echo htmlspecialchars($admin['ten_admin']); ?>"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#resetRoleModal" 
                                            title="Hạ cấp xuống Quản lý">
                                        <i class="bi bi-arrow-down-circle"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</main>

<!-- Reset Role Modal -->
<div class="modal fade" id="resetRoleModal" tabindex="-1" aria-labelledby="resetRoleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="resetRoleModalLabel">Xác nhận hạ cấp thành viên</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Bạn có chắc chắn muốn hạ cấp thành viên <strong id="adminName"></strong> xuống cấp bậc Quản lý?</p>
                <p class="text-danger">Lưu ý: Hành động này sẽ xóa tất cả quyền hạn của Admin cấp cao khỏi tài khoản này.</p>
            </div>
            <div class="modal-footer">
                <form method="post">
                    <input type="hidden" name="admin_id" id="adminId">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" name="reset_level" class="btn btn-warning">Hạ cấp</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// JavaScript for the page
$page_specific_js = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Reset role button click
    const resetButtons = document.querySelectorAll(".reset-role");
    resetButtons.forEach(button => {
        button.addEventListener("click", function() {
            const adminId = this.getAttribute("data-id");
            const adminName = this.getAttribute("data-name");
            
            document.getElementById("adminId").value = adminId;
            document.getElementById("adminName").textContent = adminName;
        });
    });
});
</script>
';

// Include footer
include('includes/footer.php');
?>
