<?php
session_start();
header('Content-Type: application/json');

// Kiểm tra đăng nhập admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Không có quyền truy cập.']);
    exit();
}

include('../../config/config.php');

// Kiểm tra dữ liệu gửi lên
if (!isset($_POST['id']) || !isset($_POST['status'])) {
    echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ.']);
    exit();
}

$customer_id = (int)$_POST['id'];
$status = (int)$_POST['status'];

// Kiểm tra giá trị status hợp lệ
if ($status !== 0 && $status !== 1) {
    echo json_encode(['success' => false, 'message' => 'Trạng thái không hợp lệ.']);
    exit();
}

// Kiểm tra customer_id tồn tại và là khách hàng
$check_stmt = $conn->prepare("SELECT id_user FROM users WHERE id_user = ? AND loai_user = 0");
$check_stmt->bind_param("i", $customer_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy khách hàng.']);
    exit();
}

// Lý do khóa (nếu có)
$reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';

// Cập nhật trạng thái
$update_stmt = $conn->prepare("UPDATE users SET trang_thai = ? WHERE id_user = ?");
$update_stmt->bind_param("ii", $status, $customer_id);

$success = $update_stmt->execute();

if ($success) {
    // Xử lý ghi log hành động (nếu cần)
    $action = $status ? 'Mở khóa tài khoản' : 'Khóa tài khoản';
    $admin_name = $_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? 'Admin';
    
    // Kiểm tra bảng user_logs tồn tại
    $table_check = $conn->query("SHOW TABLES LIKE 'user_logs'");
    
    if ($table_check->num_rows === 0) {
        // Tạo bảng nếu chưa tồn tại
        $create_table = "CREATE TABLE user_logs (
            id INT(11) NOT NULL AUTO_INCREMENT,
            user_id INT(11) NOT NULL,
            admin_id INT(11) NOT NULL,
            action VARCHAR(255) NOT NULL,
            details TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY admin_id (admin_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        
        $conn->query($create_table);
    }
    
    // Thêm log
    $admin_id = $_SESSION['admin_id'];
    $log_stmt = $conn->prepare("INSERT INTO user_logs (user_id, admin_id, action, details) VALUES (?, ?, ?, ?)");
    $details = $status ? "Mở khóa tài khoản bởi $admin_name" : "Khóa tài khoản bởi $admin_name. Lý do: " . ($reason ?: "Không có lý do cụ thể");
    $log_stmt->bind_param("iiss", $customer_id, $admin_id, $action, $details);
    $log_stmt->execute();
    
    echo json_encode([
        'success' => true, 
        'message' => $status ? 'Mở khóa tài khoản thành công!' : 'Khóa tài khoản thành công!'
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Lỗi khi cập nhật trạng thái: ' . $conn->error]);
}
?>
