<?php
session_start();
require('../../config/config.php');
require('../includes/permissions.php');

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Không có quyền truy cập!']);
    exit();
}

// Check if user has permission
if (!hasPermission('product_edit')) {
    echo json_encode(['success' => false, 'message' => 'Bạn không có quyền thực hiện hành động này!']);
    exit();
}

// Get form data
$review_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$status = isset($_POST['status']) ? (int)$_POST['status'] : 0;

// Validate data
if ($review_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID đánh giá không hợp lệ!']);
    exit();
}

if ($status !== 0 && $status !== 1) {
    echo json_encode(['success' => false, 'message' => 'Trạng thái không hợp lệ!']);
    exit();
}

try {
    // Update review status
    $update_stmt = $conn->prepare("UPDATE danhgia SET trangthai = ? WHERE id_danhgia = ?");
    $update_stmt->bind_param("ii", $status, $review_id);
    
    if ($update_stmt->execute()) {
        // Log the action
        $admin_id = $_SESSION['admin_id'];
        $action_type = $status == 1 ? 'approve_review' : 'hide_review';
        $status_text = $status == 1 ? 'hiển thị' : 'ẩn';
        
        $log_stmt = $conn->prepare("
            INSERT INTO admin_actions 
            (admin_id, action_type, target_type, target_id, details, ip_address) 
            VALUES (?, ?, 'review', ?, ?, ?)
        ");
        
        $details = "Đã " . $status_text . " đánh giá #" . $review_id;
        $ip_address = $_SERVER['REMOTE_ADDR'];
        
        $log_stmt->bind_param("isiss", $admin_id, $action_type, $review_id, $details, $ip_address);
        $log_stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Cập nhật trạng thái thành công!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Lỗi khi cập nhật trạng thái!']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
