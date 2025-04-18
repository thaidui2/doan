<?php
// Start output buffering to prevent "headers already sent" errors
ob_start();

// Set page title
$page_title = 'Chỉnh sửa sản phẩm';

include('includes/header.php');

// Check if id is provided in URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "ID sản phẩm không hợp lệ";
    header('Location: products.php');
    exit();
}

$product_id = (int)$_GET['id'];

// Get product info
$query = "SELECT * FROM sanpham WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    $_SESSION['error_message'] = "Không tìm thấy sản phẩm";
    header('Location: products.php');
    exit();
}

// Handle form submission for editing product
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tensanpham = trim($_POST['tensanpham']);
    $id_danhmuc = (int)$_POST['id_danhmuc'];
    $giagoc = (float)$_POST['giagoc'];
    $gia = (float)$_POST['gia'];
    $mota = trim($_POST['mota']);
    $trangthai = isset($_POST['trangthai']) ? (int)$_POST['trangthai'] : 0;
    $noibat = isset($_POST['noibat']) ? 1 : 0;
    $selected_sizes = isset($_POST['sizes']) ? $_POST['sizes'] : [];
    $selected_colors = isset($_POST['colors']) ? $_POST['colors'] : [];
    $brand = isset($_POST['brand']) ? trim($_POST['brand']) : null; // Add brand field

    // Validate
    if (empty($tensanpham)) {
        $error = "Vui lòng nhập tên sản phẩm";
    } elseif ($id_danhmuc <= 0) {
        $error = "Vui lòng chọn danh mục sản phẩm";
    } elseif (empty($selected_sizes) || empty($selected_colors)) {
        $error = "Vui lòng chọn ít nhất một kích thước và một màu sắc";
    } else {
        try {
            // Begin transaction
            $conn->begin_transaction();
            
            // Generate slug from product name
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $tensanpham)));
            
            // Check if we need to upload a new main image
            $hinhanh = $product['hinhanh'];
            
            if (isset($_FILES['hinh_anh']) && $_FILES['hinh_anh']['error'] == 0) {
                $target_dir = "../uploads/products/";
                
                // Ensure directory exists
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }
                
                $filename = time() . '_' . basename($_FILES['hinh_anh']['name']);
                $target_file = $target_dir . $filename;
                
                // Upload file
                if (move_uploaded_file($_FILES['hinh_anh']['tmp_name'], $target_file)) {
                    // Delete old image if it exists and is different
                    if (!empty($product['hinhanh']) && file_exists("../" . $product['hinhanh'])) {
                        unlink("../" . $product['hinhanh']);
                    }
                    $hinhanh = "uploads/products/" . $filename;
                }
            }
            
            // Update product
            $update_query = "UPDATE sanpham SET 
                            tensanpham = ?, 
                            slug = ?,
                            id_danhmuc = ?, 
                            gia = ?, 
                            giagoc = ?, 
                            hinhanh = ?, 
                            mota = ?, 
                            noibat = ?, 
                            trangthai = ?, 
                            ngay_capnhat = NOW(),
                            thuonghieu = ?
                          WHERE id = ?";
                          
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("ssiidssiisi", $tensanpham, $slug, $id_danhmuc, $gia, $giagoc, $hinhanh, $mota, $noibat, $trangthai, $brand, $product_id);
            $update_stmt->execute();
            
            // Handle product variants
            
            // First, get existing variants to compare
            $existing_variants = [];
            $variants_query = $conn->prepare("SELECT id, id_size, id_mau FROM sanpham_bien_the WHERE id_sanpham = ?");
            $variants_query->bind_param("i", $product_id);
            $variants_query->execute();
            $variants_result = $variants_query->get_result();
            
            while ($variant = $variants_result->fetch_assoc()) {
                $key = $variant['id_size'] . '_' . $variant['id_mau'];
                $existing_variants[$key] = $variant['id'];
            }
            
            // Process variants from the form
            $total_quantity = 0;
            
            foreach ($selected_sizes as $size_id) {
                foreach ($selected_colors as $color_id) {
                    $variant_key = $size_id . '_' . $color_id;
                    $quantity = 0;
                    
                    if (isset($_POST['inventory']) && 
                        is_array($_POST['inventory']) && 
                        isset($_POST['inventory'][$size_id][$color_id])) {
                        $quantity = (int)$_POST['inventory'][$size_id][$color_id];
                    }
                    
                    $total_quantity += $quantity;
                    
                    // Check if this variant already exists
                    if (isset($existing_variants[$variant_key])) {
                        // Update existing variant
                        $variant_id = $existing_variants[$variant_key];
                        $update_variant = $conn->prepare("UPDATE sanpham_bien_the SET so_luong = ? WHERE id = ?");
                        $update_variant->bind_param("ii", $quantity, $variant_id);
                        $update_variant->execute();
                        
                        // Remove from existing_variants array
                        unset($existing_variants[$variant_key]);
                    } else {
                        // Create new variant
                        $insert_variant = $conn->prepare("INSERT INTO sanpham_bien_the (id_sanpham, id_size, id_mau, so_luong) VALUES (?, ?, ?, ?)");
                        $insert_variant->bind_param("iiii", $product_id, $size_id, $color_id, $quantity);
                        $insert_variant->execute();
                    }
                }
            }
            
            // Delete variants that weren't in the form
            if (!empty($existing_variants)) {
                $variant_ids_to_delete = array_values($existing_variants);
                
                foreach ($variant_ids_to_delete as $vid) {
                    $delete_variant = $conn->prepare("DELETE FROM sanpham_bien_the WHERE id = ?");
                    $delete_variant->bind_param("i", $vid);
                    $delete_variant->execute();
                }
            }
            
            // Update total quantity in product
            $update_total = $conn->prepare("UPDATE sanpham SET so_luong = ? WHERE id = ?");
            $update_total->bind_param("ii", $total_quantity, $product_id);
            $update_total->execute();
            
            // Handle additional images
            if (isset($_FILES['hinhanh_phu']) && !empty($_FILES['hinhanh_phu']['name'][0])) {
                $target_dir = "../uploads/products/";
                
                // Ensure directory exists
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }
                
                $file_count = count($_FILES['hinhanh_phu']['name']);
                $file_count = min($file_count, 5); // Maximum 5 additional images
                
                for ($i = 0; $i < $file_count; $i++) {
                    if ($_FILES['hinhanh_phu']['error'][$i] === 0) {
                        $filename = time() . '_' . basename($_FILES['hinhanh_phu']['name'][$i]);
                        $target_file = $target_dir . $filename;
                        
                        if (move_uploaded_file($_FILES['hinhanh_phu']['tmp_name'][$i], $target_file)) {
                            // Add image to database
                            $is_main = 0; // Not main image
                            $relative_path = "uploads/products/" . $filename;
                            $insert_img = $conn->prepare("INSERT INTO sanpham_hinhanh (id_sanpham, hinhanh, la_anh_chinh) VALUES (?, ?, ?)");
                            $insert_img->bind_param("isi", $product_id, $relative_path, $is_main);
                            $insert_img->execute();
                        }
                    }
                }
            }
            
            // Add/update main image in sanpham_hinhanh if it exists
            if (!empty($hinhanh)) {
                // Check if main image already exists
                $check_main = $conn->prepare("SELECT id FROM sanpham_hinhanh WHERE id_sanpham = ? AND la_anh_chinh = 1");
                $check_main->bind_param("i", $product_id);
                $check_main->execute();
                $main_result = $check_main->get_result();
                
                if ($main_result->num_rows > 0) {
                    // Update existing main image
                    $main_id = $main_result->fetch_assoc()['id'];
                    $update_main = $conn->prepare("UPDATE sanpham_hinhanh SET hinhanh = ? WHERE id = ?");
                    $update_main->bind_param("si", $hinhanh, $main_id);
                    $update_main->execute();
                } else {
                    // Insert new main image
                    $is_main = 1;
                    $insert_main = $conn->prepare("INSERT INTO sanpham_hinhanh (id_sanpham, hinhanh, la_anh_chinh) VALUES (?, ?, ?)");
                    $insert_main->bind_param("isi", $product_id, $hinhanh, $is_main);
                    $insert_main->execute();
                }
            }
            
            // Handle deleted additional images
            if (isset($_POST['delete_images']) && !empty($_POST['delete_images'])) {
                $deleted_images = $_POST['delete_images'];
                
                foreach ($deleted_images as $img_id) {
                    // Get image path before deleting
                    $img_query = $conn->prepare("SELECT hinhanh FROM sanpham_hinhanh WHERE id = ?");
                    $img_query->bind_param("i", $img_id);
                    $img_query->execute();
                    $img_result = $img_query->get_result();
                    
                    if ($img_result->num_rows > 0) {
                        $img_path = $img_result->fetch_assoc()['hinhanh'];
                        
                        // Delete from database
                        $delete_img = $conn->prepare("DELETE FROM sanpham_hinhanh WHERE id = ?");
                        $delete_img->bind_param("i", $img_id);
                        $delete_img->execute();
                        
                        // Delete file if exists
                        if (!empty($img_path) && file_exists("../" . $img_path)) {
                            unlink("../" . $img_path);
                        }
                    }
                }
            }
            
            // Log admin action
            $admin_id = $_SESSION['admin_id'];
            $details = "Chỉnh sửa sản phẩm: $tensanpham (ID: $product_id)";
            $ip = $_SERVER['REMOTE_ADDR'];
            
            $log_query = $conn->prepare("INSERT INTO nhat_ky (id_user, hanh_dong, doi_tuong_loai, doi_tuong_id, chi_tiet, ip_address) VALUES (?, 'update', 'product', ?, ?, ?)");
            $log_query->bind_param("iiss", $admin_id, $product_id, $details, $ip);
            $log_query->execute();
            
            // Commit transaction
            $conn->commit();
            
            // Set success message and redirect
            $_SESSION['success_message'] = "Cập nhật sản phẩm thành công";
            header("Location: products.php");
            exit();
            
        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            $error = "Lỗi: " . $e->getMessage();
        }
    }
}

// Get categories
$categories = $conn->query("SELECT * FROM danhmuc WHERE trang_thai = 1 ORDER BY ten");

// Get sizes
$sizes = $conn->query("SELECT id, gia_tri as ten_kichthuoc FROM thuoc_tinh WHERE loai = 'size' ORDER BY gia_tri");

// Get colors
$colors = $conn->query("SELECT id, gia_tri as ten_mau, ma_mau FROM thuoc_tinh WHERE loai = 'color' ORDER BY gia_tri");

// Get existing product variants
$product_variants = [];
$variants_query = $conn->prepare("SELECT * FROM sanpham_bien_the WHERE id_sanpham = ?");
$variants_query->bind_param("i", $product_id);
$variants_query->execute();
$variants_result = $variants_query->get_result();

while ($variant = $variants_result->fetch_assoc()) {
    $key = $variant['id_size'] . '_' . $variant['id_mau'];
    $product_variants[$key] = $variant;
}

// Get selected sizes and colors
$selected_sizes = [];
$selected_colors = [];

foreach ($product_variants as $variant) {
    if (!in_array($variant['id_size'], $selected_sizes)) {
        $selected_sizes[] = $variant['id_size'];
    }
    if (!in_array($variant['id_mau'], $selected_colors)) {
        $selected_colors[] = $variant['id_mau'];
    }
}

// Get product images
$images_query = $conn->prepare("SELECT * FROM sanpham_hinhanh WHERE id_sanpham = ? ORDER BY la_anh_chinh DESC");
$images_query->bind_param("i", $product_id);
$images_query->execute();
$images_result = $images_query->get_result();
$product_images = [];

while ($img = $images_result->fetch_assoc()) {
    $product_images[] = $img;
}
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

    <?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo $error; ?>
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
                        </div>

                        <div class="mb-3">
                            <label for="id_danhmuc" class="form-label required-field">Danh mục</label>
                            <select class="form-select" id="id_danhmuc" name="id_danhmuc" required>
                                <option value="">-- Chọn danh mục --</option>
                                <?php while($category = $categories->fetch_assoc()): ?>
                                <option value="<?php echo $category['id']; ?>" <?php echo ($product['id_danhmuc'] == $category['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['ten']); ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="giagoc" class="form-label required-field">Giá gốc</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="giagoc" name="giagoc" value="<?php echo $product['giagoc']; ?>" min="1000" required>
                                        <span class="input-group-text">VNĐ</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="gia" class="form-label required-field">Giá bán thực tế</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="gia" name="gia" value="<?php echo $product['gia']; ?>" min="1000" required>
                                        <span class="input-group-text">VNĐ</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="brand" class="form-label">Thương hiệu</label>
                            <input type="text" class="form-control" id="brand" name="brand" value="<?php echo htmlspecialchars($product['thuonghieu']); ?>">
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="hinh_anh" class="form-label">Hình ảnh chính</label>
                            
                            <div id="image-preview" class="mb-2">
                                <?php if (!empty($product['hinhanh'])): ?>
                                <img src="../<?php echo $product['hinhanh']; ?>" alt="<?php echo htmlspecialchars($product['tensanpham']); ?>">
                                <?php else: ?>
                                <span class="text-muted">Chưa có hình ảnh</span>
                                <?php endif; ?>
                            </div>
                            
                            <input type="file" class="form-control" id="hinh_anh" name="hinh_anh" accept="image/*">
                            <div class="form-text">Để trống nếu không muốn thay đổi hình ảnh</div>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="mota" class="form-label">Mô tả sản phẩm</label>
                    <textarea class="form-control" id="mota" name="mota" rows="5"><?php echo htmlspecialchars($product['mota']); ?></textarea>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">Biến thể sản phẩm</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Kích thước <span class="text-danger">*</span></label>
                        <div class="size-checkbox-container">
                            <?php while ($size = $sizes->fetch_assoc()): ?>
                            <div class="form-check">
                                <input class="form-check-input size-checkbox" type="checkbox" name="sizes[]" 
                                       value="<?php echo $size['id']; ?>" 
                                       id="size_<?php echo $size['id']; ?>"
                                       <?php echo in_array($size['id'], $selected_sizes) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="size_<?php echo $size['id']; ?>">
                                    <?php echo htmlspecialchars($size['ten_kichthuoc']); ?>
                                </label>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Màu sắc <span class="text-danger">*</span></label>
                        <div class="color-checkbox-container">
                            <?php while ($color = $colors->fetch_assoc()): ?>
                            <div class="col-md-4 col-6 mb-2">
                                <input class="form-check-input color-checkbox" type="checkbox" name="colors[]" 
                                       value="<?php echo $color['id']; ?>" 
                                       id="color_<?php echo $color['id']; ?>"
                                       <?php echo in_array($color['id'], $selected_colors) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="color_<?php echo $color['id']; ?>">
                                    <div class="color-item">
                                        <span class="color-swatch" style="background-color: <?php echo $color['ma_mau']; ?>"></span>
                                        <span class="color-name"><?php echo htmlspecialchars($color['ten_mau']); ?></span>
                                    </div>
                                </label>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">Quản lý tồn kho</h5>
            </div>
            <div class="card-body">
                <div class="mb-3" id="noVariantsMessage" style="display: none;">
                    <div class="alert alert-info">
                        Vui lòng chọn ít nhất một kích thước và một màu sắc để quản lý tồn kho.
                    </div>
                </div>
                
                <div id="inventoryTable">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Kích thước</th>
                                <th>Màu sắc</th>
                                <th>Số lượng tồn</th>
                            </tr>
                        </thead>
                        <tbody id="inventoryTableBody">
                            <!-- Will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Hình ảnh bổ sung</h5>
                <button type="button" class="btn btn-sm btn-outline-primary" id="add-more-images">
                    <i class="bi bi-plus"></i> Thêm ảnh
                </button>
            </div>
            <div class="card-body">
                <div class="row mb-3" id="current-images">
                    <?php foreach ($product_images as $img): ?>
                        <?php if ($img['la_anh_chinh'] == 0): ?>
                        <div class="col-md-2 col-sm-4 col-6 mb-3">
                            <div class="card">
                                <img src="../<?php echo $img['hinhanh']; ?>" class="card-img-top" alt="Product image" style="height: 120px; object-fit: cover;">
                                <div class="card-body p-2 text-center">
                                    <div class="form-check">
                                        <input class="form-check-input delete-image-check" type="checkbox" name="delete_images[]" value="<?php echo $img['id']; ?>" id="img_<?php echo $img['id']; ?>">
                                        <label class="form-check-label" for="img_<?php echo $img['id']; ?>">Xóa ảnh</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <div class="mb-3">
                    <input type="file" class="form-control" id="hinhanh_phu" name="hinhanh_phu[]" accept="image/*" multiple style="display: none;">
                    <div id="new-images-preview" class="d-flex flex-wrap"></div>
                </div>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sizes and colors data for inventory table
    const sizes = <?php 
        $sizes_data = [];
        $sizes->data_seek(0);
        while($s = $sizes->fetch_assoc()) {
            $sizes_data[$s['id']] = $s['ten_kichthuoc'];
        }
        echo json_encode($sizes_data); 
    ?>;
    
    const colors = <?php 
        $colors_data = [];
        $colors->data_seek(0);
        while($c = $colors->fetch_assoc()) {
            $colors_data[$c['id']] = ['name' => $c['ten_mau'], 'color' => $c['ma_mau']];
        }
        echo json_encode($colors_data); 
    ?>;

    const variants = <?php echo json_encode($product_variants); ?>;

    // Preview main image
    const imageInput = document.getElementById('hinh_anh');
    const imagePreview = document.getElementById('image-preview');
    
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
        }
    });
    
    // Handle variants and inventory table
    const sizeCheckboxes = document.querySelectorAll('.size-checkbox');
    const colorCheckboxes = document.querySelectorAll('.color-checkbox');
    const inventoryTable = document.getElementById('inventoryTable');
    const noVariantsMessage = document.getElementById('noVariantsMessage');
    const inventoryTableBody = document.getElementById('inventoryTableBody');
    
    function updateInventoryTable() {
        const selectedSizes = Array.from(document.querySelectorAll('.size-checkbox:checked'))
            .map(cb => parseInt(cb.value));
        const selectedColors = Array.from(document.querySelectorAll('.color-checkbox:checked'))
            .map(cb => parseInt(cb.value));
        
        if (selectedSizes.length > 0 && selectedColors.length > 0) {
            inventoryTable.style.display = 'block';
            noVariantsMessage.style.display = 'none';
            
            // Clear the table body
            inventoryTableBody.innerHTML = '';
            
            // Fill with variants
            selectedSizes.forEach(sizeId => {
                selectedColors.forEach(colorId => {
                    const row = document.createElement('tr');
                    
                    // Get quantity from existing variant, if any
                    let quantity = 0;
                    const variantKey = sizeId + '_' + colorId;
                    
                    if (variants[variantKey]) {
                        quantity = variants[variantKey].so_luong;
                    }
                    
                    row.innerHTML = `
                        <td>${sizes[sizeId]}</td>
                        <td>
                            <div class="d-flex align-items-center">
                                <span class="color-swatch me-2" style="background-color: ${colors[colorId].color}"></span>
                                ${colors[colorId].name}
                            </div>
                        </td>
                        <td>
                            <input type="number" class="form-control" name="inventory[${sizeId}][${colorId}]" value="${quantity}" min="0">
                        </td>
                    `;
                    inventoryTableBody.appendChild(row);
                });
            });
        } else {
            inventoryTable.style.display = 'none';
            noVariantsMessage.style.display = 'block';
        }
    }
    
    // Handle variant selection change
    sizeCheckboxes.forEach(cb => {
        cb.addEventListener('change', updateInventoryTable);
    });
    
    colorCheckboxes.forEach(cb => {
        cb.addEventListener('change', updateInventoryTable);
    });
    
    // Add more images button
    document.getElementById('add-more-images').addEventListener('click', function() {
        document.getElementById('hinhanh_phu').click();
    });
    
    // Preview additional images
    const additionalImagesInput = document.getElementById('hinhanh_phu');
    const newImagesPreview = document.getElementById('new-images-preview');
    
    additionalImagesInput.addEventListener('change', function() {
        if (this.files && this.files.length > 0) {
            for (let i = 0; i < this.files.length; i++) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const previewDiv = document.createElement('div');
                    previewDiv.className = 'col-md-2 col-sm-4 col-6 mb-3';
                    
                    previewDiv.innerHTML = `
                        <div class="card">
                            <img src="${e.target.result}" class="card-img-top" alt="New image" style="height: 120px; object-fit: cover;">
                            <div class="card-body p-2 text-center">
                                <span class="badge bg-info">Ảnh mới</span>
                            </div>
                        </div>
                    `;
                    newImagesPreview.appendChild(previewDiv);
                }
                reader.readAsDataURL(this.files[i]);
            }
        }
    });
    
    // Initialize inventory table
    updateInventoryTable();
});
</script>

<?php include('includes/footer.php'); ?>
