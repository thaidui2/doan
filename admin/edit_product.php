<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

// Hàm upload file - giống như trong seller/xu-ly-san-pham.php
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

// Lấy thông tin chi tiết của các biến thể sản phẩm (kích thước, màu sắc, hình ảnh)
$variants_query = $conn->prepare("
    SELECT spct.*, kt.tenkichthuoc, ms.tenmau, ms.mamau 
    FROM sanpham_chitiet spct
    JOIN kichthuoc kt ON spct.id_kichthuoc = kt.id_kichthuoc
    JOIN mausac ms ON spct.id_mausac = ms.id_mausac
    WHERE spct.id_sanpham = ?
");
$variants_query->bind_param("i", $product_id);
$variants_query->execute();
$variants_result = $variants_query->get_result();

// Danh sách kích thước và màu sắc đã có của sản phẩm này
$existing_sizes = [];
$existing_colors = [];
$product_variants = [];

while ($variant = $variants_result->fetch_assoc()) {
    if (!in_array($variant['id_kichthuoc'], $existing_sizes)) {
        $existing_sizes[] = $variant['id_kichthuoc'];
    }
    if (!in_array($variant['id_mausac'], $existing_colors)) {
        $existing_colors[] = $variant['id_mausac'];
    }
    
    $product_variants[] = $variant;
}

// Lấy danh sách các hình ảnh phụ
$additional_images = [];
if (!empty($product['hinhanh_phu'])) {
    $additional_images = explode('|', $product['hinhanh_phu']);
}

$error = '';
$success = '';

// Xử lý khi form được submit
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Lấy dữ liệu từ form
    $tensanpham = trim($_POST['tensanpham']);
    $id_loai = (int)$_POST['id_loai'];
    $id_thuonghieu = !empty($_POST['id_thuonghieu']) ? (int)$_POST['id_thuonghieu'] : null;
    $gia = (float)$_POST['gia'];
    $giagoc = !empty($_POST['giagoc']) ? (float)$_POST['giagoc'] : $gia;
    $mota = trim($_POST['mota']);
    $trangthai = isset($_POST['trangthai']) ? (int)$_POST['trangthai'] : 0;
    $noibat = isset($_POST['noibat']) ? 1 : 0;
    $selected_sizes = isset($_POST['sizes']) ? $_POST['sizes'] : [];
    $selected_colors = isset($_POST['colors']) ? $_POST['colors'] : [];

    // Validate dữ liệu
    if(empty($tensanpham)) {
        $error = "Vui lòng nhập tên sản phẩm";
    } elseif($gia <= 0) {
        $error = "Giá sản phẩm phải lớn hơn 0";
    } elseif($id_loai <= 0) {
        $error = "Vui lòng chọn danh mục sản phẩm";
    } elseif(empty($selected_sizes) || empty($selected_colors)) {
        $error = "Vui lòng chọn ít nhất một kích thước và một màu sắc";
    } else {
        try {
            // Bắt đầu transaction
            $conn->begin_transaction();
            
            // Xử lý upload hình ảnh chính (nếu có)
            $hinhanh = $product['hinhanh']; // Giữ nguyên ảnh cũ nếu không upload mới
            
            if(isset($_FILES['hinh_anh']) && $_FILES['hinh_anh']['error'] == 0) {
                $target_dir = "../uploads/products/";
                $upload_result = uploadFile($_FILES['hinh_anh'], $target_dir);
                
                if($upload_result[0]) {
                    $hinhanh = $upload_result[1];
                    
                    // Xóa ảnh cũ nếu có
                    if(!empty($product['hinhanh']) && file_exists($target_dir . $product['hinhanh']) && $product['hinhanh'] != $hinhanh) {
                        unlink($target_dir . $product['hinhanh']);
                    }
                } else {
                    throw new Exception("Lỗi upload hình ảnh: " . $upload_result[1]);
                }
            }
            
            // Cập nhật thông tin sản phẩm
            $sql = "UPDATE sanpham SET 
                    tensanpham = ?, 
                    mota = ?, 
                    gia = ?, 
                    giagoc = ?, 
                    hinhanh = ?, 
                    id_loai = ?, 
                    id_thuonghieu = ?, 
                    trangthai = ?,
                    noibat = ?,
                    ngaycapnhat = NOW() 
                    WHERE id_sanpham = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssddsiiibi", $tensanpham, $mota, $gia, $giagoc, $hinhanh, $id_loai, $id_thuonghieu, $trangthai, $noibat, $product_id);
            
            if(!$stmt->execute()) {
                throw new Exception("Lỗi khi cập nhật sản phẩm: " . $stmt->error);
            }
            
            // Cập nhật biến thể sản phẩm và số lượng tồn kho
            
            // 1. Xóa tất cả các biến thể hiện tại của sản phẩm
            $delete_variants = $conn->prepare("DELETE FROM sanpham_chitiet WHERE id_sanpham = ?");
            $delete_variants->bind_param("i", $product_id);
            $delete_variants->execute();
            
            // 2. Thêm các biến thể mới được chọn
            $total_quantity = 0;
            
            if (!empty($selected_sizes) && !empty($selected_colors)) {
                // Trường hợp có cả kích thước và màu sắc: tạo tổ hợp
                $insert_variant = $conn->prepare("INSERT INTO sanpham_chitiet (id_sanpham, id_kichthuoc, id_mausac, soluong) VALUES (?, ?, ?, ?)");
                
                foreach ($selected_sizes as $size_id) {
                    foreach ($selected_colors as $color_id) {
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
                        
                        $insert_variant->bind_param("iiii", $product_id, $size_id, $color_id, $quantity);
                        if (!$insert_variant->execute()) {
                            throw new Exception("Lỗi khi thêm biến thể sản phẩm: " . $insert_variant->error);
                        }
                        
                        // TODO: Xử lý hình ảnh màu sắc nếu cần
                    }
                }
            }
            
            // Cập nhật tổng số lượng trong bảng sản phẩm
            $update_total = $conn->prepare("UPDATE sanpham SET soluong = ? WHERE id_sanpham = ?");
            $update_total->bind_param("ii", $total_quantity, $product_id);
            $update_total->execute();
            
            // Xử lý hình ảnh phụ nếu có
            if (isset($_FILES['hinhanh_phu']) && !empty($_FILES['hinhanh_phu']['name'][0])) {
                // Xử lý upload nhiều hình ảnh phụ
                $target_dir = "../uploads/products/";
                $new_additional_images = [];
                
                // Lưu danh sách hình ảnh phụ hiện tại nếu không xóa
                if (!empty($product['hinhanh_phu'])) {
                    $current_images = explode('|', $product['hinhanh_phu']);
                    foreach ($current_images as $img) {
                        if (!isset($_POST['remove_images']) || !in_array($img, $_POST['remove_images'])) {
                            $new_additional_images[] = $img;
                        } else {
                            // Xóa file ảnh phụ đã chọn
                            $img_path = $target_dir . $img;
                            if (file_exists($img_path)) {
                                unlink($img_path);
                            }
                        }
                    }
                }
                
                // Upload các ảnh phụ mới
                $file_count = count($_FILES['hinhanh_phu']['name']);
                $file_count = min($file_count, 5 - count($new_additional_images)); // Giới hạn tối đa 5 ảnh phụ
                
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
                            $new_additional_images[] = $upload_result[1];
                        }
                    }
                }
                
                // Cập nhật trường hinhanh_phu trong cơ sở dữ liệu
                $hinhanh_phu_str = !empty($new_additional_images) ? implode('|', $new_additional_images) : null;
                $update_imgs = $conn->prepare("UPDATE sanpham SET hinhanh_phu = ? WHERE id_sanpham = ?");
                $update_imgs->bind_param("si", $hinhanh_phu_str, $product_id);
                $update_imgs->execute();
            } else if (isset($_POST['remove_images']) && is_array($_POST['remove_images'])) {
                // Chỉ có xóa ảnh, không thêm ảnh mới
                $target_dir = "../uploads/products/";
                $new_additional_images = [];
                
                // Lưu danh sách hình ảnh phụ hiện tại trừ những ảnh bị xóa
                if (!empty($product['hinhanh_phu'])) {
                    $current_images = explode('|', $product['hinhanh_phu']);
                    foreach ($current_images as $img) {
                        if (!in_array($img, $_POST['remove_images'])) {
                            $new_additional_images[] = $img;
                        } else {
                            // Xóa file ảnh phụ đã chọn
                            $img_path = $target_dir . $img;
                            if (file_exists($img_path)) {
                                unlink($img_path);
                            }
                        }
                    }
                }
                
                // Cập nhật trường hinhanh_phu trong cơ sở dữ liệu
                $hinhanh_phu_str = !empty($new_additional_images) ? implode('|', $new_additional_images) : null;
                $update_imgs = $conn->prepare("UPDATE sanpham SET hinhanh_phu = ? WHERE id_sanpham = ?");
                $update_imgs->bind_param("si", $hinhanh_phu_str, $product_id);
                $update_imgs->execute();
            }
            
            // Commit transaction
            $conn->commit();
            
            // Lưu hành động vào admin_actions
            $admin_id = $_SESSION['admin_id'];
            $details = "Cập nhật sản phẩm: " . $tensanpham;
            $ip = $_SERVER['REMOTE_ADDR'];
            
            $action_log = $conn->prepare("INSERT INTO admin_actions (admin_id, action_type, target_type, target_id, details, ip_address) VALUES (?, 'edit', 'product', ?, ?, ?)");
            $action_log->bind_param("iiss", $admin_id, $product_id, $details, $ip);
            $action_log->execute();
            
            $success = "Cập nhật sản phẩm thành công!";
            
            // Cập nhật lại thông tin sản phẩm sau khi đã lưu thành công
            $stmt = $conn->prepare("SELECT * FROM sanpham WHERE id_sanpham = ?");
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $product = $result->fetch_assoc();
            
            // Cập nhật lại biến thể
            $variants_query->execute();
            $variants_result = $variants_query->get_result();
            $existing_sizes = [];
            $existing_colors = [];
            $product_variants = [];
            
            while ($variant = $variants_result->fetch_assoc()) {
                if (!in_array($variant['id_kichthuoc'], $existing_sizes)) {
                    $existing_sizes[] = $variant['id_kichthuoc'];
                }
                if (!in_array($variant['id_mausac'], $existing_colors)) {
                    $existing_colors[] = $variant['id_mausac'];
                }
                $product_variants[] = $variant;
            }
            
            // Cập nhật danh sách hình ảnh phụ
            $additional_images = [];
            if (!empty($product['hinhanh_phu'])) {
                $additional_images = explode('|', $product['hinhanh_phu']);
            }
            
        } catch (Exception $e) {
            // Rollback transaction nếu có lỗi
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

// Lấy danh sách danh mục
$categories = $conn->query("SELECT * FROM loaisanpham ORDER BY tenloai");

// Lấy danh sách tất cả kích thước
$all_sizes = $conn->query("SELECT * FROM kichthuoc ORDER BY tenkichthuoc");

// Lấy danh sách tất cả màu sắc
$all_colors = $conn->query("SELECT * FROM mausac ORDER BY tenmau");

// Lấy danh sách thương hiệu
$brands_query = $conn->query("SELECT * FROM thuonghieu WHERE trangthai = 1 ORDER BY tenthuonghieu");
$brands = [];
while ($brand = $brands_query->fetch_assoc()) {
    $brands[] = $brand;
}

// Function để kiểm tra và lấy giá trị của biến thể
function getVariantQuantity($size_id, $color_id, $variants) {
    foreach ($variants as $variant) {
        if ($variant['id_kichthuoc'] == $size_id && $variant['id_mausac'] == $color_id) {
            return $variant['soluong'];
        }
    }
    return 0;
}
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
        
        .color-checkbox-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .color-checkbox {
            display: none;
        }
        
        .color-item {
            display: flex;
            align-items: center;
            padding: 8px 10px;
            border: 1px solid #e2e2e2;
            border-radius: 8px;
            transition: all 0.2s ease;
            cursor: pointer;
            background-color: #fff;
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
            margin-right: 8px;
        }
        
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
        
        .additional-image-container {
            position: relative;
            width: 100px;
            height: 100px;
            margin: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .additional-image-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .additional-image-container .form-check {
            position: absolute;
            top: 5px;
            right: 5px;
            z-index: 10;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php
            
            include('includes/sidebar.php');
            ?>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Chỉnh sửa sản phẩm</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="products.php" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Quay lại
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

                <form method="post" enctype="multipart/form-data" id="editProductForm">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0">Thông tin cơ bản</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label for="tensanpham" class="form-label required-field">Tên sản phẩm</label>
                                        <input type="text" class="form-control" id="tensanpham" name="tensanpham" value="<?php echo htmlspecialchars($product['tensanpham']); ?>" required>
                                        <div class="form-text">Tên sản phẩm nên dễ hiểu và hấp dẫn</div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="id_loai" class="form-label required-field">Danh mục</label>
                                        <select class="form-select" id="id_loai" name="id_loai" required>
                                            <option value="">-- Chọn danh mục --</option>
                                            <?php while($category = $categories->fetch_assoc()): ?>
                                                <option value="<?php echo $category['id_loai']; ?>" <?php echo ($product['id_loai'] == $category['id_loai']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($category['tenloai']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="id_thuonghieu" class="form-label">Thương hiệu</label>
                                        <select class="form-select" id="id_thuonghieu" name="id_thuonghieu">
                                            <option value="">-- Chọn thương hiệu --</option>
                                            <?php foreach ($brands as $brand): ?>
                                            <option value="<?php echo $brand['id_thuonghieu']; ?>" <?php echo ($product['id_thuonghieu'] == $brand['id_thuonghieu']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($brand['tenthuonghieu']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="gia" class="form-label required-field">Giá bán</label>
                                                <div class="input-group">
                                                    <input type="number" class="form-control" id="gia" name="gia" 
                                                           value="<?php echo $product['gia']; ?>" min="1000" required>
                                                    <span class="input-group-text">VNĐ</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="giagoc" class="form-label">Giá gốc</label>
                                                <div class="input-group">
                                                    <input type="number" class="form-control" id="giagoc" name="giagoc" 
                                                           value="<?php echo $product['giagoc']; ?>" min="1000">
                                                    <span class="input-group-text">VNĐ</span>
                                                </div>
                                                <div class="form-text">Để trống nếu không có giá gốc</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="hinh_anh" class="form-label">Hình ảnh chính</label>
                                        
                                        <div id="image-preview" class="mb-2">
                                            <?php if(!empty($product['hinhanh']) && file_exists("../uploads/products/" . $product['hinhanh'])): ?>
                                                <img src="../uploads/products/<?php echo $product['hinhanh']; ?>" alt="<?php echo htmlspecialchars($product['tensanpham']); ?>">
                                            <?php else: ?>
                                                <span class="text-muted">Chưa có hình ảnh</span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <input type="file" class="form-control" id="hinh_anh" name="hinh_anh" accept="image/*">
                                        <div class="form-text">Chọn ảnh mới nếu muốn thay đổi. Kích thước khuyến nghị: 800x800px</div>
                                        
                                        <?php if(!empty($product['hinhanh'])): ?>
                                        <div class="form-check mt-2">
                                            <input class="form-check-input" type="checkbox" id="xoa_anh" name="xoa_anh" value="1">
                                            <label class="form-check-label text-danger" for="xoa_anh">Xóa hình ảnh hiện tại</label>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="mota" class="form-label">Mô tả sản phẩm</label>
                                <textarea class="form-control" id="mota" name="mota" rows="5"><?php echo htmlspecialchars($product['mota']); ?></textarea>
                                <div class="form-text">Mô tả chi tiết về sản phẩm, tính năng, chất liệu, v.v...</div>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0">Biến thể sản phẩm</h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted">Quản lý các phiên bản sản phẩm dựa trên kích thước và màu sắc</p>
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Kích thước <span class="text-danger">*</span></label>
                                    <div class="size-checkbox-container">
                                        <?php $all_sizes->data_seek(0); ?>
                                        <?php while($size = $all_sizes->fetch_assoc()): ?>
                                            <div class="form-check">
                                                <input class="form-check-input size-checkbox" type="checkbox" name="sizes[]" 
                                                       value="<?php echo $size['id_kichthuoc']; ?>" 
                                                       id="size_<?php echo $size['id_kichthuoc']; ?>"
                                                       <?php echo in_array($size['id_kichthuoc'], $existing_sizes) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="size_<?php echo $size['id_kichthuoc']; ?>">
                                                    <?php echo htmlspecialchars($size['tenkichthuoc']); ?>
                                                </label>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                    <div class="mt-2 mb-3 size-info text-muted small"></div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Màu sắc <span class="text-danger">*</span></label>
                                    <div class="color-checkbox-container">
                                        <?php $all_colors->data_seek(0); ?>
                                        <?php while($color = $all_colors->fetch_assoc()): ?>
                                            <div class="col-md-4 col-6 mb-2">
                                                <input class="form-check-input color-checkbox" type="checkbox" name="colors[]" 
                                                       value="<?php echo $color['id_mausac']; ?>" 
                                                       id="color_<?php echo $color['id_mausac']; ?>"
                                                       <?php echo in_array($color['id_mausac'], $existing_colors) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="color_<?php echo $color['id_mausac']; ?>">
                                                    <div class="color-item">
                                                        <span class="color-swatch" style="background-color: <?php echo $color['mamau']; ?>"></span>
                                                        <span class="color-name"><?php echo htmlspecialchars($color['tenmau']); ?></span>
                                                    </div>
                                                </label>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                    <div class="mt-2 mb-3 color-info text-muted small"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0">Quản lý tồn kho</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3" id="noVariantsMessage" style="<?php echo (!empty($existing_sizes) && !empty($existing_colors)) ? 'display: none;' : ''; ?>">
                                <div class="alert alert-info">
                                    Vui lòng chọn ít nhất một kích thước và một màu sắc để quản lý tồn kho.
                                </div>
                            </div>
                            
                            <div id="inventoryTable" style="<?php echo (empty($existing_sizes) || empty($existing_colors)) ? 'display: none;' : ''; ?>">
                                <table class="table table-bordered table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Kích thước</th>
                                            <th>Màu sắc</th>
                                            <th>Số lượng tồn</th>
                                        </tr>
                                    </thead>
                                    <tbody id="inventoryTableBody">
                                        <?php 
                                        $all_sizes->data_seek(0);
                                        while($size = $all_sizes->fetch_assoc()):
                                            if (in_array($size['id_kichthuoc'], $existing_sizes)):
                                                $all_colors->data_seek(0);
                                                while($color = $all_colors->fetch_assoc()):
                                                    if (in_array($color['id_mausac'], $existing_colors)):
                                                        $quantity = getVariantQuantity($size['id_kichthuoc'], $color['id_mausac'], $product_variants);
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($size['tenkichthuoc']); ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <span class="color-swatch" style="background-color: <?php echo $color['mamau']; ?>"></span>
                                                    <?php echo htmlspecialchars($color['tenmau']); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <input type="number" class="form-control" name="inventory[<?php echo $size['id_kichthuoc']; ?>][<?php echo $color['id_mausac']; ?>]" value="<?php echo $quantity; ?>" min="0">
                                            </td>
                                        </tr>
                                        <?php 
                                                    endif;
                                                endwhile;
                                            endif;
                                        endwhile;
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0">Hình ảnh bổ sung</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($additional_images)): ?>
                            <div class="mb-3">
                                <label class="form-label">Hình ảnh hiện có</label>
                                <div class="d-flex flex-wrap">
                                    <?php foreach ($additional_images as $index => $img): ?>
                                    <div class="position-relative additional-image-container">
                                        <img src="../uploads/products/<?php echo $img; ?>" alt="Hình ảnh bổ sung <?php echo $index+1; ?>">
                                        <div class="form-check position-absolute">
                                            <input type="hidden" name="existing_images[]" value="<?php echo $img; ?>">
                                            <input class="form-check-input" type="checkbox" name="remove_images[]" value="<?php echo $img; ?>" id="remove_<?php echo $index; ?>">
                                            <label class="form-check-label" for="remove_<?php echo $index; ?>">
                                                <i class="bi bi-trash text-danger"></i>
                                            </label>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="form-text">Đánh dấu chọn để xóa hình ảnh</div>
                            </div>
                            <?php endif; ?>

                            <div class="mb-3">
                                <label for="hinhanh_phu" class="form-label">Thêm hình ảnh mới</label>
                                <input type="file" class="form-control" id="hinhanh_phu" name="hinhanh_phu[]" accept="image/*" multiple>
                                <div class="form-text">Chọn nhiều hình ảnh cùng lúc, tối đa 5 hình</div>
                            </div>

                            <div id="additionalImagesPreview" class="d-flex flex-wrap"></div>
                        </div>
                    </div>

                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0">Cài đặt bổ sung</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="noibat" name="noibat" value="1" <?php echo $product['noibat'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="noibat">Đánh dấu là sản phẩm nổi bật</label>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="trangthai" class="form-label">Trạng thái</label>
                                        <select class="form-select" id="trangthai" name="trangthai">
                                            <option value="1" <?php echo $product['trangthai'] == 1 ? 'selected' : ''; ?>>Đang kinh doanh</option>
                                            <option value="0" <?php echo $product['trangthai'] == 0 ? 'selected' : ''; ?>>Hết hàng</option>
                                            <option value="2" <?php echo $product['trangthai'] == 2 ? 'selected' : ''; ?>>Ngừng kinh doanh</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end mb-4">
                        <a href="products.php" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle"></i> Hủy
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Lưu thay đổi
                        </button>
                    </div>
                </form>
            </main>
        </div>
    </div>
    
    <script src="../node_modules/bootstrap/dist/js/bootstrap.bundle.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Preview image before upload
            const imageInput = document.getElementById('hinh_anh');
            const imagePreview = document.getElementById('image-preview');
            
            if (imageInput) {
                imageInput.addEventListener('change', function() {
                    imagePreview.innerHTML = '';
                    
                    if (this.files && this.files[0]) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const img = document.createElement('img');
                            img.src = e.target.result;
                            img.alt = 'Preview';
                            imagePreview.appendChild(img);
                        }
                        reader.readAsDataURL(this.files[0]);
                    } else {
                        <?php if(!empty($product['hinhanh']) && file_exists("../uploads/products/" . $product['hinhanh'])): ?>
                        imagePreview.innerHTML = '<img src="../uploads/products/<?php echo $product['hinhanh']; ?>" alt="<?php echo htmlspecialchars($product['tensanpham']); ?>">';
                        <?php else: ?>
                        imagePreview.innerHTML = '<span class="text-muted">Chưa có hình ảnh</span>';
                        <?php endif; ?>
                    }
                });
            }
            
            // Xóa ảnh hiện tại
            const deleteCheckbox = document.getElementById('xoa_anh');
            if (deleteCheckbox) {
                deleteCheckbox.addEventListener('change', function() {
                    if(this.checked) {
                        imagePreview.innerHTML = '<span class="text-muted">Hình ảnh sẽ bị xóa</span>';
                    } else {
                        <?php if(!empty($product['hinhanh']) && file_exists("../uploads/products/" . $product['hinhanh'])): ?>
                        imagePreview.innerHTML = '<img src="../uploads/products/<?php echo $product['hinhanh']; ?>" alt="<?php echo htmlspecialchars($product['tensanpham']); ?>">';
                        <?php else: ?>
                        imagePreview.innerHTML = '<span class="text-muted">Chưa có hình ảnh</span>';
                        <?php endif; ?>
                    }
                });
            }
            
            // Xử lý kích thước và màu sắc
            const sizeCheckboxes = document.querySelectorAll('.size-checkbox');
            const colorCheckboxes = document.querySelectorAll('.color-checkbox');
            const inventoryTable = document.getElementById('inventoryTable');
            const noVariantsMessage = document.getElementById('noVariantsMessage');
            
            function updateInventoryTable() {
                const selectedSizes = Array.from(document.querySelectorAll('.size-checkbox:checked')).map(cb => cb.value);
                const selectedColors = Array.from(document.querySelectorAll('.color-checkbox:checked')).map(cb => cb.value);
                
                if (selectedSizes.length > 0 && selectedColors.length > 0) {
                    inventoryTable.style.display = 'block';
                    noVariantsMessage.style.display = 'none';
                    
                    // Cập nhật bảng tồn kho
                    const tableBody = document.getElementById('inventoryTableBody');
                    tableBody.innerHTML = '';
                    
                    // Lấy thông tin các kích thước và màu sắc đã chọn
                    const sizeInfo = {};
                    sizeCheckboxes.forEach(cb => {
                        if (cb.checked) {
                            const sizeId = cb.value;
                            const sizeName = cb.nextElementSibling.textContent.trim();
                            sizeInfo[sizeId] = sizeName;
                        }
                    });
                    
                    const colorInfo = {};
                    colorCheckboxes.forEach(cb => {
                        if (cb.checked) {
                            const colorId = cb.value;
                            const colorName = cb.nextElementSibling.querySelector('.color-name').textContent.trim();
                            const colorCode = cb.nextElementSibling.querySelector('.color-swatch').style.backgroundColor;
                            colorInfo[colorId] = { name: colorName, code: colorCode };
                        }
                    });
                    
                    // Tạo các hàng trong bảng
                    selectedSizes.forEach(sizeId => {
                        selectedColors.forEach(colorId => {
                            // Tìm số lượng tồn kho hiện tại nếu có
                            let quantity = 0;
                            <?php foreach($product_variants as $variant): ?>
                            if (sizeId == <?php echo $variant['id_kichthuoc']; ?> && colorId == <?php echo $variant['id_mausac']; ?>) {
                                quantity = <?php echo $variant['soluong']; ?>;
                            }
                            <?php endforeach; ?>
                            
                            const row = document.createElement('tr');
                            row.innerHTML = `
                                <td>${sizeInfo[sizeId]}</td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <span class="color-swatch" style="background-color: ${colorInfo[colorId].code}"></span>
                                        ${colorInfo[colorId].name}
                                    </div>
                                </td>
                                <td>
                                    <input type="number" class="form-control" name="inventory[${sizeId}][${colorId}]" value="${quantity}" min="0">
                                </td>
                            `;
                            tableBody.appendChild(row);
                        });
                    });
                } else {
                    inventoryTable.style.display = 'none';
                    noVariantsMessage.style.display = 'block';
                }
                
                // Cập nhật thông tin số lượng đã chọn
                const sizeInfo = document.querySelector('.size-info');
                const colorInfo = document.querySelector('.color-info');
                
                const selectedSizesCount = selectedSizes.length;
                const selectedColorsCount = selectedColors.length;
                
                sizeInfo.textContent = `Đã chọn ${selectedSizesCount} kích thước`;
                colorInfo.textContent = `Đã chọn ${selectedColorsCount} màu sắc`;
            }
            
            // Thêm sự kiện cho các checkbox
            sizeCheckboxes.forEach(cb => {
                cb.addEventListener('change', updateInventoryTable);
            });
            
            colorCheckboxes.forEach(cb => {
                cb.addEventListener('change', updateInventoryTable);
            });
            
            // Preview hình ảnh phụ
            const additionalImagesInput = document.getElementById('hinhanh_phu');
            const additionalImagesPreview = document.getElementById('additionalImagesPreview');
            
            if (additionalImagesInput) {
                additionalImagesInput.addEventListener('change', function() {
                    additionalImagesPreview.innerHTML = '';
                    
                    if (this.files && this.files.length > 0) {
                        for (let i = 0; i < this.files.length; i++) {
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                const previewContainer = document.createElement('div');
                                previewContainer.className = 'additional-image-container';
                                
                                const img = document.createElement('img');
                                img.src = e.target.result;
                                img.alt = 'Preview ' + (i + 1);
                                
                                previewContainer.appendChild(img);
                                additionalImagesPreview.appendChild(previewContainer);
                            }
                            reader.readAsDataURL(this.files[i]);
                        }
                    }
                });
            }
            
            // Khởi tạo ban đầu
            updateInventoryTable();
            
            // Form validation trước khi submit
            const editProductForm = document.getElementById('editProductForm');
            if (editProductForm) {
                editProductForm.addEventListener('submit', function(e) {
                    const selectedSizes = document.querySelectorAll('.size-checkbox:checked');
                    const selectedColors = document.querySelectorAll('.color-checkbox:checked');
                    
                    if (selectedSizes.length === 0 || selectedColors.length === 0) {
                        e.preventDefault();
                        alert('Vui lòng chọn ít nhất một kích thước và một màu sắc');
                        return false;
                    }
                    
                    return true;
                });
            }
        });
    </script>
</body>
</html>
