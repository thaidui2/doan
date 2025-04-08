<?php
session_start();
header('Content-Type: application/json');

// Kiểm tra quyền truy cập
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Không có quyền truy cập']);
    exit();
}

include('../../config/config.php');

// Kiểm tra quyền (nếu có)
if (function_exists('hasPermission') && !hasPermission('customer_edit')) {
    echo json_encode(['success' => false, 'message' => 'Bạn không có quyền chỉnh sửa thông tin khách hàng']);
    exit();
}

// Lấy dữ liệu từ request
$user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$new_type = isset($_POST['new_type']) ? (int)$_POST['new_type'] : 0;

if ($user_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID người dùng không hợp lệ']);
    exit();
}

try {
    // Bắt đầu transaction
    $conn->begin_transaction();
    
    // Kiểm tra người dùng tồn tại
    $check_user = $conn->prepare("SELECT id_user, loai_user FROM users WHERE id_user = ?");
    $check_user->bind_param("i", $user_id);
    $check_user->execute();
    $result = $check_user->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy người dùng']);
        exit();
    }
    
    $user = $result->fetch_assoc();
    
    // Cập nhật loại người dùng
    $update_query = "UPDATE users SET loai_user = ?";
    $params = [$new_type];
    $types = "i";
    
    // Nếu chuyển thành người bán, cập nhật thêm thông tin shop
    if ($new_type == 1) {
        $shop_name = isset($_POST['shop_name']) ? trim($_POST['shop_name']) : '';
        $shop_description = isset($_POST['shop_description']) ? trim($_POST['shop_description']) : '';
        
        // Validate tên shop
        if (empty($shop_name)) {
            $shop_name = "Shop của " . $user_id; // Tạo tên mặc định nếu không cung cấp
        }
        
        $update_query .= ", ten_shop = ?, mo_ta_shop = ?, ngay_tro_thanh_nguoi_ban = NOW()";
        $params[] = $shop_name;
        $params[] = $shop_description;
        $types .= "ss";
    }
    
    $update_query .= " WHERE id_user = ?";
    $params[] = $user_id;
    $types .= "i";
    
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param($types, ...$params);
    
    if ($update_stmt->execute()) {
        // Nếu chuyển từ người bán thành người mua, ẩn các sản phẩm
        if ($user['loai_user'] == 1 && $new_type == 0) {
            $hide_products = $conn->prepare("UPDATE sanpham SET trangthai = 2 WHERE id_nguoiban = ?");
            $hide_products->bind_param("i", $user_id);
            $hide_products->execute();
        }
        
        // Ghi log hành động
        $admin_id = $_SESSION['admin_id'];
        $admin_name = $_SESSION['admin_name'] ?? 'Admin';
        $action_type = $new_type == 1 ? 'upgrade_to_seller' : 'downgrade_to_buyer';
        
        $log_query = "INSERT INTO admin_actions (admin_id, action_type, target_type, target_id, details, ip_address) VALUES (?, ?, 'user', ?, ?, ?)";
        $details = $new_type == 1 ? "Nâng cấp người dùng #$user_id thành người bán" : "Chuyển người bán #$user_id thành người mua thông thường";
        $ip = $_SERVER['REMOTE_ADDR'];
        
        $log_stmt = $conn->prepare($log_query);
        $log_stmt->bind_param("isiss", $admin_id, $action_type, $user_id, $details, $ip);
        $log_stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        $message = $new_type == 1 ? 'Đã nâng cấp thành người bán thành công' : 'Đã chuyển thành người mua thành công';
        echo json_encode(['success' => true, 'message' => $message]);
    } else {
        throw new Exception("Lỗi khi cập nhật: " . $conn->error);
    }
    
} catch (Exception $e) {
    // Rollback nếu có lỗi
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?>