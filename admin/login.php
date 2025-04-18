<?php
// Start session
session_start();

// Check if already logged in
if(isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    // Redirect to dashboard
    header('Location: index.php');
    exit();
}

// Include database connection
require_once('../config/config.php');

$error = '';
$username = '';

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if(empty($username) || empty($password)) {
        $error = 'Vui lòng nhập tài khoản và mật khẩu.';
    } else {
        // Updated query to match the new database structure
        $query = "SELECT id, taikhoan, matkhau, ten, loai_user, trang_thai 
                  FROM users 
                  WHERE taikhoan = ? AND (loai_user = 1 OR loai_user = 2) LIMIT 1";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($result->num_rows === 1) {
            $admin = $result->fetch_assoc();
            
            // Check password
            if(password_verify($password, $admin['matkhau'])) {
                // Check if account is active
                if($admin['trang_thai'] == 1) {
                    // Set session variables
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_username'] = $admin['taikhoan'];
                    $_SESSION['admin_name'] = $admin['ten'];
                    $_SESSION['admin_level'] = $admin['loai_user']; // Make sure this is set!
                    
                    // Update last login time
                    $update = $conn->prepare("UPDATE users SET lan_dang_nhap_cuoi = NOW() WHERE id = ?");
                    $update->bind_param('i', $admin['id']);
                    $update->execute();
                    
                    // Log the login activity
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $log_query = $conn->prepare("INSERT INTO nhat_ky (id_user, hanh_dong, doi_tuong_loai, doi_tuong_id, chi_tiet, ip_address) VALUES (?, 'login', 'admin', ?, 'Đăng nhập hệ thống quản trị', ?)");
                    $log_query->bind_param('iis', $admin['id'], $admin['id'], $ip);
                    $log_query->execute();
                    
                    // Check if there's a redirect URL stored in session
                    if(isset($_SESSION['redirect_after_login'])) {
                        $redirect_url = $_SESSION['redirect_after_login'];
                        unset($_SESSION['redirect_after_login']);
                        header("Location: $redirect_url");
                    } else {
                        header('Location: index.php');
                    }
                    exit();
                } else {
                    $error = 'Tài khoản của bạn đã bị khóa. Vui lòng liên hệ quản trị viên.';
                }
            } else {
                $error = 'Tài khoản hoặc mật khẩu không đúng.';
            }
        } else {
            $error = 'Tài khoản hoặc mật khẩu không đúng.';
        }
    }
}

// Check if session expired
$expired = isset($_GET['expired']) ? true : false;
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - Bug Shop Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
        }
        .login-container {
            max-width: 400px;
            padding: 2rem;
            border-radius: 0.5rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            background-color: #fff;
        }
        .brand-logo {
            font-size: 1.75rem;
            font-weight: 700;
        }
        .form-control:focus {
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
            border-color: #86b7fe;
        }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center">
    <div class="login-container w-100">
        <div class="text-center mb-4">
            <h1 class="brand-logo mb-0 text-primary">
                <i class="bi bi-bug-fill me-2"></i>
                Bug Shop
            </h1>
            <p class="text-muted">Hệ thống quản trị</p>
        </div>
        
        <?php if($expired): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                Phiên làm việc đã hết hạn hoặc bạn chưa đăng nhập. Vui lòng đăng nhập lại.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if(!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <form method="post" action="">
            <div class="mb-3">
                <label for="username" class="form-label">Tài khoản</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required autofocus>
                </div>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Mật khẩu</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password" required>
                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="remember" name="remember" value="1">
                <label class="form-check-label" for="remember">Ghi nhớ đăng nhập</label>
            </div>
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Đăng nhập
                </button>
            </div>
        </form>
        
        <div class="text-center mt-4">
            <a href="../index.php" class="text-decoration-none">
                <i class="bi bi-arrow-left me-1"></i> Trở về trang chủ
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        });
    </script>
</body>
</html>