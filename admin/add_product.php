<?php
session_start();

// Kiểm tra đăng nhập
if(!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

include('../config/config.php');

$error = '';
$success = '';

// Thêm đoạn code để lấy màu mặc định đầu tiên
$default_color_query = $conn->query("SELECT id_mausac FROM mausac ORDER BY id_mausac LIMIT 1");
$default_color = 1; // Giá trị mặc định nếu không có dữ liệu

if ($default_color_query && $default_color_query->num_rows > 0) {
    $default_color = $default_color_query->fetch_assoc()['id_mausac'];
}

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
        // Xử lý upload hình ảnh
        $hinh_anh = '';
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
                    $hinh_anh = $unique_name;
                } else {
                    $error = "Có lỗi xảy ra khi tải lên hình ảnh";
                }
            } else {
                $error = "Chỉ cho phép các file hình ảnh (jpg, jpeg, png, gif, webp)";
            }
        }
        
        // Nếu không có lỗi, tiến hành thêm sản phẩm vào database
        if(empty($error)) {
            $sql = "INSERT INTO sanpham (tensanpham, gia, giagoc, soluong, mota, hinhanh, id_loai, trangthai) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sddiisii", $ten_sp, $gia, $giagoc, $so_luong, $mo_ta, $hinh_anh, $id_loai, $trang_thai);
            
            if($stmt->execute()) {
                $new_product_id = $conn->insert_id;
                
                // Thêm các biến thể cho sản phẩm mới (kích thước và màu sắc)
                if (!empty($selected_sizes) && !empty($selected_colors)) {
                    // Trường hợp có cả kích thước và màu sắc: tạo tổ hợp
                    $insert_variant = $conn->prepare("INSERT INTO sanpham_chitiet (id_sanpham, id_kichthuoc, id_mausac, soluong) VALUES (?, ?, ?, ?)");
                    
                    foreach ($selected_sizes as $size_id) {
                        foreach ($selected_colors as $color_id) {
                            $insert_variant->bind_param("iiii", $new_product_id, $size_id, $color_id, $so_luong);
                            $insert_variant->execute();
                        }
                    }
                } elseif (!empty($selected_sizes)) {
                    // Chỉ có kích thước, dùng màu mặc định
                    $insert_variant = $conn->prepare("INSERT INTO sanpham_chitiet (id_sanpham, id_kichthuoc, id_mausac, soluong) VALUES (?, ?, ?, ?)");
                    
                    foreach ($selected_sizes as $size_id) {
                        $insert_variant->bind_param("iiii", $new_product_id, $size_id, $default_color, $so_luong);
                        $insert_variant->execute();
                    }
                } elseif (!empty($selected_colors)) {
                    // Chỉ có màu sắc, dùng kích thước mặc định hoặc NULL
                    $insert_variant = $conn->prepare("INSERT INTO sanpham_chitiet (id_sanpham, id_kichthuoc, id_mausac, soluong) VALUES (?, NULL, ?, ?)");
                    
                    foreach ($selected_colors as $color_id) {
                        $insert_variant->bind_param("iii", $new_product_id, $color_id, $so_luong);
                        $insert_variant->execute();
                    }
                }
                
                // Xử lý upload hình ảnh cho từng màu sắc
                $color_images = $_FILES['color_image'] ?? [];
                $color_image_ids = $_POST['color_image_id'] ?? [];
                
                // Tạo thư mục uploads/colors nếu chưa tồn tại
                $color_upload_dir = "../uploads/colors/";
                if(!file_exists($color_upload_dir)) {
                    mkdir($color_upload_dir, 0777, true);
                }
                
                foreach ($selected_colors as $color_id) {
                    $color_key = array_search($color_id, $color_image_ids);
                    if ($color_key !== false && isset($color_images['name'][$color_key]) && !empty($color_images['name'][$color_key])) {
                        // Upload hình ảnh cho màu
                        $file_name = $color_images['name'][$color_key];
                        $file_tmp = $color_images['tmp_name'][$color_key];
                        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                        
                        if (in_array($file_extension, ["jpg", "jpeg", "png", "gif", "webp"])) {
                            $unique_name = time() . '_' . uniqid() . '_' . $color_id . '.' . $file_extension;
                            $upload_path = $color_upload_dir . $unique_name;
                            
                            if (move_uploaded_file($file_tmp, $upload_path)) {
                                // Cập nhật đường dẫn hình ảnh vào database
                                $update_image = $conn->prepare("UPDATE sanpham_chitiet SET hinhanh_mau = ? WHERE id_chitiet = ?");
                                $update_image->bind_param("si", $unique_name, $variant_id);
                                $update_image->execute();
                            }
                        }
                    }
                }
                
                $success = "Thêm sản phẩm thành công!";
                
                // Làm trống form sau khi thêm thành công
                $ten_sp = $mo_ta = '';
                $giagoc = $gia = $so_luong = '';
                $id_loai = 0;
                $trang_thai = 1;
            } else {
                $error = "Lỗi khi thêm sản phẩm: " . $stmt->error;
                
                // Nếu có lỗi và đã upload ảnh, xóa ảnh đã upload
                if(!empty($hinh_anh) && file_exists($upload_dir . $hinh_anh)) {
                    unlink($upload_dir . $hinh_anh);
                }
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
    <title>Thêm sản phẩm mới - Bug Shop Admin</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border: 2px solid #ddd;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }
        
        .color-checkbox:checked + label::after {
            content: "\F633"; /* Bootstrap icon check symbol */
            font-family: bootstrap-icons;
            position: absolute;
            color: white;
            text-shadow: 0px 0px 2px rgba(0,0,0,0.8);
            font-size: 1.2rem;
        }
        
        /* Styles for color image upload */
        .color-image-upload {
            margin-top: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        
        .color-image-item {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            padding: 10px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            background-color: white;
        }
        
        .color-swatch {
            width: 25px;
            height: 25px;
            border-radius: 50%;
            border: 1px solid rgba(0,0,0,0.1);
            box-shadow: inset 0 0 0 1px rgba(255,255,255,0.3);
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

        .color-name {
            font-size: 0.85rem;
            font-weight: 500;
            color: #212529;
            flex-grow: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
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
                    <h1 class="h2">Thêm sản phẩm mới</h1>
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
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="ten_sp" class="form-label required-field">Tên sản phẩm</label>
                                    <input type="text" class="form-control" id="ten_sp" name="ten_sp" value="<?php echo htmlspecialchars($ten_sp ?? ''); ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label for="giagoc" class="form-label required-field">Giá gốc</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="giagoc" name="giagoc" min="0" step="1000" value="<?php echo $giagoc ?? ''; ?>" required>
                                        <span class="input-group-text">₫</span>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label for="gia" class="form-label">Giá bán (sau khuyến mãi)</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="gia" name="gia" min="0" step="1000" value="<?php echo $gia ?? ''; ?>">
                                        <span class="input-group-text">₫</span>
                                    </div>
                                </div>
                                <div class="col-md-3 mt-3">
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
                                    <input type="number" class="form-control" id="so_luong" name="so_luong" min="0" value="<?php echo $so_luong ?? ''; ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="id_loai" class="form-label required-field">Danh mục</label>
                                    <select class="form-select" id="id_loai" name="id_loai" required>
                                        <option value="">-- Chọn danh mục --</option>
                                        <?php while($category = $categories->fetch_assoc()): ?>
                                        <option value="<?php echo $category['id_loai']; ?>" <?php echo (isset($id_loai) && $id_loai == $category['id_loai']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['tenloai']); ?>
                                        </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="hinh_anh" class="form-label">Hình ảnh</label>
                                    <input type="file" class="form-control" id="hinh_anh" name="hinh_anh" accept="image/*">
                                    <div id="image-preview" class="mt-2">
                                        <span class="text-muted">Chưa có hình ảnh</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="mo_ta" class="form-label">Mô tả sản phẩm</label>
                                <textarea class="form-control" id="mo_ta" name="mo_ta" rows="4"><?php echo htmlspecialchars($mo_ta ?? ''); ?></textarea>
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
                                                   value="<?php echo $size['id_kichthuoc']; ?>">
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
                                                       data-color-code="<?php echo htmlspecialchars($color['mamau']); ?>">
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

                            <!-- Hình ảnh cho từng màu sắc -->
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
                                            <!-- JavaScript sẽ tự động tạo các thẻ cho từng màu đã chọn -->
                                        </div>
                                        
                                        <div id="no-colors-selected" class="alert alert-warning py-3">
                                            <i class="bi bi-exclamation-triangle me-2"></i>
                                            <span>Vui lòng chọn ít nhất một màu sắc ở mục trên để tải lên hình ảnh</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="trang_thai" name="trang_thai" <?php echo (!isset($trang_thai) || $trang_thai == 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="trang_thai">
                                    Hiển thị sản phẩm (cho phép mua)
                                </label>
                            </div>
                            
                            <hr>
                            <div class="d-flex justify-content-between">
                                <a href="products.php" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left"></i> Hủy
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-plus-circle"></i> Thêm sản phẩm
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
                    <button type="button" class="btn btn-primary" id="saveColorBtn">Lưu màu mới</button>
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
                preview.innerHTML = '<span class="text-muted">Chưa có hình ảnh</span>';
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
            const colorSearchInput = document.getElementById('colorSearchInput');
            const clearSearchBtn = document.getElementById('clearSearch');
            const colorItems = document.querySelectorAll('.color-item-wrapper');
            const noColorsFound = document.querySelector('.no-colors-found');

            // Tìm kiếm màu
            colorSearchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase().trim();
                let visibleCount = 0;
                
                colorItems.forEach(item => {
                    const colorName = item.dataset.colorName;
                    if (colorName.includes(searchTerm)) {
                        item.style.display = '';
                        visibleCount++;
                    } else {
                        item.style.display = 'none';
                    }
                });
                
                // Show/hide "no colors found" message
                if (visibleCount === 0) {
                    noColorsFound.classList.remove('d-none');
                } else {
                    noColorsFound.classList.add('d-none');
                }
                
                // Show/hide clear button
                if (searchTerm === '') {
                    clearSearchBtn.style.display = 'none';
                } else {
                    clearSearchBtn.style.display = '';
                }
            });

            // Clear search input
            clearSearchBtn.addEventListener('click', function() {
                colorSearchInput.value = '';
                colorSearchInput.dispatchEvent(new Event('input'));
                colorSearchInput.focus();
            });

            // Khởi tạo trạng thái nút clear
            clearSearchBtn.style.display = 'none';

            // Chọn hoặc bỏ chọn tất cả màu
            document.getElementById('selectAllColors').addEventListener('click', function() {
                colorItems.forEach(item => {
                    if (item.style.display !== 'none') {
                        const checkbox = item.querySelector('.color-checkbox');
                        checkbox.checked = true;
                    }
                });
                updateColorImagesContainer();
                updateSelectedColorsCount();
            });

            document.getElementById('deselectAllColors').addEventListener('click', function() {
                document.querySelectorAll('.color-checkbox').forEach(checkbox => {
                    checkbox.checked = false;
                });
                updateColorImagesContainer();
                updateSelectedColorsCount();
            });

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
                
                // Xoá tất cả và thay thế bằng mục mới
                colorImagesContainer.innerHTML = '';
                
                // Tạo các mục mới cho từng màu đã chọn
                selectedColors.forEach(color => {
                    const colorHtml = `
                        <div class="col-lg-6 col-md-12 mb-3 color-image-item" data-color-id="${color.id}">
                            <div class="card h-100">
                                <div class="card-header bg-white">
                                    <div class="d-flex align-items-center">
                                        <div class="color-swatch me-2" style="background-color: ${color.code};"></div>
                                        <h6 class="mb-0">${color.name}</h6>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-7">
                                            <div class="mb-3">
                                                <label class="form-label">Tải lên hình ảnh</label>
                                                <input type="hidden" name="color_image_id[]" value="${color.id}">
                                                <input type="file" name="color_image[]" class="form-control" accept="image/*" 
                                                       id="color-file-${color.id}" 
                                                       onchange="previewColorImage(this, ${color.id})">
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
                            </div>
                        </div>
                    `;
                    
                    colorImagesContainer.insertAdjacentHTML('afterend', colorHtml);
                });
                
                // Cập nhật sự kiện cho các mục mới
                document.querySelectorAll('.color-image-item input[type="file"]').forEach(input => {
                    input.addEventListener('change', function() {
                        const colorId = this.closest('.color-image-item').dataset.colorId;
                        previewColorImage(this, colorId);
                    });
                });
            }
            
            // Hàm xem trước hình ảnh khi chọn file
            window.previewColorImage = function(input, colorId) {
                const colorItem = input.closest('.color-image-item');
                const previewContainer = colorItem.querySelector('.color-image-preview');
                
                if (input.files && input.files[0]) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        // Xóa placeholder nếu có
                        previewContainer.innerHTML = '';
                        
                        // Tạo hình ảnh preview
                        const img = document.createElement('img');
                        img.className = 'img-thumbnail color-preview';
                        img.dataset.colorId = colorId;
                        img.src = e.target.result;
                        img.alt = document.querySelector(`label[for="color-${colorId}"] .color-name`).textContent;
                        
                        previewContainer.appendChild(img);
                        
                        // Cập nhật thông báo
                        const statusText = colorItem.querySelector('.small.text-muted');
                        statusText.innerHTML = `<i class="bi bi-check-circle-fill text-success me-1"></i> 
                                                Hình ảnh đã được chọn, nhấn "Thêm sản phẩm" để lưu.`;
                    };
                    
                    reader.readAsDataURL(input.files[0]);
                }
            };
            
            // Cập nhật số lượng màu đã chọn
            function updateSelectedColorsCount() {
                const selectedCount = document.querySelectorAll('.color-checkbox:checked').length;
                const colorInfo = document.querySelector('.color-info');
                if (colorInfo) {
                    colorInfo.innerHTML = `Đã chọn <span class="fw-bold">${selectedCount}</span> màu sắc cho sản phẩm này.`;
                }
                
                const selectedColorsCount = document.getElementById('selected-colors-count');
                if (selectedColorsCount) {
                    selectedColorsCount.textContent = `${selectedCount} màu được chọn`;
                }
                
                // Hiện/ẩn thông báo không có màu nào được chọn
                const noColorsSelected = document.getElementById('no-colors-selected');
                if (selectedCount === 0) {
                    noColorsSelected.classList.remove('d-none');
                } else {
                    noColorsSelected.classList.add('d-none');
                }
            }
            
            // Xử lý sự kiện thay đổi cho các checkbox màu sắc
            colorCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    updateColorImagesContainer();
                    updateSelectedColorsCount();
                });
            });
            
            // Khởi tạo ban đầu
            updateColorImagesContainer();
            updateSelectedColorsCount();
            
            // Color picker functionality
            const colorPicker = document.getElementById('colorPicker');
            const colorCode = document.getElementById('colorCode');
            const colorPreview = document.getElementById('colorPreview');
            const saveColorBtn = document.getElementById('saveColorBtn');
            
            // Sync color picker and color code input
            colorPicker.addEventListener('input', function() {
                colorCode.value = this.value;
                colorPreview.style.backgroundColor = this.value;
            });
            
            colorCode.addEventListener('input', function() {
                try {
                    colorPicker.value = this.value;
                    colorPreview.style.backgroundColor = this.value;
                } catch (e) {
                    // Invalid color code
                }
            });
            
            // Save new color
            saveColorBtn.addEventListener('click', function() {
                const colorName = document.getElementById('colorName').value.trim();
                const colorCodeValue = colorCode.value.trim();
                const errorAlert = document.getElementById('colorModalAlert') || document.createElement('div');
                
                if (!colorName) {
                    errorAlert.className = 'alert alert-danger';
                    errorAlert.textContent = 'Vui lòng nhập tên màu!';
                    return;
                }
                
                // Hiển thị spinner loading
                const spinner = document.getElementById('colorSaveSpinner');
                spinner.classList.remove('d-none');
                saveColorBtn.disabled = true;
                saveColorBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Đang lưu...';
                
                // Save color via AJAX
                fetch('./ajax/add_color.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `name=${encodeURIComponent(colorName)}&code=${encodeURIComponent(colorCodeValue)}`
                })
                .then(response => response.json())
                .then(data => {
                    spinner.classList.add('d-none');
                    saveColorBtn.disabled = false;
                    saveColorBtn.innerHTML = 'Lưu màu mới';
                    
                    if (data.success) {
                        // Add new color to the list
                        const colorContainer = document.querySelector('#colorContainer');
                        const newColorHtml = `
                            <div class="col-lg-3 col-md-4 col-sm-6 col-6 color-item-wrapper mb-2" 
                                 data-color-name="${colorName.toLowerCase()}">
                                <input type="checkbox" 
                                       class="color-checkbox" 
                                       id="color-${data.id}" 
                                       name="colors[]" 
                                       value="${data.id}"
                                       data-color-name="${colorName}"
                                       data-color-code="${colorCodeValue}"
                                       checked>
                                <label for="color-${data.id}">
                                    <div class="color-item position-relative">
                                        <div class="color-swatch" style="background-color: ${colorCodeValue};"></div>
                                        <div class="color-name">${colorName}</div>
                                    </div>
                                </label>
                            </div>
                        `;
                        colorContainer.insertAdjacentHTML('afterbegin', newColorHtml);
                        
                        // Add event listener to new checkbox
                        const newCheckbox = document.querySelector(`#color-${data.id}`);
                        newCheckbox.addEventListener('change', function() {
                            updateColorImagesContainer();
                            updateSelectedColorsCount();
                        });
                        
                        // Close modal and reset form
                        const addColorModal = bootstrap.Modal.getInstance(document.getElementById('addColorModal'));
                        addColorModal.hide();
                        document.getElementById('colorName').value = '';
                        colorCode.value = '#ffffff';
                        colorPicker.value = '#ffffff';
                        colorPreview.style.backgroundColor = '#ffffff';
                        
                        // Update the color image container
                        updateColorImagesContainer();
                        updateSelectedColorsCount();
                    } else {
                        // Display error
                        errorAlert.className = 'alert alert-danger';
                        errorAlert.textContent = data.message || 'Lỗi khi thêm màu mới';
                        document.querySelector('.modal-body').insertAdjacentElement('afterbegin', errorAlert);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    spinner.classList.add('d-none');
                    saveColorBtn.disabled = false;
                    saveColorBtn.innerHTML = 'Lưu màu mới';
                    
                    errorAlert.className = 'alert alert-danger';
                    errorAlert.textContent = 'Lỗi khi kết nối đến máy chủ';
                    document.querySelector('.modal-body').insertAdjacentElement('afterbegin', errorAlert);
                });
            });
        });
    </script>
</body>
</html>