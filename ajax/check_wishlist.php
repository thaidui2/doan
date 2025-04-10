<?php
session_start();
require_once('../config/config.php');

// Thêm debug log
error_log('Check Wishlist Request - User: ' . (isset($_SESSION['user']) ? $_SESSION['user']['id'] : 'Not logged in'));

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

// Kết quả mặc định
$response = [
    'success' => false,
    'in_wishlist' => []
];

// Sửa lại phần xử lý product_ids
if (isset($_SESSION['user']) && $_SESSION['user']['logged_in'] === true) {
    $user_id = $_SESSION['user']['id'];
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Log raw data để debug
        error_log('Raw POST data: ' . print_r($_POST, true));
        
        // Xử lý dữ liệu product_ids
        $product_ids = [];
        
        if (isset($_POST['product_ids'])) {
            // Thử decode nếu là JSON string
            $decoded = json_decode($_POST['product_ids'], true);
            
            if (is_array($decoded)) {
                $product_ids = $decoded;
            } 
            // Nếu không phải JSON, kiểm tra xem có phải array không
            else if (is_array($_POST['product_ids'])) {
                $product_ids = $_POST['product_ids'];
            }
        }
        
        error_log('Processed product_ids: ' . print_r($product_ids, true));
        
        if (!empty($product_ids)) {
            // Tạo placeholders cho truy vấn IN
            $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
            
            // Chuẩn bị loại tham số
            $types = str_repeat('i', count($product_ids));
            
            // Tạo mảng tham số với user_id ở đầu
            $params = $product_ids;
            array_unshift($params, $user_id);
            
            // Chuẩn bị câu truy vấn
            $query = "SELECT id_sanpham FROM wishlist WHERE id_user = ? AND id_sanpham IN ($placeholders)";
            
            // Thực hiện truy vấn
            $stmt = $conn->prepare($query);
            
            // Bind tham số
            $stmt->bind_param('i' . $types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            
            // Lấy danh sách ID sản phẩm đã yêu thích
            $wishlist_items = [];
            while ($row = $result->fetch_assoc()) {
                $wishlist_items[] = (int)$row['id_sanpham'];
            }
            
            $response['success'] = true;
            $response['in_wishlist'] = $wishlist_items;
        }
    }
}

// Trả về kết quả dạng JSON
header('Content-Type: application/json');
echo json_encode($response);