<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include configuration file
include_once('../config/config.php');

// Include helper functions first
include_once('includes/functions.php');       // Đầu tiên
include_once('includes/admin_helpers.php');   // Thứ hai
include_once('includes/permissions.php');  

// Kiểm tra đăng nhập
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// Get current page for highlighting in sidebar
$current_page = basename($_SERVER['PHP_SELF']);

// Thiết lập cấp bậc admin để phân quyền nếu chưa có
if (!isset($_SESSION['admin_level']) && isset($_SESSION['admin_id'])) {
    $admin_id = $_SESSION['admin_id'];
    $query = "SELECT cap_bac FROM admin WHERE id_admin = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $admin = $result->fetch_assoc();
        $_SESSION['admin_level'] = $admin['cap_bac'];
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Admin Dashboard'; ?> - Bug Shop</title>
    <link rel="stylesheet" href="../node_modules/bootstrap/dist/css/bootstrap.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/admin.css">
    <?php if (isset($page_specific_css)): ?>
    <?php echo $page_specific_css; ?>
    <?php endif; ?>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Toast container -->
            <div class="toast-container position-fixed bottom-0 end-0 p-3"></div>
