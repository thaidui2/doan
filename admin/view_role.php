<?php
// Set page title
$page_title = 'Chi tiết vai trò';

// Include header (will check for login)
include('includes/header.php');

// Include database connection
include('../config/config.php');

// Check if user has permission to view roles
checkPermissionRedirect('role_view');

// Get role ID
$role_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Validate role ID
if ($role_id <= 0) {
    $_SESSION['error_message'] = 'ID vai trò không hợp lệ!';
    header('Location: roles.php');
    exit();
}

// Get role information
$role_stmt = $conn->prepare("
    SELECT r.*, COUNT(DISTINCT ar.id_admin) AS admin_count
    FROM roles r
    LEFT JOIN admin_roles ar ON r.id_role = ar.id_role
    WHERE r.id_role = ?
    GROUP BY r.id_role
");
$role_stmt->bind_param("i", $role_id);
$role_stmt->execute();
$role_result = $role_stmt->get_result();

if ($role_result->num_rows === 0) {
    $_SESSION['error_message'] = 'Không tìm thấy vai trò!';
    header('Location: roles.php');
    exit();
}

$role = $role_result->fetch_assoc();

// Get role permissions grouped by category
$permissions_query = $conn->prepare("
    SELECT p.*
    FROM permissions p
    JOIN role_permissions rp ON p.id_permission = rp.id_permission
    WHERE rp.id_role = ?
    ORDER BY p.nhom_permission, p.ten_permission
");
$permissions_query->bind_param("i", $role_id);
$permissions_query->execute();
$permissions_result = $permissions_query->get_result();

$grouped_permissions = [];
while ($permission = $permissions_result->fetch_assoc()) {
    $group = $permission['nhom_permission'] ?: 'Khác';
    if (!isset($grouped_permissions[$group])) {
        $grouped_permissions[$group] = [];
    }
    $grouped_permissions[$group][] = $permission;
}

// Get admins with this role
$stmt = $conn->prepare("
    SELECT a.id_admin, a.taikhoan, a.ten_admin
    FROM admin a 
    JOIN admin_roles ar ON a.id_admin = ar.id_admin 
    WHERE ar.id_role = ?
    ORDER BY a.ten_admin ASC
");
$stmt->bind_param("i", $role_id);
$stmt->execute();
$admins_result = $stmt->get_result();
?>

<!-- Include sidebar -->
<?php include('includes/sidebar.php'); ?>

<!-- Main content -->
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Trang chủ</a></li>
            <li class="breadcrumb-item"><a href="roles.php">Quản lý vai trò</a></li>
            <li class="breadcrumb-item active" aria-current="page">Chi tiết vai trò</li>
        </ol>
    </nav>
    
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Chi tiết vai trò: <?php echo htmlspecialchars($role['ten_role']); ?></h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <?php if (hasPermission('role_edit')): ?>
            <a href="edit_role.php?id=<?php echo $role_id; ?>" class="btn btn-sm btn-outline-primary me-2">
                <i class="bi bi-pencil"></i> Chỉnh sửa
            </a>
            <?php endif; ?>
            <a href="roles.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Quay lại
            </a>
        </div>
    </div>
    
    <div class="row">
        <!-- Role Info -->
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0">Thông tin vai trò</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6 class="fw-bold">Tên vai trò</h6>
                        <p><?php echo htmlspecialchars($role['ten_role']); ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="fw-bold">Mô tả</h6>
                        <p><?php echo htmlspecialchars($role['mo_ta'] ?? 'Không có mô tả'); ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="fw-bold">Ngày tạo</h6>
                        <p><?php echo date('d/m/Y H:i', strtotime($role['ngay_tao'])); ?></p>
                    </div>
                    
                    <div>
                        <h6 class="fw-bold">Số nhân viên có vai trò này</h6>
                        <p><?php echo $role['admin_count']; ?> nhân viên</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Role Permissions -->
        <div class="col-md-8 mb-4">
            <div class="card">
                <div class="card-header bg-white py-3">
                    <ul class="nav nav-tabs card-header-tabs" id="roleTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="permissions-tab" data-bs-toggle="tab" data-bs-target="#permissions" type="button" role="tab" aria-controls="permissions" aria-selected="true">
                                Quyền hạn <span class="badge bg-secondary ms-1"><?php echo $permissions_result->num_rows; ?></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="admins-tab" data-bs-toggle="tab" data-bs-target="#admins" type="button" role="tab" aria-controls="admins" aria-selected="false">
                                Nhân viên <span class="badge bg-secondary ms-1"><?php echo $admins_result->num_rows; ?></span>
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="roleTabsContent">
                        <!-- Permissions Tab -->
                        <div class="tab-pane fade show active" id="permissions" role="tabpanel" aria-labelledby="permissions-tab">
                            <?php if (count($grouped_permissions) > 0): ?>
                                <div class="accordion" id="permissionsAccordion">
                                    <?php $index = 0; foreach ($grouped_permissions as $group_name => $permissions): $index++; ?>
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="heading<?php echo $index; ?>">
                                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $index; ?>" aria-expanded="true" aria-controls="collapse<?php echo $index; ?>">
                                                    <strong><?php echo htmlspecialchars(ucfirst($group_name)); ?></strong>
                                                    <span class="badge bg-primary rounded-pill ms-2"><?php echo count($permissions); ?></span>
                                                </button>
                                            </h2>
                                            <div id="collapse<?php echo $index; ?>" class="accordion-collapse collapse show" aria-labelledby="heading<?php echo $index; ?>">
                                                <div class="accordion-body">
                                                    <div class="table-responsive">
                                                        <table class="table table-sm table-hover">
                                                            <thead>
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
                                    <i class="bi bi-exclamation-triangle me-2"></i> Vai trò này chưa được cấp quyền hạn nào.
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Admins Tab -->
                        <div class="tab-pane fade" id="admins" role="tabpanel" aria-labelledby="admins-tab">
                            <?php if ($admins_result->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Tên nhân viên</th>
                                                <th>Tên đăng nhập</th>
                                                <th>Email</th>
                                                <th>Cấp bậc</th>
                                                <th>Thao tác</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($admin = $admins_result->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo $admin['id_admin']; ?></td>
                                                    <td><?php echo htmlspecialchars($admin['ho_ten']); ?></td>
                                                    <td><?php echo htmlspecialchars($admin['ten_dang_nhap']); ?></td>
                                                    <td><?php echo htmlspecialchars($admin['email'] ?? 'N/A'); ?></td>
                                                    <td>
                                                        <?php if ($admin['cap_bac'] == 3): ?>
                                                            <span class="badge bg-danger">Super Admin</span>
                                                        <?php elseif ($admin['cap_bac'] == 2): ?>
                                                            <span class="badge bg-primary">Admin</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">Nhân viên</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if (hasPermission('admin_view')): ?>
                                                        <a href="view_admin.php?id=<?php echo $admin['id_admin']; ?>" class="btn btn-sm btn-outline-primary">
                                                            <i class="bi bi-eye"></i> Xem
                                                        </a>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle me-2"></i> Chưa có nhân viên nào được gán vai trò này.
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
// Include footer
include('includes/footer.php');
?>
