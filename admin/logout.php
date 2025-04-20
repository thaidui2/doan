<?php
session_start();

if (isset($_SESSION['admin_id'])) {
    $admin_id = $_SESSION['admin_id'];
    
    // Log the logout before clearing the session
    require_once '../config/config.php';
    
    $log_sql = "INSERT INTO nhat_ky (id_user, hanh_dong, doi_tuong_loai, doi_tuong_id, chi_tiet, ip_address) 
               VALUES (?, 'logout', 'admin', ?, 'Đăng xuất khỏi hệ thống quản trị', ?)";
    $log_stmt = $conn->prepare($log_sql);
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $log_stmt->bind_param('iis', $admin_id, $admin_id, $ip);
    $log_stmt->execute();
}

// Clear all session variables
$_SESSION = [];

// Delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to login page
header('Location: login.php');
exit();
