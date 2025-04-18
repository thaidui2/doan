<?php
/**
 * Admin Authentication Check
 * This file verifies admin credentials and privileges before allowing access to restricted pages
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include database connection if needed
if (!isset($conn)) {
    include_once(__DIR__ . '/../../config/config.php');
}

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // Save current URL for redirect after login
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    
    // Prevent header errors if output already started
    if (headers_sent()) {
        echo "<script>window.location.href = 'login.php?error=auth_required';</script>";
        exit;
    } else {
        header('Location: login.php?error=auth_required');
        exit;
    }
}

// Get admin ID from session
$admin_id = isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : 0;

// Validate admin exists in database and has proper permissions
$admin_query = $conn->prepare("SELECT * FROM users WHERE id = ? AND (loai_user = 1 OR loai_user = 2)");
$admin_query->bind_param("i", $admin_id);
$admin_query->execute();
$admin_result = $admin_query->get_result();

if ($admin_result->num_rows === 0) {
    // Admin not found in database or doesn't have admin privileges
    session_unset();
    session_destroy();
    
    // Prevent header errors if output already started
    if (headers_sent()) {
        echo "<script>window.location.href = 'login.php?error=invalid_admin';</script>";
        exit;
    } else {
        header('Location: login.php?error=invalid_admin');
        exit;
    }
}

// Get admin information
$admin_data = $admin_result->fetch_assoc();

// Set admin level in session if not already set
if (!isset($_SESSION['admin_level'])) {
    $_SESSION['admin_level'] = $admin_data['loai_user'];
}

// Make admin_level available globally
$admin_level = $_SESSION['admin_level'];

// Log last access time
$update_last_access = $conn->prepare("UPDATE users SET lan_dang_nhap_cuoi = NOW() WHERE id = ?");
$update_last_access->bind_param("i", $admin_id);
$update_last_access->execute();

// Store admin name in session if not already present
if (!isset($_SESSION['admin_name'])) {
    $_SESSION['admin_name'] = $admin_data['ten'];
    $_SESSION['admin_username'] = $admin_data['taikhoan'];
}
?>
