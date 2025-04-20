<?php
// Kiểm tra đăng nhập và quyền admin
session_start();
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_loai']) || $_SESSION['admin_loai'] < 1) {
    header('Location: login.php');
    exit();
}

// Kết nối database
require_once '../config/config.php';

// Kiểm tra form submit
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: categories.php?error=Phương thức không hợp lệ');
    exit();
}

// Lấy dữ liệu từ form
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$ten = trim($_POST['ten'] ?? '');
$slug = trim($_POST['slug'] ?? '');
$mo_ta = trim($_POST['mo_ta'] ?? '');
$danhmuc_cha = !empty($_POST['danhmuc_cha']) ? intval($_POST['danhmuc_cha']) : null;
$thu_tu = intval($_POST['thu_tu'] ?? 0);
$meta_title = trim($_POST['meta_title'] ?? '');
$meta_description = trim($_POST['meta_description'] ?? '');
$trang_thai = isset($_POST['trang_thai']) ? 1 : 0;

// Validate dữ liệu
if (empty($ten)) {
    header('Location: categories.php?error=Vui lòng nhập tên danh mục');
    exit();
}

// Tạo slug nếu chưa có
if (empty($slug)) {
    $slug = create_slug($ten);
}

// Kiểm tra slug unique
$check_slug_sql = "SELECT id FROM danhmuc WHERE slug = ? AND id != ?";
$check_slug_stmt = $conn->prepare($check_slug_sql);
$check_slug_stmt->bind_param("si", $slug, $id);
$check_slug_stmt->execute();
$slug_result = $check_slug_stmt->get_result();

if ($slug_result->num_rows > 0) {
    $slug = $slug . '-' . time();
}

// Kiểm tra danh mục cha tồn tại
if ($danhmuc_cha !== null) {
    $check_parent_sql = "SELECT id FROM danhmuc WHERE id = ?";
    $check_parent_stmt = $conn->prepare($check_parent_sql);
    $check_parent_stmt->bind_param("i", $danhmuc_cha);
    $check_parent_stmt->execute();
    $parent_result = $check_parent_stmt->get_result();
    
    if ($parent_result->num_rows == 0) {
        $danhmuc_cha = null;
    }
    
    // Không được chọn chính nó làm cha
    if ($danhmuc_cha == $id) {
        $danhmuc_cha = null;
    }
}

// Xử lý upload hình ảnh
$hinhanh = null;
if (isset($_FILES['hinhanh']) && $_FILES['hinhanh']['error'] === 0) {
    $upload_dir = '../uploads/categories/';
    
    // Tạo thư mục nếu chưa tồn tại
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file_name = time() . '_' . basename($_FILES['hinhanh']['name']);
    $file_path = $upload_dir . $file_name;
    
    // Kiểm tra loại file
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $file_type = mime_content_type($_FILES['hinhanh']['tmp_name']);
    
    if (!in_array($file_type, $allowed_types)) {
        header('Location: categories.php?error=Chỉ cho phép upload ảnh dạng JPG, PNG, GIF hoặc WEBP');
        exit();
    }
    
    // Kiểm tra kích thước file (tối đa 2MB)
    if ($_FILES['hinhanh']['size'] > 2 * 1024 * 1024) {
        header('Location: categories.php?error=Kích thước ảnh không được vượt quá 2MB');
        exit();
    }
    
    if (move_uploaded_file($_FILES['hinhanh']['tmp_name'], $file_path)) {
        $hinhanh = 'uploads/categories/' . $file_name;
        
        // Nếu là edit và có ảnh cũ, xóa ảnh cũ
        if ($id > 0) {
            $old_image_sql = "SELECT hinhanh FROM danhmuc WHERE id = ?";
            $old_image_stmt = $conn->prepare($old_image_sql);
            $old_image_stmt->bind_param("i", $id);
            $old_image_stmt->execute();
            $old_image = $old_image_stmt->get_result()->fetch_assoc()['hinhanh'];
            
            if (!empty($old_image) && file_exists('../' . $old_image)) {
                @unlink('../' . $old_image);
            }
        }
    }
}

try {
    $conn->begin_transaction();
    
    if ($id > 0) {
        // UPDATE
        if ($hinhanh) {
            $update_sql = "UPDATE danhmuc SET 
                          ten = ?, 
                          slug = ?, 
                          mo_ta = ?, 
                          danhmuc_cha = ?, 
                          thu_tu = ?, 
                          meta_title = ?, 
                          meta_description = ?, 
                          trang_thai = ?, 
                          hinhanh = ? 
                          WHERE id = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("ssssissssi", $ten, $slug, $mo_ta, $danhmuc_cha, $thu_tu, $meta_title, $meta_description, $trang_thai, $hinhanh, $id);
        } else {
            $update_sql = "UPDATE danhmuc SET 
                          ten = ?, 
                          slug = ?, 
                          mo_ta = ?, 
                          danhmuc_cha = ?, 
                          thu_tu = ?, 
                          meta_title = ?, 
                          meta_description = ?, 
                          trang_thai = ? 
                          WHERE id = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("ssssissii", $ten, $slug, $mo_ta, $danhmuc_cha, $thu_tu, $meta_title, $meta_description, $trang_thai, $id);
        }
        
        $stmt->execute();
        
        // Ghi log
        $log_sql = "INSERT INTO nhat_ky (id_user, hanh_dong, doi_tuong_loai, doi_tuong_id, chi_tiet, ip_address) 
                   VALUES (?, 'update', 'category', ?, ?, ?)";
        $log_stmt = $conn->prepare($log_sql);
        $admin_id = $_SESSION['admin_id'];
        $detail = "Cập nhật danh mục: $ten (ID: $id)";
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $log_stmt->bind_param("iiss", $admin_id, $id, $detail, $ip);
        $log_stmt->execute();
        
        $success = 'Đã cập nhật danh mục thành công';
    } else {
        // INSERT
        if ($hinhanh) {
            $insert_sql = "INSERT INTO danhmuc (ten, slug, mo_ta, danhmuc_cha, thu_tu, meta_title, meta_description, trang_thai, hinhanh, ngay_tao) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("sssississ", $ten, $slug, $mo_ta, $danhmuc_cha, $thu_tu, $meta_title, $meta_description, $trang_thai, $hinhanh);
        } else {
            $insert_sql = "INSERT INTO danhmuc (ten, slug, mo_ta, danhmuc_cha, thu_tu, meta_title, meta_description, trang_thai, ngay_tao) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("sssissis", $ten, $slug, $mo_ta, $danhmuc_cha, $thu_tu, $meta_title, $meta_description, $trang_thai);
        }
        
        $stmt->execute();
        $id = $conn->insert_id;
        
        // Ghi log
        $log_sql = "INSERT INTO nhat_ky (id_user, hanh_dong, doi_tuong_loai, doi_tuong_id, chi_tiet, ip_address) 
                   VALUES (?, 'create', 'category', ?, ?, ?)";
        $log_stmt = $conn->prepare($log_sql);
        $admin_id = $_SESSION['admin_id'];
        $detail = "Thêm danh mục mới: $ten";
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $log_stmt->bind_param("iiss", $admin_id, $id, $detail, $ip);
        $log_stmt->execute();
        
        $success = 'Đã thêm danh mục mới thành công';
    }
    
    $conn->commit();
    header('Location: categories.php?success=' . urlencode($success));
    exit();
    
} catch (Exception $e) {
    $conn->rollback();
    header('Location: categories.php?error=Lỗi: ' . urlencode($e->getMessage()));
    exit();
}

// Hàm tạo slug
function create_slug($string)
{
    $search = array(
        '#(à|á|ạ|ả|ã|â|ầ|ấ|ậ|ẩ|ẫ|ă|ằ|ắ|ặ|ẳ|ẵ)#',
        '#(è|é|ẹ|ẻ|ẽ|ê|ề|ế|ệ|ể|ễ)#',
        '#(ì|í|ị|ỉ|ĩ)#',
        '#(ò|ó|ọ|ỏ|õ|ô|ồ|ố|ộ|ổ|ỗ|ơ|ờ|ớ|ợ|ở|ỡ)#',
        '#(ù|ú|ụ|ủ|ũ|ư|ừ|ứ|ự|ử|ữ)#',
        '#(ỳ|ý|ỵ|ỷ|ỹ)#',
        '#(đ)#',
        '#([^a-z0-9]+)#i',
        '#-+#'
    );
    $replace = array(
        'a',
        'e',
        'i',
        'o',
        'u',
        'y',
        'd',
        '-',
        '-'
    );
    $string = strtolower($string);
    $string = preg_replace($search, $replace, $string);
    $string = preg_replace('#-+#', '-', $string);
    $string = trim($string, '-');
    return $string;
}
?>
