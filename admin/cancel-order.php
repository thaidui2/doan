<?php
// Include session check
include('includes/header.php');

// Include database connection
include('../config/config.php');

// Kiểm tra phương thức gửi dữ liệu
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: orders.php');
    exit();
}

// Lấy dữ liệu từ form
$order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';

// Kiểm tra dữ liệu hợp lệ
if ($order_id <= 0) {
    $_SESSION['error_message'] = "ID đơn hàng không hợp lệ!";
    header("Location: orders.php");
    exit();
}

if (empty($reason)) {
    $_SESSION['error_message'] = "Vui lòng nhập lý do hủy đơn hàng!";
    header("Location: order-detail.php?id=$order_id");
    exit();
}

// Cập nhật trạng thái đơn hàng thành "Đã hủy" (status = 5)
$stmt = $conn->prepare("UPDATE donhang SET trangthai = 5, ngaycapnhat = NOW() WHERE id_donhang = ?");
$stmt->bind_param("i", $order_id);

if ($stmt->execute()) {
    // Lưu lịch sử thay đổi trạng thái đơn hàng (nếu có bảng lịch sử)
    $user_name = $_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? 'Admin';
    
    $table_check = $conn->query("SHOW TABLES LIKE 'donhang_lichsu'");
    if ($table_check->num_rows > 0) {
        $history_stmt = $conn->prepare("INSERT INTO donhang_lichsu (id_donhang, hanh_dong, nguoi_thuchien, ghi_chu) VALUES (?, 'Hủy đơn hàng', ?, ?)");
        $history_stmt->bind_param("iss", $order_id, $user_name, $reason);
        $history_stmt->execute();
    }
    
    $_SESSION['success_message'] = "Đơn hàng #$order_id đã được hủy thành công!";
} else {
    $_SESSION['error_message'] = "Lỗi: " . $conn->error;
}

// Quay lại trang chi tiết đơn hàng
header("Location: order-detail.php?id=$order_id");
exit();
?>