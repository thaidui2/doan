<?php
// Start the session
session_start();

// Check admin login
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// Include database connection
include('../config/config.php');

// Get action type
$action = isset($_POST['action']) ? $_POST['action'] : '';

// Validate action
if (!in_array($action, ['add', 'edit'])) {
    $_SESSION['error_message'] = 'Hành động không hợp lệ!';
    header('Location: customers.php');
    exit();
}

// Function to validate and sanitize inputs
function validateInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Process add new customer
if ($action === 'add') {
    // Get form data
    $taikhoan = validateInput($_POST['taikhoan'] ?? '');
    $password = $_POST['password'] ?? '';
    $tenuser = validateInput($_POST['tenuser'] ?? '');
    $email = validateInput($_POST['email'] ?? '');
    $sdt = validateInput($_POST['sdt'] ?? '');
    $diachi = validateInput($_POST['diachi'] ?? '');
    $trang_thai = isset($_POST['trang_thai']) ? 1 : 0;
    $trang_thai_xac_thuc = isset($_POST['trang_thai_xac_thuc']) ? 1 : 0;
    
    // Validate required fields
    if (empty($taikhoan) || empty($password) || empty($tenuser) || empty($sdt) || empty($diachi)) {
        $_SESSION['error_message'] = 'Vui lòng điền đầy đủ các trường bắt buộc!';
        header('Location: add_customer.php');
        exit();
    }
    
    // Validate username (no spaces, alphanumeric)
    if (preg_match('/\s/', $taikhoan) || !preg_match('/^[a-zA-Z0-9_]+$/', $taikhoan)) {
        $_SESSION['error_message'] = 'Tên đăng nhập không hợp lệ! Chỉ được phép dùng chữ cái, số và dấu gạch dưới.';
        header('Location: add_customer.php');
        exit();
    }
    
    // Validate password length
    if (strlen($password) < 8) {
        $_SESSION['error_message'] = 'Mật khẩu phải có ít nhất 8 ký tự!';
        header('Location: add_customer.php');
        exit();
    }
    
    // Validate phone number
    if (!preg_match('/^[0-9]{10}$/', $sdt)) {
        $_SESSION['error_message'] = 'Số điện thoại không hợp lệ! Vui lòng nhập đúng 10 chữ số.';
        header('Location: add_customer.php');
        exit();
    }
    
    // Validate email if provided
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error_message'] = 'Email không hợp lệ!';
        header('Location: add_customer.php');
        exit();
    }
    
    // Check if username already exists
    $check_username = $conn->prepare("SELECT id_user FROM users WHERE taikhoan = ?");
    $check_username->bind_param("s", $taikhoan);
    $check_username->execute();
    $check_username->store_result();
    
    if ($check_username->num_rows > 0) {
        $_SESSION['error_message'] = 'Tên đăng nhập đã tồn tại!';
        header('Location: add_customer.php');
        exit();
    }
    
    // Check if phone number already exists
    $check_phone = $conn->prepare("SELECT id_user FROM users WHERE sdt = ?");
    $check_phone->bind_param("s", $sdt);
    $check_phone->execute();
    $check_phone->store_result();
    
    if ($check_phone->num_rows > 0) {
        $_SESSION['error_message'] = 'Số điện thoại đã được sử dụng!';
        header('Location: add_customer.php');
        exit();
    }
    
    // Check if email already exists (if provided)
    if (!empty($email)) {
        $check_email = $conn->prepare("SELECT id_user FROM users WHERE email = ?");
        $check_email->bind_param("s", $email);
        $check_email->execute();
        $check_email->store_result();
        
        if ($check_email->num_rows > 0) {
            $_SESSION['error_message'] = 'Email đã được sử dụng!';
            header('Location: add_customer.php');
            exit();
        }
    }
    
    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert new user
    $stmt = $conn->prepare("
        INSERT INTO users (taikhoan, matkhau, email, sdt, diachi, tenuser, loai_user, trang_thai, trang_thai_xac_thuc) 
        VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?)
    ");
    $stmt->bind_param("ssssssii", $taikhoan, $hashed_password, $email, $sdt, $diachi, $tenuser, $trang_thai, $trang_thai_xac_thuc);
    
    if ($stmt->execute()) {
        $new_user_id = $stmt->insert_id;
        $_SESSION['success_message'] = 'Thêm khách hàng mới thành công!';
        header("Location: customer-detail.php?id=$new_user_id");
        exit();
    } else {
        $_SESSION['error_message'] = 'Lỗi khi thêm khách hàng: ' . $conn->error;
        header('Location: add_customer.php');
        exit();
    }
}

// Process edit customer
elseif ($action === 'edit') {
    // Get form data
    $customer_id = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;
    $tenuser = validateInput($_POST['tenuser'] ?? '');
    $email = validateInput($_POST['email'] ?? '');
    $sdt = validateInput($_POST['sdt'] ?? '');
    $diachi = validateInput($_POST['diachi'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $trang_thai = isset($_POST['trang_thai']) ? 1 : 0;
    $trang_thai_xac_thuc = isset($_POST['trang_thai_xac_thuc']) ? 1 : 0;
    $remove_picture = isset($_POST['remove_picture']) ? 1 : 0;
    
    // Validate customer ID
    if ($customer_id <= 0) {
        $_SESSION['error_message'] = 'ID khách hàng không hợp lệ!';
        header('Location: customers.php');
        exit();
    }
    
    // Validate required fields
    if (empty($tenuser) || empty($sdt) || empty($diachi)) {
        $_SESSION['error_message'] = 'Vui lòng điền đầy đủ các trường bắt buộc!';
        header("Location: edit_customer.php?id=$customer_id");
        exit();
    }
    
    // Validate phone number
    if (!preg_match('/^[0-9]{10}$/', $sdt)) {
        $_SESSION['error_message'] = 'Số điện thoại không hợp lệ! Vui lòng nhập đúng 10 chữ số.';
        header("Location: edit_customer.php?id=$customer_id");
        exit();
    }
    
    // Validate email if provided
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error_message'] = 'Email không hợp lệ!';
        header("Location: edit_customer.php?id=$customer_id");
        exit();
    }
    
    // Check if phone number already exists (excluding current user)
    $check_phone = $conn->prepare("SELECT id_user FROM users WHERE sdt = ? AND id_user != ?");
    $check_phone->bind_param("si", $sdt, $customer_id);
    $check_phone->execute();
    $check_phone->store_result();
    
    if ($check_phone->num_rows > 0) {
        $_SESSION['error_message'] = 'Số điện thoại đã được sử dụng bởi người dùng khác!';
        header("Location: edit_customer.php?id=$customer_id");
        exit();
    }
    
    // Check if email already exists (excluding current user)
    if (!empty($email)) {
        $check_email = $conn->prepare("SELECT id_user FROM users WHERE email = ? AND id_user != ?");
        $check_email->bind_param("si", $email, $customer_id);
        $check_email->execute();
        $check_email->store_result();
        
        if ($check_email->num_rows > 0) {
            $_SESSION['error_message'] = 'Email đã được sử dụng bởi người dùng khác!';
            header("Location: edit_customer.php?id=$customer_id");
            exit();
        }
    }
    
    // Handle profile picture upload
    $profile_picture = '';
    $profile_picture_updated = false;
    
    // Get current profile picture
    $get_current_picture = $conn->prepare("SELECT anh_dai_dien FROM users WHERE id_user = ?");
    $get_current_picture->bind_param("i", $customer_id);
    $get_current_picture->execute();
    $get_current_picture->store_result();
    $get_current_picture->bind_result($current_picture);
    $get_current_picture->fetch();
    $get_current_picture->close();
    
    // Check if picture should be removed
    if ($remove_picture && !empty($current_picture)) {
        $file_path = "../uploads/users/" . $current_picture;
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        $profile_picture = '';
        $profile_picture_updated = true;
    } 
    // Process new upload
    elseif (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024; // 2MB
        
        if (!in_array($_FILES['profile_picture']['type'], $allowed_types)) {
            $_SESSION['error_message'] = 'Chỉ được phép tải lên file hình ảnh (JPG, PNG, GIF)!';
            header("Location: edit_customer.php?id=$customer_id");
            exit();
        }
        
        if ($_FILES['profile_picture']['size'] > $max_size) {
            $_SESSION['error_message'] = 'Kích thước file quá lớn (tối đa 2MB)!';
            header("Location: edit_customer.php?id=$customer_id");
            exit();
        }
        
        // Create directory if not exists
        $upload_dir = "../uploads/users/";
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Generate unique filename
        $file_ext = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
        $filename = time() . '_' . uniqid() . '.' . $file_ext;
        $target_file = $upload_dir . $filename;
        
        // Upload file
        if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_file)) {
            // Delete old file if exists
            if (!empty($current_picture)) {
                $old_file = $upload_dir . $current_picture;
                if (file_exists($old_file)) {
                    unlink($old_file);
                }
            }
            
            $profile_picture = $filename;
            $profile_picture_updated = true;
        } else {
            $_SESSION['error_message'] = 'Lỗi khi tải lên hình ảnh!';
            header("Location: edit_customer.php?id=$customer_id");
            exit();
        }
    }
    
    // Build the update query based on what needs to be updated
    $update_fields = [];
    $param_types = '';
    $param_values = [];

    // Always updated fields
    $update_fields[] = "tenuser = ?";
    $update_fields[] = "sdt = ?";
    $update_fields[] = "diachi = ?";
    $update_fields[] = "trang_thai = ?";
    $update_fields[] = "trang_thai_xac_thuc = ?";
    
    $param_types .= 'ssii';
    $param_values[] = $tenuser;
    $param_values[] = $sdt;
    $param_values[] = $diachi;
    $param_values[] = $trang_thai;
    $param_values[] = $trang_thai_xac_thuc;
    
    // Conditionally updated fields
    if (!empty($email)) {
        $update_fields[] = "email = ?";
        $param_types .= 's';
        $param_values[] = $email;
    } else {
        $update_fields[] = "email = NULL";
    }
    
    // Update password if provided
    if (!empty($new_password)) {
        if (strlen($new_password) < 8) {
            $_SESSION['error_message'] = 'Mật khẩu mới phải có ít nhất 8 ký tự!';
            header("Location: edit_customer.php?id=$customer_id");
            exit();
        }
        
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $update_fields[] = "matkhau = ?";
        $param_types .= 's';
        $param_values[] = $hashed_password;
    }
    
    // Update profile picture if changed
    if ($profile_picture_updated) {
        $update_fields[] = "anh_dai_dien = ?";
        $param_types .= 's';
        $param_values[] = $profile_picture;
    }
    
    // Add customer ID to parameters
    $param_types .= 'i';
    $param_values[] = $customer_id;
    
    // Prepare and execute the update query
    $update_query = "UPDATE users SET " . implode(", ", $update_fields) . " WHERE id_user = ?";
    $stmt = $conn->prepare($update_query);
    
    // Dynamically bind parameters
    $stmt->bind_param($param_types, ...$param_values);
    
    if ($stmt->execute()) {
        $_SESSION['success_message'] = 'Cập nhật thông tin khách hàng thành công!';
        header("Location: customer-detail.php?id=$customer_id");
        exit();
    } else {
        $_SESSION['error_message'] = 'Lỗi khi cập nhật thông tin khách hàng: ' . $conn->error;
        header("Location: edit_customer.php?id=$customer_id");
        exit();
    }
}
?>
