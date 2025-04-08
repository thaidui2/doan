<?php
session_start();
include('config/config.php');

// Kiểm tra đăng nhập
if (!isset($_SESSION['user']) || $_SESSION['user']['logged_in'] !== true) {
    header('Location: dangnhap.php');
    exit();
}

$user_id = $_SESSION['user']['id'];
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($order_id <= 0) {
    header('Location: donhang.php');
    exit();
}

// Kiểm tra đơn hàng thuộc về người dùng và có thể hủy được
$stmt = $conn->prepare("
    SELECT * FROM donhang
    WHERE id_donhang = ? AND id_nguoidung = ? AND trangthai = 1
");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Đơn hàng không tồn tại, không thuộc người dùng này, hoặc không thể hủy
    $_SESSION['error_message'] = 'Không thể hủy đơn hàng này.';
    header('Location: donhang.php');
    exit();
}

// Cập nhật trạng thái đơn hàng thành "Đã hủy"
$update = $conn->prepare("UPDATE donhang SET trangthai = 5 WHERE id_donhang = ?");
$update->bind_param("i", $order_id);

if ($update->execute()) {
    $_SESSION['success_message'] = 'Đơn hàng #' . $order_id . ' đã được hủy thành công.';
} else {
    $_SESSION['error_message'] = 'Có lỗi xảy ra khi hủy đơn hàng. Vui lòng thử lại sau.';
}

header('Location: donhang.php');
exit();
?>