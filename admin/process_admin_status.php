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
if (!hasPermission('admin_edit')) {
    $_SESSION['error_message'] = 'Bạn không có quyền thay đổi trạng thái tài khoản.';
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
$new_status = isset($_POST['new_status']) ? (int)$_POST['new_status'] : -1;
$reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';

// Validate data
if ($admin_id <= 0 || ($new_status !== 0 && $new_status !== 1)) {
    $_SESSION['error_message'] = 'Dữ liệu không hợp lệ.';
    header('Location: admins.php');
    exit();
}

// Verify the admin exists
$check_query = $conn->prepare("SELECT cap_bac, id_admin FROM admin WHERE id_admin = ?");
$check_query->bind_param("i", $admin_id);
$check_query->execute();
$result = $check_query->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = 'Không tìm thấy nhân viên.';
    header('Location: admins.php');
    exit();
}

$admin_data = $result->fetch_assoc();

// Check if trying to modify a Super Admin (only Super Admins can modify other Super Admins)
if ($admin_data['cap_bac'] == 3 && $_SESSION['admin_level'] < 3) {
    $_SESSION['error_message'] = 'Bạn không có quyền thay đổi trạng thái của Super Admin.';
    header('Location: admins.php');
    exit();
}

// Prevent self-locking
if ($admin_id == $_SESSION['admin_id'] && $new_status == 0) {
    $_SESSION['error_message'] = 'Bạn không thể khóa tài khoản của chính mình.';
    header('Location: admins.php');
    exit();
}

// If locking account, require a reason
if ($new_status === 0 && empty($reason)) {
    $_SESSION['error_message'] = 'Vui lòng cung cấp lý do khóa tài khoản.';
    header('Location: admins.php');
    exit();
}

// Update admin status
$update_stmt = $conn->prepare("UPDATE admin SET trang_thai = ? WHERE id_admin = ?");
$update_stmt->bind_param("ii", $new_status, $admin_id);

if ($update_stmt->execute()) {
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
    $action = $new_status ? 'enable_account' : 'disable_account';
    $details = $new_status ? 
        "Mở khóa tài khoản admin #$admin_id bởi $admin_name" : 
        "Khóa tài khoản admin #$admin_id bởi $admin_name. Lý do: $reason";
    $ip = $_SERVER['REMOTE_ADDR'];
    
    $details = "Cập nhật trạng thái admin #$admin_id"; // Tạo biến để lưu chuỗi
$target_type = 'admin'; // Lưu giá trị chuỗi vào biến
$log_stmt->bind_param("ississ", $_SESSION['admin_id'], $action, $target_type, $admin_id, $details, $ip);
    
    $_SESSION['success_message'] = $new_status ? 
        'Đã mở khóa tài khoản thành công!' : 
        'Đã khóa tài khoản thành công!';
} else {
    $_SESSION['error_message'] = 'Lỗi khi cập nhật trạng thái tài khoản: ' . $conn->error;
}

header('Location: admins.php');
exit();
?>
