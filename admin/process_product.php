<?php
session_start();

// Kiểm tra đăng nhập
if(!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

include('../config/config.php');

// Khởi tạo biến thông báo
$response = [
    'success' => false,
    'message' => '',
    'redirect' => '',
    'debug' => []
];

// Kiểm tra request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Phương thức không hợp lệ';
    echo json_encode($response);
    exit;
}

// Lấy ID sản phẩm từ POST data
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;

// Lấy dữ liệu từ form
$ten_sp = trim($_POST['ten_sp'] ?? '');
$giagoc = (float)($_POST['giagoc'] ?? 0);
$gia = !empty($_POST['gia']) ? (float)$_POST['gia'] : $giagoc;
$so_luong = (int)($_POST['so_luong'] ?? 0);
$mo_ta = trim($_POST['mo_ta'] ?? '');
$id_loai = (int)($_POST['id_loai'] ?? 0);
$trang_thai = isset($_POST['trang_thai']) ? 1 : 0;
$selected_sizes = isset($_POST['sizes']) ? $_POST['sizes'] : [];
$selected_colors = isset($_POST['colors']) ? $_POST['colors'] : [];

// Debug: Lưu các biến để kiểm tra
$response['debug']['post_data'] = $_POST;
$response['debug']['selected_colors'] = $selected_colors;
$response['debug']['files'] = $_FILES;

try {
    // Validate dữ liệu
    if (empty($ten_sp)) {
        throw new Exception("Vui lòng nhập tên sản phẩm");
    }
    
    if ($giagoc <= 0) {
        throw new Exception("Giá gốc sản phẩm phải lớn hơn 0");
    }
    
    if ($gia > $giagoc) {
        throw new Exception("Giá bán (sau khuyến mãi) không thể lớn hơn giá gốc");
    }
    
    if ($so_luong < 0) {
        throw new Exception("Số lượng không hợp lệ");
    }
    
    // Lấy thông tin sản phẩm cũ (nếu là cập nhật)
    if ($product_id > 0) {
        $stmt = $conn->prepare("SELECT * FROM sanpham WHERE id_sanpham = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Không tìm thấy sản phẩm với ID: {$product_id}");
        }
        
        $product = $result->fetch_assoc();
    }
    
    // Xử lý upload hình ảnh chính
    $hinh_anh = ($product_id > 0) ? $product['hinhanh'] : '';
    
    if (isset($_FILES['hinh_anh']) && $_FILES['hinh_anh']['error'] == 0) {
        $upload_dir = "../uploads/products/";
        
        // Tạo thư mục nếu chưa tồn tại
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_name = $_FILES['hinh_anh']['name'];
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_extensions = ["jpg", "jpeg", "png", "gif", "webp"];
        
        if (in_array($file_extension, $allowed_extensions)) {
            $unique_name = time() . '_' . uniqid() . '.' . $file_extension;
            $upload_path = $upload_dir . $unique_name;
            
            if (move_uploaded_file($_FILES['hinh_anh']['tmp_name'], $upload_path)) {
                // Xóa ảnh cũ nếu có và đây là cập nhật
                if ($product_id > 0 && !empty($product['hinhanh']) && file_exists($upload_dir . $product['hinhanh'])) {
                    unlink($upload_dir . $product['hinhanh']);
                }
                
                $hinh_anh = $unique_name;
            } else {
                throw new Exception("Có lỗi xảy ra khi tải lên hình ảnh chính");
            }
        } else {
            throw new Exception("Chỉ cho phép các file hình ảnh (jpg, jpeg, png, gif, webp)");
        }
    }
    
    // Xử lý nếu người dùng chọn xóa ảnh
    if (isset($_POST['xoa_anh']) && $_POST['xoa_anh'] == 1) {
        if ($product_id > 0 && !empty($product['hinhanh']) && file_exists("../uploads/products/" . $product['hinhanh'])) {
            unlink("../uploads/products/" . $product['hinhanh']);
        }
        $hinh_anh = '';
    }
    
    // Bắt đầu transaction
    $conn->begin_transaction();
    
    // Thêm hoặc cập nhật sản phẩm
    if ($product_id > 0) {
        // Cập nhật sản phẩm hiện có
        $sql = "UPDATE sanpham SET 
                tensanpham = ?, 
                gia = ?, 
                giagoc = ?, 
                soluong = ?, 
                mota = ?, 
                hinhanh = ?, 
                id_loai = ?, 
                trangthai = ? 
                WHERE id_sanpham = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sddsssiii", $ten_sp, $gia, $giagoc, $so_luong, $mo_ta, $hinh_anh, $id_loai, $trang_thai, $product_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Lỗi khi cập nhật sản phẩm: " . $stmt->error);
        }
    } else {
        // Thêm sản phẩm mới
        $sql = "INSERT INTO sanpham (tensanpham, gia, giagoc, soluong, mota, hinhanh, id_loai, trangthai) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sddiisii", $ten_sp, $gia, $giagoc, $so_luong, $mo_ta, $hinh_anh, $id_loai, $trang_thai);
        
        if (!$stmt->execute()) {
            throw new Exception("Lỗi khi thêm sản phẩm: " . $stmt->error);
        }
        
        $product_id = $conn->insert_id;
    }
    
    // Xóa tất cả các biến thể hiện tại của sản phẩm (nếu là cập nhật)
    if ($product_id > 0) {
        // Lưu thông tin về hình ảnh màu hiện tại
        $existing_color_images = [];
        $color_images_query = $conn->prepare("SELECT id_mausac, hinhanh_mau FROM sanpham_chitiet WHERE id_sanpham = ? AND hinhanh_mau IS NOT NULL");
        $color_images_query->bind_param("i", $product_id);
        $color_images_query->execute();
        $color_images_result = $color_images_query->get_result();
        
        while ($row = $color_images_result->fetch_assoc()) {
            if (!empty($row['hinhanh_mau'])) {
                $existing_color_images[$row['id_mausac']] = $row['hinhanh_mau'];
            }
        }
        
        $response['debug']['existing_color_images'] = $existing_color_images;
        
        // Xóa biến thể cũ
        $delete_variants = $conn->prepare("DELETE FROM sanpham_chitiet WHERE id_sanpham = ?");
        $delete_variants->bind_param("i", $product_id);
        $delete_variants->execute();
    }
    
    // Thêm các biến thể mới
    $insert_variant = $conn->prepare("INSERT INTO sanpham_chitiet (id_sanpham, id_kichthuoc, id_mausac, soluong) VALUES (?, ?, ?, ?)");
    
    // Tạo biến thể dựa trên kích thước và màu sắc
    if (!empty($selected_sizes) && !empty($selected_colors)) {
        // Tạo tổ hợp kích thước và màu sắc
        foreach ($selected_sizes as $size_id) {
            foreach ($selected_colors as $color_id) {
                $insert_variant->bind_param("iiii", $product_id, $size_id, $color_id, $so_luong);
                if (!$insert_variant->execute()) {
                    throw new Exception("Lỗi khi thêm biến thể: " . $insert_variant->error);
                }
                
                $variant_id = $conn->insert_id;
                
                // Kiểm tra nếu có ảnh cho màu này từ trước
                if (isset($existing_color_images[$color_id])) {
                    $update_color_image = $conn->prepare("UPDATE sanpham_chitiet SET hinhanh_mau = ? WHERE id_chitiet = ?");
                    $update_color_image->bind_param("si", $existing_color_images[$color_id], $variant_id);
                    $update_color_image->execute();
                }
            }
        }
    } else if (!empty($selected_sizes)) {
        // Chỉ có kích thước, dùng màu mặc định
        $default_color = 1;
        foreach ($selected_sizes as $size_id) {
            $insert_variant->bind_param("iiii", $product_id, $size_id, $default_color, $so_luong);
            $insert_variant->execute();
        }
    } else if (!empty($selected_colors)) {
        // Chỉ có màu, không có kích thước
        $null_size = null;
        foreach ($selected_colors as $color_id) {
            $insert_variant->bind_param("iiii", $product_id, $null_size, $color_id, $so_luong);
            $insert_variant->execute();
            
            $variant_id = $conn->insert_id;
            
            // Kiểm tra nếu có ảnh cho màu này từ trước
            if (isset($existing_color_images[$color_id])) {
                $update_color_image = $conn->prepare("UPDATE sanpham_chitiet SET hinhanh_mau = ? WHERE id_chitiet = ?");
                $update_color_image->bind_param("si", $existing_color_images[$color_id], $variant_id);
                $update_color_image->execute();
            }
        }
    }
    
    // Xử lý upload hình ảnh màu sắc
    $color_image_ids = $_POST['color_image_id'] ?? [];
    $color_images = $_FILES['color_image'] ?? null;
    
    $response['debug']['color_image_ids'] = $color_image_ids;
    $response['debug']['color_images'] = $color_images ? array_keys($color_images) : [];
    
    // Tạo thư mục uploadnếu chưa tồn tại
    $color_upload_dir = "../uploads/colors/";
    if (!file_exists($color_upload_dir)) {
        mkdir($color_upload_dir, 0777, true);
    }
    
    // Xử lý các file đã upload
    if ($color_images && !empty($color_image_ids)) {
        for ($i = 0; $i < count($color_image_ids); $i++) {
            $color_id = $color_image_ids[$i];
            
            // Kiểm tra nếu có file được upload
            if (isset($color_images['name'][$i]) && $color_images['error'][$i] == 0) {
                $file_name = $color_images['name'][$i];
                $file_tmp = $color_images['tmp_name'][$i];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                if (in_array($file_ext, ["jpg", "jpeg", "png", "gif", "webp"])) {
                    // Tạo tên file duy nhất
                    $unique_name = "color_" . $product_id . "_" . $color_id . "_" . time() . "." . $file_ext;
                    $upload_path = $color_upload_dir . $unique_name;
                    
                    if (move_uploaded_file($file_tmp, $upload_path)) {
                        // Xóa ảnh màu cũ nếu có
                        if (isset($existing_color_images[$color_id]) && file_exists($color_upload_dir . $existing_color_images[$color_id])) {
                            unlink($color_upload_dir . $existing_color_images[$color_id]);
                        }
                        
                        // Cập nhật đường dẫn hình ảnh cho tất cả biến thể có màu này
                        $update_color_variants = $conn->prepare("UPDATE sanpham_chitiet SET hinhanh_mau = ? WHERE id_sanpham = ? AND id_mausac = ?");
                        $update_color_variants->bind_param("sii", $unique_name, $product_id, $color_id);
                        $update_color_variants->execute();
                        
                        $response['debug']['upload_success'][$color_id] = $unique_name;
                    } else {
                        $response['debug']['upload_failed'][$color_id] = "Cannot move uploaded file";
                    }
                } else {
                    $response['debug']['invalid_ext'][$color_id] = $file_ext;
                }
            }
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    // Phản hồi thành công
    $response['success'] = true;
    $response['message'] = $product_id > 0 ? "Cập nhật sản phẩm thành công!" : "Thêm sản phẩm mới thành công!";
    $response['redirect'] = "edit_product.php?id=" . $product_id . "&success=1";
    
} catch (Exception $e) {
    // Rollback transaction nếu có lỗi
    $conn->rollback();
    
    $response['message'] = $e->getMessage();
    $response['debug']['error'] = $e->getTraceAsString();
}

// Trả về kết quả dạng JSON
header('Content-Type: application/json');
echo json_encode($response);
exit;
