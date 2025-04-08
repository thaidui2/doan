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
$note = isset($_POST['note']) ? trim($_POST['note']) : '';

// Kiểm tra dữ liệu hợp lệ
if ($order_id <= 0 || empty($note)) {
    $_SESSION['error_message'] = "Dữ liệu không hợp lệ!";
    header("Location: order-detail.php?id=$order_id");
    exit();
}

// Kiểm tra nếu bảng ghi chú đơn hàng tồn tại
$table_check = $conn->query("SHOW TABLES LIKE 'donhang_ghichu'");

if ($table_check->num_rows === 0) {
    // Tạo bảng nếu chưa tồn tại
    $create_table = "CREATE TABLE donhang_ghichu (
        id int(11) NOT NULL AUTO_INCREMENT,
        id_donhang int(11) NOT NULL,
        noidung text NOT NULL,
        nguoi_tao varchar(100) NOT NULL,
        ngay_tao timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY id_donhang (id_donhang)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $conn->query($create_table);
}

// Lưu ghi chú mới
$user_name = $_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? 'Admin';

$stmt = $conn->prepare("INSERT INTO donhang_ghichu (id_donhang, noidung, nguoi_tao) VALUES (?, ?, ?)");
$stmt->bind_param("iss", $order_id, $note, $user_name);

if ($stmt->execute()) {
    $_SESSION['success_message'] = "Đã thêm ghi chú cho đơn hàng!";
} else {
    $_SESSION['error_message'] = "Lỗi: " . $conn->error;
}

// Quay lại trang chi tiết đơn hàng
header("Location: order-detail.php?id=$order_id");
exit();
?>