<?php
session_start();

header('Content-Type: application/json');

// Nhận dữ liệu gửi đến
$data = json_decode(file_get_contents('php://input'), true);

// Kết nối cơ sở dữ liệu
include('../config/config.php');

// Ghi log để debug
function writeLog($message, $data = null) {
    $log = date('Y-m-d H:i:s') . " - " . $message;
    if ($data !== null) {
        $log .= " - " . json_encode($data);
    }
    file_put_contents('../cart_debug.log', $log . "\n", FILE_APPEND);
}

// Kiểm tra các cách lưu user_id trong session
$user_id = null;
if (isset($_SESSION['user']['id'])) {
    $user_id = $_SESSION['user']['id'];
}

// Ghi log thông tin session
$session_id = session_id();
writeLog("Session info", ['session_id' => $session_id, 'user_id' => $user_id]);

// Kiểm tra dữ liệu
if (!isset($data['productId']) || empty($data['productId'])) {
    echo json_encode(['success' => false, 'message' => 'ID sản phẩm không hợp lệ']);
    exit;
}

$product_id = (int)$data['productId'];
$quantity = isset($data['quantity']) ? (int)$data['quantity'] : 1;
$size_id = isset($data['sizeId']) ? (int)$data['sizeId'] : null;
$color_id = isset($data['colorId']) ? (int)$data['colorId'] : null;

// Kiểm tra biến thể sản phẩm tồn tại
$variant_check = $conn->prepare("
    SELECT sbt.id, sp.gia 
    FROM sanpham_bien_the sbt
    JOIN sanpham sp ON sbt.id_sanpham = sp.id
    WHERE sp.id = ? AND sbt.id_size = ? AND sbt.id_mau = ? AND sp.trangthai = 1
");

$variant_check->bind_param("iii", $product_id, $size_id, $color_id);
$variant_check->execute();
$variant_result = $variant_check->get_result();

if ($variant_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Biến thể sản phẩm không tồn tại hoặc đã ngừng kinh doanh']);
    exit;
}

$variant = $variant_result->fetch_assoc();
$variant_id = $variant['id'];
$price = $variant['gia'];

// Lấy hoặc tạo giỏ hàng
if ($user_id) {
    $cart_check = $conn->prepare("SELECT id FROM giohang WHERE id_user = ?");
    $cart_check->bind_param("i", $user_id);
} else {
    $cart_check = $conn->prepare("SELECT id FROM giohang WHERE session_id = ? AND id_user IS NULL");
    $cart_check->bind_param("s", $session_id);
}

$cart_check->execute();
$cart_result = $cart_check->get_result();

if ($cart_result->num_rows > 0) {
    $cart = $cart_result->fetch_assoc();
    $cart_id = $cart['id'];
    writeLog("Found existing cart", ['cart_id' => $cart_id]);
} else {
    // Tạo giỏ hàng mới
    if ($user_id) {
        $create_cart = $conn->prepare("INSERT INTO giohang (id_user, session_id) VALUES (?, ?)");
        $create_cart->bind_param("is", $user_id, $session_id);
    } else {
        $create_cart = $conn->prepare("INSERT INTO giohang (session_id) VALUES (?)");
        $create_cart->bind_param("s", $session_id);
    }
    $create_cart->execute();
    $cart_id = $conn->insert_id;
    writeLog("Created new cart", ['cart_id' => $cart_id]);
}

// Kiểm tra sản phẩm đã có trong giỏ hàng chưa
$check_item = $conn->prepare("
    SELECT id, so_luong, gia 
    FROM giohang_chitiet 
    WHERE id_giohang = ? AND id_bienthe = ?
");
$check_item->bind_param("ii", $cart_id, $variant_id);
$check_item->execute();
$item_result = $check_item->get_result();

$result_message = "Đã thêm sản phẩm vào giỏ hàng!";

if ($item_result->num_rows > 0) {
    // Sản phẩm đã tồn tại, cập nhật số lượng
    $item = $item_result->fetch_assoc();
    $new_quantity = $item['so_luong'] + $quantity;
    
    $update_item = $conn->prepare("UPDATE giohang_chitiet SET so_luong = ? WHERE id = ?");
    $update_item->bind_param("ii", $new_quantity, $item['id']);
    $update_item->execute();
    writeLog("Updated existing item", ['item_id' => $item['id'], 'new_quantity' => $new_quantity]);
    $result_message = "Đã cập nhật số lượng trong giỏ hàng!";
} else {
    // Thêm sản phẩm mới vào giỏ hàng
    $add_item = $conn->prepare("
        INSERT INTO giohang_chitiet (id_giohang, id_bienthe, so_luong, gia)
        VALUES (?, ?, ?, ?)
    ");
    $add_item->bind_param("iiid", $cart_id, $variant_id, $quantity, $price);
    $add_item->execute();
    writeLog("Added new item", ['product_id' => $product_id, 'quantity' => $quantity]);
}

// Đếm số sản phẩm trong giỏ
$count_items = $conn->prepare("SELECT SUM(so_luong) as count FROM giohang_chitiet WHERE id_giohang = ?");
$count_items->bind_param("i", $cart_id);
$count_items->execute();
$count_result = $count_items->get_result()->fetch_assoc();
$cart_count = $count_result['count'] ?? 0;

// Trả về kết quả
echo json_encode([
    'success' => true,
    'message' => $result_message,
    'cartCount' => (int)$cart_count
]);

?>
