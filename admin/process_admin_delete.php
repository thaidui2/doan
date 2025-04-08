<?php
session_start();

// Check if the admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// Include database and permission checker
include('../config/config.php');
include('includes/permissions.php');

// Check permission
if (!hasPermission('admin_delete')) {
    $_SESSION['error_message'] = 'Bạn không có quyền xóa tài khoản nhân viên.';
    header('Location: admins.php');
    exit();
}

// Only Super Admins can delete other users
if ($_SESSION['admin_level'] < 3) {
    $_SESSION['error_message'] = 'Chỉ Super Admin mới có quyền xóa tài khoản nhân viên.';
    header('Location: admins.php');
    exit();
}

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admins.php');
    exit();
}

// Get form data
$admin_id = isset($_POST['admin_id']) ? (int)$_POST['admin_id'] : 0;

// Validate admin ID
if ($admin_id <= 0) {
    $_SESSION['error_message'] = 'ID nhân viên không hợp lệ.';
    header('Location: admins.php');
    exit();
}

// Verify the admin exists and is not a Super Admin
$check_query = $conn->prepare("SELECT id_admin, cap_bac, ho_ten FROM admin WHERE id_admin = ?");
$check_query->bind_param("i", $admin_id);
$check_query->execute();
$result = $check_query->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = 'Không tìm thấy nhân viên.';
    header('Location: admins.php');
    exit();
}

$admin_data = $result->fetch_assoc();

// Cannot delete Super Admins
if ($admin_data['cap_bac'] == 3) {
    $_SESSION['error_message'] = 'Không thể xóa tài khoản Super Admin.';
    header('Location: admins.php');
    exit();
}

// Prevent self-deletion
if ($admin_id == $_SESSION['admin_id']) {
    $_SESSION['error_message'] = 'Bạn không thể xóa tài khoản của chính mình.';
    header('Location: admins.php');
    exit();
}

// Start transaction
$conn->begin_transaction();

try {
    // Log the deletion first
    $admin_name = $_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? 'Admin';
    $target_name = $admin_data['ho_ten'];
    
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
    $action = 'delete_admin';
    $details = "Xóa tài khoản admin #$admin_id ($target_name) bởi $admin_name";
    $ip = $_SERVER['REMOTE_ADDR'];
    
    $log_stmt->bind_param("ississ", $_SESSION['admin_id'], $action, 'admin', $admin_id, $details, $ip);
    $log_stmt->execute();
    
    // Delete admin's roles
    $delete_roles = $conn->prepare("DELETE FROM admin_roles WHERE id_admin = ?");
    $delete_roles->bind_param("i", $admin_id);
    $delete_roles->execute();
    
    // Delete admin
    $delete_admin = $conn->prepare("DELETE FROM admin WHERE id_admin = ?");
    $delete_admin->bind_param("i", $admin_id);
    $delete_admin->execute();
    
    // Commit transaction
    $conn->commit();
    
    $_SESSION['success_message'] = "Đã xóa nhân viên '$target_name' thành công.";
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    $_SESSION['error_message'] = 'Lỗi khi xóa tài khoản nhân viên: ' . $e->getMessage();
}

header('Location: admins.php');
exit();
?>
