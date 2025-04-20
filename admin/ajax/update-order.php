<?php
session_start();
require_once '../../config/config.php';

// Check if user is logged in as admin
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    echo json_encode(['success' => false, 'message' => 'Không có quyền truy cập']);
    exit;
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Phương thức không hợp lệ']);
    exit;
}

// Get and validate parameters
$order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$status = isset($_POST['status']) ? (int)$_POST['status'] : 0;

if ($order_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID đơn hàng không hợp lệ']);
    exit;
}

// Valid status values
$valid_statuses = [1, 2, 3, 4, 5]; // Match these with your order_statuses array
if (!in_array($status, $valid_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Trạng thái không hợp lệ']);
    exit;
}

// Get current order status for history
$check_order = $conn->prepare("SELECT trang_thai_don_hang FROM donhang WHERE id = ?");
$check_order->bind_param("i", $order_id);
$check_order->execute();
$result = $check_order->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Đơn hàng không tồn tại']);
    exit;
}

$current_order = $result->fetch_assoc();
$current_status = $current_order['trang_thai_don_hang'];

// Update order status
$stmt = $conn->prepare("UPDATE donhang SET trang_thai_don_hang = ?, ngay_capnhat = NOW() WHERE id = ?");
$stmt->bind_param("ii", $status, $order_id);

if ($stmt->execute()) {
    // Get admin info
    $admin_name = $_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? 'Quản trị viên';

    // Map status IDs to names
    $status_names = [
        1 => 'Chờ xác nhận',
        2 => 'Đã xác nhận',
        3 => 'Đang giao hàng',
        4 => 'Đã giao',
        5 => 'Đã hủy'
    ];
    
    // Add to order history
    $action = "Cập nhật trạng thái";
    $note = "Thay đổi trạng thái từ \"" . $status_names[$current_status] . "\" sang \"" . $status_names[$status] . "\"";
    
    $history_stmt = $conn->prepare("
        INSERT INTO donhang_lichsu (id_donhang, hanh_dong, nguoi_thuchien, ghi_chu)
        VALUES (?, ?, ?, ?)
    ");
    $history_stmt->bind_param("isss", $order_id, $action, $admin_name, $note);
    $history_stmt->execute();

    // Update inventory if status is "Delivered" (4)
    if ($status == 4) {
        updateProductInventory($conn, $order_id);
    }
    
    echo json_encode(['success' => true, 'message' => 'Cập nhật trạng thái thành công']);
    exit;
} else {
    echo json_encode(['success' => false, 'message' => 'Lỗi khi cập nhật: ' . $conn->error]);
    exit;
}

/**
 * Update product inventory when order is marked as delivered
 */
function updateProductInventory($conn, $order_id) {
    // Get order items
    $items_stmt = $conn->prepare("
        SELECT id_sanpham, id_bienthe, soluong 
        FROM donhang_chitiet 
        WHERE id_donhang = ?
    ");
    $items_stmt->bind_param("i", $order_id);
    $items_stmt->execute();
    $items_result = $items_stmt->get_result();
    
    while($item = $items_result->fetch_assoc()) {
        // Update variant quantity
        if($item['id_bienthe']) {
            $update_variant = $conn->prepare("
                UPDATE sanpham_bien_the 
                SET so_luong = GREATEST(0, so_luong - ?) 
                WHERE id = ?
            ");
            $update_variant->bind_param("ii", $item['soluong'], $item['id_bienthe']);
            $update_variant->execute();
        }
        
        // Update product total quantity
        $update_product = $conn->prepare("
            UPDATE sanpham SET 
            so_luong = (SELECT COALESCE(SUM(so_luong), 0) FROM sanpham_bien_the WHERE id_sanpham = ?),
            da_ban = da_ban + ?
            WHERE id = ?
        ");
        $update_product->bind_param("iii", $item['id_sanpham'], $item['soluong'], $item['id_sanpham']);
        $update_product->execute();
    }
}
?>
