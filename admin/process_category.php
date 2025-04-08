<?php
// Start session if not already started
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// Include database connection
include('../config/config.php');

// Include permission checker if exists
if (file_exists('includes/permissions.php')) {
    include('includes/permissions.php');
}

// Get action from form
$action = isset($_POST['action']) ? $_POST['action'] : '';

// Handle different actions
switch ($action) {
    case 'add':
        addCategory();
        break;
    case 'edit':
        editCategory();
        break;
    default:
        $_SESSION['error_message'] = 'Hành động không hợp lệ!';
        header('Location: categories.php');
        exit();
}

// Function to add a new category
function addCategory() {
    global $conn;
    
    // Check permission if function exists
    if (function_exists('hasPermission') && !hasPermission('category_add')) {
        $_SESSION['error_message'] = 'Bạn không có quyền thêm danh mục mới!';
        header('Location: categories.php');
        exit();
    }
    
    // Get form data
    $category_name = trim($_POST['category_name'] ?? '');
    $category_description = trim($_POST['category_description'] ?? '');
    $category_status = isset($_POST['category_status']) ? 1 : 0;
    
    // Validate category name
    if (empty($category_name)) {
        $_SESSION['error_message'] = 'Vui lòng nhập tên danh mục!';
        header('Location: categories.php');
        exit();
    }
    
    // Check if category name already exists
    $check_stmt = $conn->prepare("SELECT id_loai FROM loaisanpham WHERE tenloai = ?");
    $check_stmt->bind_param("s", $category_name);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $_SESSION['error_message'] = 'Tên danh mục đã tồn tại!';
        header('Location: categories.php');
        exit();
    }
    
    // Handle image upload
    $image_filename = null;
    if (isset($_FILES['category_image']) && $_FILES['category_image']['error'] === UPLOAD_ERR_OK) {
        $image_filename = processImageUpload();
        if ($image_filename === false) {
            // Error already set in processImageUpload function
            header('Location: categories.php');
            exit();
        }
    }
    
    // Insert new category
    $stmt = $conn->prepare("INSERT INTO loaisanpham (tenloai, mota, hinhanh, trangthai) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssi", $category_name, $category_description, $image_filename, $category_status);
    
    if ($stmt->execute()) {
        // Log the action if admin_actions table exists
        logAction('add', 'category', $conn->insert_id, "Thêm danh mục mới: $category_name");
        
        $_SESSION['success_message'] = 'Thêm danh mục mới thành công!';
    } else {
        $_SESSION['error_message'] = 'Lỗi khi thêm danh mục: ' . $conn->error;
    }
    
    header('Location: categories.php');
    exit();
}

// Function to edit an existing category
function editCategory() {
    global $conn;
    
    // Check permission if function exists
    if (function_exists('hasPermission') && !hasPermission('category_edit')) {
        $_SESSION['error_message'] = 'Bạn không có quyền chỉnh sửa danh mục!';
        header('Location: categories.php');
        exit();
    }
    
    // Get form data
    $category_id = (int)($_POST['category_id'] ?? 0);
    $category_name = trim($_POST['category_name'] ?? '');
    $category_description = trim($_POST['category_description'] ?? '');
    $category_status = isset($_POST['category_status']) ? 1 : 0;
    $remove_image = isset($_POST['remove_image']) ? 1 : 0;
    
    // Validate category ID and name
    if ($category_id <= 0 || empty($category_name)) {
        $_SESSION['error_message'] = 'Dữ liệu không hợp lệ!';
        header('Location: categories.php');
        exit();
    }
    
    // Check if category exists
    $check_stmt = $conn->prepare("SELECT id_loai, hinhanh FROM loaisanpham WHERE id_loai = ?");
    $check_stmt->bind_param("i", $category_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        $_SESSION['error_message'] = 'Không tìm thấy danh mục!';
        header('Location: categories.php');
        exit();
    }
    
    $current_data = $check_result->fetch_assoc();
    $current_image = $current_data['hinhanh'];
    
    // Check if category name already exists (excluding current category)
    $check_name_stmt = $conn->prepare("SELECT id_loai FROM loaisanpham WHERE tenloai = ? AND id_loai != ?");
    $check_name_stmt->bind_param("si", $category_name, $category_id);
    $check_name_stmt->execute();
    $check_name_result = $check_name_stmt->get_result();
    
    if ($check_name_result->num_rows > 0) {
        $_SESSION['error_message'] = 'Tên danh mục đã tồn tại!';
        header('Location: categories.php');
        exit();
    }
    
    // Handle image upload or removal
    $image_filename = $current_image;
    
    // If remove image is checked, delete the current image
    if ($remove_image && !empty($current_image)) {
        $image_path = "../uploads/categories/" . $current_image;
        if (file_exists($image_path)) {
            unlink($image_path);
        }
        $image_filename = null;
    } 
    // If a new image is uploaded
    elseif (isset($_FILES['category_image']) && $_FILES['category_image']['error'] === UPLOAD_ERR_OK) {
        $new_image = processImageUpload();
        if ($new_image === false) {
            // Error already set in processImageUpload function
            header('Location: categories.php');
            exit();
        }
        
        // Delete old image if exists
        if (!empty($current_image)) {
            $old_image_path = "../uploads/categories/" . $current_image;
            if (file_exists($old_image_path)) {
                unlink($old_image_path);
            }
        }
        
        $image_filename = $new_image;
    }
    
    // Update category
    $stmt = $conn->prepare("UPDATE loaisanpham SET tenloai = ?, mota = ?, hinhanh = ?, trangthai = ? WHERE id_loai = ?");
    $stmt->bind_param("sssii", $category_name, $category_description, $image_filename, $category_status, $category_id);
    
    if ($stmt->execute()) {
        // Log the action if admin_actions table exists
        logAction('edit', 'category', $category_id, "Cập nhật danh mục: $category_name");
        
        $_SESSION['success_message'] = 'Cập nhật danh mục thành công!';
    } else {
        $_SESSION['error_message'] = 'Lỗi khi cập nhật danh mục: ' . $conn->error;
    }
    
    header('Location: categories.php');
    exit();
}

// Function to process image upload
function processImageUpload() {
    // Check upload directory
    $upload_dir = "../uploads/categories/";
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file = $_FILES['category_image'];
    $file_name = $file['name'];
    $file_tmp = $file['tmp_name'];
    $file_size = $file['size'];
    
    // Validate file
    $allowed_extensions = array('jpg', 'jpeg', 'png', 'gif');
    $max_file_size = 2 * 1024 * 1024; // 2MB
    
    // Get file extension
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    // Check file extension
    if (!in_array($file_ext, $allowed_extensions)) {
        $_SESSION['error_message'] = 'Chỉ chấp nhận file hình ảnh có định dạng: ' . implode(', ', $allowed_extensions);
        return false;
    }
    
    // Check file size
    if ($file_size > $max_file_size) {
        $_SESSION['error_message'] = 'Kích thước file quá lớn. Tối đa 2MB.';
        return false;
    }
    
    // Generate unique filename
    $new_filename = uniqid() . '_' . time() . '.' . $file_ext;
    $destination = $upload_dir . $new_filename;
    
    // Move file to upload directory
    if (move_uploaded_file($file_tmp, $destination)) {
        return $new_filename;
    } else {
        $_SESSION['error_message'] = 'Có lỗi xảy ra khi tải lên file.';
        return false;
    }
}

// Function to log admin actions
function logAction($action_type, $target_type, $target_id, $details) {
    global $conn;
    
    // Check if the log table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'admin_actions'");
    
    if ($table_check->num_rows > 0) {
        $admin_id = $_SESSION['admin_id'] ?? 0;
        $ip = $_SERVER['REMOTE_ADDR'];
        
        $log_stmt = $conn->prepare("
            INSERT INTO admin_actions (admin_id, action_type, target_type, target_id, details, ip_address) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $log_stmt->bind_param("ississ", $admin_id, $action_type, $target_type, $target_id, $details, $ip);
        $log_stmt->execute();
    }
}
?>
