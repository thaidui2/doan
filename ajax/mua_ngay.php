<?php
// filepath: c:\xampp\htdocs\bug_shop\ajax\mua_ngay.php
session_start();
require_once('../config/config.php');

// Kiểm tra dữ liệu được gửi lên
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Location: ../sanpham.php');
    exit;
}

// Lấy thông tin sản phẩm
$product_id = isset($_POST['productId']) ? (int)$_POST['productId'] : 0;
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
$size_id = isset($_POST['sizeId']) ? (int)$_POST['sizeId'] : null;
$color_id = isset($_POST['colorId']) ? (int)$_POST['colorId'] : null;

// Kiểm tra sản phẩm tồn tại
$stmt = $conn->prepare("SELECT id_sanpham, tensanpham, gia, hinhanh FROM sanpham WHERE id_sanpham = ? AND trangthai = 1");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = 'Sản phẩm không tồn tại hoặc đã bị ẩn';
    header('Location: ../sanpham.php');
    exit;
}

$product = $result->fetch_assoc();

// Lưu thông tin mua ngay vào session với tên trường nhất quán
$_SESSION['buy_now_cart'] = [
    'id_sanpham' => $product_id,
    'soluong' => $quantity,
    'id_kichthuoc' => $size_id,
    'id_mausac' => $color_id,
    'gia' => $product['gia'] ?? 0,
    'tensanpham' => $product['tensanpham'] ?? 'Sản phẩm',
    'hinhanh' => $product['hinhanh'] ?? 'no-image.png',
    'thanh_tien' => ($product['gia'] ?? 0) * $quantity,
    // Giữ tên cũ để tương thích ngược
    'product_id' => $product_id,
    'quantity' => $quantity,
    'size_id' => $size_id,
    'color_id' => $color_id,
    'price' => $product['gia'] ?? 0,
    'name' => $product['tensanpham'] ?? 'Sản phẩm',
    'image' => $product['hinhanh'] ?? 'no-image.png'
];

// Chuyển hướng đến trang thanh toán
header('Location: ../thanhtoan.php?buy_now=1');
exit;
?>