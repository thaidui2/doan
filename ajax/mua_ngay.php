<?php
session_start();
require_once('../config/config.php');

// More detailed debugging
function debug_log($message, $data = null) {
    $log_message = date('Y-m-d H:i:s') . ' - ' . $message;
    if ($data !== null) {
        $log_message .= ' - ' . print_r($data, true);
    }
    file_put_contents('../debug_buy_now.log', $log_message . "\n", FILE_APPEND);
}

debug_log('Buy Now request received', $_POST);

// Kiểm tra dữ liệu được gửi lên
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    debug_log('Invalid request method', $_SERVER['REQUEST_METHOD']);
    $_SESSION['error_message'] = 'Phương thức không hợp lệ';
    header('Location: ../sanpham.php');
    exit;
}

// Lấy thông tin sản phẩm
$product_id = isset($_POST['productId']) ? (int)$_POST['productId'] : 0;
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
$size_id = isset($_POST['sizeId']) && $_POST['sizeId'] !== '' ? (int)$_POST['sizeId'] : 0;
$color_id = isset($_POST['colorId']) && $_POST['colorId'] !== '' ? (int)$_POST['colorId'] : 0;

debug_log('Extracted data', [
    'product_id' => $product_id,
    'quantity' => $quantity,
    'size_id' => $size_id,
    'color_id' => $color_id
]);

// Validation with better error handling
if (empty($product_id)) {
    debug_log('Invalid product ID');
    $_SESSION['error_message'] = 'Thông tin sản phẩm không hợp lệ';
    header('Location: ../sanpham.php');
    exit;
}

if (empty($size_id) && $size_id !== 0) {  // Allow 0 as a valid size_id if needed
    debug_log('Missing size ID');
    $_SESSION['error_message'] = 'Vui lòng chọn kích thước';
    header('Location: ../product-detail.php?id=' . $product_id);
    exit;
}

if (empty($color_id) && $color_id !== 0) {  // Allow 0 as a valid color_id if needed
    debug_log('Missing color ID');
    $_SESSION['error_message'] = 'Vui lòng chọn màu sắc';
    header('Location: ../product-detail.php?id=' . $product_id);
    exit;
}

// Kiểm tra biến thể sản phẩm tồn tại
$stmt = $conn->prepare("
    SELECT sbt.id as variant_id, sp.id, sp.tensanpham, sp.gia, sp.hinhanh, 
          size.gia_tri as ten_size, color.gia_tri as ten_mau, color.ma_mau
    FROM sanpham_bien_the sbt
    JOIN sanpham sp ON sbt.id_sanpham = sp.id
    JOIN thuoc_tinh size ON sbt.id_size = size.id
    JOIN thuoc_tinh color ON sbt.id_mau = color.id
    WHERE sp.id = ? AND sbt.id_size = ? AND sbt.id_mau = ? AND sp.trangthai = 1
");

debug_log('Executing query with params', [$product_id, $size_id, $color_id]);
$stmt->bind_param("iii", $product_id, $size_id, $color_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    debug_log('No product variant found');
    $_SESSION['error_message'] = 'Biến thể sản phẩm không tồn tại hoặc đã bị ẩn';
    header('Location: ../product-detail.php?id=' . $product_id);
    exit;
}

$product = $result->fetch_assoc();
debug_log('Product variant found', $product);

// Lưu thông tin mua ngay vào session
$_SESSION['buy_now_cart'] = [
    'id_sanpham' => $product_id,
    'id_bienthe' => $product['variant_id'],
    'so_luong' => $quantity,
    'gia' => $product['gia'],
    'ten_san_pham' => $product['tensanpham'],
    'hinh_anh' => $product['hinhanh'],
    'ten_size' => $product['ten_size'],
    'ten_mau' => $product['ten_mau'],
    'ma_mau' => $product['ma_mau'],
    'thanh_tien' => $product['gia'] * $quantity
];

debug_log('Session buy_now_cart set', $_SESSION['buy_now_cart']);
debug_log('Redirecting to checkout');

// Chuyển hướng đến trang thanh toán
header('Location: ../thanhtoan.php?buy_now=1');
exit;
?>