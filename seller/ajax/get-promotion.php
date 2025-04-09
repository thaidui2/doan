<?php
// Bao gồm file cấu hình
require_once('../../config/config.php');

// Bao gồm file session để kiểm tra đăng nhập
session_start();

// Kiểm tra đăng nhập - điều chỉnh theo cấu trúc session thực tế
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    echo json_encode(['success' => false, 'message' => 'Bạn chưa đăng nhập']);
    exit();
}

// Lấy id người dùng từ session
$user_id = $_SESSION['user']['id'];

// Lấy ID của mã khuyến mãi
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
    exit();
}

try {
    // Kiểm tra quyền truy cập
    $check_owner = $conn->prepare("SELECT id FROM khuyen_mai WHERE id = ? AND id_nguoiban = ?");
    $check_owner->bind_param("ii", $id, $user_id);
    $check_owner->execute();
    $check_result = $check_owner->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Bạn không có quyền truy cập mã khuyến mãi này']);
        exit();
    }

    // Lấy thông tin mã khuyến mãi
    $query = $conn->prepare("SELECT * FROM khuyen_mai WHERE id = ?");
    $query->bind_param("i", $id);
    $query->execute();
    $result = $query->get_result();

    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy mã khuyến mãi']);
        exit();
    }

    $promo = $result->fetch_assoc();

    // Định dạng ngày tháng cho input datetime-local
    $promo['ngay_bat_dau_formatted'] = date('Y-m-d\TH:i', strtotime($promo['ngay_bat_dau']));
    $promo['ngay_ket_thuc_formatted'] = date('Y-m-d\TH:i', strtotime($promo['ngay_ket_thuc']));
    
    // Lấy danh sách sản phẩm áp dụng
    $san_pham_ap_dung = [];
    if ($promo['ap_dung_sanpham']) {
        $product_query = $conn->prepare("SELECT id_sanpham FROM khuyen_mai_sanpham WHERE id_khuyen_mai = ?");
        $product_query->bind_param("i", $id);
        $product_query->execute();
        $product_result = $product_query->get_result();
        
        while ($row = $product_result->fetch_assoc()) {
            $san_pham_ap_dung[] = (int)$row['id_sanpham'];
        }
    }
    $promo['san_pham_ap_dung'] = $san_pham_ap_dung;
    
    // Lấy danh sách loại sản phẩm áp dụng
    $loai_ap_dung = [];
    if ($promo['ap_dung_loai']) {
        $category_query = $conn->prepare("SELECT id_loai FROM khuyen_mai_loai WHERE id_khuyen_mai = ?");
        $category_query->bind_param("i", $id);
        $category_query->execute();
        $category_result = $category_query->get_result();
        
        while ($row = $category_result->fetch_assoc()) {
            $loai_ap_dung[] = (int)$row['id_loai'];
        }
    }
    $promo['loai_ap_dung'] = $loai_ap_dung;

    // Trả về phản hồi
    echo json_encode([
        'success' => true,
        'promotion' => $promo
    ]);
    
} catch (Exception $e) {
    error_log('Lỗi lấy thông tin khuyến mãi: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Đã xảy ra lỗi khi lấy thông tin khuyến mãi',
        'error' => $e->getMessage()
    ]);
}