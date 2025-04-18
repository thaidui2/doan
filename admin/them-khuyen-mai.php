<?php
// Start output buffering to prevent "headers already sent" errors
ob_start();

// Set page title
$page_title = 'Thêm mã khuyến mãi mới';

// Include header (which includes authentication checks)
include('includes/header.php');

// Include database connection
include('../config/config.php');

// Check admin permissions - use the same level check as the main page
if ($admin_level < 1) { // Only allow admins and managers
    $_SESSION['error_message'] = "Bạn không có quyền truy cập trang này.";
    header('Location: index.php');
    exit;
}

// Initialize variables
$success = false;
$error = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $ten = $conn->real_escape_string($_POST['ten']);
    $ma_khuyenmai = strtoupper($conn->real_escape_string($_POST['ma_khuyenmai']));
    $loai_giamgia = (int)$_POST['loai_giamgia'];
    $gia_tri = (float)$_POST['gia_tri'];
    $dieu_kien_toithieu = !empty($_POST['dieu_kien_toithieu']) ? (float)$_POST['dieu_kien_toithieu'] : 0;
    $so_luong = !empty($_POST['so_luong']) ? (int)$_POST['so_luong'] : null;
    $ngay_bat_dau = !empty($_POST['ngay_bat_dau']) ? $_POST['ngay_bat_dau'] : null;
    $ngay_ket_thuc = !empty($_POST['ngay_ket_thuc']) ? $_POST['ngay_ket_thuc'] : null;
    $trang_thai = isset($_POST['trang_thai']) ? 1 : 0;
    
    // Validate required fields
    if (empty($ten)) {
        $error = 'Vui lòng nhập tên khuyến mãi';
    } elseif (empty($ma_khuyenmai)) {
        $error = 'Vui lòng nhập mã khuyến mãi';
    } elseif ($gia_tri <= 0) {
        $error = 'Giá trị khuyến mãi phải lớn hơn 0';
    } else {
        // Check if code already exists
        $check_query = $conn->prepare("SELECT id FROM khuyen_mai WHERE ma_khuyenmai = ?");
        $check_query->bind_param("s", $ma_khuyenmai);
        $check_query->execute();
        $result = $check_query->get_result();
        
        if ($result->num_rows > 0) {
            $error = 'Mã khuyến mãi này đã tồn tại';
        } else {
            try {
                // Begin transaction
                $conn->begin_transaction();
                
                // Insert promo
                $insert_query = $conn->prepare("
                    INSERT INTO khuyen_mai (
                        ten, 
                        ma_khuyenmai, 
                        loai_giamgia, 
                        gia_tri, 
                        dieu_kien_toithieu, 
                        so_luong, 
                        da_su_dung, 
                        ngay_bat_dau, 
                        ngay_ket_thuc, 
                        trang_thai
                    ) VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?)
                ");
                
                $insert_query->bind_param(
                    "ssidisssi", 
                    $ten, 
                    $ma_khuyenmai, 
                    $loai_giamgia, 
                    $gia_tri, 
                    $dieu_kien_toithieu, 
                    $so_luong, 
                    $ngay_bat_dau, 
                    $ngay_ket_thuc, 
                    $trang_thai
                );
                
                if (!$insert_query->execute()) {
                    throw new Exception("Lỗi khi thêm khuyến mãi: " . $insert_query->error);
                }
                
                $promo_id = $conn->insert_id;
                
                // Handle target application (categories, products, or all)
                $target_type = isset($_POST['loai_doi_tuong']) ? (int)$_POST['loai_doi_tuong'] : 0;
                
                if ($target_type === 0) {
                    // Apply to all products
                    $target_insert = $conn->prepare("INSERT INTO khuyen_mai_apdung (id_khuyenmai, loai_doi_tuong, id_doi_tuong) VALUES (?, 0, NULL)");
                    $target_insert->bind_param("i", $promo_id);
                    $target_insert->execute();
                } else if ($target_type === 1 && isset($_POST['categories'])) {
                    // Apply to categories
                    foreach ($_POST['categories'] as $category_id) {
                        $target_insert = $conn->prepare("INSERT INTO khuyen_mai_apdung (id_khuyenmai, loai_doi_tuong, id_doi_tuong) VALUES (?, 1, ?)");
                        $target_insert->bind_param("ii", $promo_id, $category_id);
                        $target_insert->execute();
                    }
                } else if ($target_type === 2 && isset($_POST['products'])) {
                    // Apply to products
                    foreach ($_POST['products'] as $product_id) {
                        $target_insert = $conn->prepare("INSERT INTO khuyen_mai_apdung (id_khuyenmai, loai_doi_tuong, id_doi_tuong) VALUES (?, 2, ?)");
                        $target_insert->bind_param("ii", $promo_id, $product_id);
                        $target_insert->execute();
                    }
                }
                
                // Log admin activity
                $admin_id = $_SESSION['admin_id'];
                $details = "Thêm mã khuyến mãi mới: $ten ($ma_khuyenmai)";
                
                logAdminActivity($conn, $admin_id, 'create', 'promotion', $promo_id, $details);
                
                $conn->commit();
                $success = true;
                
                $_SESSION['success_message'] = "Đã thêm mã khuyến mãi thành công!";
                ob_end_clean(); // Clear output buffer before sending headers
                header("Location: khuyen-mai.php");
                exit;
                
            } catch (Exception $e) {
                $conn->rollback();
                $error = $e->getMessage();
            }
        }
    }
}

// Get categories for selection
$categories = $conn->query("SELECT id, ten FROM danhmuc WHERE trang_thai = 1 ORDER BY ten");

// Get products for selection
$products = $conn->query("SELECT id, tensanpham FROM sanpham WHERE trangthai = 1 ORDER BY tensanpham");
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Thêm mã khuyến mãi mới</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="khuyen-mai.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Quay lại
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="post" action="them-khuyen-mai.php">
                <div class="row">
                    <!-- Thông tin cơ bản -->
                    <div class="col-md-6 mb-4">
                        <h5 class="card-title mb-3">Thông tin cơ bản</h5>
                        
                        <div class="mb-3">
                            <label for="ten" class="form-label">Tên khuyến mãi <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="ten" name="ten" required>
                            <div class="form-text">Tên mô tả khuyến mãi (chỉ hiển thị trong admin)</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="ma_khuyenmai" class="form-label">Mã khuyến mãi <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="ma_khuyenmai" name="ma_khuyenmai" required>
                            <div class="form-text">Mã code khách hàng sẽ nhập, nên viết hoa và không dấu</div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Loại giảm giá <span class="text-danger">*</span></label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="loai_giamgia" id="loai_giamgia_0" value="0" checked>
                                <label class="form-check-label" for="loai_giamgia_0">
                                    Giảm theo phần trăm (%)
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="loai_giamgia" id="loai_giamgia_1" value="1">
                                <label class="form-check-label" for="loai_giamgia_1">
                                    Giảm số tiền cố định
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="gia_tri" class="form-label">Giá trị giảm <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="gia_tri" name="gia_tri" min="0" step="1000" required>
                                <span class="input-group-text" id="gia_tri_suffix">%</span>
                            </div>
                            <div class="form-text">Giá trị khuyến mãi (% hoặc số tiền cụ thể)</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="dieu_kien_toithieu" class="form-label">Giá trị đơn hàng tối thiểu</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="dieu_kien_toithieu" name="dieu_kien_toithieu" min="0" step="1000">
                                <span class="input-group-text">VNĐ</span>
                            </div>
                            <div class="form-text">Đơn hàng phải đạt giá trị này mới được áp dụng khuyến mãi</div>
                        </div>
                    </div>
                    
                    <!-- Cài đặt khuyến mãi -->
                    <div class="col-md-6 mb-4">
                        <h5 class="card-title mb-3">Cài đặt khuyến mãi</h5>
                        
                        <div class="mb-3">
                            <label for="so_luong" class="form-label">Số lượng mã có thể sử dụng</label>
                            <input type="number" class="form-control" id="so_luong" name="so_luong" min="0">
                            <div class="form-text">Để trống nếu không giới hạn số lượng</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="ngay_bat_dau" class="form-label">Ngày bắt đầu</label>
                            <input type="date" class="form-control" id="ngay_bat_dau" name="ngay_bat_dau">
                            <div class="form-text">Để trống nếu không giới hạn ngày bắt đầu</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="ngay_ket_thuc" class="form-label">Ngày kết thúc</label>
                            <input type="date" class="form-control" id="ngay_ket_thuc" name="ngay_ket_thuc">
                            <div class="form-text">Để trống nếu không giới hạn ngày kết thúc</div>
                        </div>
                        
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="trang_thai" name="trang_thai" checked>
                            <label class="form-check-label" for="trang_thai">Kích hoạt khuyến mãi ngay</label>
                        </div>
                    </div>
                    
                    <!-- Đối tượng áp dụng -->
                    <div class="col-12 mb-4">
                        <h5 class="card-title mb-3">Đối tượng áp dụng</h5>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input target-type" type="radio" name="loai_doi_tuong" id="target_all" value="0" checked>
                                <label class="form-check-label" for="target_all">
                                    Áp dụng cho tất cả sản phẩm
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input target-type" type="radio" name="loai_doi_tuong" id="target_categories" value="1">
                                <label class="form-check-label" for="target_categories">
                                    Áp dụng cho danh mục cụ thể
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input target-type" type="radio" name="loai_doi_tuong" id="target_products" value="2">
                                <label class="form-check-label" for="target_products">
                                    Áp dụng cho sản phẩm cụ thể
                                </label>
                            </div>
                        </div>
                        
                        <!-- Category selection -->
                        <div id="category-select-container" class="mb-3" style="display: none;">
                            <label class="form-label">Chọn danh mục</label>
                            <div class="row">
                                <?php if ($categories && $categories->num_rows > 0): ?>
                                    <?php while ($category = $categories->fetch_assoc()): ?>
                                        <div class="col-md-4 mb-2">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="categories[]" value="<?php echo $category['id']; ?>" id="cat_<?php echo $category['id']; ?>">
                                                <label class="form-check-label" for="cat_<?php echo $category['id']; ?>">
                                                    <?php echo htmlspecialchars($category['ten']); ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="col-12">
                                        <p class="text-muted">Không có danh mục nào</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Product selection -->
                        <div id="product-select-container" class="mb-3" style="display: none;">
                            <label class="form-label">Chọn sản phẩm</label>
                            <div class="row">
                                <?php if ($products && $products->num_rows > 0): ?>
                                    <div class="col-12 mb-3">
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="product-search" placeholder="Tìm kiếm sản phẩm...">
                                            <button class="btn btn-outline-secondary" type="button" id="select-all-products">Chọn tất cả</button>
                                            <button class="btn btn-outline-secondary" type="button" id="unselect-all-products">Bỏ chọn tất cả</button>
                                        </div>
                                    </div>
                                    <div class="col-12" style="max-height: 300px; overflow-y: auto;">
                                        <div class="row" id="product-list">
                                            <?php while ($product = $products->fetch_assoc()): ?>
                                                <div class="col-md-4 mb-2 product-item">
                                                    <div class="form-check">
                                                        <input class="form-check-input product-checkbox" type="checkbox" name="products[]" value="<?php echo $product['id']; ?>" id="prod_<?php echo $product['id']; ?>">
                                                        <label class="form-check-label" for="prod_<?php echo $product['id']; ?>">
                                                            <?php echo htmlspecialchars($product['tensanpham']); ?>
                                                        </label>
                                                    </div>
                                                </div>
                                            <?php endwhile; ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="col-12">
                                        <p class="text-muted">Không có sản phẩm nào</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-end">
                    <a href="khuyen-mai.php" class="btn btn-outline-secondary me-2">Hủy</a>
                    <button type="submit" name="submit" class="btn btn-primary">Lưu khuyến mãi</button>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Change suffix based on discount type
    const percentRadio = document.getElementById('loai_giamgia_0');
    const amountRadio = document.getElementById('loai_giamgia_1');
    const giaTri = document.getElementById('gia_tri');
    const giaTriSuffix = document.getElementById('gia_tri_suffix');
    
    function updateGiaTri() {
        if (percentRadio.checked) {
            giaTriSuffix.textContent = '%';
            giaTri.setAttribute('step', '1');
            giaTri.setAttribute('min', '1');
            giaTri.setAttribute('max', '100');
        } else {
            giaTriSuffix.textContent = 'VNĐ';
            giaTri.setAttribute('step', '1000');
            giaTri.setAttribute('min', '1000');
            giaTri.removeAttribute('max');
        }
    }
    
    percentRadio.addEventListener('change', updateGiaTri);
    amountRadio.addEventListener('change', updateGiaTri);
    
    // Target type switch
    const targetAll = document.getElementById('target_all');
    const targetCategories = document.getElementById('target_categories');
    const targetProducts = document.getElementById('target_products');
    const categoryContainer = document.getElementById('category-select-container');
    const productContainer = document.getElementById('product-select-container');
    
    function updateTargetDisplay() {
        categoryContainer.style.display = targetCategories.checked ? 'block' : 'none';
        productContainer.style.display = targetProducts.checked ? 'block' : 'none';
    }
    
    targetAll.addEventListener('change', updateTargetDisplay);
    targetCategories.addEventListener('change', updateTargetDisplay);
    targetProducts.addEventListener('change', updateTargetDisplay);
    
    // Product search
    const productSearch = document.getElementById('product-search');
    const productItems = document.querySelectorAll('.product-item');
    
    productSearch.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        
        productItems.forEach(function(item) {
            const productName = item.textContent.toLowerCase();
            if (productName.includes(searchTerm)) {
                item.style.display = '';
            } else {
                item.style.display = 'none';
            }
        });
    });
    
    // Select/unselect all products
    const selectAllBtn = document.getElementById('select-all-products');
    const unselectAllBtn = document.getElementById('unselect-all-products');
    const productCheckboxes = document.querySelectorAll('.product-checkbox');
    
    selectAllBtn.addEventListener('click', function() {
        productCheckboxes.forEach(function(checkbox) {
            if (checkbox.closest('.product-item').style.display !== 'none') {
                checkbox.checked = true;
            }
        });
    });
    
    unselectAllBtn.addEventListener('click', function() {
        productCheckboxes.forEach(function(checkbox) {
            checkbox.checked = false;
        });
    });
    
    // Set initial values
    updateGiaTri();
    updateTargetDisplay();
});
</script>

<?php 
include('includes/footer.php'); 
// End output buffering
ob_end_flush();
?>