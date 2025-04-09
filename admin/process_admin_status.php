<?php
session_start();
include('../config/config.php');

// Kiểm tra quyền truy cập
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Lấy dữ liệu từ form
$customer_id = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
$new_status = isset($_POST['new_status']) ? (int)$_POST['new_status'] : null;
$reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';

// Kiểm tra dữ liệu đầu vào
if ($customer_id <= 0 || $new_status === null) {
    $_SESSION['error_message'] = 'Dữ liệu không hợp lệ';
    header('Location: customers.php');
    exit();
}

// Nếu khoá tài khoản, lưu lý do. Nếu mở khoá, xoá lý do cũ
if ($new_status == 0) {
    // Khoá tài khoản, lưu lý do
    $stmt = $conn->prepare("UPDATE users SET trang_thai = ?, ly_do_khoa = ? WHERE id_user = ?");
    $stmt->bind_param("isi", $new_status, $reason, $customer_id);
} else {
    // Mở khoá tài khoản, xoá lý do
    $stmt = $conn->prepare("UPDATE users SET trang_thai = ?, ly_do_khoa = NULL WHERE id_user = ?");
    $stmt->bind_param("ii", $new_status, $customer_id);
}

if ($stmt->execute()) {
    // Ghi log hành động
    $admin_name = $_SESSION['admin_name'] ?? 'Admin';
    $action = ($new_status == 1) ? 'mở khóa' : 'khóa';
    $log_query = $conn->prepare("
        INSERT INTO admin_actions (admin_id, action_type, target_type, target_id, details, ip_address) 
        VALUES (?, ?, 'user', ?, ?, ?)
    ");
    $admin_id = $_SESSION['admin_id'];
    $action_type = ($new_status == 1) ? 'unlock_account' : 'lock_account';
    $details = ($new_status == 1) 
        ? "Mở khóa tài khoản người dùng #$customer_id bởi $admin_name" 
        : "Khóa tài khoản người dùng #$customer_id bởi $admin_name. Lý do: $reason";
    $ip = $_SERVER['REMOTE_ADDR'];
    $log_query->bind_param("iisis", $admin_id, $action_type, $customer_id, $details, $ip);
    $log_query->execute();

    $_SESSION['success_message'] = "Đã $action tài khoản thành công!";
} else {
    $_SESSION['error_message'] = "Lỗi: " . $conn->error;
}

// Chuyển hướng trở lại trang chi tiết khách hàng
header('Location: customer-detail.php?id=' . $customer_id);
exit();