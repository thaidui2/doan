<?php
session_start();

// Kiểm tra đăng nhập
if(!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

include('../config/config.php');

// Kiểm tra và tự động thêm cột hinhanh_mau nếu chưa tồn tại
$check_column = $conn->query("SHOW COLUMNS FROM sanpham_chitiet LIKE 'hinhanh_mau'");
if ($check_column->num_rows == 0) {
    $alter_table = "ALTER TABLE sanpham_chitiet ADD hinhanh_mau VARCHAR(255) NULL AFTER soluong";
    $conn->query($alter_table);
}

// Kiểm tra ID sản phẩm
if(!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: products.php");
    exit();
}

$product_id = (int)$_GET['id'];

// Lấy thông tin sản phẩm
$stmt = $conn->prepare("SELECT * FROM sanpham WHERE id_sanpham = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows != 1) {
    header("Location: products.php");
    exit();
}

$product = $result->fetch_assoc();

// Lấy các kích thước đã gán cho sản phẩm này
$product_sizes = [];
$sizes_stmt = $conn->prepare("SELECT id_kichthuoc FROM sanpham_chitiet WHERE id_sanpham = ?");
$sizes_stmt->bind_param("i", $product_id);
$sizes_stmt->execute();
$sizes_result = $sizes_stmt->get_result();

while ($size_row = $sizes_result->fetch_assoc()) {
    $product_sizes[] = $size_row['id_kichthuoc'];
}

// Lấy các màu sắc đã gán cho sản phẩm này
$product_colors = [];
$colors_stmt = $conn->prepare("SELECT id_mausac FROM sanpham_chitiet WHERE id_sanpham = ?");
$colors_stmt->bind_param("i", $product_id);
$colors_stmt->execute();
$colors_result = $colors_stmt->get_result();

while ($color_row = $colors_result->fetch_assoc()) {
    $product_colors[] = $color_row['id_mausac'];
}

// Thêm đoạn code để lấy màu mặc định đầu tiên
$default_color_query = $conn->query("SELECT id_mausac FROM mausac ORDER BY id_mausac LIMIT 1");
$default_color = 1; // Giá trị mặc định nếu không có dữ liệu

if ($default_color_query && $default_color_query->num_rows > 0) {
    $default_color = $default_color_query->fetch_assoc()['id_mausac'];
}

// Lấy thông tin chi tiết của các biến thể sản phẩm (kích thước, màu sắc, hình ảnh)
$product_variants = [];
$variants_stmt = $conn->prepare("
    SELECT spct.id_chitiet, spct.id_kichthuoc, spct.id_mausac, spct.soluong, 
           kt.tenkichthuoc, ms.tenmau, ms.mamau
    FROM sanpham_chitiet spct
    LEFT JOIN kichthuoc kt ON spct.id_kichthuoc = kt.id_kichthuoc
    LEFT JOIN mausac ms ON spct.id_mausac = ms.id_mausac
    WHERE spct.id_sanpham = ?
");
$variants_stmt->bind_param("i", $product_id);
$variants_stmt->execute();
$variants_result = $variants_stmt->get_result();

while ($variant = $variants_result->fetch_assoc()) {
    // Truy vấn riêng để lấy hinhanh_mau nếu có
    $img_stmt = $conn->prepare("SELECT hinhanh_mau FROM sanpham_chitiet WHERE id_chitiet = ?");
    $img_stmt->bind_param("i", $variant['id_chitiet']);
    $img_stmt->execute();
    $img_result = $img_stmt->get_result();
    $img_data = $img_result->fetch_assoc();
    
    // Thêm hinhanh_mau vào dữ liệu variant nếu có
    if ($img_data && isset($img_data['hinhanh_mau'])) {
        $variant['hinhanh_mau'] = $img_data['hinhanh_mau'];
    } else {
        $variant['hinhanh_mau'] = null;
    }
    
    $product_variants[] = $variant;
}

// Thêm hàm hiển thị ảnh màu hiện tại
function getColorImage($product_variants, $color_id) {
    foreach($product_variants as $variant) {
        if ($variant['id_mausac'] == $color_id && !empty($variant['hinhanh_mau'])) {
            return $variant['hinhanh_mau'];
        }
    }
    return null;
}

$error = '';
$success = '';

// Xử lý khi form được submit
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Lấy dữ liệu từ form
    $ten_sp = trim($_POST['ten_sp']);
    $giagoc = (float)$_POST['giagoc'];
    $gia = !empty($_POST['gia']) ? (float)$_POST['gia'] : $giagoc; // Nếu không nhập giá bán, dùng giá gốc
    $so_luong = (int)$_POST['so_luong'];
    $mo_ta = trim($_POST['mo_ta']);
    $id_loai = (int)$_POST['id_loai'];
    $trang_thai = isset($_POST['trang_thai']) ? 1 : 0;
    $selected_sizes = isset($_POST['sizes']) ? $_POST['sizes'] : [];
    $selected_colors = isset($_POST['colors']) ? $_POST['colors'] : [];

    // Validate dữ liệu
    if(empty($ten_sp)) {
        $error = "Vui lòng nhập tên sản phẩm";
    } elseif($giagoc <= 0) {
        $error = "Giá gốc sản phẩm phải lớn hơn 0";
    } elseif($gia > $giagoc) {
        $error = "Giá bán (sau khuyến mãi) không thể lớn hơn giá gốc";
    } elseif($so_luong < 0) {
        $error = "Số lượng không hợp lệ";
    } else {
        // Xử lý upload hình ảnh mới (nếu có)
        $hinh_anh = $product['hinhanh']; // Giữ nguyên ảnh cũ nếu không upload mới
        
        if(isset($_FILES['hinh_anh']) && $_FILES['hinh_anh']['error'] == 0) {
            $upload_dir = "../uploads/products/";
            
            // Tạo thư mục nếu chưa tồn tại
            if(!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_name = $_FILES['hinh_anh']['name'];
            $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed_extensions = ["jpg", "jpeg", "png", "gif", "webp"];
            
            if(in_array($file_extension, $allowed_extensions)) {
                // Tạo tên file duy nhất để tránh trùng lặp
                $unique_name = time() . '_' . uniqid() . '.' . $file_extension;
                $upload_path = $upload_dir . $unique_name;
                
                if(move_uploaded_file($_FILES['hinh_anh']['tmp_name'], $upload_path)) {
                    // Xóa ảnh cũ nếu có
                    if(!empty($product['hinhanh']) && file_exists($upload_dir . $product['hinhanh'])) {
                        unlink($upload_dir . $product['hinhanh']);
                    }
                    
                    $hinh_anh = $unique_name;
                } else {
                    $error = "Có lỗi xảy ra khi tải lên hình ảnh";
                }
            } else {
                $error = "Chỉ cho phép các file hình ảnh (jpg, jpeg, png, gif, webp)";
            }
        }
        
        // Xóa ảnh hiện tại nếu được chọn
        if(isset($_POST['xoa_anh']) && $_POST['xoa_anh'] == 1) {
            if(!empty($product['hinhanh']) && file_exists("../uploads/products/" . $product['hinhanh'])) {
                unlink("../uploads/products/" . $product['hinhanh']);
            }
            $hinh_anh = '';
        }
        
        // Xử lý upload hình ảnh cho từng màu
        $color_images = $_FILES['color_image'] ?? [];
        $color_image_ids = $_POST['color_image_id'] ?? [];

        // Kiểm tra và tạo thư mục uploads/colors nếu chưa tồn tại
        $color_upload_dir = "../uploads/colors/";
        if(!file_exists($color_upload_dir)) {
            mkdir($color_upload_dir, 0777, true);
        }

        // Mảng lưu trữ hình ảnh màu đã upload
        $uploaded_color_images = [];

        // Xử lý từng màu được chọn
        foreach ($color_image_ids as $key => $color_id) {
            // Kiểm tra nếu có upload ảnh cho màu này
            if (isset($color_images['name'][$key]) && !empty($color_images['name'][$key])) {
                // Xử lý upload hình ảnh cho màu
                $file_name = $color_images['name'][$key];
                $file_tmp = $color_images['tmp_name'][$key];
                $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                if (in_array($file_extension, ["jpg", "jpeg", "png", "gif", "webp"])) {
                    $unique_name = time() . '_' . uniqid() . '_' . $color_id . '.' . $file_extension;
                    $upload_path = $color_upload_dir . $unique_name;
                    
                    if (move_uploaded_file($file_tmp, $upload_path)) {
                        // Lưu vào bảng mausac_hinhanh thay vì sanpham_chitiet
                        
                        // Kiểm tra xem đã có hình ảnh cho màu này chưa
                        $check_sql = "SELECT id FROM mausac_hinhanh WHERE id_sanpham = ? AND id_mausac = ?";
                        $check_stmt = $conn->prepare($check_sql);
                        $check_stmt->bind_param("ii", $product_id, $color_id);
                        $check_stmt->execute();
                        $check_result = $check_stmt->get_result();
                        
                        if ($check_result->num_rows > 0) {
                            // Cập nhật hình ảnh hiện có
                            $row = $check_result->fetch_assoc();
                            $update_sql = "UPDATE mausac_hinhanh SET hinhanh = ? WHERE id = ?";
                            $update_stmt = $conn->prepare($update_sql);
                            $update_stmt->bind_param("si", $unique_name, $row['id']);
                            $update_stmt->execute();
                        } else {
                            // Thêm hình ảnh mới
                            $insert_sql = "INSERT INTO mausac_hinhanh (id_sanpham, id_mausac, hinhanh) VALUES (?, ?, ?)";
                            $insert_stmt = $conn->prepare($insert_sql);
                            $insert_stmt->bind_param("iis", $product_id, $color_id, $unique_name);
                            $insert_stmt->execute();
                        }
                        
                        // Lưu lại để sử dụng cho các kích thước khác của cùng màu này
                        $uploaded_color_images[$color_id] = $unique_name;
                    }
                }
            }
        }
        
        // Nếu không có lỗi, cập nhật sản phẩm
        if(empty($error)) {
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
            
            if($stmt->execute()) {
                // Cập nhật bảng sanpham_chitiet cho kích thước và màu sắc sản phẩm
                
                // 1. Xóa tất cả các biến thể hiện tại của sản phẩm
                $delete_variants = $conn->prepare("DELETE FROM sanpham_chitiet WHERE id_sanpham = ?");
                $delete_variants->bind_param("i", $product_id);
                $delete_variants->execute();
                
                // 2. Thêm các biến thể mới được chọn
                if (!empty($selected_sizes) && !empty($selected_colors)) {
                    // Mảng lưu trữ hình ảnh màu đã upload để sử dụng lại cho nhiều size
                    $uploaded_color_images = [];
                    
                    // Trường hợp có cả kích thước và màu sắc: tạo tổ hợp
                    $insert_variant = $conn->prepare("INSERT INTO sanpham_chitiet (id_sanpham, id_kichthuoc, id_mausac, soluong) VALUES (?, ?, ?, ?)");
                    
                    foreach ($selected_sizes as $size_id) {
                        foreach ($selected_colors as $color_id) {
                            $insert_variant->bind_param("iiii", $product_id, $size_id, $color_id, $so_luong);
                            $insert_variant->execute();
                            
                            // Lấy ID vừa chèn vào để cập nhật hình ảnh màu (nếu có)
                            $variant_id = $conn->insert_id;
                            
                            // Nếu color_id này đã có hình ảnh được upload trong request này
                            if (isset($uploaded_color_images[$color_id])) {
                                // Sử dụng lại hình ảnh đã upload
                                $update_image = $conn->prepare("UPDATE sanpham_chitiet SET hinhanh_mau = ? WHERE id_chitiet = ?");
                                $update_image->bind_param("si", $uploaded_color_images[$color_id], $variant_id);
                                $update_image->execute();
                                continue;
                            }
                            
                            // Kiểm tra nếu có upload ảnh cho màu này
                            $color_key = array_search($color_id, $color_image_ids);
                            if ($color_key !== false && isset($color_images['name'][$color_key]) && !empty($color_images['name'][$color_key])) {
                                // Xử lý upload hình ảnh cho màu
                                $file_name = $color_images['name'][$color_key];
                                $file_tmp = $color_images['tmp_name'][$color_key];
                                $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                                
                                if (in_array($file_extension, ["jpg", "jpeg", "png", "gif", "webp"])) {
                                    $unique_name = time() . '_' . uniqid() . '_' . $color_id . '.' . $file_extension;
                                    $upload_path = $color_upload_dir . $unique_name;
                                    
                                    if (move_uploaded_file($file_tmp, $upload_path)) {
                                        // Lưu đường dẫn hình ảnh vào database
                                        $update_image = $conn->prepare("UPDATE sanpham_chitiet SET hinhanh_mau = ? WHERE id_chitiet = ?");
                                        $update_image->bind_param("si", $unique_name, $variant_id);
                                        $update_image->execute();
                                        
                                        // Lưu lại để sử dụng cho các kích thước khác của cùng màu này
                                        $uploaded_color_images[$color_id] = $unique_name;
                                    }
                                }
                            }
                            // Tìm và sao chép hình ảnh từ biến thể cũ nếu có
                            else {
                                foreach ($product_variants as $old_variant) {
                                    if ($old_variant['id_mausac'] == $color_id && !empty($old_variant['hinhanh_mau'])) {
                                        // Sao chép đường dẫn hình ảnh từ biến thể cũ
                                        $update_image = $conn->prepare("UPDATE sanpham_chitiet SET hinhanh_mau = ? WHERE id_chitiet = ?");
                                        $update_image->bind_param("si", $old_variant['hinhanh_mau'], $variant_id);
                                        $update_image->execute();
                                        
                                        // Lưu lại để sử dụng cho các kích thước khác
                                        $uploaded_color_images[$color_id] = $old_variant['hinhanh_mau'];
                                        break;
                                    }
                                }
                            }
                        }
                    }
                } 
                
                $success = "Cập nhật sản phẩm thành công!";
                
                // Cập nhật lại danh sách kích thước và màu sắc của sản phẩm
                $product_sizes = $selected_sizes;
                $product_colors = $selected_colors;
                
                // Lấy lại thông tin chi tiết của các biến thể
                $variants_stmt->execute();
                $variants_result = $variants_stmt->get_result();
                $product_variants = [];
                while ($variant = $variants_result->fetch_assoc()) {
                    $img_stmt->bind_param("i", $variant['id_chitiet']);
                    $img_stmt->execute();
                    $img_result = $img_stmt->get_result();
                    $img_data = $img_result->fetch_assoc();
                    
                    if ($img_data && isset($img_data['hinhanh_mau'])) {
                        $variant['hinhanh_mau'] = $img_data['hinhanh_mau'];
                    } else {
                        $variant['hinhanh_mau'] = null;
                    }
                    
                    $product_variants[] = $variant;
                }
                
                // Lấy dữ liệu sản phẩm mới
                $stmt = $conn->prepare("SELECT * FROM sanpham WHERE id_sanpham = ?");
                $stmt->bind_param("i", $product_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $product = $result->fetch_assoc();
            } else {
                $error = "Lỗi khi cập nhật sản phẩm: " . $stmt->error;
            }
        }
    }
}

// Lấy danh sách danh mục
$categories = $conn->query("SELECT * FROM loaisanpham ORDER BY tenloai");

// Lấy danh sách tất cả kích thước
$all_sizes = $conn->query("SELECT * FROM kichthuoc ORDER BY tenkichthuoc");

// Lấy danh sách tất cả màu sắc
$all_colors = $conn->query("SELECT * FROM mausac ORDER BY tenmau");
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chỉnh sửa sản phẩm - Bug Shop Admin</title>
    <link rel="stylesheet" href="../node_modules/bootstrap/dist/css/bootstrap.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../css/admin-styles.css">
    <style>
        body {
            font-size: 0.875rem;
        }
        
        .sidebar {
            min-height: 100vh;
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
            background-color: #212529;
        }
        
        .sidebar .nav-link {
            color: #adb5bd;
            font-weight: 500;
            padding: 0.75rem 1rem;
        }
        
        .sidebar .nav-link:hover {
            color: #fff;
        }
        
        .sidebar .nav-link.active {
            color: #fff;
        }
        
        .sidebar .nav-link i {
            margin-right: 10px;
        }
        
        main {
            padding-top: 20px;
        }
        
        .required-field::after {
            content: " *";
            color: red;
        }
        
        #image-preview {
            max-width: 200px;
            max-height: 200px;
            border: 1px dashed #ddd;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            margin-top: 10px;
        }
        
        #image-preview img {
            width: 100%;
            height: auto;
            object-fit: contain;
        }
        
        /* Styles for size checkboxes */
        .size-checkbox-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .size-checkbox {
            display: none;
        }
        
        .size-checkbox + label {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            border: 1px solid #ddd;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.2s;
        }
        
        .size-checkbox:checked + label {
            background-color: #212529;
            color: white;
            border-color: #212529;
        }
        
        .size-checkbox:disabled + label {
            opacity: 0.5;
            cursor: not-allowed;
            text-decoration: line-through;
        }
        
        /* Styles for color checkboxes */
        .color-checkbox-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .color-checkbox {
            display: none;
        }
        
        .color-checkbox + label {
            margin-bottom: 0;
            width: 100%;
            border: none;
            background: none;
            display: block;
            height: auto;
        }
        
        .color-item {
            display: flex;
            align-items: center;
            padding: 8px 10px;
            border: 1px solid #e2e2e2;
            border-radius: 8px;
            transition: all 0.2s ease;
            height: 100%;
            cursor: pointer;
            background-color: #fff;
        }

        .color-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .color-checkbox:checked + label .color-item {
            border-color: #0d6efd;
            background-color: #f0f7ff;
            box-shadow: 0 0 0 1px #0d6efd;
        }

        .color-swatch {
            width: 25px;
            height: 25px;
            border-radius: 50%;
            border: 1px solid rgba(0,0,0,0.1);
            box-shadow: inset 0 0 0 1px rgba(255,255,255,0.3);
        }

        .color-name {
            font-size: 0.85rem;
            font-weight: 500;
            color: #212529;
            flex-grow: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .color-checkbox:checked + label::after {
            display: none;
        }

        /* Cải thiện hiển thị khi được chọn */
        .color-checkbox:checked + label .color-item::after {
            content: "\F633";
            font-family: bootstrap-icons;
            position: absolute;
            right: 8px;
            color: #0d6efd;
            font-size: 1rem;
        }

        /* Cải thiện phần tìm kiếm màu */
        .color-filter .input-group {
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .color-image-item .card {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            transition: all 0.3s ease;
        }

        .color-image-item .card:hover {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }

        .color-image-preview {
            min-height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .color-image-preview img {
            max-height: 120px;
            object-fit: contain;
        }

        .no-image-placeholder {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            min-height: 120px;
            background-color: #f8f9fa;
            border-radius: 4px;
            padding: 1rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav id="sidebar" class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <img src="../images/logo.png" alt="Bug Shop Logo" height="40">
                        <h5 class="text-white mt-2">Admin Panel</h5>
                    </div>
                    <hr class="bg-light">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="index.php">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="products.php">
                                <i class="bi bi-box"></i> Sản phẩm
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="bi bi-cart"></i> Đơn hàng
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="bi bi-people"></i> Khách hàng
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="bi bi-tag"></i> Danh mục
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="bi bi-percent"></i> Khuyến mãi
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="bi bi-graph-up"></i> Thống kê
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="bi bi-gear"></i> Cài đặt
                            </a>
                        </li>
                        <hr class="bg-light">
                        <li class="nav-item">
                            <a class="nav-link" href="index.php?action=logout">
                                <i class="bi bi-box-arrow-right"></i> Đăng xuất
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-2 mb-3 border-bottom">
                    <h1 class="h2">Chỉnh sửa sản phẩm</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="products.php" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Quay lại danh sách
                        </a>
                    </div>
                </div>

                <?php if(!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <?php if(!empty($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <div class="card shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">Sản phẩm #<?php echo $product['id_sanpham']; ?></h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="ten_sp" class="form-label required-field">Tên sản phẩm</label>
                                    <input type="text" class="form-control" id="ten_sp" name="ten_sp" value="<?php echo htmlspecialchars($product['tensanpham']); ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label for="giagoc" class="form-label required-field">Giá gốc</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="giagoc" name="giagoc" min="0" step="1000" value="<?php echo $product['giagoc']; ?>" required>
                                        <span class="input-group-text">₫</span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label for="gia" class="form-label">Giá bán (sau khuyến mãi)</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="gia" name="gia" min="0" step="1000" value="<?php echo $product['gia']; ?>">
                                        <span class="input-group-text">₫</span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label for="discount_percent" class="form-label">% Giảm giá</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="discount_percent" min="0" max="100" readonly>
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label for="so_luong" class="form-label required-field">Số lượng</label>
                                    <input type="number" class="form-control" id="so_luong" name="so_luong" min="0" value="<?php echo $product['soluong']; ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="id_loai" class="form-label required-field">Danh mục</label>
                                    <select class="form-select" id="id_loai" name="id_loai" required>
                                        <option value="">-- Chọn danh mục --</option>
                                        <?php 
                                        // Reset con trỏ của categories
                                        $categories->data_seek(0);
                                        while($category = $categories->fetch_assoc()): 
                                        ?>
                                        <option value="<?php echo $category['id_loai']; ?>" <?php echo ($product['id_loai'] == $category['id_loai']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['tenloai']); ?>
                                        </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="hinh_anh" class="form-label">Hình ảnh</label>
                                    <input type="file" class="form-control" id="hinh_anh" name="hinh_anh" accept="image/*">
                                    <div id="image-preview" class="mt-2">
                                        <?php if(!empty($product['hinhanh']) && file_exists("../uploads/products/" . $product['hinhanh'])): ?>
                                            <img src="../uploads/products/<?php echo $product['hinhanh']; ?>" alt="<?php echo htmlspecialchars($product['tensanpham']); ?>">
                                        <?php else: ?>
                                            <span class="text-muted">Chưa có hình ảnh</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if(!empty($product['hinhanh'])): ?>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" id="xoa_anh" name="xoa_anh" value="1">
                                        <label class="form-check-label" for="xoa_anh">
                                            Xóa hình ảnh hiện tại
                                        </label>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="mo_ta" class="form-label">Mô tả sản phẩm</label>
                                <textarea class="form-control" id="mo_ta" name="mo_ta" rows="4"><?php echo htmlspecialchars($product['mota']); ?></textarea>
                            </div>
                            
                            <!-- Kích thước sản phẩm -->
                            <div class="mb-3">
                                <label class="form-label">Kích thước sản phẩm:</label>
                                <div class="size-checkbox-container">
                                    <?php while ($size = $all_sizes->fetch_assoc()): ?>
                                        <div>
                                            <input type="checkbox" 
                                                   class="size-checkbox" 
                                                   id="size-<?php echo $size['id_kichthuoc']; ?>" 
                                                   name="sizes[]" 
                                                   value="<?php echo $size['id_kichthuoc']; ?>"
                                                   <?php echo in_array($size['id_kichthuoc'], $product_sizes) ? 'checked' : ''; ?>>
                                            <label for="size-<?php echo $size['id_kichthuoc']; ?>"><?php echo htmlspecialchars($size['tenkichthuoc']); ?></label>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                                <div class="form-text size-info">Chọn các kích thước có sẵn cho sản phẩm này.</div>
                            </div>
                            
                            <!-- Màu sắc sản phẩm -->
                            <div class="mb-4">
                                <label class="form-label">Màu sắc sản phẩm:</label>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <div class="color-filter">
                                            <div class="input-group">
                                                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                                                <input type="text" id="colorSearchInput" class="form-control" placeholder="Tìm màu..." aria-label="Tìm màu">
                                                <button class="btn btn-outline-secondary" type="button" id="clearSearch">
                                                    <i class="bi bi-x"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-flex justify-content-end gap-2">
                                            <button type="button" class="btn btn-sm btn-outline-secondary" id="selectAllColors">
                                                <i class="bi bi-check-all"></i> Chọn tất cả
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" id="deselectAllColors">
                                                <i class="bi bi-x-lg"></i> Bỏ chọn tất cả
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addColorModal">
                                                <i class="bi bi-plus-lg"></i> Thêm màu mới
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Container màu sắc -->
                                <div class="color-container p-3 border rounded">
                                    <div class="row g-3" id="colorContainer">
                                        <?php 
                                        // Reset con trỏ để đảm bảo lấy đầy đủ dữ liệu
                                        $all_colors->data_seek(0);
                                        while ($color = $all_colors->fetch_assoc()): 
                                        ?>
                                            <div class="col-lg-3 col-md-4 col-sm-6 col-6 color-item-wrapper mb-2" 
                                                 data-color-name="<?php echo strtolower(htmlspecialchars($color['tenmau'])); ?>">
                                                <input type="checkbox" 
                                                       class="color-checkbox" 
                                                       id="color-<?php echo $color['id_mausac']; ?>" 
                                                       name="colors[]" 
                                                       value="<?php echo $color['id_mausac']; ?>"
                                                       data-color-name="<?php echo htmlspecialchars($color['tenmau']); ?>"
                                                       data-color-code="<?php echo htmlspecialchars($color['mamau']); ?>"
                                                       <?php echo in_array($color['id_mausac'], $product_colors) ? 'checked' : ''; ?>>
                                                <label for="color-<?php echo $color['id_mausac']; ?>">
                                                    <div class="color-item position-relative">
                                                        <div class="color-swatch" style="background-color: <?php echo htmlspecialchars($color['mamau']); ?>;"></div>
                                                        <div class="color-name"><?php echo htmlspecialchars($color['tenmau']); ?></div>
                                                    </div>
                                                </label>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                    <div class="no-colors-found text-center py-3 d-none">
                                        <p class="text-muted mb-0"><i class="bi bi-emoji-frown"></i> Không tìm thấy màu sắc nào khớp với tìm kiếm</p>
                                    </div>
                                </div>
                                
                                <div class="form-text color-info">Chọn các màu sắc có sẵn cho sản phẩm này.</div>
                            </div>

                            <!-- Thay thế phần hiển thị container hình ảnh màu sắc -->
                            <div class="mb-4 color-image-upload">
                                <div class="card">
                                    <div class="card-header bg-light">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h5 class="mb-0">Hình ảnh cho từng màu sắc</h5>
                                            <span class="badge bg-primary" id="selected-colors-count">0 màu được chọn</span>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="alert alert-info py-2">
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-lightbulb-fill text-primary fs-5 me-2"></i>
                                                <small>Mỗi màu sắc nên có hình ảnh riêng để khách hàng thấy được sản phẩm thực tế trong từng màu</small>
                                            </div>
                                        </div>

                                        <div id="color-images-container" class="row g-3">
                                            <?php
                                            // Lấy danh sách hình ảnh màu hiện có
                                            $color_images_sql = "SELECT mh.id, mh.id_mausac, mh.hinhanh, ms.tenmau, ms.mamau 
                                                                 FROM mausac_hinhanh mh 
                                                                 JOIN mausac ms ON mh.id_mausac = ms.id_mausac 
                                                                 WHERE mh.id_sanpham = ?";
                                            $color_images_stmt = $conn->prepare($color_images_sql);
                                            $color_images_stmt->bind_param("i", $product_id);
                                            $color_images_stmt->execute();
                                            $color_images_result = $color_images_stmt->get_result();
                                            
                                            // Tạo mảng lưu trữ hình ảnh theo màu
                                            $color_images = [];
                                            while ($row = $color_images_result->fetch_assoc()) {
                                                $color_images[$row['id_mausac']] = $row;
                                            }
                                            
                                            // Hiển thị form upload cho mỗi màu đã chọn
                                            foreach($product_colors as $color_id):
                                                // Lấy thông tin màu
                                                $color_info_sql = "SELECT tenmau, mamau FROM mausac WHERE id_mausac = ?";
                                                $color_info_stmt = $conn->prepare($color_info_sql);
                                                $color_info_stmt->bind_param("i", $color_id);
                                                $color_info_stmt->execute();
                                                $color_info = $color_info_stmt->get_result()->fetch_assoc();
                                                
                                                // Lấy hình ảnh hiện có cho màu này (nếu có)
                                                $color_image = $color_images[$color_id]['hinhanh'] ?? null;
                                            ?>
                                            <div class="col-lg-6 col-md-12 mb-3 color-image-item" data-color-id="<?php echo $color_id; ?>">
                                                <div class="card h-100">
                                                    <div class="card-header bg-white">
                                                        <div class="d-flex align-items-center">
                                                            <div class="color-swatch me-2" style="background-color: <?php echo $color_info['mamau']; ?>;"></div>
                                                            <h6 class="mb-0"><?php echo htmlspecialchars($color_info['tenmau']); ?></h6>
                                                        </div>
                                                    </div>
                                                    <div class="card-body">
                                                        <div class="row">
                                                            <div class="col-md-7">
                                                                <div class="mb-3">
                                                                    <label class="form-label">Tải lên hình ảnh</label>
                                                                    <input type="hidden" name="color_image_id[]" value="<?php echo $color_id; ?>">
                                                                    <input type="file" name="color_image[]" class="form-control" accept="image/*" 
                                                                           id="color-file-<?php echo $color_id; ?>" 
                                                                           onchange="previewColorImage(this, <?php echo $color_id; ?>)">
                                                                </div>
                                                                <div class="small text-muted mt-1">
                                                                    <?php if (!empty($color_image)): ?>
                                                                        <i class="bi bi-check-circle-fill text-success me-1"></i> 
                                                                        Đã có ảnh. Tải lên ảnh mới sẽ thay thế ảnh cũ.
                                                                    <?php else: ?>
                                                                        <i class="bi bi-exclamation-circle text-warning me-1"></i>
                                                                        Chưa có ảnh cho màu này.
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-5">
                                                                <div class="color-image-preview text-center">
                                                                    <?php if (!empty($color_image)): ?>
                                                                        <img src="../uploads/colors/<?php echo $color_image; ?>" 
                                                                             class="img-thumbnail color-preview" 
                                                                             alt="<?php echo htmlspecialchars($color_info['tenmau']); ?>"
                                                                             data-color-id="<?php echo $color_id; ?>">
                                                                    <?php else: ?>
                                                                        <div class="no-image-placeholder">
                                                                            <i class="bi bi-image fs-1 text-muted"></i>
                                                                            <p class="text-muted small">Chưa có ảnh</p>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="card-footer bg-white">
                                                        <div class="d-flex justify-content-end">
                                                            <button type="button" class="btn btn-sm btn-outline-primary preview-color-btn me-2" 
                                                                    data-color-id="<?php echo $color_id; ?>"
                                                                    <?php echo empty($color_image) ? 'disabled' : ''; ?>>
                                                                <i class="bi bi-eye"></i> Xem ảnh
                                                            </button>
                                                            <?php if (!empty($color_image)): ?>
                                                            <button type="button" class="btn btn-sm btn-outline-danger remove-color-img-btn" 
                                                                    data-color-id="<?php echo $color_id; ?>">
                                                                <i class="bi bi-x-lg"></i> Xóa
                                                            </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                        
                                        <div id="no-colors-selected" class="alert alert-warning py-3 <?php echo !empty($product_colors) ? 'd-none' : ''; ?>">
                                            <i class="bi bi-exclamation-triangle me-2"></i>
                                            <span>Vui lòng chọn ít nhất một màu sắc ở mục trên để tải lên hình ảnh</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="trang_thai" name="trang_thai" <?php echo ($product['trangthai'] == 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="trang_thai">
                                    Hiển thị sản phẩm (cho phép mua)
                                </label>
                            </div>
                            
                            <hr>
                            
                            <div class="d-flex justify-content-between">
                                <a href="products.php" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left"></i> Quay lại
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Lưu thay đổi
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Modal thêm màu mới -->
    <div class="modal fade" id="addColorModal" tabindex="-1" aria-labelledby="addColorModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addColorModalLabel">Thêm màu mới</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="colorModalAlert" class="alert d-none"></div>
                    <form id="addColorForm">
                        <div class="mb-3">
                            <label for="colorName" class="form-label">Tên màu</label>
                            <input type="text" class="form-control" id="colorName" required>
                        </div>
                        <div class="mb-3">
                            <label for="colorCode" class="form-label">Mã màu</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="colorCode" value="#ffffff" required>
                                <input type="color" class="form-control form-control-color" id="colorPicker" value="#ffffff" title="Chọn màu">
                            </div>
                            <div id="colorPreview" class="mt-2 p-3 border rounded" style="background-color: #ffffff;">
                                Xem trước màu
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="button" class="btn btn-primary" id="saveColorBtn">
                        <span class="spinner-border spinner-border-sm d-none" id="colorSaveSpinner" role="status" aria-hidden="true"></span>
                        Lưu màu mới
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Color Image Preview Modal -->
    <div class="modal fade" id="colorImagePreviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="colorImagePreviewTitle">Xem trước hình ảnh màu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="colorImagePreviewImg" src="" class="img-fluid" alt="Color preview">
                </div>
            </div>
        </div>
    </div>

    <script src="../node_modules/bootstrap/dist/js/bootstrap.bundle.js"></script>
    <script>
        // Preview image before upload
        document.getElementById('hinh_anh').addEventListener('change', function() {
            const preview = document.getElementById('image-preview');
            preview.innerHTML = '';
            
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    preview.appendChild(img);
                }
                reader.readAsDataURL(this.files[0]);
            } else {
                <?php if(!empty($product['hinhanh']) && file_exists("../uploads/products/" . $product['hinhanh'])): ?>
                preview.innerHTML = '<img src="../uploads/products/<?php echo $product['hinhanh']; ?>" alt="<?php echo htmlspecialchars($product['tensanpham']); ?>">';
                <?php else: ?>
                preview.innerHTML = '<span class="text-muted">Chưa có hình ảnh</span>';
                <?php endif; ?>
            }
        });
        
        // Xóa ảnh hiện tại
        document.getElementById('xoa_anh')?.addEventListener('change', function() {
            const preview = document.getElementById('image-preview');
            if(this.checked) {
                preview.innerHTML = '<span class="text-muted">Hình ảnh sẽ bị xóa</span>';
            } else {
                <?php if(!empty($product['hinhanh']) && file_exists("../uploads/products/" . $product['hinhanh'])): ?>
                preview.innerHTML = '<img src="../uploads/products/<?php echo $product['hinhanh']; ?>" alt="<?php echo htmlspecialchars($product['tensanpham']); ?>">';
                <?php else: ?>
                preview.innerHTML = '<span class="text-muted">Chưa có hình ảnh</span>';
                <?php endif; ?>
            }
        });

        // Tính toán % giảm giá tự động
        function calculateDiscount() {
            const giagoc = parseFloat(document.getElementById('giagoc').value) || 0;
            const gia = parseFloat(document.getElementById('gia').value) || 0;
            
            if (giagoc > 0 && gia > 0 && gia < giagoc) {
                const discountPercent = Math.round(((giagoc - gia) / giagoc) * 100);
                document.getElementById('discount_percent').value = discountPercent;
            } else {
                document.getElementById('discount_percent').value = 0;
            }
        }

        document.getElementById('giagoc').addEventListener('input', calculateDiscount);
        document.getElementById('gia').addEventListener('input', calculateDiscount);

        // Tính toán ban đầu khi trang tải
        document.addEventListener('DOMContentLoaded', calculateDiscount);

        // Quản lý kích thước và màu sắc sản phẩm
        document.addEventListener('DOMContentLoaded', function() {
            // Xử lý kích thước
            const sizeCheckboxes = document.querySelectorAll('.size-checkbox');
            function updateSelectedSizesCount() {
                const selectedCount = document.querySelectorAll('.size-checkbox:checked').length;
                document.querySelector('.size-info').textContent = `Đã chọn ${selectedCount} kích thước cho sản phẩm này.`;
            }
            sizeCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateSelectedSizesCount);
            });
            updateSelectedSizesCount();
            
            // Xử lý màu sắc và cập nhật container hình ảnh màu
            const colorCheckboxes = document.querySelectorAll('.color-checkbox');
            const colorImagesContainer = document.getElementById('color-images-container');

            function updateColorImagesContainer() {
                const colorImagesContainer = document.getElementById('color-images-container');
                const noColorsSelected = document.getElementById('no-colors-selected');
                let selectedColors = [];
                
                // Lấy tất cả màu đã chọn
                document.querySelectorAll('.color-checkbox:checked').forEach(checkbox => {
                    selectedColors.push({
                        id: checkbox.value,
                        name: checkbox.dataset.colorName,
                        code: checkbox.dataset.colorCode
                    });
                });
                
                // Hiện/ẩn thông báo không có màu nào được chọn
                if (selectedColors.length === 0) {
                    colorImagesContainer.innerHTML = '';
                    noColorsSelected.classList.remove('d-none');
                    return;
                } else {
                    noColorsSelected.classList.add('d-none');
                }
                
                // Lưu trữ thông tin về các màu đã có ảnh
                let existingColorImages = {};
                document.querySelectorAll('.color-image-item').forEach(item => {
                    const colorId = item.dataset.colorId;
                    const imgElement = item.querySelector('.color-preview');
                    if (imgElement) {
                        existingColorImages[colorId] = imgElement.src;
                    }
                });
                
                // Xoá tất cả và thay thế bằng mục mới
                colorImagesContainer.innerHTML = '';
                
                // Tạo các mục mới cho từng màu đã chọn
                selectedColors.forEach(color => {
                    // Kiểm tra nếu màu này đã có ảnh từ PHP
                    let existingImage = null;
                    <?php foreach($product_variants as $variant): ?>
                        if (<?php echo $variant['id_mausac']; ?> == color.id && '<?php echo $variant['hinhanh_mau'] ?? ''; ?>' != '') {
                            existingImage = '<?php echo $variant['hinhanh_mau']; ?>';
                        }
                    <?php endforeach; ?>
                    
                    // Tạo phần tử HTML cho mỗi màu
                    const colorHtml = `
                        <div class="col-lg-6 col-md-12">
                            <div class="color-image-item" data-color-id="${color.id}">
                                <div class="d-flex align-items-center">
                                    <div class="color-swatch me-2" style="background-color: ${color.code}"></div>
                                    <h6 class="mb-0 text-truncate">${color.name}</h6>
                                </div>
                                
                                <div class="mt-2">
                                    <div class="input-group">
                                        <input type="hidden" name="color_image_id[]" value="${color.id}">
                                        <input type="file" name="color_image[]" class="form-control" accept="image/*" id="color-file-${color.id}" 
                                               onchange="previewColorImage(this, ${color.id})">
                                        <button class="btn btn-outline-secondary preview-color-btn" type="button" data-color-id="${color.id}"
                                                ${!existingImage ? 'disabled' : ''}>
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                    <div class="small text-muted mt-1">
                                        ${existingImage ? 
                                            `<i class="bi bi-check-circle-fill text-success me-1"></i> 
                                             Đã có ảnh. Upload ảnh mới sẽ thay thế ảnh cũ.` : 
                                            `<i class="bi bi-exclamation-circle text-warning me-1"></i>
                                             Chưa có ảnh cho màu này.`
                                        }
                                    </div>
                                
                                ${existingImage ? `
                                <div class="color-image-preview mt-2">
                                    <img src="../uploads/colors/${existingImage}" 
                                         class="img-thumbnail color-preview" 
                                         alt="${color.name}"
                                         data-color-id="${color.id}">
                                </div>
                                ` : ''}
                            </div>
                        </div>
                    `;
                    
                    colorImagesContainer.insertAdjacentHTML('beforeend', colorHtml);
                });
                
                // Gán sự kiện cho các nút preview mới
                document.querySelectorAll('.preview-color-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const colorId = this.dataset.colorId;
                        const colorImage = document.querySelector(`.color-preview[data-color-id="${colorId}"]`);
                        
                        if (colorImage) {
                            // Hiển thị hình ảnh trong modal
                            document.getElementById('colorImagePreviewImg').src = colorImage.src;
                            const colorName = colorImage.alt;
                            document.getElementById('colorImagePreviewTitle').textContent = `Hình ảnh màu: ${colorName}`;
                            
                            // Hiển thị modal
                            const previewModal = new bootstrap.Modal(document.getElementById('colorImagePreviewModal'));
                            previewModal.show();
                        } else {
                            alert('Chưa có hình ảnh cho màu này');
                        }
                    });
                });
            }
            
            // Hàm xem trước hình ảnh khi chọn file
            window.previewColorImage = function(input, colorId) {
                const colorItem = input.closest('.color-image-item');
                let previewContainer = colorItem.querySelector('.color-image-preview');
                const previewBtn = colorItem.querySelector('.preview-color-btn');
                
                if (input.files && input.files[0]) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        // Nếu chưa có container, tạo mới
                        if (!previewContainer) {
                            previewContainer = document.createElement('div');
                            previewContainer.className = 'color-image-preview mt-2';
                            colorItem.appendChild(previewContainer);
                        }
                        
                        // Cập nhật hoặc tạo mới hình ảnh
                        let img = previewContainer.querySelector('img');
                        if (!img) {
                            img = document.createElement('img');
                            img.className = 'img-thumbnail color-preview';
                            img.dataset.colorId = colorId;
                            previewContainer.appendChild(img);
                        }
                        
                        // Cập nhật source và thay đổi trạng thái
                        img.src = e.target.result;
                        img.alt = document.querySelector(`label[for="color-${colorId}"] .color-name`).textContent;
                        
                        // Cập nhật nút preview
                        previewBtn.disabled = false;
                        
                        // Cập nhật thông báo
                        const statusMsg = colorItem.querySelector('.small.text-muted');
                        statusMsg.innerHTML = `<i class="bi bi-check-circle-fill text-success me-1"></i> Hình ảnh mới đã được chọn, nhấn "Lưu" để áp dụng.`;
                    };
                    
                    reader.readAsDataURL(input.files[0]);
                }
            };
            
            // Xử lý sự kiện thay đổi cho các checkbox màu sắc
            colorCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    updateColorImagesContainer();
                    updateSelectedColorsCount();
                });
            });
            
            // Cập nhật số lượng màu đã chọn
            function updateSelectedColorsCount() {
                const selectedCount = document.querySelectorAll('.color-checkbox:checked').length;
                const colorInfo = document.querySelector('.color-info');
                if (colorInfo) {
                    colorInfo.innerHTML = `Đã chọn <span class="fw-bold">${selectedCount}</span> màu sắc cho sản phẩm này.`;
                }
                
                // Hiện/ẩn thông báo không có màu nào được chọn
                const noColorsSelected = document.getElementById('no-colors-selected');
                if (selectedCount === 0) {
                    noColorsSelected.classList.remove('d-none');
                } else {
                    noColorsSelected.classList.add('d-none');
                }
            }
            
            // Khởi tạo ban đầu
            updateColorImagesContainer();
            updateSelectedColorsCount();
            
            // Xử lý form submission
            const editProductForm = document.querySelector('form');
            if (editProductForm) {
                editProductForm.addEventListener('submit', function(e) {
                    // Kiểm tra xem có màu nào được chọn không
                    const selectedColors = document.querySelectorAll('.color-checkbox:checked');
                    if (selectedColors.length === 0) {
                        const confirmNoColors = confirm('Bạn chưa chọn màu nào cho sản phẩm này. Bạn có muốn tiếp tục lưu?');
                        if (!confirmNoColors) {
                            e.preventDefault();
                            return false;
                        }
                    }
                    
                    // Tiếp tục submit form
                    return true;
                });
            }
        });

        // Thêm vào phần <script> của file
        // Cập nhật danh sách màu sắc khi có thay đổi
        function updateColorImageItems() {
            const colorCheckboxes = document.querySelectorAll('.color-checkbox:checked');
            const colorImagesContainer = document.getElementById('color-images-container');
            const noColorsSelected = document.getElementById('no-colors-selected');
            const selectedColorsCount = document.getElementById('selected-colors-count');
            
            // Lưu trữ các ID màu hiện tại để biết màu nào cần thêm/xóa
            const currentColorIds = Array.from(document.querySelectorAll('.color-image-item'))
                                        .map(item => parseInt(item.dataset.colorId));
            
            // Danh sách màu đã chọn
            const selectedColorIds = Array.from(colorCheckboxes)
                                         .map(checkbox => parseInt(checkbox.value));
            
            // Cập nhật số lượng màu đã chọn
            selectedColorsCount.textContent = `${selectedColorIds.length} màu được chọn`;
            
            // Hiển thị/ẩn thông báo không có màu nào được chọn
            if (selectedColorIds.length === 0) {
                noColorsSelected.classList.remove('d-none');
                return;
            } else {
                noColorsSelected.classList.add('d-none');
            }
            
            // Cần xóa những màu đã bỏ chọn
            currentColorIds.forEach(colorId => {
                if (!selectedColorIds.includes(colorId)) {
                    const itemToRemove = document.querySelector(`.color-image-item[data-color-id="${colorId}"]`);
                    if (itemToRemove) {
                        itemToRemove.remove();
                    }
                }
            });
            
            // Cần thêm những màu mới được chọn
            selectedColorIds.forEach(colorId => {
                if (!currentColorIds.includes(colorId)) {
                    // Lấy thông tin màu
                    const colorCheckbox = document.querySelector(`.color-checkbox[value="${colorId}"]`);
                    const colorName = colorCheckbox.dataset.colorName;
                    const colorCode = colorCheckbox.dataset.colorCode;
                    
                    // Tạo phần tử HTML mới
                    const newColorHtml = `
                        <div class="col-lg-6 col-md-12 mb-3 color-image-item" data-color-id="${colorId}">
                            <div class="card h-100">
                                <div class="card-header bg-white">
                                    <div class="d-flex align-items-center">
                                        <div class="color-swatch me-2" style="background-color: ${colorCode};"></div>
                                        <h6 class="mb-0">${colorName}</h6>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-7">
                                            <div class="mb-3">
                                                <label class="form-label">Tải lên hình ảnh</label>
                                                <input type="hidden" name="color_image_id[]" value="${colorId}">
                                                <input type="file" name="color_image[]" class="form-control" accept="image/*" 
                                                       id="color-file-${colorId}" 
                                                       onchange="previewColorImage(this, ${colorId})">
                                            </div>
                                            <div class="small text-muted mt-1">
                                                <i class="bi bi-exclamation-circle text-warning me-1"></i>
                                                Chưa có ảnh cho màu này.
                                            </div>
                                        </div>
                                        <div class="col-md-5">
                                            <div class="color-image-preview text-center">
                                                <div class="no-image-placeholder">
                                                    <i class="bi bi-image fs-1 text-muted"></i>
                                                    <p class="text-muted small">Chưa có ảnh</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer bg-white">
                                    <div class="d-flex justify-content-end">
                                        <button type="button" class="btn btn-sm btn-outline-primary preview-color-btn me-2" 
                                                data-color-id="${colorId}" disabled>
                                            <i class="bi bi-eye"></i> Xem ảnh
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    colorImagesContainer.insertAdjacentHTML('beforeend', newColorHtml);
                }
            });
            
            // Cập nhật sự kiện cho các nút mới
            addColorImageButtonEvents();
        }

        // Xem trước hình ảnh khi chọn file
        function previewColorImage(input, colorId) {
            const colorItem = input.closest('.color-image-item');
            const previewContainer = colorItem.querySelector('.color-image-preview');
            const previewBtn = colorItem.querySelector('.preview-color-btn');
            const statusText = colorItem.querySelector('.small.text-muted');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    // Xóa placeholder nếu có
                    const placeholder = previewContainer.querySelector('.no-image-placeholder');
                    if (placeholder) {
                        placeholder.remove();
                    }
                    
                    // Cập nhật hoặc tạo mới hình ảnh
                    let img = previewContainer.querySelector('img');
                    if (!img) {
                        img = document.createElement('img');
                        img.className = 'img-thumbnail color-preview';
                        img.dataset.colorId = colorId;
                        previewContainer.appendChild(img);
                    }
                    
                    // Cập nhật source và thông tin
                    img.src = e.target.result;
                    img.alt = document.querySelector(`label[for="color-${colorId}"] .color-name`).textContent;
                    
                    // Cập nhật trạng thái nút và thông báo
                    previewBtn.disabled = false;
                    statusText.innerHTML = `<i class="bi bi-check-circle-fill text-success me-1"></i> 
                                            Hình ảnh mới đã được chọn, nhấn "Lưu thay đổi" để áp dụng.`;
                    
                    // Thêm nút xóa nếu chưa có
                    const footerBtns = colorItem.querySelector('.card-footer .d-flex');
                    if (!colorItem.querySelector('.remove-color-img-btn')) {
                        const removeBtn = document.createElement('button');
                        removeBtn.className = 'btn btn-sm btn-outline-danger remove-color-img-btn';
                        removeBtn.dataset.colorId = colorId;
                        removeBtn.innerHTML = '<i class="bi bi-x-lg"></i> Xóa';
                        removeBtn.addEventListener('click', function() {
                            handleRemoveColorImage(colorId);
                        });
                        footerBtns.appendChild(removeBtn);
                    }
                };
                
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Xử lý xóa hình ảnh màu
        function handleRemoveColorImage(colorId) {
            if (confirm('Bạn có chắc muốn xóa hình ảnh này?')) {
                const colorItem = document.querySelector(`.color-image-item[data-color-id="${colorId}"]`);
                const previewContainer = colorItem.querySelector('.color-image-preview');
                const fileInput = colorItem.querySelector('input[type="file"]');
                const previewBtn = colorItem.querySelector('.preview-color-btn');
                const statusText = colorItem.querySelector('.small.text-muted');
                const removeBtn = colorItem.querySelector('.remove-color-img-btn');
                
                // Reset file input
                fileInput.value = '';
                
                // Xóa hình ảnh và thêm placeholder
                previewContainer.innerHTML = `
                    <div class="no-image-placeholder">
                        <i class="bi bi-image fs-1 text-muted"></i>
                        <p class="text-muted small">Chưa có ảnh</p>
                    </div>
                `;
                
                // Cập nhật trạng thái
                previewBtn.disabled = true;
                statusText.innerHTML = `<i class="bi bi-exclamation-circle text-warning me-1"></i>
                                       Hình ảnh sẽ bị xóa khi bạn lưu thay đổi.`;
                
                // Thêm hidden input để đánh dấu xóa ảnh
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'delete_color_image[]';
                hiddenInput.value = colorId;
                colorItem.appendChild(hiddenInput);
                
                // Xóa nút "Xóa"
                if (removeBtn) {
                    removeBtn.remove();
                }
            }
        }

        // Thêm sự kiện cho các nút
        function addColorImageButtonEvents() {
            // Sự kiện xem trước ảnh
            document.querySelectorAll('.preview-color-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const colorId = this.dataset.colorId;
                    const img = document.querySelector(`.color-preview[data-color-id="${colorId}"]`);
                    
                    if (img) {
                        document.getElementById('colorImagePreviewImg').src = img.src;
                        const colorName = img.alt || document.querySelector(`label[for="color-${colorId}"] .color-name`).textContent;
                        document.getElementById('colorImagePreviewTitle').textContent = `Hình ảnh màu: ${colorName}`;
                        
                        const previewModal = new bootstrap.Modal(document.getElementById('colorImagePreviewModal'));
                        previewModal.show();
                    }
                });
            });
            
            // Sự kiện xóa ảnh
            document.querySelectorAll('.remove-color-img-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    handleRemoveColorImage(this.dataset.colorId);
                });
            });
        }

        // Cập nhật khi thay đổi chọn màu
        colorCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateColorImageItems);
        });

        // Khởi tạo ban đầu
        document.addEventListener('DOMContentLoaded', function() {
            updateColorImageItems();
            addColorImageButtonEvents();
        });
    </script>
</body>
</html>
