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
if (!hasPermission('product_delete')) {
    echo json_encode(['success' => false, 'message' => 'Bạn không có quyền thực hiện hành động này!']);
    exit();
}

// Get review ID
$review_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

// Validate data
if ($review_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID đánh giá không hợp lệ!']);
    exit();
}

try {
    // Begin transaction
    $conn->begin_transaction();
    
    // Get review information before deletion for logging
    $get_review = $conn->prepare("
        SELECT d.*, p.tensanpham 
        FROM danhgia d 
        JOIN sanpham p ON d.id_sanpham = p.id_sanpham 
        WHERE d.id_danhgia = ?
    ");
    $get_review->bind_param("i", $review_id);
    $get_review->execute();
    $review = $get_review->get_result()->fetch_assoc();
    
    if (!$review) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy đánh giá!']);
        $conn->rollback();
        exit();
    }
    
    // Delete review image if exists
    if (!empty($review['hinhanh'])) {
        $image_path = "../../uploads/reviews/" . $review['hinhanh'];
        if (file_exists($image_path)) {
            unlink($image_path);
        }
    }
    
    // Delete review
    $delete_stmt = $conn->prepare("DELETE FROM danhgia WHERE id_danhgia = ?");
    $delete_stmt->bind_param("i", $review_id);
    
    if ($delete_stmt->execute()) {
        // Update product average rating
        $update_product_rating = $conn->prepare("
            UPDATE sanpham p
            SET diemdanhgia_tb = (
                SELECT AVG(diemdanhgia) 
                FROM danhgia 
                WHERE id_sanpham = ? AND trangthai = 1
            ),
            soluong_danhgia = (
                SELECT COUNT(*) 
                FROM danhgia 
                WHERE id_sanpham = ? AND trangthai = 1
            )
            WHERE id_sanpham = ?
        ");
        
        $update_product_rating->bind_param("iii", $review['id_sanpham'], $review['id_sanpham'], $review['id_sanpham']);
        $update_product_rating->execute();
        
        // Log the action
        $admin_id = $_SESSION['admin_id'];
        $action_type = 'delete_review';
        
        $log_stmt = $conn->prepare("
            INSERT INTO admin_actions 
            (admin_id, action_type, target_type, target_id, details, ip_address) 
            VALUES (?, ?, 'review', ?, ?, ?)
        ");
        
        $details = "Đã xóa đánh giá #" . $review_id . " của sản phẩm \"" . $review['tensanpham'] . "\"";
        $ip_address = $_SERVER['REMOTE_ADDR'];
        
        $log_stmt->bind_param("isiss", $admin_id, $action_type, $review_id, $details, $ip_address);
        $log_stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode(['success' => true, 'message' => 'Xóa đánh giá thành công!']);
    } else {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Lỗi khi xóa đánh giá!']);
    }
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
