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

// Get review ID and action
$review_id = isset($_POST['review_id']) ? (int)$_POST['review_id'] : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';

// Validate data
if ($review_id <= 0 || empty($action) || !in_array($action, ['show', 'hide'])) {
    $_SESSION['error_message'] = 'Dữ liệu không hợp lệ';
    header('Location: reviews.php');
    exit;
}

try {
    // Determine new status based on action
    $new_status = ($action === 'show') ? 1 : 0;
    
    // Update review status in database
    $stmt = $conn->prepare("UPDATE danhgia SET trang_thai = ? WHERE id = ?");
    $stmt->bind_param("ii", $new_status, $review_id);
    $success = $stmt->execute();
    
    if ($success) {
        // Log the action
        $admin_id = $_SESSION['admin_id'];
        $action_type = ($new_status == 1) ? 'show' : 'hide';
        $details = ($new_status == 1) ? 'Hiển thị đánh giá ID: ' : 'Ẩn đánh giá ID: ';
        $details .= $review_id;
        
        // Use the logAdminActivity function from functions.php
        include_once('includes/functions.php');
        logAdminActivity($conn, $admin_id, $action_type, 'review', $review_id, $details);
        
        // Set success message
        $message = ($new_status == 1) ? 'Đã hiển thị đánh giá thành công' : 'Đã ẩn đánh giá thành công';
        $_SESSION['success_message'] = $message;
    } else {
        $_SESSION['error_message'] = 'Không thể cập nhật trạng thái đánh giá: ' . $stmt->error;
    }
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Lỗi: ' . $e->getMessage();
}

// Redirect back to reviews page
header('Location: reviews.php');
exit;
