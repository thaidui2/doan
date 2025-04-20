<?php
/**
 * Authentication check for admin panel
 */

// Start session if not started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Kiểm tra kết nối database
if (!isset($conn) || !$conn) {
    die("Lỗi kết nối cơ sở dữ liệu. Vui lòng kiểm tra file database.php");
}

// Check if user is logged in as admin
if (!isset($_SESSION['admin_id'])) {
    // Store the requested URL for redirection after login
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    
    // Redirect to login page
    header('Location: login.php');
    exit();
}

// Check if user account is still active
try {
    // Detect connection type (PDO or MySQLi) and use appropriate syntax
    if ($conn instanceof PDO) {
        // PDO connection
        $stmt = $conn->prepare("SELECT trang_thai, loai_user FROM users WHERE id = ? AND loai_user > 0");
        $stmt->execute([$_SESSION['admin_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        // MySQLi connection
        $stmt = $conn->prepare("SELECT trang_thai, loai_user FROM users WHERE id = ? AND loai_user > 0");
        $stmt->bind_param("i", $_SESSION['admin_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
    }
    
    if (!$user || $user['trang_thai'] != 1) {
        // User is not active or doesn't exist, log them out
        session_unset();
        session_destroy();
        
        header('Location: login.php?error=account_inactive');
        exit();
    }
    
    // Store user role in session for easier access
    $_SESSION['admin_role'] = $user['loai_user'];
    
} catch (Exception $e) {
    // Log error and redirect
    error_log("Database error in auth_check.php: " . $e->getMessage());
    header('Location: login.php?error=db_error');
    exit();
}

// For staff page, restrict access to only admin (loai_user = 2) if necessary
// Comment out these lines if you want all staff to access the staff management page
if (basename($_SERVER['PHP_SELF']) == 'staff.php' && $_SESSION['admin_role'] < 2) {
    header('Location: dashboard.php?error=permission_denied');
    exit();
}

// Update last login time
if ($conn instanceof PDO) {
    $stmt = $conn->prepare("UPDATE users SET lan_dang_nhap_cuoi = NOW() WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
} else {
    $stmt = $conn->prepare("UPDATE users SET lan_dang_nhap_cuoi = NOW() WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['admin_id']);
    $stmt->execute();
}
