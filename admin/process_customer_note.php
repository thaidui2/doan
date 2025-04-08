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
$note = isset($_POST['note']) ? trim($_POST['note']) : '';

// Validate data
if ($customer_id <= 0 || empty($note)) {
    $_SESSION['error_message'] = 'Vui lòng nhập ghi chú!';
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

// Check if customer_notes table exists
$table_check = $conn->query("SHOW TABLES LIKE 'customer_notes'");

if ($table_check->num_rows === 0) {
    // Create table if it doesn't exist
    $create_table = "CREATE TABLE customer_notes (
        id INT(11) NOT NULL AUTO_INCREMENT,
        id_user INT(11) NOT NULL,
        note TEXT NOT NULL,
        created_by VARCHAR(100) NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY id_user (id_user)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $conn->query($create_table);
}

// Add the note
$admin_name = $_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? 'Admin';

$stmt = $conn->prepare("INSERT INTO customer_notes (id_user, note, created_by) VALUES (?, ?, ?)");
$stmt->bind_param("iss", $customer_id, $note, $admin_name);

if ($stmt->execute()) {
    $_SESSION['success_message'] = 'Đã thêm ghi chú thành công!';
} else {
    $_SESSION['error_message'] = 'Lỗi khi thêm ghi chú: ' . $conn->error;
}

header("Location: customer-detail.php?id=$customer_id");
exit();
?>
