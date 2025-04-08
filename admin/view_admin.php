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
    
    <div class="row">
        <!-- Admin Info Card -->
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Thông tin cơ bản</h5>
                    <span class="badge <?php echo $admin['trang_thai'] ? 'bg-success' : 'bg-danger'; ?>">
                        <?php echo $admin['trang_thai'] ? 'Hoạt động' : 'Bị khóa'; ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center mx-auto" style="width: 100px; height: 100px; font-size: 2.5rem;">
                            <i class="bi bi-person"></i>
                        </div>
                        <h5 class="mt-3"><?php echo htmlspecialchars($admin['ten_admin']); ?></h5>
                        <p class="text-muted"><?php echo htmlspecialchars($admin['taikhoan']); ?></p>
                        <div>
                            <?php if ($admin['cap_bac'] == 3): ?>
                                <span class="badge bg-danger">Super Admin</span>
                            <?php elseif ($admin['cap_bac'] == 2): ?>
                                <span class="badge bg-primary">Admin</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Nhân viên</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="mb-3">
                        <h6 class="text-muted">Thông tin liên hệ</h6>
                        <p class="mb-1"><i class="bi bi-envelope me-2"></i> <?php echo htmlspecialchars($admin['email'] ?: 'Không có thông tin'); ?></p>
                    </div>
                    
                    <hr>
                    
                    <div class="mb-3">
                        <h6 class="text-muted">Thông tin tài khoản</h6>
                        <p class="mb-1"><i class="bi bi-calendar-check me-2"></i> Ngày tạo: <?php echo date('d/m/Y H:i', strtotime($admin['ngay_tao'] ?? 'now')); ?></p>
                        <p class="mb-1"><i class="bi bi-clock-history me-2"></i> Đăng nhập gần nhất: <?php echo $admin['lan_dang_nhap_cuoi'] ? date('d/m/Y H:i', strtotime($admin['lan_dang_nhap_cuoi'])) : 'Chưa có thông tin'; ?></p>
                    </div>
                </div>
                <div class="card-footer bg-white">
                    <div class="d-flex justify-content-between">
                        <?php if (hasPermission('admin_edit') && ($admin['cap_bac'] < 3 || $_SESSION['admin_level'] == 3) && $admin['id_admin'] != $_SESSION['admin_id']): ?>
                        <button type="button" class="btn btn-sm <?php echo $admin['trang_thai'] ? 'btn-outline-danger' : 'btn-outline-success'; ?> toggle-status"
                                data-id="<?php echo $admin['id_admin']; ?>" 
                                data-status="<?php echo $admin['trang_thai']; ?>"
                                data-name="<?php echo htmlspecialchars($admin['ten_admin']); ?>"
                            <?php if ($admin['trang_thai']): ?>
                                <i class="bi bi-lock"></i> Khóa tài khoản
                            <?php else: ?>
                                <i class="bi bi-unlock"></i> Mở khóa tài khoản
                            <?php endif; ?>
                        </button>
                        <?php else: ?>
                        <div></div>
                        <?php endif; ?>
                        
                        <?php if (hasPermission('admin_delete') && $_SESSION['admin_level'] == 3 && $admin['cap_bac'] < 3 && $admin['id_admin'] != $_SESSION['admin_id']): ?>
                        <button type="button" class="btn btn-sm btn-outline-danger delete-admin"
                                data-id="<?php echo $admin['id_admin']; ?>"
                                data-name="<?php echo htmlspecialchars($admin['ten_admin']); ?>"
                            <i class="bi bi-trash"></i> Xóa tài khoản
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Admin Roles and Permissions -->
        <div class="col-md-8 mb-4">
            <div class="card">
                <div class="card-header bg-white py-3">
                    <ul class="nav nav-tabs card-header-tabs" id="adminDetailsTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="roles-tab" data-bs-toggle="tab" data-bs-target="#roles" type="button" role="tab" aria-controls="roles" aria-selected="true">
                                Vai trò <span class="badge bg-secondary ms-1"><?php echo $roles_result->num_rows; ?></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="permissions-tab" data-bs-toggle="tab" data-bs-target="#permissions" type="button" role="tab" aria-controls="permissions" aria-selected="false">
                                Quyền hạn
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="activity-tab" data-bs-toggle="tab" data-bs-target="#activity" type="button" role="tab" aria-controls="activity" aria-selected="false">
                                Hoạt động gần đây
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="adminDetailsTabsContent">
                        <!-- Roles Tab -->
                        <div class="tab-pane fade show active" id="roles" role="tabpanel" aria-labelledby="roles-tab">
                            <?php if ($roles_result->num_rows > 0): ?>
                                <div class="list-group">
                                    <?php while($role = $roles_result->fetch_assoc()): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($role['ten_role']); ?></h6>
                                                <a href="view_role.php?id=<?php echo $role['id_role']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-eye"></i> Xem
                                                </a>
                                            </div>
                                            <p class="mb-1 text-muted"><?php echo htmlspecialchars($role['mo_ta'] ?? 'Không có mô tả'); ?></p>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <?php if ($admin['cap_bac'] == 3): ?>
                                        <p>Tài khoản này là Super Admin nên có tất cả các quyền hệ thống mà không cần gán vai trò cụ thể.</p>
                                    <?php else: ?>
                                        <p>Tài khoản này chưa được gán vai trò nào.</p>
                                        <?php if (hasPermission('admin_edit')): ?>
                                            <a href="edit_admin.php?id=<?php echo $admin_id; ?>" class="btn btn-sm btn-primary">
                                                <i class="bi bi-person-badge"></i> Gán vai trò
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Permissions Tab -->
                        <div class="tab-pane fade" id="permissions" role="tabpanel" aria-labelledby="permissions-tab">
                            <?php if ($admin['cap_bac'] == 3): ?>
                                <div class="alert alert-info mb-3">
                                    <i class="bi bi-info-circle me-2"></i> Tài khoản này là Super Admin nên có tất cả các quyền trong hệ thống.
                                </div>
                            <?php endif; ?>
                            
                            <?php if (count($grouped_permissions) > 0 || $admin['cap_bac'] == 3): ?>
                                <div class="accordion" id="permissionsAccordion">
                                    <?php 
                                    if ($admin['cap_bac'] == 3) {
                                        // For Super Admin, get all permissions
                                        $all_permissions_query = "SELECT * FROM permissions ORDER BY nhom_permission, ten_permission";
                                        $all_permissions_result = $conn->query($all_permissions_query);
                                        $grouped_permissions = [];
                                        while ($permission = $all_permissions_result->fetch_assoc()) {
                                            $group = $permission['nhom_permission'] ?: 'Khác';
                                            if (!isset($grouped_permissions[$group])) {
                                                $grouped_permissions[$group] = [];
                                            }
                                            $grouped_permissions[$group][] = $permission;
                                        }
                                    }
                                    
                                    $index = 0; 
                                    foreach ($grouped_permissions as $group_name => $permissions): 
                                        $index++; 
                                    ?>
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="heading<?php echo $index; ?>">
                                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $index; ?>" aria-expanded="false" aria-controls="collapse<?php echo $index; ?>">
                                                    <strong><?php echo htmlspecialchars(ucfirst($group_name)); ?></strong>
                                                    <span class="badge bg-primary rounded-pill ms-2"><?php echo count($permissions); ?></span>
                                                </button>
                                            </h2>
                                            <div id="collapse<?php echo $index; ?>" class="accordion-collapse collapse" aria-labelledby="heading<?php echo $index; ?>" data-bs-parent="#permissionsAccordion">
                                                <div class="accordion-body p-0">
                                                    <div class="table-responsive">
                                                        <table class="table table-sm table-hover mb-0">
                                                            <thead class="table-light">
                                                                <tr>
                                                                    <th>Tên quyền</th>
                                                                    <th>Mã quyền</th>
                                                                    <th>Mô tả</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php foreach ($permissions as $permission): ?>
                                                                    <tr>
                                                                        <td><?php echo htmlspecialchars($permission['ten_permission']); ?></td>
                                                                        <td><code><?php echo htmlspecialchars($permission['ma_permission']); ?></code></td>
                                                                        <td><?php echo htmlspecialchars($permission['mo_ta'] ?? ''); ?></td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle me-2"></i> Tài khoản này chưa có quyền hạn nào.
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Activity Tab -->
                        <div class="tab-pane fade" id="activity" role="tabpanel" aria-labelledby="activity-tab">
                            <?php if ($activity_result->num_rows > 0): ?>
                                <div class="timeline">
                                    <?php while ($activity = $activity_result->fetch_assoc()): ?>
                                        <div class="card mb-3">
                                            <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                                                <span>
                                                    <?php 
                                                    $icon = 'bi-activity';
                                                    switch ($activity['action_type']) {
                                                        case 'login':
                                                            $icon = 'bi-box-arrow-in-right';
                                                            break;
                                                        case 'logout':
                                                            $icon = 'bi-box-arrow-left';
                                                            break;
                                                        case 'update':
                                                        case 'edit':
                                                            $icon = 'bi-pencil';
                                                            break;
                                                        case 'delete':
                                                            $icon = 'bi-trash';
                                                            break;
                                                        case 'create':
                                                        case 'add':
                                                            $icon = 'bi-plus-circle';
                                                            break;
                                                        case 'enable_account':
                                                            $icon = 'bi-unlock';
                                                            break;
                                                        case 'disable_account':
                                                            $icon = 'bi-lock';
                                                            break;
                                                    }
                                                    ?>
                                                    <i class="bi <?php echo $icon; ?> me-2"></i>
                                                    <?php echo ucfirst($activity['action_type']); ?>
                                                </span>
                                                <small><?php echo date('d/m/Y H:i:s', strtotime($activity['created_at'])); ?></small>
                                            </div>
                                            <div class="card-body py-2">
                                                <p class="card-text"><?php echo nl2br(htmlspecialchars($activity['details'])); ?></p>
                                                <div class="d-flex justify-content-between">
                                                    <small class="text-muted">
                                                        <?php echo $activity['target_type']; ?> #<?php echo $activity['target_id']; ?>
                                                    </small>
                                                    <small class="text-muted">
                                                        IP: <?php echo $activity['ip_address']; ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle me-2"></i> Chưa có hoạt động nào được ghi nhận.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Toggle Status Modal -->
<div class="modal fade" id="toggleStatusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="toggleStatusTitle">Thay đổi trạng thái</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="toggleStatusForm" action="process_admin_status.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="admin_id" id="toggleStatusAdminId" value="">
                    <input type="hidden" name="new_status" id="toggleStatusNewStatus" value="">
                    <p id="toggleStatusMessage"></p>
                    
                    <div id="lockReasonContainer" class="mb-3">
                        <label for="lockReason" class="form-label">Lý do khóa tài khoản</label>
                        <textarea class="form-control" id="lockReason" name="reason" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn" id="toggleStatusSubmitBtn">Xác nhận</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Admin Modal -->
<div class="modal fade" id="deleteAdminModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Xác nhận xóa nhân viên</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="deleteAdminForm" action="process_admin_delete.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="admin_id" id="deleteAdminId" value="">
                    <p>Bạn có chắc chắn muốn xóa nhân viên <strong id="deleteAdminName"></strong>?</p>
                    <p class="text-danger">Lưu ý: Hành động này không thể hoàn tác và có thể ảnh hưởng đến tính toàn vẹn của dữ liệu!</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-danger">Xác nhận xóa</button>
                </div>
            </form>
        </div>
    </div>
</div>

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
