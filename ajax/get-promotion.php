<?php
session_start();
require('../config/config.php');

// Kiểm tra quyền truy cập
if (!isset($_SESSION['user']) || $_SESSION['user']['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Không có quyền truy cập']);
    exit();
}

$user_id = $_SESSION['user']['id'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
    exit();
}

// Lấy thông tin mã khuyến mãi
$stmt = $conn->prepare("
    SELECT * FROM khuyen_mai
    WHERE id = ? AND id_nguoiban = ?
");
$stmt->bind_param("ii", $id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy mã khuyến mãi']);
    exit();
}

$promotion = $result->fetch_assoc();

// Định dạng ngày
$promotion['ngay_bat_dau'] = date('Y-m-d', strtotime($promotion['ngay_bat_dau']));
$promotion['ngay_ket_thuc'] = date('Y-m-d', strtotime($promotion['ngay_ket_thuc']));

// Lấy danh sách sản phẩm áp dụng nếu có
if ($promotion['ap_dung_sanpham'] == 1) {
    $product_stmt = $conn->prepare("
        SELECT id_sanpham FROM khuyen_mai_sanpham
        WHERE id_khuyen_mai = ?
    ");
    $product_stmt->bind_param("i", $id);
    $product_stmt->execute();
    $products = $product_stmt->get_result();
    
    $promotion['san_pham_ap_dung'] = [];
    while ($row = $products->fetch_assoc()) {
        $promotion['san_pham_ap_dung'][] = (int)$row['id_sanpham'];
    }
}

// Lấy danh sách loại sản phẩm áp dụng nếu có
if ($promotion['ap_dung_loai'] == 1) {
    $category_stmt = $conn->prepare("
        SELECT id_loai FROM khuyen_mai_loai
        WHERE id_khuyen_mai = ?
    ");
    $category_stmt->bind_param("i", $id);
    $category_stmt->execute();
    $categories = $category_stmt->get_result();
    
    $promotion['loai_ap_dung'] = [];
    while ($row = $categories->fetch_assoc()) {
        $promotion['loai_ap_dung'][] = (int)$row['id_loai'];
    }
}

// Return the promotion data as JSON
echo json_encode(['success' => true, 'promotion' => $promotion]);