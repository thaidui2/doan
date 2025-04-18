<?php
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
$stmt->bind_param("iii", $product_id, $size_id, $color_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = 'Sản phẩm không tồn tại hoặc đã bị ẩn';
    header('Location: ../sanpham.php');
    exit;
}

$product = $result->fetch_assoc();

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

// Chuyển hướng đến trang thanh toán
header('Location: ../thanhtoan.php?buy_now=1');
exit;
?>