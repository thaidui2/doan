<?php
session_start();
include('../config/config.php');

// Debug inventory data
if (isset($_POST['action']) && $_POST['action'] === 'add' && isset($_POST['inventory'])) {
    // Log inventory data for debugging
    $log_file = fopen("../logs/inventory_log.txt", "a");
    fwrite($log_file, "Time: " . date('Y-m-d H:i:s') . "\n");
    fwrite($log_file, "Inventory data: " . print_r($_POST['inventory'], true) . "\n");
    fwrite($log_file, "------------------------\n");
    fclose($log_file);
}

// Kiểm tra đăng nhập
if (!isset($_SESSION['user']) || $_SESSION['user']['logged_in'] !== true) {
    header('Location: ../dangnhap.php?redirect=seller/danh-sach-san-pham.php');
    exit();
}

$user_id = $_SESSION['user']['id'];

// Kiểm tra quyền seller
$check_seller = $conn->prepare("SELECT * FROM users WHERE id_user = ? AND loai_user = 1 AND trang_thai = 1");
$check_seller->bind_param("i", $user_id);
$check_seller->execute();
$result = $check_seller->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "Bạn không có quyền truy cập trang người bán!";
    header("Location: ../index.php");
    exit();
}

// Hàm upload file
function uploadFile($file, $target_dir) {
    // Tạo thư mục nếu không tồn tại
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $filename = time() . '_' . basename($file['name']);
    $target_file = $target_dir . $filename;
    
    // Kiểm tra định dạng file
    $allowed_extensions = array('jpg', 'jpeg', 'png', 'gif', 'webp');
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_extension, $allowed_extensions)) {
        return [false, "Chỉ chấp nhận file ảnh: jpg, jpeg, png, gif, webp"];
    }
    
    // Kiểm tra kích thước file
    if ($file['size'] > 2 * 1024 * 1024) {
        return [false, "File ảnh không được vượt quá 2MB"];
    }
    
    // Upload file
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        return [true, $filename];
    } else {
        return [false, "Có lỗi xảy ra khi tải file lên"];
    }
}

// Xử lý thêm sản phẩm mới
if (isset($_POST['action']) && $_POST['action'] === 'add') {
    // Lấy dữ liệu từ form
    $tensanpham = trim($_POST['tensanpham']);
    $id_loai = (int)$_POST['id_loai'];
    $id_thuonghieu = !empty($_POST['id_thuonghieu']) ? (int)$_POST['id_thuonghieu'] : null;
    $gia = (float)$_POST['gia'];
    $giagoc = !empty($_POST['giagoc']) ? (float)$_POST['giagoc'] : null;
    $mota = trim($_POST['mota']);
    $trangthai = (int)$_POST['trangthai'];
    $noibat = isset($_POST['noibat']) ? 1 : 0;
    
    // Kiểm tra dữ liệu đầu vào
    if (empty($tensanpham) || $id_loai <= 0 || $gia <= 0) {
        $_SESSION['error_message'] = "Vui lòng điền đầy đủ thông tin bắt buộc";
        header("Location: them-san-pham.php");
        exit();
    }
    
    // Kiểm tra đã chọn kích thước và màu sắc chưa
    if (!isset($_POST['sizes']) || !isset($_POST['colors'])) {
        $_SESSION['error_message'] = "Vui lòng chọn ít nhất một kích thước và một màu sắc";
        header("Location: them-san-pham.php");
        exit();
    }
    
    // Xử lý upload hình ảnh chính
    $target_dir = dirname(__FILE__, 2) . "/uploads/products/";
    $upload_result = uploadFile($_FILES['hinhanh'], $target_dir);
    
    if (!$upload_result[0]) {
        $_SESSION['error_message'] = "Lỗi upload hình ảnh chính: " . $upload_result[1];
        header("Location: them-san-pham.php");
        exit();
    }
    
    $hinhanh = $upload_result[1];
    
    // Xử lý upload hình ảnh phụ (nếu có)
    $hinhanh_phu = [];
    if (isset($_FILES['hinhanh_phu']) && $_FILES['hinhanh_phu']['name'][0] != '') {
        $file_count = count($_FILES['hinhanh_phu']['name']);
        $file_count = min($file_count, 5); // Giới hạn tối đa 5 ảnh phụ
        
        for ($i = 0; $i < $file_count; $i++) {
            $file = [
                'name' => $_FILES['hinhanh_phu']['name'][$i],
                'type' => $_FILES['hinhanh_phu']['type'][$i],
                'tmp_name' => $_FILES['hinhanh_phu']['tmp_name'][$i],
                'error' => $_FILES['hinhanh_phu']['error'][$i],
                'size' => $_FILES['hinhanh_phu']['size'][$i]
            ];
            
            if ($file['error'] === 0) {
                $upload_result = uploadFile($file, $target_dir);
                
                if ($upload_result[0]) {
                    $hinhanh_phu[] = $upload_result[1];
                }
            }
        }
    }
    
    // Chuyển mảng hình ảnh phụ thành chuỗi để lưu vào database
    $hinhanh_phu_str = !empty($hinhanh_phu) ? implode('|', $hinhanh_phu) : null;
    
    try {
        // Bắt đầu transaction
        $conn->begin_transaction();
        
        // Thêm thông tin sản phẩm cơ bản
        $stmt = $conn->prepare("
            INSERT INTO sanpham (
                tensanpham, mota, gia, giagoc, hinhanh, hinhanh_phu, 
                id_loai, id_thuonghieu, id_nguoiban, trangthai, noibat, ngaytao
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->bind_param(
            "ssddssiiiii",
            $tensanpham, $mota, $gia, $giagoc, $hinhanh, $hinhanh_phu_str,
            $id_loai, $id_thuonghieu, $user_id, $trangthai, $noibat
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Lỗi khi thêm sản phẩm: " . $stmt->error);
        }
        
        $id_sanpham = $conn->insert_id;
        $sizes = $_POST['sizes'];
        $colors = $_POST['colors'];
        $total_quantity = 0;
        
        // Thêm biến thể sản phẩm và số lượng tồn kho
        foreach ($sizes as $size_id) {
            foreach ($colors as $color_id) {
                // Lấy số lượng từ form
                $quantity = 0;
                if (isset($_POST['inventory']) && 
                    is_array($_POST['inventory']) && 
                    isset($_POST['inventory'][$size_id]) && 
                    is_array($_POST['inventory'][$size_id]) && 
                    isset($_POST['inventory'][$size_id][$color_id])) {
                    $quantity = (int)$_POST['inventory'][$size_id][$color_id];
                }
                
                $total_quantity += $quantity;
                
                // Thêm vào bảng sanpham_chitiet
                $variant_stmt = $conn->prepare("
                    INSERT INTO sanpham_chitiet (id_sanpham, id_kichthuoc, id_mausac, soluong) 
                    VALUES (?, ?, ?, ?)
                ");
                
                $variant_stmt->bind_param("iiii", $id_sanpham, $size_id, $color_id, $quantity);
                
                if (!$variant_stmt->execute()) {
                    throw new Exception("Lỗi khi thêm biến thể sản phẩm: " . $variant_stmt->error);
                }
                
                // Xử lý upload hình ảnh biến thể (nếu có)
                $variant_id = $conn->insert_id;
                
                if (isset($_FILES['variant_image']['name'][$size_id][$color_id]) && 
                    $_FILES['variant_image']['tmp_name'][$size_id][$color_id] && 
                    $_FILES['variant_image']['error'][$size_id][$color_id] === 0) {
                    $file = [
                        'name' => $_FILES['variant_image']['name'][$size_id][$color_id],
                        'type' => $_FILES['variant_image']['type'][$size_id][$color_id],
                        'tmp_name' => $_FILES['variant_image']['tmp_name'][$size_id][$color_id],
                        'error' => $_FILES['variant_image']['error'][$size_id][$color_id],
                        'size' => $_FILES['variant_image']['size'][$size_id][$color_id]
                    ];
                    
                    $upload_result = uploadFile($file, $target_dir);
                    
                    if ($upload_result[0]) {
                        $variant_img = $upload_result[1];
                        
                        // Cập nhật hình ảnh vào bảng sanpham_chitiet
                        $update_img = $conn->prepare("UPDATE sanpham_chitiet SET hinhanh_mau = ? WHERE id_chitiet = ?");
                        $update_img->bind_param("si", $variant_img, $variant_id);
                        $update_img->execute();
                        
                        // Thêm vào bảng mausac_hinhanh để hiển thị trong trang chi tiết sản phẩm
                        $color_img = $conn->prepare("
                            INSERT INTO mausac_hinhanh (id_sanpham, id_mausac, hinhanh) 
                            VALUES (?, ?, ?) 
                            ON DUPLICATE KEY UPDATE hinhanh = ?
                        ");
                        $color_img->bind_param("iiss", $id_sanpham, $color_id, $variant_img, $variant_img);
                        $color_img->execute();
                    }
                }
            }
        }
        
        // Cập nhật tổng số lượng trong bảng sản phẩm
        $update_total = $conn->prepare("UPDATE sanpham SET soluong = ? WHERE id_sanpham = ?");
        $update_total->bind_param("ii", $total_quantity, $id_sanpham);
        $update_total->execute();
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['success_message'] = "Thêm sản phẩm thành công!";
        header("Location: danh-sach-san-pham.php");
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction nếu có lỗi
        $conn->rollback();
        $_SESSION['error_message'] = $e->getMessage();
        header("Location: them-san-pham.php");
        exit();
    }
}

// Xử lý sửa sản phẩm
else if (isset($_POST['action']) && $_POST['action'] === 'edit') {
    // Lấy ID sản phẩm
    $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
    
    // Kiểm tra sản phẩm có tồn tại và thuộc về người bán này không
    $check_product = $conn->prepare("SELECT id_sanpham FROM sanpham WHERE id_sanpham = ? AND id_nguoiban = ?");
    $check_product->bind_param("ii", $product_id, $user_id);
    $check_product->execute();
    
    if ($check_product->get_result()->num_rows === 0) {
        $_SESSION['error_message'] = "Bạn không có quyền chỉnh sửa sản phẩm này";
        header("Location: danh-sach-san-pham.php");
        exit();
    }
    
    // Xử lý và cập nhật dữ liệu tương tự như khi thêm sản phẩm...
}

// Xử lý xóa sản phẩm
else if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $product_id = (int)$_GET['id'];
    
    // Kiểm tra sản phẩm có thuộc về người bán này không
    $check_owner = $conn->prepare("SELECT id_sanpham FROM sanpham WHERE id_sanpham = ? AND id_nguoiban = ?");
    $check_owner->bind_param("ii", $product_id, $user_id);
    $check_owner->execute();
    
    if ($check_owner->get_result()->num_rows === 0) {
        $_SESSION['error_message'] = "Bạn không có quyền xóa sản phẩm này";
        header("Location: danh-sach-san-pham.php");
        exit();
    }
    
    try {
        // Bắt đầu transaction
        $conn->begin_transaction();
        
        // Xóa các biến thể sản phẩm
        $delete_variants = $conn->prepare("DELETE FROM sanpham_chitiet WHERE id_sanpham = ?");
        $delete_variants->bind_param("i", $product_id);
        $delete_variants->execute();
        
        // Xóa hình ảnh màu sắc
        $delete_color_images = $conn->prepare("DELETE FROM mausac_hinhanh WHERE id_sanpham = ?");
        $delete_color_images->bind_param("i", $product_id);
        $delete_color_images->execute();
        
        // Xóa sản phẩm
        $delete_product = $conn->prepare("DELETE FROM sanpham WHERE id_sanpham = ?");
        $delete_product->bind_param("i", $product_id);
        $delete_product->execute();
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['success_message'] = "Xóa sản phẩm thành công!";
    } catch (Exception $e) {
        // Rollback transaction nếu có lỗi
        $conn->rollback();
        $_SESSION['error_message'] = "Lỗi khi xóa sản phẩm: " . $e->getMessage();
    }
    
    header("Location: danh-sach-san-pham.php");
    exit();
}

// Xử lý các thao tác hàng loạt
else if (isset($_GET['action']) && strpos($_GET['action'], 'bulk_') === 0 && isset($_GET['ids'])) {
    $action = substr($_GET['action'], 5); // Lấy phần sau 'bulk_'
    $ids = explode(',', $_GET['ids']);
    $ids = array_map('intval', $ids); // Chuyển đổi thành số nguyên để đảm bảo an toàn
    
    // Kiểm tra tất cả sản phẩm có thuộc về người bán này không
    foreach ($ids as $id) {
        $check_owner = $conn->prepare("SELECT id_sanpham FROM sanpham WHERE id_sanpham = ? AND id_nguoiban = ?");
        $check_owner->bind_param("ii", $id, $user_id);
        $check_owner->execute();
        
        if ($check_owner->get_result()->num_rows === 0) {
            $_SESSION['error_message'] = "Bạn không có quyền thao tác trên một hoặc nhiều sản phẩm đã chọn";
            header("Location: danh-sach-san-pham.php");
            exit();
        }
    }
    
    try {
        // Bắt đầu transaction
        $conn->begin_transaction();
        
        switch ($action) {
            case 'active':
                // Hiển thị sản phẩm (trạng thái = 1)
                $stmt = $conn->prepare("UPDATE sanpham SET trangthai = 1 WHERE id_sanpham IN (" . implode(',', array_fill(0, count($ids), '?')) . ")");
                $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
                $stmt->execute();
                $_SESSION['success_message'] = "Đã hiển thị " . count($ids) . " sản phẩm";
                break;
                
            case 'inactive':
                // Ẩn sản phẩm (trạng thái = 2 - ngừng kinh doanh)
                $stmt = $conn->prepare("UPDATE sanpham SET trangthai = 2 WHERE id_sanpham IN (" . implode(',', array_fill(0, count($ids), '?')) . ")");
                $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
                $stmt->execute();
                $_SESSION['success_message'] = "Đã ẩn " . count($ids) . " sản phẩm";
                break;
                
            case 'delete':
                // Xóa sản phẩm (sẽ xóa cả biến thể)
                foreach ($ids as $id) {
                    // Xóa các biến thể sản phẩm
                    $delete_variants = $conn->prepare("DELETE FROM sanpham_chitiet WHERE id_sanpham = ?");
                    $delete_variants->bind_param("i", $id);
                    $delete_variants->execute();
                    
                    // Xóa hình ảnh màu sắc
                    $delete_color_images = $conn->prepare("DELETE FROM mausac_hinhanh WHERE id_sanpham = ?");
                    $delete_color_images->bind_param("i", $id);
                    $delete_color_images->execute();
                }
                
                // Xóa sản phẩm
                $stmt = $conn->prepare("DELETE FROM sanpham WHERE id_sanpham IN (" . implode(',', array_fill(0, count($ids), '?')) . ")");
                $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
                $stmt->execute();
                $_SESSION['success_message'] = "Đã xóa " . count($ids) . " sản phẩm";
                break;
                
            default:
                throw new Exception("Hành động không hợp lệ");
        }
        
        // Commit transaction
        $conn->commit();
        
    } catch (Exception $e) {
        // Rollback transaction nếu có lỗi
        $conn->rollback();
        $_SESSION['error_message'] = "Lỗi khi xử lý hành động hàng loạt: " . $e->getMessage();
    }
    
    header("Location: danh-sach-san-pham.php");
    exit();
}

// Mặc định chuyển về trang danh sách sản phẩm
else {
    header("Location: danh-sach-san-pham.php");
    exit();
}
?>

<!-- JavaScript code for product form validation -->
