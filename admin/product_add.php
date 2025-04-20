<?php
// Kiểm tra đăng nhập và quyền admin
session_start();
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_loai']) || $_SESSION['admin_loai'] < 1) {
    header('Location: login.php');
    exit();
}

// Kết nối database
require_once '../config/config.php';
include 'includes/header.php';
include 'includes/sidebar.php';
// Khởi tạo biến
$error = $success = '';
$form_data = [
    'tensanpham' => '',
    'id_danhmuc' => '',
    'thuonghieu' => '',
    'gia' => '',
    'giagoc' => '',
    'mota' => '',
    'mota_ngan' => '',
    'noibat' => 0,
    'trangthai' => 1
];

// Lấy danh sách danh mục
$categories_sql = "SELECT id, ten FROM danhmuc WHERE trang_thai = 1 ORDER BY ten";
$categories = $conn->query($categories_sql);

// Lấy danh sách thương hiệu
$brands_sql = "SELECT id, ten FROM thuong_hieu ORDER BY ten";
$brands = $conn->query($brands_sql);

// Lấy danh sách thuộc tính (màu sắc, kích cỡ)
$colors_sql = "SELECT id, ten, ma_mau FROM thuoc_tinh WHERE loai = 'color' ORDER BY ten";
$colors = $conn->query($colors_sql);

$sizes_sql = "SELECT id, ten, gia_tri FROM thuoc_tinh WHERE loai = 'size' ORDER BY gia_tri + 0"; // Sắp xếp số
$sizes = $conn->query($sizes_sql);

// Xử lý form khi submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lấy dữ liệu từ form
    $form_data = [
        'tensanpham' => $_POST['tensanpham'] ?? '',
        'id_danhmuc' => $_POST['id_danhmuc'] ?? '',
        'thuonghieu' => $_POST['thuonghieu'] ?? '',
        'gia' => $_POST['gia'] ?? '',
        'giagoc' => $_POST['giagoc'] ?? '',
        'mota' => $_POST['mota'] ?? '',
        'mota_ngan' => $_POST['mota_ngan'] ?? '',
        'noibat' => isset($_POST['noibat']) ? 1 : 0,
        'trangthai' => isset($_POST['trangthai']) ? 1 : 0
    ];
    
    // Validate dữ liệu
    $errors = [];
    
    if (empty($form_data['tensanpham'])) {
        $errors[] = "Vui lòng nhập tên sản phẩm";
    }
    
    if (empty($form_data['id_danhmuc'])) {
        $errors[] = "Vui lòng chọn danh mục";
    }
    
    if (empty($form_data['gia']) || !is_numeric($form_data['gia']) || $form_data['gia'] < 0) {
        $errors[] = "Vui lòng nhập giá hợp lệ";
    }
    
    // Kiểm tra giá gốc nếu có
    if (!empty($form_data['giagoc']) && (!is_numeric($form_data['giagoc']) || $form_data['giagoc'] < 0)) {
        $errors[] = "Giá gốc không hợp lệ";
    }
    
    // Kiểm tra biến thể
    $variants = [];
    if (isset($_POST['variant_size']) && isset($_POST['variant_color']) && isset($_POST['variant_quantity'])) {
        for ($i = 0; $i < count($_POST['variant_size']); $i++) {
            if (isset($_POST['variant_size'][$i]) && isset($_POST['variant_color'][$i]) && isset($_POST['variant_quantity'][$i])) {
                $size_id = $_POST['variant_size'][$i];
                $color_id = $_POST['variant_color'][$i];
                $quantity = $_POST['variant_quantity'][$i];
                
                if (!empty($size_id) && !empty($color_id) && is_numeric($quantity) && $quantity >= 0) {
                    $variants[] = [
                        'size_id' => $size_id,
                        'color_id' => $color_id,
                        'quantity' => $quantity
                    ];
                }
            }
        }
    }
    
    if (count($variants) === 0) {
        $errors[] = "Vui lòng thêm ít nhất một biến thể sản phẩm";
    }
    
    // Xử lý nếu không có lỗi
    if (empty($errors)) {
        // Bắt đầu transaction
        $conn->begin_transaction();
        
        try {
            // Tạo slug từ tên sản phẩm
            $slug = create_slug($form_data['tensanpham']);
            
            // Kiểm tra slug đã tồn tại chưa
            $check_slug_sql = "SELECT id FROM sanpham WHERE slug = ?";
            $check_slug_stmt = $conn->prepare($check_slug_sql);
            $check_slug_stmt->bind_param("s", $slug);
            $check_slug_stmt->execute();
            $slug_result = $check_slug_stmt->get_result();
            
            // Nếu slug đã tồn tại, thêm suffix
            if ($slug_result->num_rows > 0) {
                $slug = $slug . '-' . time();
            }
            
            // Upload ảnh chính
            $main_image = '';
            if (isset($_FILES['hinhanh']) && $_FILES['hinhanh']['error'] === 0) {
                $upload_dir = '../uploads/products/';
                
                // Tạo thư mục nếu chưa tồn tại
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_name = time() . '_' . basename($_FILES['hinhanh']['name']);
                $file_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['hinhanh']['tmp_name'], $file_path)) {
                    $main_image = 'uploads/products/' . $file_name;
                } else {
                    throw new Exception("Không thể upload hình ảnh chính");
                }
            }
            
            // Thêm sản phẩm vào database
            $insert_sql = "INSERT INTO sanpham (tensanpham, slug, id_danhmuc, gia, giagoc, hinhanh, mota, mota_ngan, noibat, trangthai, thuonghieu) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("ssiidsssiis", 
                $form_data['tensanpham'], 
                $slug, 
                $form_data['id_danhmuc'], 
                $form_data['gia'], 
                $form_data['giagoc'], 
                $main_image, 
                $form_data['mota'], 
                $form_data['mota_ngan'], 
                $form_data['noibat'], 
                $form_data['trangthai'],
                $form_data['thuonghieu']
            );
            $insert_stmt->execute();
            
            $product_id = $conn->insert_id;
            
            // Thêm ảnh chính vào bảng ảnh sản phẩm
            if (!empty($main_image)) {
                $insert_img_sql = "INSERT INTO sanpham_hinhanh (id_sanpham, hinhanh, la_anh_chinh) VALUES (?, ?, 1)";
                $insert_img_stmt = $conn->prepare($insert_img_sql);
                $insert_img_stmt->bind_param("is", $product_id, $main_image);
                $insert_img_stmt->execute();
            }
            
            // Upload và thêm ảnh phụ
            if (isset($_FILES['product_images'])) {
                $file_count = count($_FILES['product_images']['name']);
                
                for ($i = 0; $i < $file_count; $i++) {
                    if ($_FILES['product_images']['error'][$i] === 0) {
                        $upload_dir = '../uploads/products/';
                        $file_name = time() . '_' . $i . '_' . basename($_FILES['product_images']['name'][$i]);
                        $file_path = $upload_dir . $file_name;
                        
                        if (move_uploaded_file($_FILES['product_images']['tmp_name'][$i], $file_path)) {
                            $image_path = 'uploads/products/' . $file_name;
                            
                            $insert_img_sql = "INSERT INTO sanpham_hinhanh (id_sanpham, hinhanh, la_anh_chinh) VALUES (?, ?, 0)";
                            $insert_img_stmt = $conn->prepare($insert_img_sql);
                            $insert_img_stmt->bind_param("is", $product_id, $image_path);
                            $insert_img_stmt->execute();
                        }
                    }
                }
            }
            
            // Thêm các biến thể sản phẩm
            $variant_sql = "INSERT INTO sanpham_bien_the (id_sanpham, id_mau, id_size, so_luong) VALUES (?, ?, ?, ?)";
            $variant_stmt = $conn->prepare($variant_sql);
            
            $total_stock = 0;
            
            foreach ($variants as $variant) {
                $variant_stmt->bind_param("iiii", $product_id, $variant['color_id'], $variant['size_id'], $variant['quantity']);
                $variant_stmt->execute();
                $total_stock += $variant['quantity'];
            }
            
            // Cập nhật tổng số lượng trong bảng sanpham
            $update_stock_sql = "UPDATE sanpham SET so_luong = ? WHERE id = ?";
            $update_stock_stmt = $conn->prepare($update_stock_sql);
            $update_stock_stmt->bind_param("ii", $total_stock, $product_id);
            $update_stock_stmt->execute();
            
            // Ghi log hoạt động
            $log_sql = "INSERT INTO nhat_ky (id_user, hanh_dong, doi_tuong_loai, doi_tuong_id, chi_tiet, ip_address) 
                      VALUES (?, 'create', 'product', ?, ?, ?)";
            $log_stmt = $conn->prepare($log_sql);
            $admin_id = $_SESSION['admin_id'];
            $detail = "Thêm sản phẩm mới: " . $form_data['tensanpham'];
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $log_stmt->bind_param("iiss", $admin_id, $product_id, $detail, $ip);
            $log_stmt->execute();
            
            // Commit transaction
            $conn->commit();
            
            // Thành công
            $success = "Thêm sản phẩm thành công!";
            
            // Reset form data
            $form_data = [
                'tensanpham' => '',
                'id_danhmuc' => '',
                'thuonghieu' => '',
                'gia' => '',
                'giagoc' => '',
                'mota' => '',
                'mota_ngan' => '',
                'noibat' => 0,
                'trangthai' => 1
            ];
            
            // Chuyển hướng đến trang sản phẩm với thông báo thành công
            header("Location: products.php?success=" . urlencode($success));
            exit();
            
        } catch (Exception $e) {
            // Rollback transaction nếu có lỗi
            $conn->rollback();
            $error = "Lỗi khi thêm sản phẩm: " . $e->getMessage();
        }
    } else {
        $error = implode("<br>", $errors);
    }
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

// Format tiền VNĐ
function formatVND($amount) {
    return number_format($amount, 0, ',', '.') . ' ₫';
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm sản phẩm - Bug Shop Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #858796;
            --success-color: #1cc88a;
            --info-color: #36b9cc;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
        }
        
        body {
            background-color: #f8f9fc;
            font-family: "Nunito", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, var(--primary-color) 10%, #224abe 100%);
            color: white;
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 0.75rem 1rem;
        }
        
        .sidebar .nav-link:hover {
            color: #fff;
        }
        
        .sidebar .nav-link.active {
            font-weight: bold;
            color: #fff;
        }
        
        .sidebar-brand {
            height: 4.375rem;
            font-size: 1.2rem;
            font-weight: 800;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.05rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .form-card {
            background-color: #fff;
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        
        .color-preview {
            display: inline-block;
            width: 20px;
            height: 20px;
            margin-right: 8px;
            border: 1px solid #ddd;
            vertical-align: middle;
        }
        
        .variant-row {
            background-color: rgba(0, 0, 0, 0.01);
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .image-preview {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        
        .remove-variant {
            display: inline-block;
            cursor: pointer;
            color: var(--danger-color);
        }
        
        .summernote {
            min-height: 200px;
        }
        
        .required-field::after {
            content: "*";
            color: red;
            margin-left: 4px;
        }
        
        .image-upload-container {
            border: 2px dashed #ddd;
            border-radius: 5px;
            padding: 25px;
            text-align: center;
            cursor: pointer;
            margin-bottom: 20px;
        }
        
        .image-upload-container:hover {
            border-color: var(--primary-color);
        }
    </style>
</head>
<body>


        
        <!-- Main Content -->
        <div class="col-md-10 col-lg-10 ms-auto">
            <div class="container-fluid py-4 px-4">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0 text-gray-800">Thêm sản phẩm mới</h1>
                    <a href="products.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Quay lại danh sách
                    </a>
                </div>
                
                <!-- Thông báo -->
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-12">
                        <div class="card form-card shadow-sm mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Thông tin sản phẩm</h6>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="" enctype="multipart/form-data" id="productForm">
                                    <!-- Thông tin cơ bản -->
                                    <div class="row mb-4">
                                        <div class="col-lg-8">
                                            <div class="mb-3">
                                                <label for="tensanpham" class="form-label required-field">Tên sản phẩm</label>
                                                <input type="text" class="form-control" id="tensanpham" name="tensanpham" 
                                                       value="<?php echo htmlspecialchars($form_data['tensanpham']); ?>" required>
                                                <small class="text-muted">Tên sản phẩm sẽ hiển thị trên website và trong đơn hàng</small>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label for="id_danhmuc" class="form-label required-field">Danh mục</label>
                                                    <select class="form-select" id="id_danhmuc" name="id_danhmuc" required>
                                                        <option value="">-- Chọn danh mục --</option>
                                                        <?php while ($category = $categories->fetch_assoc()): ?>
                                                            <option value="<?php echo $category['id']; ?>" 
                                                                <?php echo ($form_data['id_danhmuc'] == $category['id']) ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars($category['ten']); ?>
                                                            </option>
                                                        <?php endwhile; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label for="thuonghieu" class="form-label">Thương hiệu</label>
                                                    <select class="form-select" id="thuonghieu" name="thuonghieu">
                                                        <option value="">-- Chọn thương hiệu --</option>
                                                        <?php while ($brand = $brands->fetch_assoc()): ?>
                                                            <option value="<?php echo $brand['id']; ?>" 
                                                                <?php echo ($form_data['thuonghieu'] == $brand['id']) ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars($brand['ten']); ?>
                                                            </option>
                                                        <?php endwhile; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label for="gia" class="form-label required-field">Giá bán</label>
                                                    <div class="input-group">
                                                        <input type="number" class="form-control" id="gia" name="gia" 
                                                               value="<?php echo htmlspecialchars($form_data['gia']); ?>" min="0" step="1000" required>
                                                        <span class="input-group-text">₫</span>
                                                    </div>
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label for="giagoc" class="form-label">Giá gốc</label>
                                                    <div class="input-group">
                                                        <input type="number" class="form-control" id="giagoc" name="giagoc" 
                                                               value="<?php echo htmlspecialchars($form_data['giagoc']); ?>" min="0" step="1000">
                                                        <span class="input-group-text">₫</span>
                                                    </div>
                                                    <small class="text-muted">Để trống nếu không có giảm giá</small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-lg-4">
                                            <div class="mb-3">
                                                <label for="hinhanh" class="form-label required-field">Ảnh chính</label>
                                                <div class="image-upload-container" id="mainImageContainer">
                                                    <img id="mainImagePreview" class="image-preview mb-2 d-none" src="" alt="Ảnh sản phẩm">
                                                    <div id="mainImagePlaceholder">
                                                        <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-2"></i>
                                                        <p class="mb-0">Click để chọn hình ảnh chính</p>
                                                        <small class="text-muted">JPG, PNG, GIF (max 5MB)</small>
                                                    </div>
                                                    <input type="file" class="d-none" id="hinhanh" name="hinhanh" accept="image/*" required>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="trangthai" name="trangthai" 
                                                           <?php echo $form_data['trangthai'] ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="trangthai">Hiển thị sản phẩm</label>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="noibat" name="noibat" 
                                                           <?php echo $form_data['noibat'] ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="noibat">Sản phẩm nổi bật</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Mô tả -->
                                    <div class="row mb-4">
                                        <div class="col-12 mb-3">
                                            <label for="mota_ngan" class="form-label">Mô tả ngắn</label>
                                            <textarea class="form-control" id="mota_ngan" name="mota_ngan" rows="3" 
                                                      maxlength="500"><?php echo htmlspecialchars($form_data['mota_ngan']); ?></textarea>
                                            <small class="text-muted">Tối đa 500 ký tự</small>
                                        </div>
                                        <div class="col-12">
                                            <label for="mota" class="form-label">Mô tả chi tiết</label>
                                            <textarea class="form-control summernote" id="mota" name="mota"><?php echo htmlspecialchars($form_data['mota']); ?></textarea>
                                        </div>
                                    </div>
                                    
                                    <!-- Hình ảnh phụ -->
                                    <div class="row mb-4">
                                        <div class="col-12">
                                            <h5 class="border-bottom pb-2 mb-3">Hình ảnh bổ sung</h5>
                                            <div class="mb-3">
                                                <label class="form-label">Ảnh bổ sung sản phẩm (tối đa 5 ảnh)</label>
                                                <div class="image-upload-container" id="extraImagesContainer">
                                                    <i class="fas fa-images fa-3x text-muted mb-2"></i>
                                                    <p class="mb-0">Click để thêm nhiều hình ảnh</p>
                                                    <small class="text-muted">JPG, PNG, GIF (max 5MB mỗi ảnh)</small>
                                                    <div id="extraImagesPreview" class="d-flex flex-wrap gap-2 mt-3"></div>
                                                    <input type="file" class="d-none" id="product_images" name="product_images[]" accept="image/*" multiple>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Biến thể sản phẩm -->
                                    <div class="row mb-4">
                                        <div class="col-12">
                                            <h5 class="border-bottom pb-2 mb-3">Biến thể sản phẩm</h5>
                                            <p class="text-muted">Thêm các biến thể kích thước và màu sắc cho sản phẩm</p>
                                            
                                            <div id="variants-container">
                                                <!-- Biến thể mẫu sẽ được thêm bằng JavaScript -->
                                            </div>
                                            
                                            <div class="mb-3">
                                                <button type="button" id="add-variant" class="btn btn-outline-primary">
                                                    <i class="fas fa-plus me-1"></i> Thêm biến thể
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Nút submit -->
                                    <div class="row">
                                        <div class="col-12 text-end">
                                            <hr>
                                            <button type="reset" class="btn btn-secondary me-2">
                                                <i class="fas fa-redo me-1"></i> Làm lại
                                            </button>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-save me-1"></i> Lưu sản phẩm
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Template cho biến thể sản phẩm -->
<template id="variant-template">
    <div class="variant-row">
        <div class="row align-items-center">
            <div class="col-md-4">
                <div class="mb-3 mb-md-0">
                    <label class="form-label required-field">Kích thước</label>
                    <select class="form-select variant-size" name="variant_size[]" required>
                        <option value="">-- Chọn kích thước --</option>
                        <?php 
                        // Reset pointer về đầu để dùng lại
                        $sizes->data_seek(0);
                        while ($size = $sizes->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $size['id']; ?>">
                                <?php echo htmlspecialchars($size['ten']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
            <div class="col-md-4">
                <div class="mb-3 mb-md-0">
                    <label class="form-label required-field">Màu sắc</label>
                    <select class="form-select variant-color" name="variant_color[]" required>
                        <option value="">-- Chọn màu sắc --</option>
                        <?php 
                        // Reset pointer về đầu để dùng lại
                        $colors->data_seek(0);
                        while ($color = $colors->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $color['id']; ?>" data-color="<?php echo htmlspecialchars($color['ma_mau']); ?>">
                                <span class="color-display">
                                    <?php echo htmlspecialchars($color['ten']); ?>
                                </span>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
            <div class="col-md-3">
                <div class="mb-3 mb-md-0">
                    <label class="form-label required-field">Số lượng</label>
                    <input type="number" class="form-control variant-quantity" name="variant_quantity[]" min="0" value="0" required>
                </div>
            </div>
            <div class="col-md-1">
                <div class="d-flex h-100 align-items-center justify-content-center mt-3 mt-md-0">
                    <a href="#" class="remove-variant" title="Xóa biến thể">
                        <i class="fas fa-trash"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</template>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Khởi tạo Summernote
    $('.summernote').summernote({
        height: 300,
        toolbar: [
            ['style', ['style', 'bold', 'italic', 'underline', 'clear']],
            ['font', ['strikethrough', 'superscript', 'subscript']],
            ['fontsize', ['fontsize']],
            ['color', ['color']],
            ['para', ['ul', 'ol', 'paragraph']],
            ['table', ['table']],
            ['insert', ['link', 'picture']],
            ['view', ['fullscreen', 'codeview', 'help']]
        ],
        callbacks: {
            onImageUpload: function(files) {
                // Xử lý upload ảnh nếu cần
                alert('Upload ảnh trong editor chưa được hỗ trợ, vui lòng dùng link ảnh');
            }
        }
    });
    
    // Xử lý upload ảnh chính
    const mainImageContainer = document.getElementById('mainImageContainer');
    const mainImageInput = document.getElementById('hinhanh');
    const mainImagePreview = document.getElementById('mainImagePreview');
    const mainImagePlaceholder = document.getElementById('mainImagePlaceholder');
    
    mainImageContainer.addEventListener('click', function() {
        mainImageInput.click();
    });
    
    mainImageInput.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            const file = this.files[0];
            
            // Kiểm tra kích thước file (max 5MB)
            if (file.size > 5 * 1024 * 1024) {
                alert('Kích thước file quá lớn. Vui lòng chọn file nhỏ hơn 5MB.');
                this.value = '';
                return;
            }
            
            // Kiểm tra loại file
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!allowedTypes.includes(file.type)) {
                alert('Định dạng file không hỗ trợ. Vui lòng chọn ảnh có định dạng JPG, PNG hoặc GIF.');
                this.value = '';
                return;
            }
            
            const reader = new FileReader();
            
            reader.onload = function(e) {
                mainImagePreview.src = e.target.result;
                mainImagePreview.classList.remove('d-none');
                mainImagePlaceholder.classList.add('d-none');
            }
            
            reader.readAsDataURL(file);
        }
    });
    
    // Xử lý upload nhiều ảnh
    const extraImagesContainer = document.getElementById('extraImagesContainer');
    const extraImagesInput = document.getElementById('product_images');
    const extraImagesPreview = document.getElementById('extraImagesPreview');
    
    extraImagesContainer.addEventListener('click', function() {
        extraImagesInput.click();
    });
    
    extraImagesInput.addEventListener('change', function() {
        extraImagesPreview.innerHTML = '';
        
        if (this.files && this.files.length > 0) {
            // Giới hạn số lượng file
            if (this.files.length > 5) {
                alert('Bạn chỉ có thể upload tối đa 5 ảnh.');
                this.value = '';
                return;
            }
            
            for (let i = 0; i < this.files.length; i++) {
                const file = this.files[i];
                
                // Kiểm tra kích thước file (max 5MB)
                if (file.size > 5 * 1024 * 1024) {
                    alert(`File "${file.name}" quá lớn. Vui lòng chọn file nhỏ hơn 5MB.`);
                    continue;
                }
                
                // Kiểm tra loại file
                const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    alert(`File "${file.name}" không hỗ trợ. Vui lòng chọn ảnh có định dạng JPG, PNG hoặc GIF.`);
                    continue;
                }
                
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const imgContainer = document.createElement('div');
                    imgContainer.className = 'position-relative';
                    
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.className = 'image-preview';
                    img.alt = 'Ảnh sản phẩm ' + (i + 1);
                    
                    imgContainer.appendChild(img);
                    extraImagesPreview.appendChild(imgContainer);
                }
                
                reader.readAsDataURL(file);
            }
        }
    });
    
    // Xử lý thêm/xóa biến thể
    const variantsContainer = document.getElementById('variants-container');
    const addVariantBtn = document.getElementById('add-variant');
    const variantTemplate = document.getElementById('variant-template');
    
    // Thêm biến thể đầu tiên khi tải trang
    addVariantRow();
    
    addVariantBtn.addEventListener('click', function() {
        addVariantRow();
    });
    
    // Xử lý xóa biến thể
    variantsContainer.addEventListener('click', function(e) {
        if (e.target.closest('.remove-variant')) {
            e.preventDefault();
            
            const variantRow = e.target.closest('.variant-row');
            
            // Chỉ xóa nếu có nhiều hơn 1 biến thể
            if (variantsContainer.querySelectorAll('.variant-row').length > 1) {
                variantRow.remove();
            } else {
                alert('Phải có ít nhất một biến thể cho sản phẩm.');
            }
        }
    });
    
    // Xử lý hiển thị màu sắc trong dropdown
    variantsContainer.addEventListener('change', function(e) {
        if (e.target.classList.contains('variant-color')) {
            const selectedOption = e.target.options[e.target.selectedIndex];
            const colorCode = selectedOption.getAttribute('data-color');
            
            if (colorCode) {
                selectedOption.style.backgroundColor = colorCode;
                if (isLightColor(colorCode)) {
                    selectedOption.style.color = '#000';
                } else {
                    selectedOption.style.color = '#fff';
                }
            }
        }
    });
    
    // Validate form trước khi submit
    const productForm = document.getElementById('productForm');
    
    productForm.addEventListener('submit', function(e) {
        // Kiểm tra các trường bắt buộc
        const requiredFields = productForm.querySelectorAll('[required]');
        let isValid = true;
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('is-invalid');
                isValid = false;
            } else {
                field.classList.remove('is-invalid');
            }
        });
        
        // Kiểm tra có ảnh chính chưa
        if (!mainImageInput.files || !mainImageInput.files[0]) {
            mainImageContainer.classList.add('border-danger');
            isValid = false;
        } else {
            mainImageContainer.classList.remove('border-danger');
        }
        
        // Kiểm tra các biến thể
        const variants = variantsContainer.querySelectorAll('.variant-row');
        let hasValidVariant = false;
        
        variants.forEach(variant => {
            const sizeSelect = variant.querySelector('.variant-size');
            const colorSelect = variant.querySelector('.variant-color');
            const quantityInput = variant.querySelector('.variant-quantity');
            
            if (sizeSelect.value && colorSelect.value && parseInt(quantityInput.value) >= 0) {
                hasValidVariant = true;
            }
        });
        
        if (!hasValidVariant) {
            alert('Vui lòng thêm ít nhất một biến thể hợp lệ cho sản phẩm.');
            isValid = false;
        }
        
        if (!isValid) {
            e.preventDefault();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    });
    
    // Các hàm hỗ trợ
    function addVariantRow() {
        const variantContent = variantTemplate.content.cloneNode(true);
        variantsContainer.appendChild(variantContent);
    }
    
    function isLightColor(color) {
        // Chuyển mã màu hex thành RGB
        let r, g, b;
        
        if (color.startsWith('#')) {
            color = color.substring(1);
            
            if (color.length === 3) {
                r = parseInt(color[0] + color[0], 16);
                g = parseInt(color[1] + color[1], 16);
                b = parseInt(color[2] + color[2], 16);
            } else if (color.length === 6) {
                r = parseInt(color.substring(0, 2), 16);
                g = parseInt(color.substring(2, 4), 16);
                b = parseInt(color.substring(4, 6), 16);
            } else {
                return true;
            }
            
            // Tính độ sáng (YIQ)
            const yiq = ((r * 299) + (g * 587) + (b * 114)) / 1000;
            
            // Nếu YIQ > 128, màu sáng
            return yiq > 128;
        }
        
        return true;
    }
});
</script>
</body>
</html>
