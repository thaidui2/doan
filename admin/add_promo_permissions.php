<?php
// Kết nối cơ sở dữ liệu
require_once('../config/config.php');

// Kiểm tra xác thực admin
require_once('includes/header.php');

// Không yêu cầu quản trị viên cao nhất để chạy script
// Bất kỳ admin nào cũng có thể thực hiện để sửa quyền của chính họ
// if (!isset($_SESSION['admin_id'])) {
//     $_SESSION['error_message'] = "Bạn cần đăng nhập để thực hiện chức năng này!";
//     header('Location: login.php');
//     exit();
// }

// Định nghĩa các quyền cần thiết về khuyến mãi
$promo_permissions = [
    [
        'ten_permission' => 'Xem khuyến mãi',
        'ma_permission' => 'promo_view',
        'mo_ta' => 'Quyền xem mã khuyến mãi',
        'nhom_permission' => 'promos'
    ],
    [
        'ten_permission' => 'Thêm khuyến mãi',
        'ma_permission' => 'promo_add',
        'mo_ta' => 'Quyền thêm mã khuyến mãi mới',
        'nhom_permission' => 'promos'
    ],
    [
        'ten_permission' => 'Sửa khuyến mãi',
        'ma_permission' => 'promo_edit',
        'mo_ta' => 'Quyền chỉnh sửa mã khuyến mãi',
        'nhom_permission' => 'promos'
    ],
    [
        'ten_permission' => 'Xóa khuyến mãi',
        'ma_permission' => 'promo_delete',
        'mo_ta' => 'Quyền xóa mã khuyến mãi',
        'nhom_permission' => 'promos'
    ]
];

// ID của vai trò "Quản lý" và tài khoản hiện tại
$manager_role_id = 2;
$current_admin_id = $_SESSION['admin_id'];

// Kiểm tra vai trò của admin hiện tại
$role_query = $conn->prepare("SELECT r.id_role FROM roles r 
                             JOIN admin_roles ar ON r.id_role = ar.id_role 
                             WHERE ar.id_admin = ?");
$role_query->bind_param("i", $current_admin_id);
$role_query->execute();
$role_result = $role_query->get_result();
$admin_roles = [];
while ($role = $role_result->fetch_assoc()) {
    $admin_roles[] = $role['id_role'];
}

$conn->begin_transaction();
try {
    $added_permissions = [];
    $updated_permissions = [];
    
    foreach ($promo_permissions as $permission) {
        // Kiểm tra xem quyền đã tồn tại chưa
        $check_query = $conn->prepare("SELECT id_permission FROM permissions WHERE ma_permission = ?");
        $check_query->bind_param("s", $permission['ma_permission']);
        $check_query->execute();
        $result = $check_query->get_result();
        
        if ($result->num_rows == 0) {
            // Thêm quyền mới
            $insert_query = $conn->prepare("INSERT INTO permissions (ten_permission, ma_permission, mo_ta, nhom_permission) VALUES (?, ?, ?, ?)");
            $insert_query->bind_param("ssss", 
                $permission['ten_permission'], 
                $permission['ma_permission'], 
                $permission['mo_ta'], 
                $permission['nhom_permission']
            );
            $insert_query->execute();
            $permission_id = $conn->insert_id;
            $added_permissions[] = $permission['ma_permission'];
        } else {
            // Quyền đã tồn tại, lấy ID
            $row = $result->fetch_assoc();
            $permission_id = $row['id_permission'];
        }
        
        // Kiểm tra xem quyền đã được gán cho vai trò quản lý chưa
        $check_role_query = $conn->prepare("SELECT id FROM role_permissions WHERE id_role = ? AND id_permission = ?");
        $check_role_query->bind_param("ii", $manager_role_id, $permission_id);
        $check_role_query->execute();
        $role_result = $check_role_query->get_result();
        
        if ($role_result->num_rows == 0) {
            // Gán quyền cho vai trò quản lý
            $assign_query = $conn->prepare("INSERT INTO role_permissions (id_role, id_permission) VALUES (?, ?)");
            $assign_query->bind_param("ii", $manager_role_id, $permission_id);
            $assign_query->execute();
            $updated_permissions[] = $permission['ma_permission'];
        }
    }
    
    // Kiểm tra xem admin hiện tại có vai trò Quản lý không
    $is_manager = in_array($manager_role_id, $admin_roles);
    
    // Commit transaction
    $conn->commit();
    
    $_SESSION['success_message'] = "Đã cập nhật quyền quản lý khuyến mãi thành công!";
    
    if ($is_manager) {
        $_SESSION['success_message'] .= " Bạn có vai trò Quản lý, đã được gán các quyền mới. Vui lòng đăng xuất và đăng nhập lại để cập nhật phiên làm việc.";
    }
    
    // Ghi log hành động
    if (function_exists('logAdminActivity')) {
        logAdminActivity($conn, $current_admin_id, 'update', 'permissions', 0, 'Cập nhật quyền quản lý khuyến mãi cho vai trò Quản lý');
    }
    
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error_message'] = "Có lỗi xảy ra: " . $e->getMessage();
}

// Bao gồm sidebar và hiển thị thông tin
include('includes/sidebar.php');
?>

<!-- Main content -->
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Cập nhật quyền khuyến mãi</h1>
    </div>
    
    <?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i><?php echo $_SESSION['success_message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i><?php echo $_SESSION['error_message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>
    
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h5 class="card-title mb-0">Kết quả cập nhật quyền</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6>Quyền đã thêm mới:</h6>
                    <?php if (!empty($added_permissions)): ?>
                    <ul class="list-group mb-4">
                        <?php foreach($added_permissions as $perm): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <?php echo $perm; ?>
                            <span class="badge bg-success rounded-pill">Đã thêm</span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php else: ?>
                    <p class="text-muted">Không có quyền mới nào được thêm.</p>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <h6>Quyền đã gán cho Quản lý:</h6>
                    <?php if (!empty($updated_permissions)): ?>
                    <ul class="list-group">
                        <?php foreach($updated_permissions as $perm): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <?php echo $perm; ?>
                            <span class="badge bg-primary rounded-pill">Đã gán</span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php else: ?>
                    <p class="text-muted">Không có quyền mới nào được gán (có thể đã được gán từ trước).</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <hr class="my-4">
            
            <div class="d-flex justify-content-between">
                <a href="roles.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left me-2"></i> Quay lại trang vai trò
                </a>
                
                <div>
                    <a href="check_permissions.php" class="btn btn-info me-2">
                        <i class="bi bi-search me-2"></i> Kiểm tra quyền
                    </a>
                    <a href="logout.php" class="btn btn-primary">
                        <i class="bi bi-arrow-clockwise me-2"></i> Đăng xuất để cập nhật phiên
                    </a>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include('includes/footer.php'); ?>