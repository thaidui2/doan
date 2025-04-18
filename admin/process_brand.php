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
    
    // Add new brand
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        
        // Validate input
        if (empty($name)) {
            $_SESSION['error_message'] = "Vui lòng nhập tên thương hiệu!";
            header('Location: brands.php');
            exit;
        }
        
        // Process logo upload
        $logo = null;
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === 0) {
            $upload_dir = "../uploads/brands/";
            
            // Create directory if not exists
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_tmp = $_FILES['logo']['tmp_name'];
            $file_name = basename($_FILES['logo']['name']);
            $file_size = $_FILES['logo']['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // Check file extension
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array($file_ext, $allowed_ext)) {
                // Check file size (2MB)
                if ($file_size <= 2097152) {
                    $new_file_name = 'brand_' . uniqid() . '.' . $file_ext;
                    $dest_path = $upload_dir . $new_file_name;
                    
                    if (move_uploaded_file($file_tmp, $dest_path)) {
                        $logo = $new_file_name;
                    } else {
                        $_SESSION['error_message'] = "Không thể tải lên logo, vui lòng thử lại!";
                        header('Location: brands.php');
                        exit;
                    }
                } else {
                    $_SESSION['error_message'] = "Kích thước file quá lớn. Vui lòng tải lên file nhỏ hơn 2MB!";
                    header('Location: brands.php');
                    exit;
                }
            } else {
                $_SESSION['error_message'] = "Định dạng file không được hỗ trợ. Vui lòng sử dụng: " . implode(', ', $allowed_ext);
                header('Location: brands.php');
                exit;
            }
        }
        
        // Insert new brand
        $stmt = $conn->prepare("INSERT INTO thuong_hieu (ten, mo_ta, logo) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $description, $logo);
        
        if ($stmt->execute()) {
            $brand_id = $conn->insert_id;
            
            // Log activity
            if (function_exists('logAdminActivity')) {
                logAdminActivity($conn, $_SESSION['admin_id'], 'create', 'brand', $brand_id, "Thêm thương hiệu mới: $name");
            }
            
            $_SESSION['success_message'] = "Đã thêm thương hiệu thành công!";
        } else {
            $_SESSION['error_message'] = "Lỗi khi thêm thương hiệu: " . $conn->error;
        }
    }
    // Edit existing brand
    else if ($action === 'edit') {
        $id = (int)$_POST['id'];
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $current_logo = $_POST['current_logo'] ?? '';
        
        // Validate input
        if (empty($id) || empty($name)) {
            $_SESSION['error_message'] = "Dữ liệu không hợp lệ!";
            header('Location: brands.php');
            exit;
        }
        
        // Process logo upload if provided
        $logo = $current_logo;
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === 0) {
            $upload_dir = "../uploads/brands/";
            
            // Create directory if not exists
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_tmp = $_FILES['logo']['tmp_name'];
            $file_name = basename($_FILES['logo']['name']);
            $file_size = $_FILES['logo']['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // Check file extension
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array($file_ext, $allowed_ext)) {
                // Check file size (2MB)
                if ($file_size <= 2097152) {
                    $new_file_name = 'brand_' . uniqid() . '.' . $file_ext;
                    $dest_path = $upload_dir . $new_file_name;
                    
                    if (move_uploaded_file($file_tmp, $dest_path)) {
                        $logo = $new_file_name;
                        
                        // Delete old logo if exists
                        if (!empty($current_logo)) {
                            $old_logo_path = $upload_dir . $current_logo;
                            if (file_exists($old_logo_path) && is_file($old_logo_path)) {
                                unlink($old_logo_path);
                            }
                        }
                    } else {
                        $_SESSION['error_message'] = "Không thể tải lên logo, vui lòng thử lại!";
                        header('Location: brands.php');
                        exit;
                    }
                } else {
                    $_SESSION['error_message'] = "Kích thước file quá lớn. Vui lòng tải lên file nhỏ hơn 2MB!";
                    header('Location: brands.php');
                    exit;
                }
            } else {
                $_SESSION['error_message'] = "Định dạng file không được hỗ trợ. Vui lòng sử dụng: " . implode(', ', $allowed_ext);
                header('Location: brands.php');
                exit;
            }
        }
        
        // Update brand
        $stmt = $conn->prepare("UPDATE thuong_hieu SET ten = ?, mo_ta = ?, logo = ? WHERE id = ?");
        $stmt->bind_param("sssi", $name, $description, $logo, $id);
        
        if ($stmt->execute()) {
            // Log activity
            if (function_exists('logAdminActivity')) {
                logAdminActivity($conn, $_SESSION['admin_id'], 'update', 'brand', $id, "Cập nhật thương hiệu: $name");
            }
            
            $_SESSION['success_message'] = "Đã cập nhật thương hiệu thành công!";
        } else {
            $_SESSION['error_message'] = "Lỗi khi cập nhật thương hiệu: " . $conn->error;
        }
    }
    
    header('Location: brands.php');
    exit;
}

// If accessed directly without POST data, redirect to brands page
header('Location: brands.php');
exit;
?>
