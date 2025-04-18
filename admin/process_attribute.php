<?php
// Start output buffering
ob_start();

session_start();
include('../config/config.php');

// Check if user is logged in
if(!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Add new attribute
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $type = trim($_POST['type'] ?? '');
        $value = trim($_POST['value'] ?? '');
        $color = ($type === 'color') ? trim($_POST['color'] ?? '') : null;
        
        // Validate input
        if (empty($name) || empty($type) || empty($value)) {
            $_SESSION['error_message'] = "Vui lòng điền đầy đủ thông tin thuộc tính!";
            header('Location: attributes.php');
            exit;
        }
        
        // Check if attribute with same type and value already exists
        $check_stmt = $conn->prepare("SELECT id FROM thuoc_tinh WHERE loai = ? AND gia_tri = ?");
        $check_stmt->bind_param("ss", $type, $value);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $_SESSION['error_message'] = "Thuộc tính với giá trị này đã tồn tại!";
            header('Location: attributes.php');
            exit;
        }
        
        // Insert new attribute
        $stmt = $conn->prepare("INSERT INTO thuoc_tinh (ten, loai, gia_tri, ma_mau) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $type, $value, $color);
        
        if ($stmt->execute()) {
            $attribute_id = $conn->insert_id;
            
            // Log activity
            if (function_exists('logAdminActivity')) {
                logAdminActivity($conn, $_SESSION['admin_id'], 'create', 'attribute', $attribute_id, "Thêm thuộc tính mới: $name ($type)");
            }
            
            $_SESSION['success_message'] = "Đã thêm thuộc tính thành công!";
        } else {
            $_SESSION['error_message'] = "Lỗi khi thêm thuộc tính: " . $conn->error;
        }
    }
    // Edit existing attribute
    else if ($action === 'edit') {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name'] ?? '');
        $type = trim($_POST['type'] ?? '');
        $value = trim($_POST['value'] ?? '');
        $color = ($type === 'color') ? trim($_POST['color'] ?? '') : null;
        
        // Validate input
        if (empty($id) || empty($name) || empty($type) || empty($value)) {
            $_SESSION['error_message'] = "Vui lòng điền đầy đủ thông tin thuộc tính!";
            header('Location: attributes.php');
            exit;
        }
        
        // Check if attribute with same type and value already exists (excluding current one)
        $check_stmt = $conn->prepare("SELECT id FROM thuoc_tinh WHERE loai = ? AND gia_tri = ? AND id != ?");
        $check_stmt->bind_param("ssi", $type, $value, $id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $_SESSION['error_message'] = "Thuộc tính với giá trị này đã tồn tại!";
            header('Location: attributes.php');
            exit;
        }
        
        // Update attribute
        $stmt = $conn->prepare("UPDATE thuoc_tinh SET ten = ?, loai = ?, gia_tri = ?, ma_mau = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $name, $type, $value, $color, $id);
        
        if ($stmt->execute()) {
            // Log activity
            if (function_exists('logAdminActivity')) {
                logAdminActivity($conn, $_SESSION['admin_id'], 'update', 'attribute', $id, "Cập nhật thuộc tính: $name ($type)");
            }
            
            $_SESSION['success_message'] = "Đã cập nhật thuộc tính thành công!";
        } else {
            $_SESSION['error_message'] = "Lỗi khi cập nhật thuộc tính: " . $conn->error;
        }
    }
    
    header('Location: attributes.php');
    exit;
}

// If accessed directly without POST data, redirect to attributes page
header('Location: attributes.php');
exit;
?>
