<?php
ob_start();
// Set page title
$page_title = 'Chỉnh sửa tài khoản quản trị';

// Include header (will check for login)
include('includes/header.php');

// Include database connection
include('../config/config.php');

// Check if user has admin permission (admin_level should be 2 for admin)
if ($admin_level < 2) {
    $_SESSION['error_message'] = 'Bạn không có quyền thực hiện thao tác này.';
    header('Location: admins.php');
    exit();
}

// Get user ID
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Validate user ID
if ($user_id <= 0) {
    $_SESSION['error_message'] = 'ID tài khoản không hợp lệ!';
    header('Location: admins.php');
    exit();
}

// Get user information - updated for new schema
$user_query = $conn->prepare("SELECT * FROM users WHERE id = ? AND (loai_user = 1 OR loai_user = 2)");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user_result = $user_query->get_result();
$user = $user_result->fetch_assoc();

if ($user_result->num_rows === 0) {
    $_SESSION['error_message'] = 'Không tìm thấy tài khoản quản trị!';
    header('Location: admins.php');
    exit();
}

// Prevent editing super admin if you're not super admin yourself
if ($user['loai_user'] == 2 && $_SESSION['admin_level'] < 2 && $user_id != $_SESSION['admin_id']) {
    $_SESSION['error_message'] = 'Bạn không có quyền chỉnh sửa thông tin của quản trị viên.';
    header('Location: admins.php');
    exit();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $taikhoan = $user['taikhoan'];
    $ten = trim($_POST['ten']);
    $email = trim($_POST['email']);
    $matkhau = $_POST['matkhau'];
    $loai_user = (int)$_POST['loai_user'];
    $trang_thai = isset($_POST['trang_thai']) ? 1 : 0;
    
    // Basic validation
    $errors = [];
    
    if (empty($ten)) {
        $errors[] = 'Vui lòng nhập họ tên.';
    }
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email không hợp lệ.';
    }
    
    if (!empty($matkhau) && strlen($matkhau) < 8) {
        $errors[] = 'Mật khẩu mới phải có ít nhất 8 ký tự.';
    }
    
    // Check if email already exists (if provided and changed)
    if (!empty($email) && $email != $user['email']) {
        $check_email = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check_email->bind_param("si", $email, $user_id);
        $check_email->execute();
        if ($check_email->get_result()->num_rows > 0) {
            $errors[] = 'Email đã được sử dụng.';
        }
    }
    
    // Validate loai_user (only accept valid values)
    if ($loai_user != 1 && $loai_user != 2) {
        $errors[] = 'Loại tài khoản không hợp lệ.';
    }
    
    // Prevent self-locking
    if ($user_id == $_SESSION['admin_id'] && $trang_thai == 0) {
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
            $query_parts[] = "ten = ?";
            $query_types .= "s";
            $query_params[] = $ten;
            
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
            
            // Update loai_user and trang_thai
            $query_parts[] = "loai_user = ?";
            $query_types .= "i";
            $query_params[] = $loai_user;
            
            $query_parts[] = "trang_thai = ?";
            $query_types .= "i";
            $query_params[] = $trang_thai;
            
            // Add user_id to params for WHERE clause
            $query_types .= "i";
            $query_params[] = $user_id;
            
            // Build and execute update query
            $update_query = "UPDATE users SET " . implode(", ", $query_parts) . " WHERE id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param($query_types, ...$query_params);
            $update_stmt->execute();
            
            // Log the action
            $details = "Chỉnh sửa tài khoản quản trị: " . $user['ten'] . " (ID: $user_id)";
            logAdminActivity($conn, $_SESSION['admin_id'], 'update', 'admin', $user_id, $details);
            
            // Commit transaction
            $conn->commit();
            
            // Update session variables if editing own account
            if ($user_id == $_SESSION['admin_id']) {
                $_SESSION['admin_name'] = $ten;
                if ($loai_user != $_SESSION['admin_level']) {
                    $_SESSION['admin_level'] = $loai_user;
                }
            }
            
            $_SESSION['success_message'] = 'Cập nhật thông tin tài khoản thành công!';
            header('Location: admins.php');
            exit();
            
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            $_SESSION['error_message'] = 'Lỗi khi cập nhật thông tin: ' . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = implode('<br>', $errors);
    }
}
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Trang chủ</a></li>
            <li class="breadcrumb-item"><a href="admins.php">Quản lý tài khoản</a></li>
            <li class="breadcrumb-item active" aria-current="page">Chỉnh sửa tài khoản</li>
        </ol>
    </nav>
    
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Chỉnh sửa tài khoản quản trị</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
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
            <h5 class="card-title mb-0">Thông tin tài khoản</h5>
        </div>
        <div class="card-body">
            <form method="post" action="">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="taikhoan" class="form-label">Tên đăng nhập</label>
                            <input type="text" class="form-control" id="taikhoan" value="<?php echo htmlspecialchars($user['taikhoan']); ?>" disabled>
                            <div class="form-text">Tên đăng nhập không thể thay đổi</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="ten" class="form-label">Họ và tên <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="ten" name="ten" value="<?php echo htmlspecialchars($user['ten']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
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
                            <label for="loai_user" class="form-label">Cấp bậc <span class="text-danger">*</span></label>
                            <select class="form-select" id="loai_user" name="loai_user" required>
                                <option value="1" <?php echo ($user['loai_user'] == 1) ? 'selected' : ''; ?>>Nhân viên</option>
                                <option value="2" <?php echo ($user['loai_user'] == 2) ? 'selected' : ''; ?>>Quản trị viên</option>
                            </select>
                            <div class="form-text">Chọn cấp bậc phù hợp với vai trò của tài khoản</div>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input class="form-check-input" type="checkbox" id="trang_thai" name="trang_thai" value="1" 
                                <?php echo $user['trang_thai'] ? 'checked' : ''; ?>
                                <?php echo ($user_id == $_SESSION['admin_id']) ? 'disabled' : ''; ?>>
                            <label class="form-check-label" for="trang_thai">Tài khoản hoạt động</label>
                            <?php if ($user_id == $_SESSION['admin_id']): ?>
                                <input type="hidden" name="trang_thai" value="1">
                                <div class="form-text">Bạn không thể khóa tài khoản của chính mình</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between mt-4">
                    <a href="admins.php" class="btn btn-secondary">
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
    });
</script>

<?php include('includes/footer.php'); ?>
