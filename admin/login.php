<?php
session_start();

// Kiểm tra nếu đã đăng nhập
if(isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: index.php");
    exit();
}

include('../config/config.php');

// Hiển thị lỗi PHP để dễ debug khi cần
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Kiểm tra kết nối cơ sở dữ liệu
if ($conn->connect_error) {
    die("Kết nối database thất bại: " . $conn->connect_error);
}

$error = '';
$debug_info = ''; // Thông tin để debug

// Xử lý đăng nhập khi form được submit
if(isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if(empty($username) || empty($password)) {
        $error = "Vui lòng điền đầy đủ thông tin đăng nhập!";
    } else {
        // Kiểm tra thông tin đăng nhập trong database - Sử dụng bảng users thay vì admin
        $stmt = $conn->prepare("SELECT * FROM users WHERE taikhoan = ? AND (loai_user = 1 OR loai_user = 2)");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($result->num_rows == 1) {
            $admin = $result->fetch_assoc();
            
            // Verify password
            if(password_verify($password, $admin['matkhau'])) {
                // Đăng nhập thành công
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $admin['id']; // Thay đổi: id_admin -> id
                $_SESSION['admin_username'] = $admin['taikhoan'];
                $_SESSION['admin_name'] = $admin['ten']; // Thay đổi: ten_admin -> ten
                $_SESSION['admin_level'] = $admin['loai_user']; // Thay đổi: cap_bac -> loai_user
                
                // Cập nhật thời gian đăng nhập cuối
                $update_stmt = $conn->prepare("UPDATE users SET lan_dang_nhap_cuoi = NOW() WHERE id = ?"); // Thay đổi: admin -> users, id_admin -> id
                $update_stmt->bind_param("i", $admin['id']); // Thay đổi: id_admin -> id
                $update_stmt->execute();
                
                // Redirect to the intended page or to the dashboard
                $redirect = isset($_SESSION['redirect_after_login']) ? $_SESSION['redirect_after_login'] : 'index.php';
                unset($_SESSION['redirect_after_login']); // Clear the stored URL
                
                header("Location: " . $redirect);
                exit();
            } else {
                $error = "Tài khoản hoặc mật khẩu không chính xác!";
            }
        } else {
            $error = "Tài khoản hoặc mật khẩu không chính xác!";
        }
    }
}

// Check if session expired
$expired_message = '';
if (isset($_GET['expired']) && $_GET['expired'] == 1) {
    $expired_message = 'Phiên làm việc đã hết hạn. Vui lòng đăng nhập lại.';
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - Bug Shop Admin</title>
    <link rel="stylesheet" href="../node_modules/bootstrap/dist/css/bootstrap.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            height: 100vh;
            display: flex;
            align-items: center;
            background-color: #f5f5f5;
        }
        
        .form-signin {
            width: 100%;
            max-width: 400px;
            padding: 15px;
            margin: auto;
        }
        
        .form-signin .form-floating:focus-within {
            z-index: 2;
        }
        
        .form-signin input[type="text"] {
            margin-bottom: -1px;
            border-bottom-right-radius: 0;
            border-bottom-left-radius: 0;
        }
        
        .form-signin input[type="password"] {
            margin-bottom: 10px;
            border-top-left-radius: 0;
            border-top-right-radius: 0;
        }
    </style>
</head>
<body class="text-center">
    <main class="form-signin">
        <form method="post" action="">
            <img class="mb-4" src="../images/logo.png" alt="Bug Shop Logo" height="72">
            <h1 class="h3 mb-3 fw-normal">Admin Login</h1>
            
            <?php if(!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if(!empty($expired_message)): ?>
                <div class="alert alert-warning"><?php echo $expired_message; ?></div>
            <?php endif; ?>
            
            <div class="form-floating mb-3">
                <input type="text" class="form-control" id="username" name="username" placeholder="Username" required>
                <label for="username">Username</label>
            </div>
            <div class="form-floating mb-3">
                <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                <label for="password">Password</label>
            </div>
            
            <div class="form-check text-start mb-3">
                <input class="form-check-input" type="checkbox" name="remember" id="remember">
                <label class="form-check-label" for="remember">
                    Ghi nhớ đăng nhập
                </label>
            </div>
            
            <button class="w-100 btn btn-lg btn-primary" type="submit" name="login">Đăng nhập</button>
            <p class="mt-4 mb-3 text-muted">&copy; <?php echo date('Y'); ?> Bug Shop</p>
        </form>
        
        <?php if(!empty($debug_info) && $_SERVER['REMOTE_ADDR'] === '127.0.0.1'): ?>
            <div class="alert alert-info mt-3">
                <strong>Debug Info:</strong><br>
                <?php echo $debug_info; ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>