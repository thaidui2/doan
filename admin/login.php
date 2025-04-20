<?php
session_start();
require_once '../config/config.php';

// Redirect if already logged in
if (isset($_SESSION['admin_id']) && isset($_SESSION['admin_loai']) && $_SESSION['admin_loai'] >= 1) {
    header('Location: dashboard.php');
    exit();
}

$error = '';

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Vui lòng nhập đầy đủ tên đăng nhập và mật khẩu';
    } else {
        // Get user from database
        $sql = "SELECT id, taikhoan, matkhau, ten, loai_user, trang_thai FROM users 
                WHERE taikhoan = ? AND loai_user >= 1";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $user['matkhau'])) {
                // Check if account is active
                if ($user['trang_thai'] != 1) {
                    $error = 'Tài khoản đã bị khóa. Vui lòng liên hệ quản trị viên.';
                } else {
                    // Set session variables
                    $_SESSION['admin_id'] = $user['id'];
                    $_SESSION['admin_ten'] = $user['ten'];
                    $_SESSION['admin_loai'] = $user['loai_user'];
                    
                    // Update last login time
                    $update_sql = "UPDATE users SET lan_dang_nhap_cuoi = CURRENT_TIMESTAMP() WHERE id = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param('i', $user['id']);
                    $update_stmt->execute();
                    
                    // Log the login action
                    $log_sql = "INSERT INTO nhat_ky (id_user, hanh_dong, doi_tuong_loai, doi_tuong_id, chi_tiet, ip_address) 
                               VALUES (?, 'login', 'admin', ?, 'Đăng nhập hệ thống quản trị', ?)";
                    $log_stmt = $conn->prepare($log_sql);
                    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                    $log_stmt->bind_param('iis', $user['id'], $user['id'], $ip);
                    $log_stmt->execute();
                    
                    // Redirect to dashboard
                    header('Location: dashboard.php');
                    exit();
                }
            } else {
                $error = 'Tên đăng nhập hoặc mật khẩu không chính xác';
            }
        } else {
            $error = 'Tên đăng nhập hoặc mật khẩu không chính xác';
        }
    }
}

// Get site name from settings
$site_name = 'Bug Shop Admin';
$site_logo = '';

$sql_settings = "SELECT setting_value FROM settings WHERE setting_key = 'site_name'";
$result_settings = $conn->query($sql_settings);
if ($result_settings && $result_settings->num_rows > 0) {
    $site_name = $result_settings->fetch_assoc()['setting_value'] . ' Admin';
}

$sql_logo = "SELECT setting_value FROM settings WHERE setting_key = 'logo'";
$result_logo = $conn->query($sql_logo);
if ($result_logo && $result_logo->num_rows > 0) {
    $logo_file = $result_logo->fetch_assoc()['setting_value'];
    if (!empty($logo_file) && file_exists('../uploads/settings/' . $logo_file)) {
        $site_logo = '../uploads/settings/' . $logo_file;
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - <?php echo htmlspecialchars($site_name); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/login.css">
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-5">
                <div class="login-card">
                    <div class="login-header">
                        <?php if (!empty($site_logo)): ?>
                            <img src="<?php echo htmlspecialchars($site_logo); ?>" alt="<?php echo htmlspecialchars($site_name); ?>" class="login-logo">
                        <?php else: ?>
                            <i class="fas fa-bug fa-3x mb-3"></i>
                        <?php endif; ?>
                        <h4><?php echo htmlspecialchars($site_name); ?></h4>
                    </div>
                    
                    <div class="login-form">
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger alert-login" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="form-group">
                                <input type="text" class="form-control form-input" name="username" id="username" placeholder="Tên đăng nhập" required autofocus>
                                <i class="fas fa-user form-icon"></i>
                            </div>
                            
                            <div class="form-group">
                                <input type="password" class="form-control form-input" name="password" id="password" placeholder="Mật khẩu" required>
                                <i class="fas fa-lock form-icon"></i>
                            </div>
                            
                            <div class="form-group form-check">
                                <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                <label class="form-check-label" for="remember">Ghi nhớ đăng nhập</label>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-login btn-block w-100">Đăng nhập</button>
                        </form>
                        
                        <hr>
                        
                        <div class="text-center">
                            <a href="forgot-password.php" class="forgot-link">Quên mật khẩu?</a>
                        </div>
                        
                        <div class="mt-4 text-center">
                            <a href="../index.php" class="register-link">
                                <i class="fas fa-arrow-left me-1"></i> Quay lại trang chủ
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/login.js"></script>
</body>
</html>
