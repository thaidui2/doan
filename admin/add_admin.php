<?php
// Set page title
$page_title = 'Thêm nhân viên mới';

// Include header (will check for login)
include('includes/header.php');

// Include database connection
include('../config/config.php');

// Check if user has permission to add admins
checkPermissionRedirect('admin_add');

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $taikhoan = trim($_POST['taikhoan']);
    $ten_admin = trim($_POST['ten_admin']);
    $email = trim($_POST['email']);
    $matkhau = $_POST['matkhau'];
    $cap_bac = (int)$_POST['cap_bac'];
    $trang_thai = isset($_POST['trang_thai']) ? 1 : 0;
    
    // Basic validation
    $errors = [];
    
    if (empty($taikhoan)) {
        $errors[] = 'Vui lòng nhập tên đăng nhập.';
    } else if (preg_match('/\s/', $taikhoan)) {
        $errors[] = 'Tên đăng nhập không được chứa khoảng trắng.';
    }
    
    if (empty($ten_admin)) {
        $errors[] = 'Vui lòng nhập họ tên.';
    }
    
    if (empty($matkhau)) {
        $errors[] = 'Vui lòng nhập mật khẩu.';
    } else if (strlen($matkhau) < 8) {
        $errors[] = 'Mật khẩu phải có ít nhất 8 ký tự.';
    }
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email không hợp lệ.';
    }
    
    // Check if username already exists
    $check_username = $conn->prepare("SELECT id_admin FROM admin WHERE taikhoan = ?");
    $check_username->bind_param("s", $taikhoan);
    $check_username->execute();
    if ($check_username->get_result()->num_rows > 0) {
        $errors[] = 'Tên đăng nhập đã tồn tại.';
    }
    
    // Check if email already exists (if provided)
    if (!empty($email)) {
        $check_email = $conn->prepare("SELECT id_admin FROM admin WHERE email = ?");
        $check_email->bind_param("s", $email);
        $check_email->execute();
        if ($check_email->get_result()->num_rows > 0) {
            $errors[] = 'Email đã được sử dụng.';
        }
    }
    
    // Validate cap_bac (only accept valid values)
    if ($cap_bac < 1 || $cap_bac > 2) {
        $errors[] = 'Cấp bậc không hợp lệ.';
    }
    
    if (empty($errors)) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Hash the password
            $hashed_password = password_hash($matkhau, PASSWORD_DEFAULT);
            
            // Insert new admin
            $insert_stmt = $conn->prepare("
                INSERT INTO admin (taikhoan, matkhau, ten_admin, email, cap_bac, trang_thai) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $insert_stmt->bind_param("ssssis", $taikhoan, $hashed_password, $ten_admin, $email, $cap_bac, $trang_thai);
            $insert_stmt->execute();
            $admin_id = $conn->insert_id;
            
            // Commit transaction
            $conn->commit();
            $_SESSION['success_message'] = 'Thêm nhân viên mới thành công!';
            header("Location: admins.php");
            exit();
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            $_SESSION['error_message'] = 'Lỗi khi thêm nhân viên: ' . $e->getMessage();
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
            <li class="breadcrumb-item active" aria-current="page">Thêm nhân viên mới</li>
        </ol>
    </nav>
    
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Thêm nhân viên mới</h1>
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
            <h5 class="card-title mb-0">Thông tin nhân viên</h5>
        </div>
        <div class="card-body">
            <form method="post" action="">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="taikhoan" class="form-label">Tên đăng nhập <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="taikhoan" name="taikhoan" required>
                            <div class="form-text">Tên đăng nhập không được chứa khoảng trắng</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="ten_admin" class="form-label">Họ và tên <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="ten_admin" name="ten_admin" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email">
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="matkhau" class="form-label">Mật khẩu <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="matkhau" name="matkhau" required>
                                <button class="btn btn-outline-secondary" type="button" id="togglePasswordBtn">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button class="btn btn-outline-secondary" type="button" id="generatePasswordBtn">
                                    <i class="bi bi-magic"></i> Tạo
                                </button>
                            </div>
                            <div class="form-text">Mật khẩu phải có ít nhất 8 ký tự</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="cap_bac" class="form-label">Cấp bậc <span class="text-danger">*</span></label>
                            <select class="form-select" id="cap_bac" name="cap_bac" required>
                                <option value="1">Quản lý</option>
                                <option value="2">Admin cấp cao</option>
                            </select>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input class="form-check-input" type="checkbox" id="trang_thai" name="trang_thai" value="1" checked>
                            <label class="form-check-label" for="trang_thai">Tài khoản hoạt động</label>
                        </div>
                    </div>
                </div>
                
                <hr>
                
                <div class="d-flex justify-content-between">
                    <a href="admins.php" class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i> Hủy
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Thêm nhân viên
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
            const username = document.getElementById("taikhoan").value;
            const password = document.getElementById("matkhau").value;
            
            let isValid = true;
            let errorMessage = "";
            
            if (username.includes(" ")) {
                isValid = false;
                errorMessage += "Tên đăng nhập không được chứa khoảng trắng.<br>";
            }
            
            if (password.length < 8) {
                isValid = false;
                errorMessage += "Mật khẩu phải có ít nhất 8 ký tự.<br>";
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
