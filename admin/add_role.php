<?php
// Set page title
$page_title = 'Thêm vai trò mới';

// Include header (will check for login)
include('includes/header.php');

// Include database connection
include('../config/config.php');

// Check if user has permission to add roles
checkPermissionRedirect('role_add');

// Get all permissions grouped by category
$query = "SELECT * FROM permissions ORDER BY nhom_permission, ten_permission";
$permissions_result = $conn->query($query);

$grouped_permissions = [];
while ($permission = $permissions_result->fetch_assoc()) {
    $group = $permission['nhom_permission'] ?: 'Khác';
    if (!isset($grouped_permissions[$group])) {
        $grouped_permissions[$group] = [];
    }
    $grouped_permissions[$group][] = $permission;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role_name = trim($_POST['role_name']);
    $role_description = trim($_POST['role_description']);
    $selected_permissions = $_POST['permissions'] ?? [];
    
    // Basic validation
    if (empty($role_name)) {
        $_SESSION['error_message'] = 'Vui lòng nhập tên vai trò!';
    } else {
        // Check if role name already exists
        $check_stmt = $conn->prepare("SELECT id_role FROM roles WHERE ten_role = ?");
        $check_stmt->bind_param("s", $role_name);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $_SESSION['error_message'] = 'Tên vai trò đã tồn tại!';
        } else {
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Insert role
                $insert_stmt = $conn->prepare("INSERT INTO roles (ten_role, mo_ta) VALUES (?, ?)");
                $insert_stmt->bind_param("ss", $role_name, $role_description);
                $insert_stmt->execute();
                
                $role_id = $conn->insert_id;
                
                // Insert permissions
                if (!empty($selected_permissions)) {
                    $insert_perm_stmt = $conn->prepare("INSERT INTO role_permissions (id_role, id_permission) VALUES (?, ?)");
                    
                    foreach ($selected_permissions as $permission_id) {
                        $insert_perm_stmt->bind_param("ii", $role_id, $permission_id);
                        $insert_perm_stmt->execute();
                    }
                }
                
                // Commit transaction
                $conn->commit();
                
                $_SESSION['success_message'] = 'Thêm vai trò mới thành công!';
                header("Location: roles.php");
                exit();
                
            } catch (Exception $e) {
                // Rollback on error
                $conn->rollback();
                $_SESSION['error_message'] = 'Lỗi khi thêm vai trò: ' . $e->getMessage();
            }
        }
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
            <li class="breadcrumb-item"><a href="roles.php">Quản lý vai trò</a></li>
            <li class="breadcrumb-item active" aria-current="page">Thêm vai trò mới</li>
        </ol>
    </nav>
    
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Thêm vai trò mới</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="roles.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Quay lại
            </a>
        </div>
    </div>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['error_message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header bg-white py-3">
            <h5 class="card-title mb-0">Thông tin vai trò</h5>
        </div>
        <div class="card-body">
            <form method="post" action="">
                <div class="mb-3">
                    <label for="role_name" class="form-label">Tên vai trò <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="role_name" name="role_name" required>
                </div>
                
                <div class="mb-3">
                    <label for="role_description" class="form-label">Mô tả</label>
                    <textarea class="form-control" id="role_description" name="role_description" rows="3"></textarea>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Quyền hạn <span class="text-danger">*</span></label>
                    <div class="mb-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="selectAllPermissions">Chọn tất cả</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="deselectAllPermissions">Bỏ chọn tất cả</button>
                    </div>
                    
                    <div class="card mb-3">
                        <div class="card-body p-0">
                            <div class="accordion" id="permissionsAccordion">
                                <?php $index = 0; foreach ($grouped_permissions as $group_name => $permissions): $index++; ?>
                                    <div class="accordion-item">
                                        <h2 class="accordion-header" id="heading<?php echo $index; ?>">
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $index; ?>" aria-expanded="false" aria-controls="collapse<?php echo $index; ?>">
                                                <div class="d-flex align-items-center w-100">
                                                    <strong><?php echo htmlspecialchars(ucfirst($group_name)); ?></strong>
                                                    <div class="ms-auto">
                                                        <button type="button" class="btn btn-sm btn-link select-group" data-group="<?php echo $index; ?>">Chọn nhóm</button>
                                                    </div>
                                                </div>
                                            </button>
                                        </h2>
                                        <div id="collapse<?php echo $index; ?>" class="accordion-collapse collapse" aria-labelledby="heading<?php echo $index; ?>" data-bs-parent="#permissionsAccordion">
                                            <div class="accordion-body">
                                                <div class="row">
                                                    <?php foreach ($permissions as $permission): ?>
                                                        <div class="col-lg-4 col-md-6 mb-2">
                                                            <div class="form-check">
                                                                <input class="form-check-input permission-checkbox group-<?php echo $index; ?>" type="checkbox" name="permissions[]" value="<?php echo $permission['id_permission']; ?>" id="perm_<?php echo $permission['id_permission']; ?>">
                                                                <label class="form-check-label" for="perm_<?php echo $permission['id_permission']; ?>" title="<?php echo htmlspecialchars($permission['mo_ta'] ?? ''); ?>">
                                                                    <?php echo htmlspecialchars($permission['ten_permission']); ?>
                                                                    <small class="text-muted d-block"><?php echo htmlspecialchars($permission['ma_permission']); ?></small>
                                                                </label>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <hr>
                
                <div class="d-flex justify-content-between">
                    <a href="roles.php" class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i> Hủy
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Lưu vai trò
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<?php 
// JavaScript for the page
$page_specific_js = '
<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Select all permissions
        document.getElementById("selectAllPermissions").addEventListener("click", function() {
            document.querySelectorAll(".permission-checkbox").forEach(function(checkbox) {
                checkbox.checked = true;
            });
        });
        
        // Deselect all permissions
        document.getElementById("deselectAllPermissions").addEventListener("click", function() {
            document.querySelectorAll(".permission-checkbox").forEach(function(checkbox) {
                checkbox.checked = false;
            });
        });
        
        // Select group permissions
        document.querySelectorAll(".select-group").forEach(function(button) {
            button.addEventListener("click", function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const groupId = this.getAttribute("data-group");
                const checkboxes = document.querySelectorAll(".group-" + groupId);
                
                // Check if any checkbox in the group is unchecked
                let allChecked = true;
                checkboxes.forEach(function(checkbox) {
                    if (!checkbox.checked) {
                        allChecked = false;
                    }
                });
                
                // Toggle all checkboxes based on current state
                checkboxes.forEach(function(checkbox) {
                    checkbox.checked = !allChecked;
                });
            });
        });
    });
</script>
';

// Include footer
include('includes/footer.php');
?>
