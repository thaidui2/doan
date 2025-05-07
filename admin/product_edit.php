<?php
// Kiểm tra đăng nhập và quyền admin
session_start();
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_loai']) || $_SESSION['admin_loai'] < 1) {
    header('Location: login.php');
    exit();
}

// Kết nối database
require_once '../config/config.php';

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

// Kiểm tra ID sản phẩm
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: products.php?error=' . urlencode('ID sản phẩm không hợp lệ'));
    exit();
}

$product_id = intval($_GET['id']);

// Lấy thông tin sản phẩm
$product_sql = "SELECT * FROM sanpham WHERE id = ?";
$product_stmt = $conn->prepare($product_sql);
$product_stmt->bind_param("i", $product_id);
$product_stmt->execute();
$product_result = $product_stmt->get_result();

if ($product_result->num_rows == 0) {
    header('Location: products.php?error=' . urlencode('Không tìm thấy sản phẩm'));
    exit();
}

$product = $product_result->fetch_assoc();

// Lấy danh sách ảnh sản phẩm
$images_sql = "SELECT * FROM sanpham_hinhanh WHERE id_sanpham = ? ORDER BY la_anh_chinh DESC";
$images_stmt = $conn->prepare($images_sql);
$images_stmt->bind_param("i", $product_id);
$images_stmt->execute();
$images_result = $images_stmt->get_result();
$product_images = $images_result->fetch_all(MYSQLI_ASSOC);

// Lấy biến thể sản phẩm
$variants_sql = "SELECT sb.*, tt_mau.ten as ten_mau, tt_mau.ma_mau, tt_size.ten as ten_size 
                FROM sanpham_bien_the sb
                JOIN thuoc_tinh tt_mau ON sb.id_mau = tt_mau.id
                JOIN thuoc_tinh tt_size ON sb.id_size = tt_size.id
                WHERE sb.id_sanpham = ?";
$variants_stmt = $conn->prepare($variants_sql);
$variants_stmt->bind_param("i", $product_id);
$variants_stmt->execute();
$variants_result = $variants_stmt->get_result();
$product_variants = $variants_result->fetch_all(MYSQLI_ASSOC);

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

// Gán dữ liệu sản phẩm vào form
$form_data = [
    'tensanpham' => $product['tensanpham'],
    'id_danhmuc' => $product['id_danhmuc'],
    'thuonghieu' => $product['thuonghieu'],
    'gia' => $product['gia'],
    'giagoc' => $product['giagoc'],
    'mota' => $product['mota'],
    'mota_ngan' => $product['mota_ngan'],
    'noibat' => $product['noibat'],
    'trangthai' => $product['trangthai']
];

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
                $variant_id = isset($_POST['variant_id'][$i]) ? $_POST['variant_id'][$i] : null;
                
                if (!empty($size_id) && !empty($color_id) && is_numeric($quantity) && $quantity >= 0) {
                    $variants[] = [
                        'id' => $variant_id,
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
            // Tạo slug từ tên sản phẩm nếu tên đã thay đổi
            if ($form_data['tensanpham'] !== $product['tensanpham']) {
                $slug = create_slug($form_data['tensanpham']);
                
                // Kiểm tra slug đã tồn tại chưa (trừ sản phẩm hiện tại)
                $check_slug_sql = "SELECT id FROM sanpham WHERE slug = ? AND id != ?";
                $check_slug_stmt = $conn->prepare($check_slug_sql);
                $check_slug_stmt->bind_param("si", $slug, $product_id);
                $check_slug_stmt->execute();
                $slug_result = $check_slug_stmt->get_result();
                
                // Nếu slug đã tồn tại, thêm suffix
                if ($slug_result->num_rows > 0) {
                    $slug = $slug . '-' . time();
                }
            } else {
                $slug = $product['slug'];
            }
            
            // Upload ảnh chính nếu có cập nhật
            $main_image = $product['hinhanh'];
            if (isset($_FILES['hinhanh']) && $_FILES['hinhanh']['error'] === 0) {
                $upload_dir = '../uploads/products/';
                
                // Tạo thư mục nếu chưa tồn tại
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_name = time() . '_' . basename($_FILES['hinhanh']['name']);
                $file_path = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['hinhanh']['tmp_name'], $file_path)) {
                    // Xóa ảnh cũ nếu có
                    if (!empty($main_image) && file_exists('../' . $main_image)) {
                        @unlink('../' . $main_image);
                    }
                    
                    $main_image = 'uploads/products/' . $file_name;
                    
                    // Cập nhật ảnh chính trong bảng sanpham_hinhanh
                    // Xóa ảnh chính cũ trong bảng sanpham_hinhanh
                    $delete_main_img_sql = "DELETE FROM sanpham_hinhanh WHERE id_sanpham = ? AND la_anh_chinh = 1";
                    $delete_main_img_stmt = $conn->prepare($delete_main_img_sql);
                    $delete_main_img_stmt->bind_param("i", $product_id);
                    $delete_main_img_stmt->execute();
                    
                    // Thêm ảnh chính mới vào bảng sanpham_hinhanh
                    $insert_img_sql = "INSERT INTO sanpham_hinhanh (id_sanpham, hinhanh, la_anh_chinh) VALUES (?, ?, 1)";
                    $insert_img_stmt = $conn->prepare($insert_img_sql);
                    $insert_img_stmt->bind_param("is", $product_id, $main_image);
                    $insert_img_stmt->execute();
                }
            }
            
            // Cập nhật sản phẩm
            $update_sql = "UPDATE sanpham SET 
                          tensanpham = ?, 
                          slug = ?, 
                          id_danhmuc = ?, 
                          gia = ?, 
                          giagoc = ?, 
                          hinhanh = ?, 
                          mota = ?, 
                          mota_ngan = ?, 
                          noibat = ?, 
                          trangthai = ?,
                          thuonghieu = ?,
                          ngay_capnhat = NOW()
                          WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ssiiisssiiis", 
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
                $form_data['thuonghieu'],
                $product_id
            );
            $update_stmt->execute();
            
            // Upload và thêm ảnh phụ nếu có
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
            
            // Xóa ảnh cũ nếu được chọn xóa
            if (isset($_POST['delete_images']) && is_array($_POST['delete_images'])) {
                foreach ($_POST['delete_images'] as $image_id) {
                    // Lấy thông tin ảnh trước khi xóa
                    $get_image_sql = "SELECT hinhanh FROM sanpham_hinhanh WHERE id = ? AND id_sanpham = ?";
                    $get_image_stmt = $conn->prepare($get_image_sql);
                    $get_image_stmt->bind_param("ii", $image_id, $product_id);
                    $get_image_stmt->execute();
                    $image_result = $get_image_stmt->get_result();
                    
                    if ($image_result->num_rows > 0) {
                        $image_data = $image_result->fetch_assoc();
                        $image_path = $image_data['hinhanh'];
                        
                        // Xóa file ảnh nếu tồn tại
                        if (!empty($image_path) && file_exists('../' . $image_path)) {
                            @unlink('../' . $image_path);
                        }
                        
                        // Xóa record trong DB
                        $delete_image_sql = "DELETE FROM sanpham_hinhanh WHERE id = ? AND id_sanpham = ?";
                        $delete_image_stmt = $conn->prepare($delete_image_sql);
                        $delete_image_stmt->bind_param("ii", $image_id, $product_id);
                        $delete_image_stmt->execute();
                    }
                }
            }
            
            // Cập nhật biến thể sản phẩm
            // 1. Lấy danh sách ID biến thể hiện có
            $existing_variants = [];
            $get_variants_sql = "SELECT id FROM sanpham_bien_the WHERE id_sanpham = ?";
            $get_variants_stmt = $conn->prepare($get_variants_sql);
            $get_variants_stmt->bind_param("i", $product_id);
            $get_variants_stmt->execute();
            $variants_result = $get_variants_stmt->get_result();
            
            while ($row = $variants_result->fetch_assoc()) {
                $existing_variants[] = $row['id'];
            }
            
            // 2. Xử lý từng biến thể từ form
            $variant_sql_insert = "INSERT INTO sanpham_bien_the (id_sanpham, id_mau, id_size, so_luong) VALUES (?, ?, ?, ?)";
            $variant_stmt_insert = $conn->prepare($variant_sql_insert);
            
            $variant_sql_update = "UPDATE sanpham_bien_the SET id_mau = ?, id_size = ?, so_luong = ? WHERE id = ? AND id_sanpham = ?";
            $variant_stmt_update = $conn->prepare($variant_sql_update);
            
            $updated_variants = [];
            $total_stock = 0;
            
            foreach ($variants as $variant) {
                if (!empty($variant['id'])) {
                    // Cập nhật biến thể hiện có
                    $variant_stmt_update->bind_param("iiiii", $variant['color_id'], $variant['size_id'], $variant['quantity'], $variant['id'], $product_id);
                    $variant_stmt_update->execute();
                    $updated_variants[] = $variant['id'];
                } else {
                    // Thêm biến thể mới
                    $variant_stmt_insert->bind_param("iiii", $product_id, $variant['color_id'], $variant['size_id'], $variant['quantity']);
                    $variant_stmt_insert->execute();
                    $updated_variants[] = $conn->insert_id;
                }
                
                $total_stock += $variant['quantity'];
            }
            
            // 3. Xóa các biến thể không còn trong danh sách cập nhật
            $variants_to_delete = array_diff($existing_variants, $updated_variants);
            
            if (!empty($variants_to_delete)) {
                $delete_variants_sql = "DELETE FROM sanpham_bien_the WHERE id IN (" . implode(',', $variants_to_delete) . ") AND id_sanpham = ?";
                $delete_variants_stmt = $conn->prepare($delete_variants_sql);
                $delete_variants_stmt->bind_param("i", $product_id);
                $delete_variants_stmt->execute();
            }
            
            // Cập nhật tổng số lượng trong bảng sanpham
            $update_stock_sql = "UPDATE sanpham SET so_luong = ? WHERE id = ?";
            $update_stock_stmt = $conn->prepare($update_stock_sql);
            $update_stock_stmt->bind_param("ii", $total_stock, $product_id);
            $update_stock_stmt->execute();
            
            // Ghi log hoạt động
            $log_sql = "INSERT INTO nhat_ky (id_user, hanh_dong, doi_tuong_loai, doi_tuong_id, chi_tiet, ip_address) 
                      VALUES (?, 'update', 'product', ?, ?, ?)";
            $log_stmt = $conn->prepare($log_sql);
            $admin_id = $_SESSION['admin_id'];
            $detail = "Chỉnh sửa sản phẩm: " . $form_data['tensanpham'] . " (ID: " . $product_id . ")";
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $log_stmt->bind_param("iiss", $admin_id, $product_id, $detail, $ip);
            $log_stmt->execute();
            
            // Commit transaction
            $conn->commit();
            
            // Thành công
            $success = "Cập nhật sản phẩm thành công!";
            
            // Cập nhật lại thông tin sản phẩm sau khi cập nhật
            $product_stmt->execute();
            $product_result = $product_stmt->get_result();
            $product = $product_result->fetch_assoc();
            
            $form_data = [
                'tensanpham' => $product['tensanpham'],
                'id_danhmuc' => $product['id_danhmuc'],
                'thuonghieu' => $product['thuonghieu'],
                'gia' => $product['gia'],
                'giagoc' => $product['giagoc'],
                'mota' => $product['mota'],
                'mota_ngan' => $product['mota_ngan'],
                'noibat' => $product['noibat'],
                'trangthai' => $product['trangthai']
            ];
            
            // Lấy lại danh sách ảnh
            $images_stmt->execute();
            $images_result = $images_stmt->get_result();
            $product_images = $images_result->fetch_all(MYSQLI_ASSOC);
            
            // Lấy lại danh sách biến thể
            $variants_stmt->execute();
            $variants_result = $variants_stmt->get_result();
            $product_variants = $variants_result->fetch_all(MYSQLI_ASSOC);
            
        } catch (Exception $e) {
            // Rollback transaction nếu có lỗi
            $conn->rollback();
            $error = "Lỗi khi cập nhật sản phẩm: " . $e->getMessage();
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

// Thiết lập tiêu đề trang và định nghĩa CSS/JS
$page_title = 'Chỉnh sửa sản phẩm';
$current_page = 'products';

// CSS riêng cho trang này
$page_css = ['css/product_edit.css'];

// Javascript riêng cho trang này
$page_js = ['js/product.js'];

// Thêm CSS và JS cho Summernote
$head_custom = '
<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.js"></script>
';

// Include header and sidebar
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chỉnh sửa sản phẩm - Bug Shop Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/product.css">
</head>
<body>
        
        <!-- Main Content -->
        <div class="col-md-10 col-lg-10 ms-auto">
            <div class="container-fluid py-4 px-4">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0 text-gray-800">Chỉnh sửa sản phẩm</h1>
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
                                <h6 class="m-0 font-weight-bold text-primary">Thông tin sản phẩm (#<?php echo $product_id; ?>)</h6>
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
                                                        <?php 
                                                        // Reset pointer về đầu để dùng lại
                                                        $categories->data_seek(0);
                                                        while ($category = $categories->fetch_assoc()): 
                                                        ?>
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
                                                        <?php 
                                                        // Reset pointer về đầu để dùng lại
                                                        $brands->data_seek(0);
                                                        while ($brand = $brands->fetch_assoc()): 
                                                        ?>
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
                                                <label for="hinhanh" class="form-label">Ảnh chính</label>
                                                <div class="image-upload-container" id="mainImageContainer">
                                                    <?php if (!empty($product['hinhanh'])): ?>
                                                    <img id="mainImagePreview" class="image-preview mb-2" 
                                                         src="<?php echo '../' . htmlspecialchars($product['hinhanh']); ?>" alt="Ảnh sản phẩm">
                                                    <div id="mainImagePlaceholder" class="d-none">
                                                    <?php else: ?>
                                                    <img id="mainImagePreview" class="image-preview mb-2 d-none" src="" alt="Ảnh sản phẩm">
                                                    <div id="mainImagePlaceholder">
                                                    <?php endif; ?>
                                                        <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-2"></i>
                                                        <p class="mb-0">Click để chọn hình ảnh chính mới</p>
                                                        <small class="text-muted">JPG, PNG, GIF (max 5MB)</small>
                                                    </div>
                                                    <input type="file" class="d-none" id="hinhanh" name="hinhanh" accept="image/*">
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
                                    
                                    <!-- Hình ảnh hiện có -->
                                    <div class="row mb-4">
                                        <div class="col-12">
                                            <h5 class="border-bottom pb-2 mb-3">Hình ảnh sản phẩm</h5>
                                            <div class="mb-3">
                                                <label class="form-label">Hình ảnh hiện tại</label>
                                                <div class="d-flex flex-wrap mt-2">
                                                    <?php foreach($product_images as $image): ?>
                                                        <div class="product-image-container <?php echo $image['la_anh_chinh'] ? 'main-image' : ''; ?> me-2 mb-2">
                                                            <img src="<?php echo '../' . htmlspecialchars($image['hinhanh']); ?>" 
                                                                 alt="Ảnh sản phẩm" 
                                                                 class="image-preview">
                                                            <?php if (!$image['la_anh_chinh']): ?>
                                                                <span class="delete-image" data-image-id="<?php echo $image['id']; ?>">
                                                                    <i class="fas fa-times"></i>
                                                                </span>
                                                                <input type="checkbox" name="delete_images[]" 
                                                                       value="<?php echo $image['id']; ?>" 
                                                                       class="d-none delete-image-checkbox">
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                    
                                                    <?php if (count($product_images) == 0): ?>
                                                        <p class="text-muted fst-italic">Không có hình ảnh nào.</p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Thêm hình ảnh bổ sung</label>
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
                                            <p class="text-muted">Quản lý các biến thể kích thước và màu sắc cho sản phẩm</p>
                                            
                                            <div id="variants-container">
                                                <!-- Hiển thị biến thể hiện có -->
                                                <?php foreach ($product_variants as $variant): ?>
                                                <div class="variant-row" data-variant-id="<?php echo $variant['id']; ?>">
                                                    <input type="hidden" name="variant_id[]" value="<?php echo $variant['id']; ?>">
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
                                                                        <option value="<?php echo $size['id']; ?>" <?php echo ($variant['id_size'] == $size['id']) ? 'selected' : ''; ?>>
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
                                                                        <option value="<?php echo $color['id']; ?>" <?php echo ($variant['id_mau'] == $color['id']) ? 'selected' : ''; ?> data-color="<?php echo htmlspecialchars($color['ma_mau']); ?>">
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
                                                                <input type="number" class="form-control variant-quantity" name="variant_quantity[]" min="0" value="<?php echo $variant['so_luong']; ?>" required>
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
                                                <?php endforeach; ?>
                                                
                                                <?php if (count($product_variants) == 0): ?>
                                                    <!-- Thêm một biến thể mặc định nếu không có -->
                                                    <div class="variant-row">
                                                        <input type="hidden" name="variant_id[]" value="">
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
                                                <?php endif; ?>
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
                                                <i class="fas fa-save me-1"></i> Cập nhật sản phẩm
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
        <input type="hidden" name="variant_id[]" value="">
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
<script src="js/product.js"></script>

</body>
</html>