<?php
// Start session
session_start();

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Include database connection
include('../config/config.php');

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = 'Phương thức không hợp lệ';
    header('Location: reviews.php');
    exit;
}

// Get review ID
$review_id = isset($_POST['review_id']) ? (int)$_POST['review_id'] : 0;

// Validate data
if ($review_id <= 0) {
    $_SESSION['error_message'] = 'ID đánh giá không hợp lệ';
    header('Location: reviews.php');
    exit;
}

try {
    // Start transaction
    $conn->begin_transaction();
    
    // Get review info for logging
    $get_review = $conn->prepare("SELECT id_sanpham, id_user FROM danhgia WHERE id = ?");
    $get_review->bind_param("i", $review_id);
    $get_review->execute();
    $review_info = $get_review->get_result()->fetch_assoc();
    
    // Delete review
    $stmt = $conn->prepare("DELETE FROM danhgia WHERE id = ?");
    $stmt->bind_param("i", $review_id);
    $success = $stmt->execute();
    
    if ($success) {
        // Log the action
        $admin_id = $_SESSION['admin_id'];
        $details = "Đã xóa đánh giá ID: $review_id (Sản phẩm ID: {$review_info['id_sanpham']}, User ID: {$review_info['id_user']})";
        
        // Use the logAdminActivity function from functions.php
        include_once('includes/functions.php');
        logAdminActivity($conn, $admin_id, 'delete', 'review', $review_id, $details);
        
        // Commit transaction
        $conn->commit();
        
        // Set success message
        $_SESSION['success_message'] = 'Đã xóa đánh giá thành công';
    } else {
        // Rollback on error
        $conn->rollback();
        $_SESSION['error_message'] = 'Không thể xóa đánh giá: ' . $stmt->error;
    }
} catch (Exception $e) {
    // Rollback on exception
    $conn->rollback();
    $_SESSION['error_message'] = 'Lỗi: ' . $e->getMessage();
}

// Redirect back to reviews page
header('Location: reviews.php');
exit;
