<?php
session_start();
header('Content-Type: application/json');

// Kiểm tra đăng nhập admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Không có quyền truy cập.']);
    exit();
}

// Include necessary files
include_once('../../config/config.php');
include_once('../includes/permissions.php');

// Kiểm tra quyền xóa khách hàng
if (!hasPermission('customer_delete')) {
    echo json_encode(['success' => false, 'message' => 'Bạn không có quyền xóa người dùng.']);
    exit();
}

// Kiểm tra dữ liệu gửi lên
if (!isset($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
    exit();
}

$customer_id = (int)$_POST['id'];

// Kiểm tra customer_id tồn tại và là khách hàng
$check_stmt = $conn->prepare("SELECT id_user, anh_dai_dien FROM users WHERE id_user = ? AND loai_user = 0");
$check_stmt->bind_param("i", $customer_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy khách hàng.']);
    exit();
}

$customer = $check_result->fetch_assoc();

// Bắt đầu transaction
$conn->begin_transaction();

try {
    // 1. Lưu log trước khi xóa
    $admin_id = $_SESSION['admin_id'];
    $admin_name = $_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? 'Admin';
    
    // Kiểm tra bảng admin_actions tồn tại
    $table_check = $conn->query("SHOW TABLES LIKE 'admin_actions'");
    
    if ($table_check->num_rows === 0) {
        // Tạo bảng nếu chưa tồn tại
        $create_table = "CREATE TABLE admin_actions (
            id INT(11) NOT NULL AUTO_INCREMENT,
            admin_id INT(11) NOT NULL,
            action_type VARCHAR(100) NOT NULL,
            target_type VARCHAR(50) NOT NULL,
            target_id INT(11) NOT NULL,
            details TEXT DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY admin_id (admin_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        
        $conn->query($create_table);
    }
    
    // Thêm log
    $log_stmt = $conn->prepare("
        INSERT INTO admin_actions (admin_id, action_type, target_type, target_id, details, ip_address) 
        VALUES (?, 'delete', 'user', ?, ?, ?)
    ");
    $details = "Xóa tài khoản khách hàng #$customer_id bởi $admin_name";
    $ip = $_SERVER['REMOTE_ADDR'];
    $log_stmt->bind_param("iiss", $admin_id, $customer_id, $details, $ip);
    $log_stmt->execute();
    
    // 2. Xóa các bản ghi liên quan từ các bảng khác trước
    
    // 2.1 Xóa giỏ hàng của khách hàng
    $delete_cart_details = $conn->prepare("
        DELETE gct FROM giohang_chitiet gct
        JOIN giohang g ON gct.id_giohang = g.id_giohang
        WHERE g.id_nguoidung = ?
    ");
    $delete_cart_details->bind_param("i", $customer_id);
    $delete_cart_details->execute();
    
    $delete_cart = $conn->prepare("DELETE FROM giohang WHERE id_nguoidung = ?");
    $delete_cart->bind_param("i", $customer_id);
    $delete_cart->execute();
    
    // 2.2 Xóa đánh giá của khách hàng (nếu cần)
    $delete_reviews = $conn->prepare("DELETE FROM danhgia WHERE id_user = ?");
    $delete_reviews->bind_param("i", $customer_id);
    $delete_reviews->execute();
    
    // 2.3 Xóa bất kỳ dữ liệu nào khác liên quan đến người dùng
    
    // 3. Xóa file ảnh đại diện (nếu có)
    if (!empty($customer['anh_dai_dien'])) {
        $file_path = "../uploads/users/" . $customer['anh_dai_dien'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }
    
    // 4. Xóa người dùng
    $delete_user = $conn->prepare("DELETE FROM users WHERE id_user = ?");
    $delete_user->bind_param("i", $customer_id);
    $delete_user->execute();
    
    // Commit transaction nếu thành công
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => 'Xóa tài khoản thành công!']);
    
} catch (Exception $e) {
    // Rollback nếu có lỗi
    $conn->rollback();
    
    echo json_encode(['success' => false, 'message' => 'Lỗi khi xóa khách hàng: ' . $e->getMessage()]);
}
?>
