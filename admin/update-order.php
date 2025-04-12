<?php
session_start();
header('Content-Type: application/json');

// Kiểm tra đăng nhập admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Không có quyền truy cập.']);
    exit();
}

include('../../config/config.php');

// Kiểm tra dữ liệu gửi lên
if (!isset($_POST['order_id']) || !isset($_POST['status'])) {
    echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
    exit();
}

$order_id = (int)$_POST['order_id'];
$status = (int)$_POST['status'];

// Kiểm tra trạng thái hợp lệ
$valid_statuses = [1, 2, 3, 4, 5, 6]; // 1: Chờ xác nhận, 2: Đang xử lý, 3: Đang giao hàng, 4: Đã giao, 5: Đã hủy, 6: Hoàn trả
if (!in_array($status, $valid_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Trạng thái không hợp lệ.']);
    exit();
}

// Cập nhật trạng thái đơn hàng
$stmt = $conn->prepare("UPDATE donhang SET trangthai = ?, ngaycapnhat = NOW() WHERE id_donhang = ?");
$stmt->bind_param("ii", $status, $order_id);
$result = $stmt->execute();

if ($result) {
    // Thêm vào lịch sử đơn hàng nếu có bảng lịch sử
    
    
    echo json_encode(['success' => true, 'message' => 'Cập nhật trạng thái thành công.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Lỗi khi cập nhật: ' . $conn->error]);
}

// Đóng kết nối
$stmt->close();
$conn->close();
?>