<?php
session_start();
require_once('../config/config.php');

// Kiểm tra và tạo bảng wishlist nếu chưa tồn tại
$check_table = $conn->query("SHOW TABLES LIKE 'wishlist'");
if ($check_table->num_rows == 0) {
    $create_table = "CREATE TABLE IF NOT EXISTS `wishlist` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `id_user` int(11) NOT NULL,
      `id_sanpham` int(11) NOT NULL,
      `ngay_them` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `user_product` (`id_user`,`id_sanpham`),
      KEY `id_user` (`id_user`),
      KEY `id_sanpham` (`id_sanpham`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
    $conn->query($create_table);
}

// Thêm debug log
error_log('Toggle Wishlist Request - User: ' . (isset($_SESSION['user']) ? $_SESSION['user']['id'] : 'Not logged in') . 
          ', Product: ' . (isset($_POST['product_id']) ? $_POST['product_id'] : 'None'));

// Kết quả mặc định
$response = [
    'success' => false,
    'message' => 'Có lỗi xảy ra',
    'is_in_wishlist' => false,
    'action' => ''
];

// Kiểm tra nếu người dùng đã đăng nhập
if (!isset($_SESSION['user']) || $_SESSION['user']['logged_in'] !== true) {
    $response['message'] = 'login_required';
    echo json_encode($response);
    exit();
}

// Kiểm tra request method và tham số
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'])) {
    $user_id = $_SESSION['user']['id'];
    $product_id = (int)$_POST['product_id'];
    
    // Kiểm tra sản phẩm có tồn tại không
    $product_check = $conn->prepare("SELECT id_sanpham FROM sanpham WHERE id_sanpham = ? AND trangthai = 1");
    $product_check->bind_param("i", $product_id);
    $product_check->execute();
    $result = $product_check->get_result();
    
    if ($result->num_rows === 0) {
        $response['message'] = 'Sản phẩm không tồn tại hoặc đã bị ẩn';
        echo json_encode($response);
        exit();
    }
    
    // Kiểm tra sản phẩm đã có trong danh sách yêu thích chưa
    $check_stmt = $conn->prepare("SELECT id FROM wishlist WHERE id_user = ? AND id_sanpham = ?");
    $check_stmt->bind_param("ii", $user_id, $product_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Sản phẩm đã tồn tại trong danh sách yêu thích -> Xóa
        $delete_stmt = $conn->prepare("DELETE FROM wishlist WHERE id_user = ? AND id_sanpham = ?");
        $delete_stmt->bind_param("ii", $user_id, $product_id);
        
        if ($delete_stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Đã xóa sản phẩm khỏi danh sách yêu thích';
            $response['is_in_wishlist'] = false;
            $response['action'] = 'removed';
        }
    } else {
        // Sản phẩm chưa có trong danh sách yêu thích -> Thêm mới
        $add_stmt = $conn->prepare("INSERT INTO wishlist (id_user, id_sanpham, ngay_them) VALUES (?, ?, NOW())");
        $add_stmt->bind_param("ii", $user_id, $product_id);
        
        if ($add_stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Đã thêm sản phẩm vào danh sách yêu thích';
            $response['is_in_wishlist'] = true;
            $response['action'] = 'added';
        }
    }
} else {
    $response['message'] = 'Yêu cầu không hợp lệ';
}

// Trả về kết quả dạng JSON
header('Content-Type: application/json');
echo json_encode($response);