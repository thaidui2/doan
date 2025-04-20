<?php
session_start();
include('../config/config.php');

// Set proper content type for AJAX response
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Vui lòng đăng nhập để sử dụng chức năng này',
        'redirect' => 'dangnhap.php'
    ]);
    exit;
}

// Get user ID from session
$user_id = $_SESSION['user']['id'];

// Process POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate product_id
    if (!isset($_POST['product_id']) || empty($_POST['product_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'ID sản phẩm không hợp lệ'
        ]);
        exit;
    }
    
    $product_id = (int)$_POST['product_id'];
    
    // Check if product exists
    $check_product = $conn->prepare("SELECT id FROM sanpham WHERE id = ? AND trangthai = 1");
    $check_product->bind_param("i", $product_id);
    $check_product->execute();
    $result = $check_product->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Sản phẩm không tồn tại hoặc đã bị vô hiệu hóa'
        ]);
        exit;
    }
    
    // Check if product is already in wishlist
    $check_wishlist = $conn->prepare("SELECT id FROM yeu_thich WHERE id_user = ? AND id_sanpham = ?");
    $check_wishlist->bind_param("ii", $user_id, $product_id);
    $check_wishlist->execute();
    $wishlist_result = $check_wishlist->get_result();
    
    if ($wishlist_result->num_rows > 0) {
        // Product is already in wishlist, remove it
        $delete_stmt = $conn->prepare("DELETE FROM yeu_thich WHERE id_user = ? AND id_sanpham = ?");
        $delete_stmt->bind_param("ii", $user_id, $product_id);
        
        if ($delete_stmt->execute()) {
            echo json_encode([
                'success' => true,
                'status' => 'removed',
                'message' => 'Đã xóa sản phẩm khỏi danh sách yêu thích'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Lỗi khi xóa sản phẩm khỏi danh sách yêu thích: ' . $conn->error
            ]);
        }
    } else {
        // Product is not in wishlist, add it
        try {
            $insert_stmt = $conn->prepare("INSERT INTO yeu_thich (id_user, id_sanpham) VALUES (?, ?)");
            $insert_stmt->bind_param("ii", $user_id, $product_id);
            
            if ($insert_stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'status' => 'added',
                    'message' => 'Đã thêm sản phẩm vào danh sách yêu thích'
                ]);
            } else {
                throw new Exception($conn->error);
            }
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi thêm sản phẩm vào yêu thích: ' . $e->getMessage()
            ]);
        }
    }
} 
// Process GET request to check if products are in wishlist
else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['check_products'])) {
        $product_ids = json_decode($_GET['check_products']);
        
        if (is_array($product_ids) && !empty($product_ids)) {
            $in_wishlist = [];
            
            // Prepare statement to check multiple products
            $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
            $types = str_repeat('i', count($product_ids) + 1); // +1 for user_id
            
            $query = "SELECT id_sanpham FROM yeu_thich WHERE id_user = ? AND id_sanpham IN ($placeholders)";
            
            $stmt = $conn->prepare($query);
            
            if (!$stmt) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Database error: ' . $conn->error
                ]);
                exit;
            }
            
            // Combine parameters
            $params = array_merge([$user_id], $product_ids);
            
            // Bind parameters
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                $in_wishlist[] = (int)$row['id_sanpham'];
            }
            
            echo json_encode([
                'success' => true,
                'wishlist_items' => $in_wishlist
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Không có sản phẩm nào để kiểm tra'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Thiếu tham số check_products'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Phương thức không được hỗ trợ'
    ]);
}
?>
