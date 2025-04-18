<?php
// Start output buffering to prevent "headers already sent" errors
ob_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Kiểm tra đăng nhập
if(!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Include database connection BEFORE using $conn
include('../config/config.php');

// Check if sanpham_bien_the table exists instead of the old table
$check_table = $conn->query("SHOW TABLES LIKE 'sanpham_bien_the'");
if ($check_table->num_rows == 0) {
    $_SESSION['error_message'] = "Cấu trúc cơ sở dữ liệu không phù hợp. Vui lòng cập nhật cơ sở dữ liệu.";
    header("Location: products.php");
    exit();
}

// Set the page title for the header
$page_title = "Thêm sản phẩm mới";

// Include header (which now contains the sidebar)
include('includes/header.php');

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

$error = '';
$success = '';

// Xử lý khi form được submit
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Lấy dữ liệu từ form
    $tensanpham = trim($_POST['tensanpham']);
    $id_danhmuc = (int)$_POST['id_danhmuc'];
    $giagoc = (float)$_POST['giagoc'];
    $gia = (float)$_POST['gia'];
    $mota = trim($_POST['mota']);
    $trangthai = isset($_POST['trangthai']) ? (int)$_POST['trangthai'] : 0;
    $noibat = isset($_POST['noibat']) ? 1 : 0;
    $thuonghieu = isset($_POST['thuonghieu']) ? (int)$_POST['thuonghieu'] : null; // Added brand field
    $selected_sizes = isset($_POST['sizes']) ? $_POST['sizes'] : [];
    $selected_colors = isset($_POST['colors']) ? $_POST['colors'] : [];
    
    // Validate dữ liệu
    if(empty($tensanpham)) {
        $error = "Vui lòng nhập tên sản phẩm";
    } elseif($giagoc <= 0) {
        $error = "Giá gốc sản phẩm phải lớn hơn 0";
    } elseif($gia <= 0) {
        $error = "Giá bán sản phẩm phải lớn hơn 0";
    } elseif($gia > $giagoc) {
        $error = "Giá bán không thể cao hơn giá gốc";
    } elseif($id_danhmuc <= 0) {
        $error = "Vui lòng chọn danh mục sản phẩm";
    } elseif(empty($selected_sizes) || empty($selected_colors)) {
        $error = "Vui lòng chọn ít nhất một kích thước và một màu sắc";
    } else {
        try {
            // Bắt đầu transaction
            $conn->begin_transaction();
            
            // Xử lý upload hình ảnh chính
            $hinhanh = '';
            if(isset($_FILES['hinh_anh']) && $_FILES['hinh_anh']['error'] == 0) {
                $target_dir = "../uploads/products/";
                $upload_result = uploadFile($_FILES['hinh_anh'], $target_dir);
                if($upload_result[0]) {
                    $hinhanh = "uploads/products/" . $upload_result[1];  // Add full relative path
                } else {
                    throw new Exception("Lỗi upload hình ảnh: " . $upload_result[1]);
                }
            }
            
            // Generate slug from tensanpham
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $tensanpham)));
            
            // Thêm sản phẩm mới - with updated column names
            $sql = "INSERT INTO sanpham (tensanpham, slug, mota, gia, giagoc, hinhanh, id_danhmuc, thuonghieu, noibat, trangthai, ngay_tao) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssddsiiii", $tensanpham, $slug, $mota, $gia, $giagoc, $hinhanh, $id_danhmuc, $thuonghieu, $noibat, $trangthai);
            if(!$stmt->execute()) {
                throw new Exception("Lỗi khi thêm sản phẩm: " . $stmt->error);
            }
            
            $product_id = $conn->insert_id; // Lấy ID của sản phẩm vừa thêm
            
            // Thêm biến thể sản phẩm và số lượng tồn kho - updating table and columns
            $total_quantity = 0;
            if (!empty($selected_sizes) && !empty($selected_colors)) {
                // Insert into sanpham_bien_the
                $insert_variant = $conn->prepare("INSERT INTO sanpham_bien_the (id_sanpham, id_size, id_mau, so_luong) VALUES (?, ?, ?, ?)");
                foreach ($selected_sizes as $size_id) {
                    foreach ($selected_colors as $color_id) {
                        // Get quantity from form
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
                    }
                }
            }
            
            // Cập nhật tổng số lượng trong bảng sản phẩm
            $update_total = $conn->prepare("UPDATE sanpham SET so_luong = ? WHERE id = ?");
            $update_total->bind_param("ii", $total_quantity, $product_id);
            $update_total->execute();
            
            // Upload and save additional images
            if (isset($_FILES['hinhanh_phu']) && !empty($_FILES['hinhanh_phu']['name'][0])) {
                $target_dir = "../uploads/products/";
                $file_count = count($_FILES['hinhanh_phu']['name']);
                $file_count = min($file_count, 5); // Maximum 5 additional images
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
                            $img_path = "uploads/products/" . $upload_result[1];
                            // Insert into sanpham_hinhanh table
                            $is_main = ($i === 0) ? 1 : 0;
                            $insert_img = $conn->prepare("INSERT INTO sanpham_hinhanh (id_sanpham, hinhanh, la_anh_chinh) VALUES (?, ?, ?)");
                            $insert_img->bind_param("isi", $product_id, $img_path, $is_main);
                            $insert_img->execute();
                        }
                    }
                }
            }
            
            // Add main image to sanpham_hinhanh if it exists
            if (!empty($hinhanh)) {
                $insert_main_img = $conn->prepare("INSERT INTO sanpham_hinhanh (id_sanpham, hinhanh, la_anh_chinh) VALUES (?, ?, 1)");
                $insert_main_img->bind_param("is", $product_id, $hinhanh);
                $insert_main_img->execute();
            }
            
            // Commit transaction
            $conn->commit();
            
            // Log action
            $admin_id = $_SESSION['admin_id'] ?? 1;
            $details = "Thêm sản phẩm mới: " . $tensanpham;
            $action_log = $conn->prepare("INSERT INTO nhat_ky (id_user, hanh_dong, doi_tuong_loai, doi_tuong_id, chi_tiet, ip_address) VALUES (?, 'create', 'product', ?, ?, ?)");
            $ip = $_SERVER['REMOTE_ADDR'];
            $action_log->bind_param("iiss", $admin_id, $product_id, $details, $ip);
            $action_log->execute();
            
            $success = "Thêm sản phẩm thành công!";
            // Redirect
            $_SESSION['success_message'] = "Thêm sản phẩm thành công!";
            header("Location: products.php");
            exit();
        } catch (Exception $e) {
            // Rollback transaction if error
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

// Lấy danh sách danh mục (updated table name)
$categories = $conn->query("SELECT * FROM danhmuc WHERE trang_thai = 1 ORDER BY ten");
// Lấy danh sách kích thước từ thuoc_tinh thay vì bảng kichthuoc
$all_sizes = $conn->query("SELECT id, gia_tri as ten_kichthuoc FROM thuoc_tinh WHERE loai = 'size' ORDER BY gia_tri");
// Lấy danh sách màu sắc từ thuoc_tinh thay vì bảng mausac
$all_colors = $conn->query("SELECT id, gia_tri as ten_mau, ma_mau FROM thuoc_tinh WHERE loai = 'color' ORDER BY gia_tri");
// Lấy danh sách thương hiệu
$brands = $conn->query("SELECT id, ten FROM thuong_hieu ORDER BY ten");

?>

<!-- Main content -->
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Thêm sản phẩm mới</h1>
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

    <form method="post" enctype="multipart/form-data" id="addProductForm">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">Thông tin cơ bản</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        <div class="mb-3">
                            <label for="tensanpham" class="form-label required-field">Tên sản phẩm</label>
                            <input type="text" class="form-control" id="tensanpham" name="tensanpham" value="" required>
                            <div class="form-text">Tên sản phẩm nên dễ hiểu và hấp dẫn</div>
                        </div>

                        <div class="mb-3">
                            <label for="id_danhmuc" class="form-label required-field">Danh mục</label>
                            <select class="form-select" id="id_danhmuc" name="id_danhmuc" required>
                                <option value="">-- Chọn danh mục --</option>
                                <?php while($category = $categories->fetch_assoc()): ?>
                                    <option value="<?php echo $category['id']; ?>">
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
                                        <input type="number" class="form-control" id="giagoc" name="giagoc" value="" min="1000" required>
                                        <span class="input-group-text">VNĐ</span>
                                    </div>
                                    <div class="form-text">Giá gốc/niêm yết của sản phẩm</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="gia" class="form-label required-field">Giá bán thực tế</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="gia" name="gia" value="" min="1000" required>
                                        <span class="input-group-text">VNĐ</span>
                                    </div>
                                    <div class="form-text">Giá bán sau khi giảm giá (nếu có)</div>
                                </div>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="discount_percent" class="form-label">Phần trăm giảm giá</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="discount_percent" readonly>
                                        <span class="input-group-text">%</span>
                                    </div>
                                    <div class="form-text">Tự động tính toán dựa trên giá gốc và giá bán</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="hinh_anh" class="form-label required-field">Hình ảnh chính</label>
                            <div id="image-preview" class="mb-2">
                                <span class="text-muted">Chưa có hình ảnh</span>
                            </div>
                            <input type="file" class="form-control" id="hinh_anh" name="hinh_anh" accept="image/*" required>
                            <div class="form-text">Kích thước khuyến nghị: 800x800px</div>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="mota" class="form-label">Mô tả sản phẩm</label>
                    <textarea class="form-control" id="mota" name="mota" rows="5"></textarea>
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
                                           value="<?php echo $size['id']; ?>" 
                                           id="size_<?php echo $size['id']; ?>">
                                    <label class="form-check-label" for="size_<?php echo $size['id']; ?>">
                                        <?php echo htmlspecialchars($size['ten_kichthuoc']); ?>
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
                                           value="<?php echo $color['id']; ?>" 
                                           id="color_<?php echo $color['id']; ?>">
                                    <label class="form-check-label" for="color_<?php echo $color['id']; ?>">
                                        <div class="color-item">
                                            <span class="color-swatch" style="background-color: <?php echo $color['ma_mau']; ?>"></span>
                                            <span class="color-name"><?php echo htmlspecialchars($color['ten_mau']); ?></span>
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
                <div class="mb-3" id="noVariantsMessage">
                    <div class="alert alert-info">
                        Vui lòng chọn ít nhất một kích thước và một màu sắc để quản lý tồn kho.
                    </div>
                </div>
                
                <div id="inventoryTable" style="display: none;">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Kích thước</th>
                                <th>Màu sắc</th>
                                <th>Số lượng tồn</th>
                            </tr>
                        </thead>
                        <tbody id="inventoryTableBody">
                            <!-- Các hàng sẽ được thêm bằng JavaScript -->
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
                <div class="mb-3">
                    <label for="hinhanh_phu" class="form-label">Thêm hình ảnh</label>
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
                            <input class="form-check-input" type="checkbox" id="noibat" name="noibat" value="1">
                            <label class="form-check-label" for="noibat">Đánh dấu là sản phẩm nổi bật</label>
                        </div>
                        
                        <!-- Thêm trường chọn thương hiệu -->
                        <div class="mb-3">
                            <label for="thuonghieu" class="form-label">Thương hiệu</label>
                            <select class="form-select" id="thuonghieu" name="thuonghieu">
                                <option value="">-- Không chọn thương hiệu --</option>
                                <?php if ($brands && $brands->num_rows > 0): ?>
                                    <?php while ($brand = $brands->fetch_assoc()): ?>
                                        <option value="<?php echo $brand['id']; ?>"><?php echo htmlspecialchars($brand['ten']); ?></option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="trangthai" class="form-label">Trạng thái</label>
                            <select class="form-select" id="trangthai" name="trangthai">
                                <option value="1" selected>Đang kinh doanh</option>
                                <option value="0">Hết hàng</option>
                                <option value="2">Ngừng kinh doanh</option>
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
                <i class="bi bi-plus-circle"></i> Thêm sản phẩm
            </button>
        </div>
    </form>
</main>

<?php
// Include footer
include('includes/footer.php');
?>

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
                    imagePreview.innerHTML = '<span class="text-muted">Chưa có hình ảnh</span>';
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
                                <input type="number" class="form-control" name="inventory[${sizeId}][${colorId}]" value="0" min="0">
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
        const addProductForm = document.getElementById('addProductForm');
        if (addProductForm) {
            addProductForm.addEventListener('submit', function(e) {
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
        
        // Tính toán phần trăm giảm giá
        function calculateDiscount() {
            const giagoc = parseFloat(document.getElementById('giagoc').value) || 0;
            const gia = parseFloat(document.getElementById('gia').value) || 0;
            
            if (giagoc > 0 && gia > 0) {
                if (gia > giagoc) {
                    // Show warning if sale price is higher than original price
                    document.getElementById('discount_percent').value = "Lỗi: Giá bán > Giá gốc";
                    document.getElementById('discount_percent').classList.add('text-danger');
                } else {
                    const discountPercent = Math.round(((giagoc - gia) / giagoc) * 100);
                    document.getElementById('discount_percent').value = discountPercent;
                    document.getElementById('discount_percent').classList.remove('text-danger');
                }
            } else {
                document.getElementById('discount_percent').value = "0";
            }
        }
        
        // Add event listeners for price inputs
        document.getElementById('giagoc').addEventListener('input', calculateDiscount);
        document.getElementById('gia').addEventListener('input', calculateDiscount);
        
        // Validation for prices
        document.getElementById('addProductForm').addEventListener('submit', function(e) {
            const giagoc = parseFloat(document.getElementById('giagoc').value) || 0;
            const gia = parseFloat(document.getElementById('gia').value) || 0;
            
            if (gia > giagoc) {
                e.preventDefault();
                alert('Giá bán thực tế không thể cao hơn giá gốc');
                return false;
            }
                 
            // Continue with other validations
            const selectedSizes = document.querySelectorAll('.size-checkbox:checked');
            const selectedColors = document.querySelectorAll('.color-checkbox:checked');
            
            if (selectedSizes.length === 0 || selectedColors.length === 0) {
                e.preventDefault();
                alert('Vui lòng chọn ít nhất một kích thước và một màu sắc');
                return false;
            }
            
            return true;
        });
        
        // Initialize discount calculation
        calculateDiscount();
    });
</script>
</body>
</html>
