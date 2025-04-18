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
        $log .= " - " . json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    file_put_contents('../cart_debug.log', $log . "\n", FILE_APPEND);
}

// Log the raw input for diagnosis
writeLog("Raw POST input", file_get_contents('php://input'));

// Log the incoming request data
writeLog("Received add to cart request", $data);

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
    writeLog("Error: Invalid product ID", $data);
    exit;
}

// Validate product ID is a positive integer
$product_id = (int)$data['productId'];
if ($product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID sản phẩm phải là số dương']);
    writeLog("Error: Product ID must be a positive integer", ['received' => $data['productId'], 'parsed' => $product_id]);
    exit;
}

$quantity = isset($data['quantity']) ? (int)$data['quantity'] : 1;
$size_id = isset($data['sizeId']) && $data['sizeId'] !== '' && $data['sizeId'] !== null ? (int)$data['sizeId'] : null;
$color_id = isset($data['colorId']) && $data['colorId'] !== '' && $data['colorId'] !== null ? (int)$data['colorId'] : null;

// Log the processed data
writeLog("Processed data", [
    'product_id' => $product_id, 
    'quantity' => $quantity, 
    'size_id' => $size_id, 
    'color_id' => $color_id
]);

// Special case for related products without variants (size and color)
if ($size_id === null || $color_id === null) {
    // Check if product exists and get its default variant
    $product_check = $conn->prepare("SELECT id, gia FROM sanpham WHERE id = ? AND trangthai = 1");
    $product_check->bind_param("i", $product_id);
    $product_check->execute();
    $product_result = $product_check->get_result();
    
    if ($product_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Sản phẩm không tồn tại hoặc đã bị ẩn']);
        writeLog("Error: Product not found", ['product_id' => $product_id]);
        exit;
    }
    
    $product = $product_result->fetch_assoc();
    $price = $product['gia'];
    
    // Get the first available variant - Fix the missing SQL query
    $variant_check = $conn->prepare("
        SELECT id FROM sanpham_bien_the 
        WHERE id_sanpham = ? AND so_luong > 0
        LIMIT 1
    ");
    
    $variant_check->bind_param("i", $product_id);
    $variant_check->execute();
    $variant_result = $variant_check->get_result();
    
    if ($variant_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Sản phẩm này hiện không có biến thể tồn kho']);
        writeLog("Error: No variants available", ['product_id' => $product_id]);
        exit;
    }
    
    $variant = $variant_result->fetch_assoc();
    $variant_id = $variant['id'];
    
} else {
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
        writeLog("Error: Variant not found", ['product_id' => $product_id, 'size_id' => $size_id, 'color_id' => $color_id]);
        exit;
    }
    
    $variant = $variant_result->fetch_assoc();
    $variant_id = $variant['id'];
    $price = $variant['gia'];
}

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
    writeLog("Added new item", ['variant_id' => $variant_id, 'quantity' => $quantity]);
}

// Đếm số sản phẩm trong giỏ
$count_items = $conn->prepare("SELECT SUM(so_luong) as count FROM giohang_chitiet WHERE id_giohang = ?");
$count_items->bind_param("i", $cart_id);
$count_items->execute();
$count_result = $count_items->get_result()->fetch_assoc();
$cart_count = $count_result['count'] ?? 0;

// Trả về kết quả với success và message fields
echo json_encode([
    'success' => true,
    'message' => $result_message,
    'cartCount' => (int)$cart_count
]);

writeLog("Request completed successfully", ['cart_count' => $cart_count]);
?>
