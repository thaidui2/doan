<?php
// filepath: c:\xampp\htdocs\bug_shop\add_to_cart.php
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

writeLog("Received data", $data);

// Kiểm tra các cách lưu user_id trong session
$user_id = null;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
} elseif (isset($_SESSION['user']['id'])) {
    $user_id = $_SESSION['user']['id'];
} elseif (isset($_SESSION['user_username'])) {
    // Nếu chỉ có username, tìm id từ database
    $username = $_SESSION['user_username'];
    $user_query = $conn->prepare("SELECT id_user FROM users WHERE taikhoan = ?");
    $user_query->bind_param("s", $username);
    $user_query->execute();
    $user_result = $user_query->get_result();
    
    if ($user_result->num_rows > 0) {
        $user_id = $user_result->fetch_assoc()['id_user'];
    }
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

// Kiểm tra sản phẩm tồn tại
$product_check = $conn->prepare("SELECT id_sanpham, gia, trangthai FROM sanpham WHERE id_sanpham = ? AND trangthai = 1");
$product_check->bind_param("i", $product_id);
$product_check->execute();
$product_result = $product_check->get_result();

if ($product_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Sản phẩm không tồn tại hoặc đã ngừng kinh doanh']);
    exit;
}

$product = $product_result->fetch_assoc();
$price = $product['gia'];

// Đảm bảo giá không bao giờ là NULL
if (empty($price) || $price <= 0) {
    // Lấy giá từ bảng sản phẩm
    $price_check = $conn->prepare("SELECT gia FROM sanpham WHERE id_sanpham = ?");
    $price_check->bind_param("i", $product_id);
    $price_check->execute();
    $price_result = $price_check->get_result();
    
    if ($price_result->num_rows > 0) {
        $price_data = $price_result->fetch_assoc();
        $price = $price_data['gia'];
    } else {
        // Giá mặc định nếu không tìm thấy
        $price = 0;
        logError("No price found for product ID: $product_id");
    }
}

// Lấy hoặc tạo giỏ hàng
if ($user_id) {
    $cart_check = $conn->prepare("SELECT id_giohang FROM giohang WHERE id_nguoidung = ?");
    $cart_check->bind_param("i", $user_id);
} else {
    $cart_check = $conn->prepare("SELECT id_giohang FROM giohang WHERE session_id = ? AND id_nguoidung IS NULL");
    $cart_check->bind_param("s", $session_id);
}

$cart_check->execute();
$cart_result = $cart_check->get_result();

if ($cart_result->num_rows > 0) {
    $cart = $cart_result->fetch_assoc();
    $cart_id = $cart['id_giohang'];
    writeLog("Found existing cart", ['cart_id' => $cart_id]);
} else {
    // Tạo giỏ hàng mới
    if ($user_id) {
        $create_cart = $conn->prepare("INSERT INTO giohang (id_nguoidung, session_id) VALUES (?, ?)");
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
    SELECT id_chitiet, soluong, gia 
    FROM giohang_chitiet 
    WHERE id_giohang = ? AND id_sanpham = ?
    AND (id_kichthuoc = ? OR (id_kichthuoc IS NULL AND ? IS NULL))
    AND (id_mausac = ? OR (id_mausac IS NULL AND ? IS NULL))
");
$check_item->bind_param("iiiiii", $cart_id, $product_id, $size_id, $size_id, $color_id, $color_id);
$check_item->execute();
$item_result = $check_item->get_result();

$result_message = "Đã thêm sản phẩm vào giỏ hàng!";

if ($item_result->num_rows > 0) {
    // Sản phẩm đã tồn tại, cập nhật số lượng
    $item = $item_result->fetch_assoc();
    $new_quantity = $item['soluong'] + $quantity;
    $new_total = $price * $new_quantity;
    
    $update_item = $conn->prepare("UPDATE giohang_chitiet SET soluong = ?, thanh_tien = ? WHERE id_chitiet = ?");
    $update_item->bind_param("idi", $new_quantity, $new_total, $item['id_chitiet']);
    $update_item->execute();
    writeLog("Updated existing item", ['item_id' => $item['id_chitiet'], 'new_quantity' => $new_quantity]);
    $result_message = "Đã cập nhật số lượng trong giỏ hàng!";
} else {
    // Thêm sản phẩm mới vào giỏ hàng
    $total = $price * $quantity;
    $add_item = $conn->prepare("
        INSERT INTO giohang_chitiet (id_giohang, id_sanpham, id_kichthuoc, id_mausac, soluong, gia, thanh_tien)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $add_item->bind_param("iiiiidi", $cart_id, $product_id, $size_id, $color_id, $quantity, $price, $total);
    $add_item->execute();
    writeLog("Added new item", ['product_id' => $product_id, 'quantity' => $quantity]);
}

// Cập nhật tổng tiền giỏ hàng
$update_cart = $conn->prepare("
    UPDATE giohang
    SET tong_tien = (
        SELECT SUM(thanh_tien) FROM giohang_chitiet WHERE id_giohang = ?
    ),
    ngay_capnhat = NOW()
    WHERE id_giohang = ?
");
$update_cart->bind_param("ii", $cart_id, $cart_id);
$update_cart->execute();

// Đếm số sản phẩm trong giỏ
$count_items = $conn->prepare("SELECT SUM(soluong) as count FROM giohang_chitiet WHERE id_giohang = ?");
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
