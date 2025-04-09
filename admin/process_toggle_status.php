<?php
session_start();
include('../config/config.php');

// Kiểm tra quyền truy cập
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Debug - ghi log
file_put_contents('toggle_debug.log', 'POST data: ' . print_r($_POST, true), FILE_APPEND);

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
    file_put_contents('toggle_debug.log', "\nKhóa tài khoản với lý do: $reason", FILE_APPEND);
} else {
    // Mở khoá tài khoản, xoá lý do
    $stmt = $conn->prepare("UPDATE users SET trang_thai = ?, ly_do_khoa = NULL WHERE id_user = ?");
    $stmt->bind_param("ii", $new_status, $customer_id);
    file_put_contents('toggle_debug.log', "\nMở khóa tài khoản", FILE_APPEND);
}

$result = $stmt->execute();
file_put_contents('toggle_debug.log', "\nKết quả thực thi: " . ($result ? "Thành công" : "Lỗi: " . $conn->error), FILE_APPEND);

if ($result) {
    // Ghi log hành động
    $_SESSION['success_message'] = ($new_status == 1) ? "Đã mở khóa tài khoản thành công!" : "Đã khóa tài khoản thành công!";
} else {
    $_SESSION['error_message'] = "Lỗi: " . $conn->error;
}

// Chuyển hướng trở lại trang chi tiết khách hàng
header('Location: customer-detail.php?id=' . $customer_id);
exit();
?>
