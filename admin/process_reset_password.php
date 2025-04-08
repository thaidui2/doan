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
$new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';

// Validate data
if ($customer_id <= 0 || empty($new_password)) {
    $_SESSION['error_message'] = 'Dữ liệu không hợp lệ!';
    header("Location: customer-detail.php?id=$customer_id");
    exit();
}

// Validate password length
if (strlen($new_password) < 8) {
    $_SESSION['error_message'] = 'Mật khẩu mới phải có ít nhất 8 ký tự!';
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

// Hash the new password
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

// Update the password
$update_stmt = $conn->prepare("UPDATE users SET matkhau = ? WHERE id_user = ?");
$update_stmt->bind_param("si", $hashed_password, $customer_id);

if ($update_stmt->execute()) {
    // Log the password reset
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
    $action = "Đặt lại mật khẩu";
    $details = "Mật khẩu được đặt lại bởi $admin_name";
    $log_stmt->bind_param("iiss", $customer_id, $admin_id, $action, $details);
    $log_stmt->execute();
    
    $_SESSION['success_message'] = 'Đặt lại mật khẩu thành công!';
} else {
    $_SESSION['error_message'] = 'Lỗi khi đặt lại mật khẩu: ' . $conn->error;
}

header("Location: customer-detail.php?id=$customer_id");
exit();
?>
