<?php
ob_start(); // Thêm dòng này
// Thiết lập tiêu đề trang
$page_title = "Chỉnh Sửa Sản Phẩm";

// Include header
include('includes/header.php');

// Kiểm tra và xử lý form submit trước tiên
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
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
        header("Location: chinh-sua-san-pham.php?id=" . $product_id);
        exit();
    }
    
    // Kiểm tra đã chọn kích thước và màu sắc chưa
    if (!isset($_POST['sizes']) || !isset($_POST['colors'])) {
        $_SESSION['error_message'] = "Vui lòng chọn ít nhất một kích thước và một màu sắc";
        header("Location: chinh-sua-san-pham.php?id=" . $product_id);
        exit();
    }
    
    try {
        // Bắt đầu transaction
        $conn->begin_transaction();
        
        // Xử lý upload hình ảnh chính (nếu có)
        $hinhanh = null;
        if (!empty($_FILES['hinhanh']['name'])) {
            $target_dir = dirname(__FILE__, 2) . "/uploads/products/";
            $upload_result = uploadFile($_FILES['hinhanh'], $target_dir);
            
            if (!$upload_result[0]) {
                throw new Exception("Lỗi upload hình ảnh chính: " . $upload_result[1]);
            }
            
            $hinhanh = $upload_result[1];
        }
        
        // Cập nhật thông tin sản phẩm
        if ($hinhanh) {
            // Nếu có upload hình mới
            $stmt = $conn->prepare("
                UPDATE sanpham SET 
                tensanpham = ?, mota = ?, gia = ?, giagoc = ?, hinhanh = ?, 
                id_loai = ?, id_thuonghieu = ?, trangthai = ?, noibat = ?, ngaycapnhat = NOW()
                WHERE id_sanpham = ?
            ");
            
            $stmt->bind_param(
                "ssddsiiibi",
                $tensanpham, $mota, $gia, $giagoc, $hinhanh, 
                $id_loai, $id_thuonghieu, $trangthai, $noibat, $product_id
            );
        } else {
            // Nếu không upload hình mới
            $stmt = $conn->prepare("
                UPDATE sanpham SET 
                tensanpham = ?, mota = ?, gia = ?, giagoc = ?,
                id_loai = ?, id_thuonghieu = ?, trangthai = ?, noibat = ?, ngaycapnhat = NOW()
                WHERE id_sanpham = ?
            ");
            
            $stmt->bind_param(
                "ssdsiiibi",
                $tensanpham, $mota, $gia, $giagoc,
                $id_loai, $id_thuonghieu, $trangthai, $noibat, $product_id
            );
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Lỗi khi cập nhật sản phẩm: " . $stmt->error);
        }
        
        // Cập nhật biến thể sản phẩm và số lượng tồn kho
        $sizes = $_POST['sizes'];
        $colors = $_POST['colors'];
        
        // Xóa các biến thể hiện tại
        $delete_variants = $conn->prepare("DELETE FROM sanpham_chitiet WHERE id_sanpham = ?");
        $delete_variants->bind_param("i", $product_id);
        $delete_variants->execute();
        
        $total_quantity = 0;
        
        // Thêm lại các biến thể với số lượng mới
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
                
                $variant_stmt->bind_param("iiii", $product_id, $size_id, $color_id, $quantity);
                
                if (!$variant_stmt->execute()) {
                    throw new Exception("Lỗi khi thêm biến thể sản phẩm: " . $variant_stmt->error);
                }
            }
        }
        
        // Cập nhật tổng số lượng trong bảng sản phẩm
        $update_total = $conn->prepare("UPDATE sanpham SET soluong = ? WHERE id_sanpham = ?");
        $update_total->bind_param("ii", $total_quantity, $product_id);
        $update_total->execute();
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['success_message'] = "Cập nhật sản phẩm thành công!";
        header("Location: danh-sach-san-pham.php");
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction nếu có lỗi
        $conn->rollback();
        $_SESSION['error_message'] = $e->getMessage();
        header("Location: chinh-sua-san-pham.php?id=" . $product_id);
        exit();
    }
} else {
    // Nếu là GET request, lấy ID từ tham số URL
    $product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($product_id <= 0) {
        $_SESSION['error_message'] = "ID sản phẩm không hợp lệ";
        header("Location: danh-sach-san-pham.php");
        exit();
    }
    
    // Kiểm tra xem sản phẩm có thuộc về người bán này không
    $product_query = $conn->prepare("SELECT * FROM sanpham WHERE id_sanpham = ? AND id_nguoiban = ?");
    $product_query->bind_param("ii", $product_id, $user_id);
    $product_query->execute();
    $result = $product_query->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['error_message'] = "Bạn không có quyền chỉnh sửa sản phẩm này";
        header("Location: danh-sach-san-pham.php");
        exit();
    }
    
    $product = $result->fetch_assoc();
    
    // Lấy thông tin biến thể sản phẩm
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
    
    while ($variant = $variants_result->fetch_assoc()) {
        if (!in_array($variant['id_kichthuoc'], $existing_sizes)) {
            $existing_sizes[] = $variant['id_kichthuoc'];
        }
        if (!in_array($variant['id_mausac'], $existing_colors)) {
            $existing_colors[] = $variant['id_mausac'];
        }
    }
    
    // Reset con trỏ kết quả để có thể lặp lại
    $variants_result->data_seek(0);
    
    // Lấy danh sách các hình ảnh phụ
    $additional_images = [];
    if (!empty($product['hinhanh_phu'])) {
        $additional_images = explode('|', $product['hinhanh_phu']);
    }
    
    // Lấy danh sách danh mục sản phẩm
    $categories_query = $conn->query("SELECT * FROM loaisanpham WHERE trangthai = 1 ORDER BY tenloai");
    $categories = [];
    while ($category = $categories_query->fetch_assoc()) {
        $categories[] = $category;
    }
    
    // Lấy danh sách thương hiệu
    $brands_query = $conn->query("SELECT * FROM thuonghieu WHERE trangthai = 1 ORDER BY tenthuonghieu");
    $brands = [];
    while ($brand = $brands_query->fetch_assoc()) {
        $brands[] = $brand;
    }
    
    // Lấy danh sách kích thước
    $sizes_query = $conn->query("SELECT * FROM kichthuoc ORDER BY tenkichthuoc");
    $sizes = [];
    while ($size = $sizes_query->fetch_assoc()) {
        $sizes[] = $size;
    }
    
    // Lấy danh sách màu sắc
    $colors_query = $conn->query("SELECT * FROM mausac WHERE trangthai = 1 ORDER BY tenmau");
    $colors = [];
    while ($color = $colors_query->fetch_assoc()) {
        $colors[] = $color;
    }
}
?>

<h1 class="h2 mb-4">Chỉnh Sửa Sản Phẩm</h1>

<div class="row">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-body">
            <form action="chinh-sua-san-pham.php" method="post" enctype="multipart/form-data" id="editProductForm">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                    
                    <!-- Thông tin cơ bản -->
                    <div class="mb-4">
                        <h5 class="card-title">Thông tin cơ bản</h5>
                        <div class="row g-3">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="tensanpham" class="form-label">Tên sản phẩm <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="tensanpham" name="tensanpham" value="<?php echo htmlspecialchars($product['tensanpham']); ?>" required>
                                    <div class="form-text">Tên sản phẩm nên dễ hiểu và hấp dẫn</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="id_loai" class="form-label">Danh mục <span class="text-danger">*</span></label>
                                    <select class="form-select" id="id_loai" name="id_loai" required>
                                        <option value="">-- Chọn danh mục --</option>
                                        <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id_loai']; ?>" <?php echo ($product['id_loai'] == $category['id_loai']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['tenloai']); ?>
                                        </option>
                                        <?php endforeach; ?>
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
                                            <label for="gia" class="form-label">Giá bán <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" id="gia" name="gia" value="<?php echo $product['gia']; ?>" min="1000" required>
                                                <span class="input-group-text">VNĐ</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="giagoc" class="form-label">Giá gốc</label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" id="giagoc" name="giagoc" value="<?php echo $product['giagoc']; ?>" min="1000">
                                                <span class="input-group-text">VNĐ</span>
                                            </div>
                                            <div class="form-text">Để trống nếu không có giá gốc</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="hinhanh" class="form-label">Hình ảnh chính</label>
                                    
                                    <div id="currentImagePreview" class="mb-2">
                                        <img src="<?php echo !empty($product['hinhanh']) ? '../uploads/products/'.$product['hinhanh'] : '../images/no-image.png'; ?>" 
                                             alt="Hình ảnh hiện tại" class="img-thumbnail" style="max-height: 150px;">
                                        <div class="form-text">Hình ảnh hiện tại</div>
                                    </div>
                                    
                                    <input type="file" class="form-control" id="hinhanh" name="hinhanh" accept="image/*">
                                    <div class="form-text">Chọn ảnh mới nếu muốn thay đổi. Kích thước khuyến nghị: 800x800px, tối đa 2MB</div>
                                    
                                    <div id="imagePreview" class="mt-2 text-center d-none">
                                        <img src="" alt="Hình ảnh mới" class="img-thumbnail" style="max-height: 150px;">
                                        <div class="form-text">Hình ảnh mới</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="mota" class="form-label">Mô tả sản phẩm</label>
                            <textarea class="form-control" id="mota" name="mota" rows="5"><?php echo htmlspecialchars($product['mota']); ?></textarea>
                            <div class="form-text">Mô tả chi tiết về sản phẩm, tính năng, chất liệu, v.v...</div>
                        </div>
                    </div>
                    
                    <!-- Biến thể sản phẩm -->
                    <div class="mb-4">
                        <h5 class="card-title">Biến thể sản phẩm</h5>
                        <p class="text-muted">Quản lý các phiên bản sản phẩm dựa trên kích thước và màu sắc</p>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Kích thước <span class="text-danger">*</span></label>
                                <div class="border p-3 rounded mb-3">
                                    <div class="row">
                                        <?php foreach ($sizes as $size): ?>
                                        <div class="col-md-3 col-4 mb-2">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="sizes[]" 
                                                       value="<?php echo $size['id_kichthuoc']; ?>" 
                                                       id="size_<?php echo $size['id_kichthuoc']; ?>"
                                                       <?php echo in_array($size['id_kichthuoc'], $existing_sizes) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="size_<?php echo $size['id_kichthuoc']; ?>">
                                                    <?php echo htmlspecialchars($size['tenkichthuoc']); ?>
                                                </label>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Màu sắc <span class="text-danger">*</span></label>
                                <div class="border p-3 rounded mb-3">
                                    <div class="row">
                                        <?php foreach ($colors as $color): ?>
                                        <div class="col-md-4 col-6 mb-2">
                                            <div class="form-check d-flex align-items-center">
                                                <input class="form-check-input" type="checkbox" name="colors[]" 
                                                       value="<?php echo $color['id_mausac']; ?>" 
                                                       id="color_<?php echo $color['id_mausac']; ?>"
                                                       <?php echo in_array($color['id_mausac'], $existing_colors) ? 'checked' : ''; ?>>
                                                <label class="form-check-label d-flex align-items-center ms-2" for="color_<?php echo $color['id_mausac']; ?>">
                                                    <span class="color-preview me-2" 
                                                          style="width: 18px; height: 18px; background-color: <?php echo $color['mamau']; ?>; display: inline-block; border-radius: 3px; border: 1px solid #ddd;"></span>
                                                    <?php echo htmlspecialchars($color['tenmau']); ?>
                                                </label>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <div class="mt-3">
                                        <button type="button" class="btn btn-sm btn-outline-primary" id="addColorButton">
                                            <i class="bi bi-plus-circle me-1"></i> Thêm màu mới
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quản lý số lượng -->
                    <div class="mb-4" id="inventorySection">
                        <h5 class="card-title">Quản lý tồn kho</h5>
                        
                        <div class="mb-3" id="noVariantsMessage" style="<?php echo (count($existing_sizes) > 0 && count($existing_colors) > 0) ? 'display: none;' : ''; ?>">
                            <div class="alert alert-info py-2">
                                Vui lòng chọn ít nhất một kích thước và một màu sắc để quản lý tồn kho.
                            </div>
                        </div>
                        
                        <div id="variantsContainer" style="<?php echo (count($existing_sizes) > 0 && count($existing_colors) > 0) ? '' : 'display: none;'; ?>">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Kích thước</th>
                                        <th>Màu sắc</th>
                                        <th>Số lượng tồn</th>
                                    </tr>
                                </thead>
                                <tbody id="variantsTableBody">
                                    <?php
                                    // Tạo mảng lưu trữ thông tin tồn kho
                                    $inventory = [];
                                    
                                    // Reset con trỏ kết quả
                                    $variants_result->data_seek(0);
                                    
                                    while ($variant = $variants_result->fetch_assoc()) {
                                        $size_id = $variant['id_kichthuoc'];
                                        $color_id = $variant['id_mausac'];
                                        $inventory[$size_id][$color_id] = $variant['soluong'];
                                        
                                        echo '<tr>';
                                        echo '<td>' . htmlspecialchars($variant['tenkichthuoc']) . '</td>';
                                        echo '<td>';
                                        echo '<div class="d-flex align-items-center">';
                                        echo '<span class="color-preview me-2" style="width: 18px; height: 18px; background-color: ' . $variant['mamau'] . '; display: inline-block; border-radius: 3px; border: 1px solid #ddd;"></span>';
                                        echo htmlspecialchars($variant['tenmau']);
                                        echo '</div>';
                                        echo '</td>';
                                        echo '<td>';
                                        echo '<input type="number" class="form-control inventory-input" name="inventory[' . $size_id . '][' . $color_id . ']" value="' . $variant['soluong'] . '" min="0" required>';
                                        echo '</td>';
                                        echo '</tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Hình ảnh bổ sung -->
                    <div class="mb-4">
                        <h5 class="card-title">Hình ảnh bổ sung</h5>
                        
                        <?php if (!empty($additional_images)): ?>
                            <div class="mb-3">
                                <label class="form-label">Hình ảnh hiện có</label>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach ($additional_images as $img): ?>
                                        <div class="position-relative additional-image-container">
                                            <img src="../uploads/products/<?php echo $img; ?>" 
                                                 class="img-thumbnail" 
                                                 style="width: 100px; height: 100px; object-fit: cover;">
                                            <div class="form-check position-absolute" style="top: 5px; right: 5px;">
                                                <input type="hidden" name="existing_images[]" value="<?php echo $img; ?>">
                                                <input class="form-check-input" type="checkbox" name="remove_images[]" value="<?php echo $img; ?>" id="remove_<?php echo $img; ?>">
                                                <label class="form-check-label" for="remove_<?php echo $img; ?>">
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
                            <label for="hinhanh_phu" class="form-label">Thêm hình ảnh mới (tối đa 5 hình)</label>
                            <input type="file" class="form-control" id="hinhanh_phu" name="hinhanh_phu[]" accept="image/*" multiple>
                            <div class="form-text">Chọn nhiều hình ảnh cùng lúc để mô tả chi tiết sản phẩm.</div>
                        </div>
                        
                        <div id="additionalImagesPreview" class="mt-2 d-flex flex-wrap gap-2"></div>
                    </div>
                    
                    <!-- Cài đặt bổ sung -->
                    <div class="mb-4">
                        <h5 class="card-title">Cài đặt bổ sung</h5>
                        
                        <div class="row g-3">
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
                                        <option value="1" <?php echo $product['trangthai'] == 1 ? 'selected' : ''; ?>>Còn hàng</option>
                                        <option value="0" <?php echo $product['trangthai'] == 0 ? 'selected' : ''; ?>>Hết hàng</option>
                                        <option value="2" <?php echo $product['trangthai'] == 2 ? 'selected' : ''; ?>>Ngừng kinh doanh</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end">
                        <a href="danh-sach-san-pham.php" class="btn btn-outline-secondary me-2">
                            <i class="bi bi-x-circle me-1"></i> Hủy
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i> Lưu thay đổi
                        </button>
                    </div>
                </form>
            </div>
        </div>
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
                        <label for="newColorName" class="form-label">Tên màu</label>
                        <input type="text" class="form-control" id="newColorName" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="newColorCode" class="form-label">Mã màu</label>
                        <input type="color" class="form-control form-control-color w-100" id="newColorCode" name="code" value="#563d7c" required>
                        <div class="form-text">Chọn mã màu theo định dạng HEX</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" id="saveNewColor">Thêm màu</button>
            </div>
        </div>
    </div>
</div>

<?php
// Debug inventory data
if (isset($_POST['action']) && $_POST['action'] === 'add' && isset($_POST['inventory'])) {
    // Tạo thư mục logs nếu chưa tồn tại
    if (!file_exists("../logs")) {
        mkdir("../logs", 0777, true);
    }
    
    // Log inventory data for debugging
    $log_file = fopen("../logs/inventory_log.txt", "a");
    fwrite($log_file, "Time: " . date('Y-m-d H:i:s') . "\n");
    fwrite($log_file, "Inventory data: " . print_r($_POST['inventory'], true) . "\n");
    fwrite($log_file, "------------------------\n");
    fclose($log_file);
}

// JavaScript cho trang
$page_specific_js = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    /* Code JavaScript đã có trước đó */
});
</script>
<script>
document.getElementById("editProductForm").addEventListener("submit", function() {
    const inventoryInputs = document.querySelectorAll("input[name^=\'inventory\']");
    inventoryInputs.forEach(input => {
        if (input.value === "" || isNaN(parseInt(input.value))) {
            input.value = 0;
        }
    });
});
</script>
';

// Include footer
include('includes/footer.php');
ob_end_flush(); // Thêm dòng này
?>
