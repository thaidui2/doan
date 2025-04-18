<?php
session_start();
header('Content-Type: application/json');

// Check if request is AJAX
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access forbidden']);
    exit;
}

// Get data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['itemId']) || empty($data['itemId'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid item ID']);
    exit;
}

// Connect to database
include('../config/config.php');

$item_id = (int)$data['itemId'];

// Get cart ID
$session_id = session_id();
$user_id = isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : null;

if ($user_id) {
    $stmt = $conn->prepare("SELECT id FROM giohang WHERE id_user = ?");
    $stmt->bind_param("i", $user_id);
} else {
    $stmt = $conn->prepare("SELECT id FROM giohang WHERE session_id = ? AND id_user IS NULL");
    $stmt->bind_param("s", $session_id);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Cart not found']);
    exit;
}

$cart = $result->fetch_assoc();
$cart_id = $cart['id'];

// Delete item from cart
$delete = $conn->prepare("DELETE FROM giohang_chitiet WHERE id = ? AND id_giohang = ?");
$delete->bind_param("ii", $item_id, $cart_id);
$delete->execute();

if ($delete->affected_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Item not found in cart']);
    exit;
}

// Count remaining items
$count_query = $conn->prepare("SELECT SUM(so_luong) as total_items FROM giohang_chitiet WHERE id_giohang = ?");
$count_query->bind_param("i", $cart_id);
$count_query->execute();
$count_result = $count_query->get_result()->fetch_assoc();
$total_items = $count_result['total_items'] ?? 0;

// Calculate total price
$total_query = $conn->prepare("SELECT SUM(so_luong * gia) as total_price FROM giohang_chitiet WHERE id_giohang = ?");
$total_query->bind_param("i", $cart_id);
$total_query->execute();
$total_result = $total_query->get_result()->fetch_assoc();
$total_price = $total_result['total_price'] ?? 0;

// Return success response with updated data
echo json_encode([
    'success' => true,
    'message' => 'Đã xóa sản phẩm khỏi giỏ hàng',
    'cartCount' => (int)$total_items,
    'cartTotal' => number_format($total_price, 0, ',', '.') . '₫'
]);
?>
