<?php
// Thiết lập tiêu đề trang
$page_title = 'Kiểm tra quyền';

// Include header (kiểm tra đăng nhập)
include('includes/header.php');

// Include database connection
include('../config/config.php');

// Kiểm tra admin đang đăng nhập
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

$admin_id = $_SESSION['admin_id'];

// 1. Lấy thông tin admin
$admin_query = $conn->prepare("SELECT a.*, ar.id_role 
                              FROM admin a 
                              LEFT JOIN admin_roles ar ON a.id_admin = ar.id_admin 
                              WHERE a.id_admin = ?");
$admin_query->bind_param("i", $admin_id);
$admin_query->execute();
$admin_result = $admin_query->get_result();
$admin_info = $admin_result->fetch_assoc();

// 2. Lấy thông tin về vai trò
$role_id = $admin_info['id_role'] ?? null;
$role_info = null;
if ($role_id) {
    $role_query = $conn->prepare("SELECT * FROM roles WHERE id_role = ?");
    $role_query->bind_param("i", $role_id);
    $role_query->execute();
    $role_result = $role_query->get_result();
    $role_info = $role_result->fetch_assoc();
}

// 3. Kiểm tra các quyền liên quan đến khuyến mãi
$promo_permissions = ['promo_view', 'promo_add', 'promo_edit', 'promo_delete'];
$permissions_status = [];

foreach ($promo_permissions as $permission) {
    $permissions_status[$permission] = hasPermission($permission);
    
    // Nếu có vai trò, kiểm tra quyền trong DB
    if ($role_id) {
        $perm_query = $conn->prepare("
            SELECT p.* FROM permissions p
            JOIN role_permissions rp ON p.id_permission = rp.id_permission
            WHERE rp.id_role = ? AND p.ma_permission = ?
        ");
        $perm_query->bind_param("is", $role_id, $permission);
        $perm_query->execute();
        $perm_result = $perm_query->get_result();
        $permissions_status[$permission . '_exists'] = $perm_result->num_rows > 0;
        
        if ($perm_result->num_rows > 0) {
            $permissions_status[$permission . '_details'] = $perm_result->fetch_assoc();
        }
    }
}

// Include sidebar
include('includes/sidebar.php');
?>

<!-- Main content -->
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Kiểm tra quyền khuyến mãi</h1>
    </div>
    
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Thông tin người dùng</h5>
                </div>
                <div class="card-body">
                    <table class="table table-bordered">
                        <tr>
                            <th width="25%">ID Admin:</th>
                            <td><?php echo $admin_info['id_admin']; ?></td>
                        </tr>
                        <tr>
                            <th>Tên đăng nhập:</th>
                            <td><?php echo htmlspecialchars($admin_info['taikhoan']); ?></td>
                        </tr>
                        <tr>
                            <th>Tên:</th>
                            <td><?php echo htmlspecialchars($admin_info['ten_admin']); ?></td>
                        </tr>
                        <tr>
                            <th>Cấp bậc:</th>
                            <td><?php echo $admin_info['cap_bac'] == 2 ? 'Admin' : 'Quản lý'; ?></td>
                        </tr>
                        <tr>
                            <th>Vai trò (Role):</th>
                            <td>
                                <?php if ($role_info): ?>
                                    ID: <?php echo $role_info['id_role']; ?> - 
                                    <?php echo htmlspecialchars($role_info['ten_role']); ?>
                                <?php else: ?>
                                    <span class="text-danger">Không có vai trò</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Quyền khuyến mãi</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info mb-4">
                        <p class="mb-0">Bảng dưới đây hiển thị quyền khuyến mãi của tài khoản. Nếu các quyền bị thiếu, hãy thực hiện các bước khắc phục.</p>
                    </div>
                    
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Quyền</th>
                                <th>Kết quả hasPermission()</th>
                                <th>Tồn tại trong DB</th>
                                <th>Thông tin quyền</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($promo_permissions as $permission): ?>
                            <tr>
                                <td><code><?php echo $permission; ?></code></td>
                                <td>
                                    <?php if ($permissions_status[$permission]): ?>
                                        <span class="badge bg-success">Có quyền</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Không có quyền</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($permissions_status[$permission . '_exists'])): ?>
                                        <span class="badge bg-success">Đã gán trong DB</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Chưa gán trong DB</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($permissions_status[$permission . '_details'])): ?>
                                        ID: <?php echo $permissions_status[$permission . '_details']['id_permission']; ?>, 
                                        Tên: <?php echo htmlspecialchars($permissions_status[$permission . '_details']['ten_permission']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Không có thông tin</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="card-footer bg-white">
                    <h5 class="mt-3 mb-3">Các bước khắc phục:</h5>
                    <ol class="list-group list-group-numbered mb-3">
                        <li class="list-group-item">Đăng xuất và đăng nhập lại để cập nhật phiên làm việc</li>
                        <li class="list-group-item">Kiểm tra xem người dùng đã được gán đúng vai trò Quản lý (vai trò ID 2) chưa</li>
                        <li class="list-group-item">Thực thi lại các câu SQL để thêm quyền</li>
                        <li class="list-group-item">Kiểm tra xem quyền trên bảng khuyen-mai.php có đúng tên không (promo_add, promo_edit, promo_delete)</li>
                    </ol>
                    
                    <h5 class="mt-4 mb-3">Khắc phục nhanh:</h5>
                    <div class="d-grid gap-2">
                        <a href="add_promo_permissions.php" class="btn btn-primary">
                            <i class="bi bi-lightning-charge me-2"></i>Thêm quyền khuyến mãi tự động
                        </a>
                        <a href="logout.php" class="btn btn-outline-secondary">
                            <i class="bi bi-box-arrow-right me-2"></i>Đăng xuất để cập nhật phiên
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include('includes/footer.php'); ?>