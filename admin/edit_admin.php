<?php
// Set page title
$page_title = 'Chỉnh sửa nhân viên';

// Include header (will check for login)
include('includes/header.php');

// Include database connection
include('../config/config.php');

// Check if user has permission to edit admins
checkPermissionRedirect('admin_edit');

// Get admin ID
$admin_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Validate admin ID
if ($admin_id <= 0) {
    $_SESSION['error_message'] = 'ID nhân viên không hợp lệ!';
    header('Location: admins.php');
    exit();
}

// Get admin information
$admin_query = $conn->prepare("SELECT * FROM admin WHERE id_admin = ?");
$admin_query->bind_param("i", $admin_id);
$admin_query->execute();
$admin_result = $admin_query->get_result();
$admin = $admin_result->fetch_assoc();

// Nếu cần, in thông tin để debug
// var_dump($admin); exit;

if ($admin_result->num_rows === 0) {
    $_SESSION['error_message'] = 'Không tìm thấy nhân viên!';
    header('Location: admins.php');
    exit();
}

// Check if current admin can edit this admin
if ($admin['cap_bac'] == 3 && $_SESSION['admin_level'] < 3 && $admin_id != $_SESSION['admin_id']) {
    $_SESSION['error_message'] = 'Bạn không có quyền chỉnh sửa thông tin của Super Admin.';
    header('Location: admins.php');
    exit();
}

// Get all roles
$roles_query = "SELECT * FROM roles ORDER BY ten_role";
$roles_result = $conn->query($roles_query);

// Get admin's current roles
$admin_roles = [];
$admin_roles_query = $conn->prepare("SELECT id_role FROM admin_roles WHERE id_admin = ?");
$admin_roles_query->bind_param("i", $admin_id);
$admin_roles_query->execute();
$admin_roles_result = $admin_roles_query->get_result();

while ($role = $admin_roles_result->fetch_assoc()) {
    $admin_roles[] = $role['id_role'];
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $taikhoan = trim($_POST['taikhoan']);  // Không phải ten_dang_nhap
    $ten_admin = trim($_POST['ten_admin']); // Không phải ho_ten
    $matkhau = $_POST['matkhau'];           // Không phải mat_khau
    $email = trim($_POST['email']);
    $cap_bac = (int)$_POST['cap_bac'];
    $trang_thai = isset($_POST['trang_thai']) ? 1 : 0;
    
    // Thêm đoạn này sau các lệnh lấy dữ liệu từ form (sau dòng $trang_thai = isset($_POST['trang_thai']) ? 1 : 0;)
    $selected_roles = isset($_POST['roles']) ? $_POST['roles'] : [];
    
    // Basic validation
    $errors = [];
    
    if (empty($ten_admin)) {
        $errors[] = 'Vui lòng nhập họ tên.';
    }
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email không hợp lệ.';
    }
    
    if (!empty($matkhau) && strlen($matkhau) < 8) {
        $errors[] = 'Mật khẩu mới phải có ít nhất 8 ký tự.';
    }
    
    // Check if email already exists (if provided and changed)
    if (!empty($email) && $email != $admin['email']) {
        $check_email = $conn->prepare("SELECT id_admin FROM admin WHERE email = ? AND id_admin != ?");
        $check_email->bind_param("si", $email, $admin_id);
        $check_email->execute();
        if ($check_email->get_result()->num_rows > 0) {
            $errors[] = 'Email đã được sử dụng.';
        }
    }
    
    // Validate cap_bac (only accept valid values)
    if ($cap_bac < 1 || $cap_bac > 2) {
        $errors[] = 'Cấp bậc không hợp lệ.';
    }
    
    // Prevent self-locking
    if ($admin_id == $_SESSION['admin_id'] && $trang_thai == 0) {
        $errors[] = 'Bạn không thể khóa tài khoản của chính mình.';
    }
    
    if (empty($errors)) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Build query components
            $query_parts = [];
            $query_params = [];
            $query_types = "";
            
            // Always update these fields
            $query_parts[] = "ten_admin = ?";
            $query_types .= "s";
            $query_params[] = $ten_admin;
            
            // Update email if provided
            if (!empty($email)) {
                $query_parts[] = "email = ?";
                $query_types .= "s";
                $query_params[] = $email;
            }
            
            // Update password if provided
            if (!empty($matkhau)) {
                $hashed_password = password_hash($matkhau, PASSWORD_DEFAULT);
                $query_parts[] = "matkhau = ?";
                $query_types .= "s";
                $query_params[] = $hashed_password;
            }
            
            // Update cap_bac and trang_thai
            $query_parts[] = "cap_bac = ?";
            $query_types .= "i";
            $query_params[] = $cap_bac;
            
            $query_parts[] = "trang_thai = ?";
            $query_types .= "i";
            $query_params[] = $trang_thai;
            
            // Add admin_id to params for WHERE clause
            $query_types .= "i";
            $query_params[] = $admin_id;
            
            // Build and execute update query
            $update_query = "UPDATE admin SET ten_admin = ?, email = ?, cap_bac = ?, trang_thai = ? WHERE id_admin = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param($query_types, ...$query_params);
            $update_stmt->execute();
            
            // Insert new admin
            $insert_stmt = $conn->prepare("
                INSERT INTO admin (taikhoan, matkhau, ten_admin, email, cap_bac, trang_thai) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $insert_stmt->bind_param("ssssis", $taikhoan, $hashed_password, $ten_admin, $email, $cap_bac, $trang_thai);
            
            // Update roles
            
            // Delete existing roles
            $delete_roles = $conn->prepare("DELETE FROM admin_roles WHERE id_admin = ?");
            $delete_roles->bind_param("i", $admin_id);
            $delete_roles->execute();
            
            // Insert new roles
            if (!empty($selected_roles)) {
                $insert_role_stmt = $conn->prepare("INSERT INTO admin_roles (id_admin, id_role) VALUES (?, ?)");
                
                foreach ($selected_roles as $role_id) {
                    $insert_role_stmt->bind_param("ii", $admin_id, $role_id);
                    $insert_role_stmt->execute();
                }
            }
            
            // Log the action
            $admin_name = $_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? 'Admin';
            
            // Check if the log table exists
            $table_check = $conn->query("SHOW TABLES LIKE 'admin_actions'");
            
            if ($table_check->num_rows === 0) {
                // Create table if it doesn't exist
                $create_table = "CREATE TABLE admin_actions (
                    id INT(11) NOT NULL AUTO_INCREMENT,
                    admin_id INT(11) NOT NULL,
                    action_type VARCHAR(100) NOT NULL,
                    target_type VARCHAR(50) NOT NULL,
                    target_id INT(11) NOT NULL,
                    details TEXT DEFAULT NULL,
                    ip_address VARCHAR(45) DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    KEY admin_id (admin_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
                
                $conn->query($create_table);
            }
            
            // Add log entry
            $log_stmt = $conn->prepare("
                INSERT INTO admin_actions (admin_id, action_type, target_type, target_id, details, ip_address) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $action = 'edit';
            $target_type = 'admin'; // Đặt chuỗi vào biến
            $username_display = isset($admin['taikhoan']) ? $admin['taikhoan'] : 'Unknown';
            $details = "Chỉnh sửa thông tin admin #$admin_id ($username_display) bởi $admin_name";
            $ip = $_SERVER['REMOTE_ADDR'];
            
            $log_stmt->bind_param("ississ", $_SESSION['admin_id'], $action, $target_type, $admin_id, $details, $ip);
            $log_stmt->execute();
            
            // Commit transaction
            $conn->commit();
            
            // Update session variables if editing own account
            if ($admin_id == $_SESSION['admin_id']) {
                $_SESSION['admin_name'] = $ten_admin;
                if ($cap_bac != $_SESSION['admin_level']) {
                    $_SESSION['admin_level'] = $cap_bac;
                    // Clear cached permissions to force refresh
                    unset($_SESSION['admin_permissions']);
                    unset($_SESSION['admin_roles']);
                }
            }
            
            $_SESSION['success_message'] = 'Cập nhật thông tin nhân viên thành công!';
            header("Location: view_admin.php?id=$admin_id");
            exit();
            
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            $_SESSION['error_message'] = 'Lỗi khi cập nhật thông tin nhân viên: ' . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = implode('<br>', $errors);
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
            <li class="breadcrumb-item"><a href="admins.php">Quản lý nhân viên</a></li>
            <li class="breadcrumb-item active" aria-current="page">Chỉnh sửa nhân viên</li>
        </ol>
    </nav>
    
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Chỉnh sửa thông tin nhân viên</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="view_admin.php?id=<?php echo $admin_id; ?>" class="btn btn-sm btn-outline-secondary me-2">
                <i class="bi bi-eye"></i> Xem chi tiết
            </a>
            <a href="admins.php" class="btn btn-sm btn-outline-secondary">
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
            <h5 class="card-title mb-0">Thông tin nhân viên</h5>
        </div>
        <div class="card-body">
            <form method="post" action="">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="taikhoan" class="form-label">Tên đăng nhập</label>
                            <input type="text" class="form-control" id="taikhoan" value="<?php echo htmlspecialchars($admin['taikhoan']); ?>" disabled>
                            <div class="form-text">Tên đăng nhập không thể thay đổi</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="ten_admin" class="form-label">Họ và tên <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="ten_admin" name="ten_admin" value="<?php echo htmlspecialchars($admin['ten_admin']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($admin['email'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="matkhau" class="form-label">Mật khẩu mới</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="matkhau" name="matkhau">
                                <button class="btn btn-outline-secondary" type="button" id="togglePasswordBtn">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button class="btn btn-outline-secondary" type="button" id="generatePasswordBtn">
                                    <i class="bi bi-magic"></i> Tạo
                                </button>
                            </div>
                            <div class="form-text">Để trống nếu không muốn thay đổi mật khẩu. Mật khẩu mới phải có ít nhất 8 ký tự.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="cap_bac" class="form-label">Cấp bậc <span class="text-danger">*</span></label>
                            <select class="form-select" id="cap_bac" name="cap_bac" required>
                                <option value="1" <?php echo ($admin['cap_bac'] == 1) ? 'selected' : ''; ?>>Quản lý</option>
                                <option value="2" <?php echo ($admin['cap_bac'] == 2) ? 'selected' : ''; ?>>Admin</option>
                            </select>
                            <div class="form-text">Chọn cấp bậc phù hợp với vai trò của nhân viên</div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input class="form-check-input" type="checkbox" id="trang_thai" name="trang_thai" value="1" 
                                <?php echo $admin['trang_thai'] ? 'checked' : ''; ?>
                                <?php echo ($admin_id == $_SESSION['admin_id']) ? 'disabled' : ''; ?>>
                            <label class="form-check-label" for="trang_thai">Tài khoản hoạt động</label>
                            <?php if ($admin_id == $_SESSION['admin_id']): ?>
                                <input type="hidden" name="trang_thai" value="1">
                                <div class="form-text">Bạn không thể khóa tài khoản của chính mình</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <hr>
                
                <div class="mb-4">
                    <label class="form-label">Vai trò</label>
                    <?php if ($admin['cap_bac'] == 3): ?>
                        <div class="alert alert-info mb-3">
                            <i class="bi bi-info-circle me-2"></i> Super Admin có tất cả các quyền mà không cần phải gán vai trò cụ thể.
                        </div>
                    <?php endif; ?>
                    
                    <div class="card">
                        <div class="card-body">
                            <?php if ($roles_result->num_rows > 0): ?>
                                <div class="row">
                                    <?php while ($role = $roles_result->fetch_assoc()): ?>
                                        <div class="col-md-4 mb-2">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="role_<?php echo $role['id_role']; ?>" 
                                                    name="roles[]" value="<?php echo $role['id_role']; ?>"
                                                    <?php echo in_array($role['id_role'], $admin_roles) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="role_<?php echo $role['id_role']; ?>">
                                                    <?php echo htmlspecialchars($role['ten_role']); ?>
                                                    <small class="text-muted d-block"><?php echo htmlspecialchars($role['mo_ta'] ?? ''); ?></small>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning mb-0">
                                    Chưa có vai trò nào. <a href="add_role.php">Tạo vai trò mới</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between">
                    <a href="view_admin.php?id=<?php echo $admin_id; ?>" class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i> Hủy
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Lưu thay đổi
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
        // Password toggle visibility
        const togglePasswordBtn = document.getElementById("togglePasswordBtn");
        const passwordInput = document.getElementById("matkhau");
        
        togglePasswordBtn.addEventListener("click", function() {
            const type = passwordInput.getAttribute("type") === "password" ? "text" : "password";
            passwordInput.setAttribute("type", type);
            
            // Toggle icon
            const icon = this.querySelector("i");
            icon.classList.toggle("bi-eye");
            icon.classList.toggle("bi-eye-slash");
        });
        
        // Password generator
        const generatePasswordBtn = document.getElementById("generatePasswordBtn");
        
        generatePasswordBtn.addEventListener("click", function() {
            const length = 12;
            const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+";
            let password = "";
            
            for (let i = 0; i < length; i++) {
                const randomIndex = Math.floor(Math.random() * charset.length);
                password += charset[randomIndex];
            }
            
            passwordInput.value = password;
            passwordInput.setAttribute("type", "text");
            
            // Update toggle button icon
            const icon = togglePasswordBtn.querySelector("i");
            if (icon.classList.contains("bi-eye")) {
                icon.classList.replace("bi-eye", "bi-eye-slash");
            }
        });
        
        // Form validation
        const form = document.querySelector("form");
        
        form.addEventListener("submit", function(event) {
            const password = document.getElementById("matkhau").value;
            
            let isValid = true;
            let errorMessage = "";
            
            if (password && password.length < 8) {
                isValid = false;
                errorMessage += "Mật khẩu mới phải có ít nhất 8 ký tự.<br>";
            }
            
            if (!isValid) {
                event.preventDefault();
                
                const errorAlert = document.createElement("div");
                errorAlert.className = "alert alert-danger alert-dismissible fade show";
                errorAlert.role = "alert";
                errorAlert.innerHTML = errorMessage;
                
                const closeButton = document.createElement("button");
                closeButton.type = "button";
                closeButton.className = "btn-close";
                closeButton.setAttribute("data-bs-dismiss", "alert");
                closeButton.setAttribute("aria-label", "Close");
                
                errorAlert.appendChild(closeButton);
                
                // Insert error alert after the heading
                const heading = document.querySelector(".border-bottom");
                heading.insertAdjacentElement("afterend", errorAlert);
                
                // Scroll to top
                window.scrollTo(0, 0);
            }
        });
    });
</script>
';

// Include footer
include('includes/footer.php');
?>
