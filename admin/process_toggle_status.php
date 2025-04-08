<?php
// Start the session
session_start();

// Check admin login
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// Include database connection
include('../config/config.php');

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: customers.php');
    exit();
}

// Get form data
$customer_id = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
$new_status = isset($_POST['new_status']) ? (int)$_POST['new_status'] : -1;
$reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';

// Validate data
if ($customer_id <= 0 || ($new_status !== 0 && $new_status !== 1)) {
    $_SESSION['error_message'] = 'Dữ liệu không hợp lệ!';
    header("Location: customers.php");
    exit();
}

// If locking account, require a reason
if ($new_status === 0 && empty($reason)) {
    $_SESSION['error_message'] = 'Vui lòng nhập lý do khóa tài khoản!';
    header("Location: customer-detail.php?id=$customer_id");
    exit();
}

// Check if customer exists
$check_stmt = $conn->prepare("SELECT id_user FROM users WHERE id_user = ? AND loai_user = 0");
$check_stmt->bind_param("i", $customer_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    $_SESSION['error_message'] = 'Không tìm thấy khách hàng!';
    header('Location: customers.php');
    exit();
}

// Update the status
$update_stmt = $conn->prepare("UPDATE users SET trang_thai = ? WHERE id_user = ?");
$update_stmt->bind_param("ii", $new_status, $customer_id);

if ($update_stmt->execute()) {
    // Log the action
    $admin_id = $_SESSION['admin_id'];
    $admin_name = $_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? 'Admin';
    
    // Check if the log table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'user_logs'");
    
    if ($table_check->num_rows === 0) {
        // Create table if it doesn't exist
        $create_table = "CREATE TABLE user_logs (
            id INT(11) NOT NULL AUTO_INCREMENT,
            user_id INT(11) NOT NULL,
            admin_id INT(11) NOT NULL,
            action VARCHAR(255) NOT NULL,
            details TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY admin_id (admin_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        
        $conn->query($create_table);
    }
    
    // Add log entry
    $log_stmt = $conn->prepare("INSERT INTO user_logs (user_id, admin_id, action, details) VALUES (?, ?, ?, ?)");
    $action = $new_status ? 'Mở khóa tài khoản' : 'Khóa tài khoản';
    $details = $new_status ? 
        "Tài khoản được mở khóa bởi $admin_name" : 
        "Tài khoản bị khóa bởi $admin_name. Lý do: $reason";
    
    $log_stmt->bind_param("iiss", $customer_id, $admin_id, $action, $details);
    $log_stmt->execute();
    
    $_SESSION['success_message'] = $new_status ? 
        'Mở khóa tài khoản thành công!' : 
        'Khóa tài khoản thành công!';
} else {
    $_SESSION['error_message'] = 'Lỗi khi cập nhật trạng thái tài khoản: ' . $conn->error;
}

header("Location: customer-detail.php?id=$customer_id");
exit();
?>
