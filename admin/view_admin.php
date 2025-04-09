<?php
// Set page title
$page_title = 'Chi tiết nhân viên';

// Include header (will check for login)
include('includes/header.php');

// Include database connection
include('../config/config.php');

// Check if user has permission to view admins
checkPermissionRedirect('admin_view');

// Get admin ID
$admin_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Validate admin ID
if ($admin_id <= 0) {
    $_SESSION['error_message'] = 'ID nhân viên không hợp lệ.';
    header('Location: admins.php');
    exit();
}

// Get admin information
$admin_stmt = $conn->prepare("SELECT * FROM admin WHERE id_admin = ?");
$admin_stmt->bind_param("i", $admin_id);
$admin_stmt->execute();
$admin_result = $admin_stmt->get_result();

if ($admin_result->num_rows === 0) {
    $_SESSION['error_message'] = 'Không tìm thấy nhân viên.';
    header('Location: admins.php');
    exit();
}

$admin = $admin_result->fetch_assoc();

// Get admin's roles
$roles_query = $conn->prepare("
    SELECT r.* 
    FROM roles r
    JOIN admin_roles ar ON r.id_role = ar.id_role
    WHERE ar.id_admin = ?
    ORDER BY r.ten_role
");
$roles_query->bind_param("i", $admin_id);
$roles_query->execute();
$roles_result = $roles_query->get_result();

// Get admin's permissions based on roles
$permissions_query = $conn->prepare("
    SELECT DISTINCT p.* 
    FROM permissions p
    JOIN role_permissions rp ON p.id_permission = rp.id_permission
    JOIN admin_roles ar ON rp.id_role = ar.id_role
    WHERE ar.id_admin = ?
    ORDER BY p.nhom_permission, p.ten_permission
");
$permissions_query->bind_param("i", $admin_id);
$permissions_query->execute();
$permissions_result = $permissions_query->get_result();

// Group permissions by category
$grouped_permissions = [];
while ($permission = $permissions_result->fetch_assoc()) {
    $group = $permission['nhom_permission'] ?: 'Khác';
    if (!isset($grouped_permissions[$group])) {
        $grouped_permissions[$group] = [];
    }
    $grouped_permissions[$group][] = $permission;
}

// Get admin's recent activity
$activity_query = $conn->prepare("
    SELECT * 
    FROM admin_actions
    WHERE admin_id = ?
    ORDER BY created_at DESC
    LIMIT 20
");
$activity_query->bind_param("i", $admin_id);
$activity_query->execute();
$activity_result = $activity_query->get_result();
?>

<!-- Include sidebar -->
<?php include('includes/sidebar.php'); ?>

<!-- Main content -->
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Trang chủ</a></li>
            <li class="breadcrumb-item"><a href="admins.php">Quản lý nhân viên</a></li>
            <li class="breadcrumb-item active" aria-current="page">Chi tiết nhân viên</li>
        </ol>
    </nav>
    
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Chi tiết nhân viên: <?php echo htmlspecialchars($admin['ten_admin']); ?></h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <?php if (hasPermission('admin_edit') && ($admin['cap_bac'] < 3 || $_SESSION['admin_level'] == 3)): ?>
            <a href="edit_admin.php?id=<?php echo $admin_id; ?>" class="btn btn-sm btn-outline-primary me-2">
                <i class="bi bi-pencil"></i> Chỉnh sửa
            </a>
            <?php endif; ?>
            <a href="admins.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Quay lại
            </a>
        </div>
    </div>
    
    <div class="row">
        <!-- Admin Information -->
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0">Thông tin cơ bản</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3 text-center">
                        <?php if (!empty($admin['anh_dai_dien'])): ?>
                            <img src="<?php echo $admin['anh_dai_dien']; ?>" class="rounded-circle mb-3" width="100" height="100" alt="Avatar">
                        <?php else: ?>
                            <div class="avatar-placeholder rounded-circle d-flex align-items-center justify-content-center bg-secondary text-white mb-3 mx-auto" style="width: 100px; height: 100px;">
                                <span style="font-size: 2.5rem;"><?php echo strtoupper(substr($admin['ten_admin'], 0, 1)); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <table class="table table-borderless">
                        <tr>
                            <th class="ps-0">Họ và tên:</th>
                            <td class="text-end pe-0"><?php echo htmlspecialchars($admin['ten_admin']); ?></td>
                        </tr>
                        <tr>
                            <th class="ps-0">Tên đăng nhập:</th>
                            <td class="text-end pe-0"><?php echo htmlspecialchars($admin['taikhoan']); ?></td>
                        </tr>
                        <tr>
                            <th class="ps-0">Email:</th>
                            <td class="text-end pe-0"><?php echo htmlspecialchars($admin['email'] ?? 'Chưa cập nhật'); ?></td>
                        </tr>
                        <tr>
                            <th class="ps-0">Cấp bậc:</th>
                            <td class="text-end pe-0">
                                <?php if ($admin['cap_bac'] == 3): ?>
                                    <span class="badge bg-danger">Super Admin</span>
                                <?php elseif ($admin['cap_bac'] == 2): ?>
                                    <span class="badge bg-primary">Admin</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Nhân viên</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th class="ps-0">Trạng thái:</th>
                            <td class="text-end pe-0">
                                <?php if ($admin['trang_thai'] == 1): ?>
                                    <span class="badge bg-success">Hoạt động</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Bị khóa</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th class="ps-0">Ngày tạo:</th>
                            <td class="text-end pe-0"><?php echo date('d/m/Y', strtotime($admin['ngay_tao'])); ?></td>
                        </tr>
                        <tr>
                            <th class="ps-0">Đăng nhập gần đây:</th>
                            <td class="text-end pe-0">
                                <?php echo $admin['lan_dang_nhap_cuoi'] ? date('d/m/Y H:i', strtotime($admin['lan_dang_nhap_cuoi'])) : 'Chưa đăng nhập'; ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Admin Roles & Permissions -->
        <div class="col-md-8 mb-4">
            <div class="card h-100">
                <div class="card-header bg-white py-3">
                    <ul class="nav nav-tabs card-header-tabs" id="adminTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="roles-tab" data-bs-toggle="tab" data-bs-target="#roles" type="button" role="tab" aria-controls="roles" aria-selected="true">
                                Vai trò <span class="badge bg-secondary ms-1"><?php echo $roles_result->num_rows; ?></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="permissions-tab" data-bs-toggle="tab" data-bs-target="#permissions" type="button" role="tab" aria-controls="permissions" aria-selected="false">
                                Quyền hạn <span class="badge bg-secondary ms-1"><?php echo $permissions_result->num_rows; ?></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="activity-tab" data-bs-toggle="tab" data-bs-target="#activity" type="button" role="tab" aria-controls="activity" aria-selected="false">
                                Hoạt động gần đây <span class="badge bg-secondary ms-1"><?php echo $activity_result->num_rows; ?></span>
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="adminTabsContent">
                        <!-- Roles Tab -->
                        <div class="tab-pane fade show active" id="roles" role="tabpanel" aria-labelledby="roles-tab">
                            <?php if ($admin['cap_bac'] == 3): ?>
                                <div class="alert alert-info mb-3">
                                    <i class="bi bi-info-circle me-2"></i> Super Admin có tất cả các quyền mà không cần gán vai trò cụ thể.
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($roles_result->num_rows > 0): ?>
                                <div class="list-group">
                                    <?php while ($role = $roles_result->fetch_assoc()): ?>
                                        <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($role['ten_role']); ?></h6>
                                                <p class="mb-1 text-secondary small"><?php echo htmlspecialchars($role['mo_ta'] ?? 'Không có mô tả'); ?></p>
                                            </div>
                                            <?php if (hasPermission('role_view')): ?>
                                            <a href="view_role.php?id=<?php echo $role['id_role']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i> Xem chi tiết
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i> Người dùng này chưa được gán vai trò nào.
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Permissions Tab -->
                        <div class="tab-pane fade" id="permissions" role="tabpanel" aria-labelledby="permissions-tab">
                            <?php if ($admin['cap_bac'] == 3): ?>
                                <div class="alert alert-info mb-3">
                                    <i class="bi bi-info-circle me-2"></i> Super Admin có tất cả các quyền trong hệ thống.
                                </div>
                            <?php endif; ?>
                            
                            <?php if (count($grouped_permissions) > 0): ?>
                                <div class="accordion" id="permissionsAccordion">
                                    <?php $index = 0; foreach ($grouped_permissions as $group_name => $permissions): $index++; ?>
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="heading<?php echo $index; ?>">
                                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $index; ?>" aria-expanded="false" aria-controls="collapse<?php echo $index; ?>">
                                                    <strong><?php echo htmlspecialchars(ucfirst($group_name)); ?></strong>
                                                    <span class="badge bg-primary ms-2"><?php echo count($permissions); ?></span>
                                                </button>
                                            </h2>
                                            <div id="collapse<?php echo $index; ?>" class="accordion-collapse collapse" aria-labelledby="heading<?php echo $index; ?>" data-bs-parent="#permissionsAccordion">
                                                <div class="accordion-body p-0">
                                                    <div class="list-group list-group-flush">
                                                        <?php foreach ($permissions as $permission): ?>
                                                            <div class="list-group-item">
                                                                <div class="d-flex w-100 justify-content-between">
                                                                    <h6 class="mb-1"><?php echo htmlspecialchars($permission['ten_permission']); ?></h6>
                                                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($permission['ma_permission']); ?></span>
                                                                </div>
                                                                <p class="mb-1 text-muted small"><?php echo htmlspecialchars($permission['mo_ta'] ?? 'Không có mô tả'); ?></p>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i> Người dùng này chưa có quyền hạn nào.
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Activity Tab -->
                        <div class="tab-pane fade" id="activity" role="tabpanel" aria-labelledby="activity-tab">
                            <?php if ($activity_result->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Thời gian</th>
                                                <th>Hành động</th>
                                                <th>Đối tượng</th>
                                                <th>Chi tiết</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($action = $activity_result->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo date('d/m/Y H:i', strtotime($action['created_at'])); ?></td>
                                                    <td>
                                                        <?php 
                                                        $action_labels = [
                                                            'create' => '<span class="badge bg-success">Tạo mới</span>',
                                                            'edit' => '<span class="badge bg-info">Chỉnh sửa</span>',
                                                            'delete' => '<span class="badge bg-danger">Xóa</span>',
                                                            'enable' => '<span class="badge bg-success">Kích hoạt</span>',
                                                            'disable' => '<span class="badge bg-warning text-dark">Vô hiệu</span>',
                                                        ];
                                                        echo $action_labels[$action['action_type']] ?? '<span class="badge bg-secondary">' . htmlspecialchars($action['action_type']) . '</span>';
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        $target_labels = [
                                                            'product' => 'Sản phẩm',
                                                            'category' => 'Danh mục',
                                                            'order' => 'Đơn hàng',
                                                            'customer' => 'Khách hàng',
                                                            'admin' => 'Nhân viên',
                                                            'role' => 'Vai trò',
                                                            'review' => 'Đánh giá',
                                                        ];
                                                        echo $target_labels[$action['target_type']] ?? htmlspecialchars($action['target_type']);
                                                        echo ' #' . $action['target_id'];
                                                        ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($action['details']); ?></td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle me-2"></i> Không có hoạt động nào được ghi nhận.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
<?php 
// JavaScript for the page
$page_specific_js = '
<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Toggle admin status
        const toggleStatusButtons = document.querySelectorAll(".toggle-status");
        toggleStatusButtons.forEach(button => {
            button.addEventListener("click", function() {
                const adminId = this.getAttribute("data-id");
                const currentStatus = parseInt(this.getAttribute("data-status"));
                const adminName = this.getAttribute("data-name");
                const newStatus = currentStatus === 1 ? 0 : 1;
                
                document.getElementById("toggleStatusAdminId").value = adminId;
                document.getElementById("toggleStatusNewStatus").value = newStatus;
                document.getElementById("toggleStatusMessage").textContent = newStatus === 1 
                    ? `Bạn có chắc chắn muốn mở khóa tài khoản của nhân viên ${adminName}?` 
                    : `Bạn có chắc chắn muốn khóa tài khoản của nhân viên ${adminName}?`;
                
                // Show/hide reason field based on action
                document.getElementById("lockReasonContainer").style.display = newStatus === 1 ? "none" : "block";
                
                // Set button text and style
                document.getElementById("toggleStatusTitle").textContent = newStatus === 1 ? "Mở khóa tài khoản" : "Khóa tài khoản";
                const submitBtn = document.getElementById("toggleStatusSubmitBtn");
                submitBtn.textContent = newStatus === 1 ? "Mở khóa" : "Khóa";
                submitBtn.className = newStatus === 1 ? "btn btn-success" : "btn btn-danger";
                
                // Show modal
                const modal = new bootstrap.Modal(document.getElementById("toggleStatusModal"));
                modal.show();
            });
        });
        
        // Delete admin
        const deleteButtons = document.querySelectorAll(".delete-admin");
        deleteButtons.forEach(button => {
            button.addEventListener("click", function() {
                const adminId = this.getAttribute("data-id");
                const adminName = this.getAttribute("data-name");
                
                document.getElementById("deleteAdminId").value = adminId;
                document.getElementById("deleteAdminName").textContent = adminName;
                
                // Show modal
                const modal = new bootstrap.Modal(document.getElementById("deleteAdminModal"));
                modal.show();
            });
        });
    });
</script>
';

// Include footer
include('includes/footer.php');
?>
