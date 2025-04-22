<?php
// Include database connection
require_once('../config/database.php');

// Include authentication check
require_once('includes/auth_check.php');

// Set current page for sidebar highlighting
$current_page = 'products';
$page_title = 'Chi tiết sản phẩm';

// Get product ID from URL
$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$product_id) {
    // Redirect to products list if no ID provided
    header('Location: products.php');
    exit;
}

// Process form submissions
$message = '';
$message_type = '';

// Fetch product details
try {
    $product_stmt = $conn->prepare("
        SELECT p.*, d.ten AS danhmuc_ten, th.ten AS thuonghieu_ten
        FROM sanpham p 
        LEFT JOIN danhmuc d ON p.id_danhmuc = d.id 
        LEFT JOIN thuong_hieu th ON p.thuonghieu = th.id
        WHERE p.id = ?
    ");
    $product_stmt->execute([$product_id]);
    $product = $product_stmt->fetch();
    
    if (!$product) {
        $_SESSION['message'] = 'Sản phẩm không tồn tại!';
        $_SESSION['message_type'] = 'danger';
        header('Location: products.php');
        exit;
    }
    
    // Fetch product variants
    $variants_stmt = $conn->prepare("
        SELECT spbt.*, 
               size.ten AS size_name, size.gia_tri AS size_value,
               color.ten AS color_name, color.gia_tri AS color_value, color.ma_mau AS color_hex
        FROM sanpham_bien_the spbt
        JOIN thuoc_tinh size ON spbt.id_size = size.id
        JOIN thuoc_tinh color ON spbt.id_mau = color.id
        WHERE spbt.id_sanpham = ?
        ORDER BY size.gia_tri, color.gia_tri
    ");
    $variants_stmt->execute([$product_id]);
    $variants = $variants_stmt->fetchAll();
    
    // Fetch product images
    $images_stmt = $conn->prepare("
        SELECT * FROM sanpham_hinhanh
        WHERE id_sanpham = ?
        ORDER BY la_anh_chinh DESC
    ");
    $images_stmt->execute([$product_id]);
    $images = $images_stmt->fetchAll();
    
    // Fetch available sizes for this product
    $sizes_stmt = $conn->prepare("
        SELECT DISTINCT size.id, size.ten, size.gia_tri
        FROM thuoc_tinh size
        JOIN sanpham_bien_the spbt ON size.id = spbt.id_size
        WHERE spbt.id_sanpham = ? AND size.loai = 'size'
    ");
    $sizes_stmt->execute([$product_id]);
    $product_sizes = $sizes_stmt->fetchAll();
    
    // Fetch available colors for this product
    $colors_stmt = $conn->prepare("
        SELECT DISTINCT color.id, color.ten, color.gia_tri, color.ma_mau
        FROM thuoc_tinh color
        JOIN sanpham_bien_the spbt ON color.id = spbt.id_mau
        WHERE spbt.id_sanpham = ? AND color.loai = 'color'
    ");
    $colors_stmt->execute([$product_id]);
    $product_colors = $colors_stmt->fetchAll();
    
    // Fetch all sizes
    $all_sizes_stmt = $conn->prepare("SELECT id, ten, gia_tri FROM thuoc_tinh WHERE loai = 'size' ORDER BY gia_tri ASC");
    $all_sizes_stmt->execute();
    $all_sizes = $all_sizes_stmt->fetchAll();
    
    // Fetch all colors
    $all_colors_stmt = $conn->prepare("SELECT id, ten, gia_tri, ma_mau FROM thuoc_tinh WHERE loai = 'color' ORDER BY gia_tri ASC");
    $all_colors_stmt->execute();
    $all_colors = $all_colors_stmt->fetchAll();
    
    // Count reviews
    $reviews_stmt = $conn->prepare("SELECT COUNT(*) as total, AVG(diem) as average FROM danhgia WHERE id_sanpham = ?");
    $reviews_stmt->execute([$product_id]);
    $reviews = $reviews_stmt->fetch();
    
    // Get product sales
    $sales_stmt = $conn->prepare("
        SELECT SUM(dc.soluong) as total_sold
        FROM donhang_chitiet dc
        JOIN donhang d ON dc.id_donhang = d.id
        WHERE dc.id_sanpham = ? AND d.trang_thai_don_hang = 4
    ");
    $sales_stmt->execute([$product_id]);
    $sales = $sales_stmt->fetch();
    
} catch (PDOException $e) {
    $message = 'Lỗi database: ' . $e->getMessage();
    $message_type = 'danger';
}

// Handle variant update via AJAX
if (isset($_POST['action']) && $_POST['action'] == 'update_variant') {
    header('Content-Type: application/json');
    
    try {
        $variant_id = isset($_POST['variant_id']) ? intval($_POST['variant_id']) : 0;
        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;
        $price = isset($_POST['price']) ? (float)$_POST['price'] : null;
        
        if ($variant_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID biến thể không hợp lệ']);
            exit;
        }
        
        // If price is null or empty, set SQL for updating only quantity
        if (empty($price)) {
            $update_stmt = $conn->prepare("UPDATE sanpham_bien_the SET so_luong = ? WHERE id = ?");
            $update_stmt->execute([$quantity, $variant_id]);
        } else {
            // Update both quantity and price
            $update_stmt = $conn->prepare("UPDATE sanpham_bien_the SET so_luong = ?, gia_bien_the = ? WHERE id = ?");
            $update_stmt->execute([$quantity, $price, $variant_id]);
        }
        
        // Log the action
        $log_stmt = $conn->prepare("INSERT INTO nhat_ky (id_user, hanh_dong, doi_tuong_loai, doi_tuong_id, chi_tiet, ip_address) 
                                   VALUES (?, 'update', 'variant', ?, ?, ?)");
        $log_stmt->execute([$_SESSION['admin_id'], $variant_id, "Cập nhật biến thể sản phẩm ID: $variant_id", $_SERVER['REMOTE_ADDR']]);
        
        echo json_encode(['success' => true, 'message' => 'Cập nhật thành công']);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
        exit;
    }
}

// Include header
include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="col-md-10 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Chi tiết sản phẩm</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="products.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Quay lại
                        </a>
                        <a href="product_edit.php?id=<?php echo $product_id; ?>" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-edit"></i> Chỉnh sửa sản phẩm
                        </a>
                        <a href="../product-detail.php?id=<?php echo $product_id; ?>" target="_blank" class="btn btn-sm btn-outline-info">
                            <i class="fas fa-eye"></i> Xem trên trang chủ
                        </a>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <!-- Product Overview -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0">Thông tin cơ bản</h5>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-4 fw-bold">ID sản phẩm:</div>
                                <div class="col-md-8"><?php echo $product['id']; ?></div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4 fw-bold">Tên sản phẩm:</div>
                                <div class="col-md-8"><?php echo htmlspecialchars($product['tensanpham']); ?></div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4 fw-bold">Slug:</div>
                                <div class="col-md-8"><?php echo htmlspecialchars($product['slug']); ?></div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4 fw-bold">Giá:</div>
                                <div class="col-md-8"><?php echo number_format($product['gia'], 0, ',', '.'); ?>đ</div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4 fw-bold">Giá gốc:</div>
                                <div class="col-md-8"><?php echo number_format($product['giagoc'], 0, ',', '.'); ?>đ</div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4 fw-bold">Danh mục:</div>
                                <div class="col-md-8"><?php echo htmlspecialchars($product['danhmuc_ten']); ?></div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4 fw-bold">Thương hiệu:</div>
                                <div class="col-md-8"><?php echo htmlspecialchars($product['thuonghieu_ten']); ?></div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4 fw-bold">Ngày tạo:</div>
                                <div class="col-md-8"><?php echo date('d/m/Y H:i', strtotime($product['ngay_tao'])); ?></div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-4 fw-bold">Cập nhật lần cuối:</div>
                                <div class="col-md-8">
                                    <?php echo $product['ngay_capnhat'] ? date('d/m/Y H:i', strtotime($product['ngay_capnhat'])) : 'Chưa cập nhật'; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0">Tổng quan</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <div class="border rounded p-3 text-center">
                                        <h3 class="text-primary mb-0"><?php echo $product['so_luong']; ?></h3>
                                        <p class="small text-muted mb-0">Tổng số lượng</p>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <div class="border rounded p-3 text-center">
                                        <h3 class="text-success mb-0"><?php echo $sales ? $sales['total_sold'] : 0; ?></h3>
                                        <p class="small text-muted mb-0">Đã bán</p>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <div class="border rounded p-3 text-center">
                                        <h3 class="text-info mb-0"><?php echo $reviews ? $reviews['total'] : 0; ?></h3>
                                        <p class="small text-muted mb-0">Đánh giá</p>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <div class="border rounded p-3 text-center">
                                        <h3 class="text-warning mb-0">
                                            <?php echo $reviews && $reviews['average'] ? number_format($reviews['average'], 1) : '0.0'; ?>
                                        </h3>
                                        <p class="small text-muted mb-0">Điểm đánh giá trung bình</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-2 mb-3">
                                <h6>Trạng thái:</h6>
                                <div>
                                    <span class="badge <?php echo $product['trangthai'] ? 'bg-success' : 'bg-danger'; ?> me-2">
                                        <?php echo $product['trangthai'] ? 'Đang hiển thị' : 'Đã ẩn'; ?>
                                    </span>
                                    
                                    <span class="badge <?php echo $product['noibat'] ? 'bg-warning' : 'bg-secondary'; ?>">
                                        <?php echo $product['noibat'] ? 'Nổi bật' : 'Không nổi bật'; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <h6>Ảnh sản phẩm:</h6>
                                <div class="d-flex flex-wrap">
                                    <?php foreach ($images as $image): ?>
                                        <div class="me-2 mb-2 position-relative">
                                            <img src="../<?php echo $image['hinhanh']; ?>" class="img-thumbnail" style="max-width: 100px; max-height: 100px;" alt="Product Image">
                                            <?php if ($image['la_anh_chinh']): ?>
                                                <span class="position-absolute top-0 start-0 badge bg-primary">Chính</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Product Variants -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Biến thể sản phẩm</h5>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addVariantModal">
                        <i class="fas fa-plus"></i> Thêm biến thể mới
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Màu sắc</th>
                                    <th>Kích thước</th>
                                    <th>Giá biến thể</th>
                                    <th>Số lượng</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($variants as $variant): ?>
                                    <tr data-variant-id="<?php echo $variant['id']; ?>">
                                        <td><?php echo $variant['id']; ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php if ($variant['color_hex']): ?>
                                                    <span class="color-preview me-2" style="display: inline-block; width: 20px; height: 20px; background-color: <?php echo $variant['color_hex']; ?>; border: 1px solid #ddd; border-radius: 3px;"></span>
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars($variant['color_name']); ?>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($variant['size_name']); ?></td>
                                        <td>
                                            <input type="text" class="form-control form-control-sm variant-price" 
                                                value="<?php echo $variant['gia_bien_the'] ? number_format($variant['gia_bien_the'], 0, '', '') : ''; ?>" 
                                                placeholder="<?php echo number_format($product['gia'], 0, '', ''); ?>"
                                                data-original="<?php echo $variant['gia_bien_the'] ? number_format($variant['gia_bien_the'], 0, '', '') : ''; ?>">
                                        </td>
                                        <td>
                                            <input type="number" min="0" class="form-control form-control-sm variant-quantity" 
                                                value="<?php echo $variant['so_luong']; ?>"
                                                data-original="<?php echo $variant['so_luong']; ?>">
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-success save-variant" style="display:none;">
                                                <i class="fas fa-save"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger delete-variant">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Product Description -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Mô tả sản phẩm</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($product['mota'])): ?>
                        <div>
                            <?php echo $product['mota']; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">Không có mô tả chi tiết.</p>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Add Variant Modal -->
<div class="modal fade" id="addVariantModal" tabindex="-1" aria-labelledby="addVariantModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addVariantModalLabel">Thêm biến thể mới</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addVariantForm" action="product_actions.php" method="post">
                    <input type="hidden" name="action" value="add_variant">
                    <input type="hidden" name="product_id" value="<?php echo $product_id; ?>">
                    
                    <div class="mb-3">
                        <label for="color_id" class="form-label">Màu sắc</label>
                        <select class="form-select" id="color_id" name="color_id" required>
                            <option value="">-- Chọn màu sắc --</option>
                            <?php foreach ($all_colors as $color): ?>
                                <option value="<?php echo $color['id']; ?>" data-hex="<?php echo $color['ma_mau']; ?>">
                                    <?php echo htmlspecialchars($color['ten']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="size_id" class="form-label">Kích thước</label>
                        <select class="form-select" id="size_id" name="size_id" required>
                            <option value="">-- Chọn kích thước --</option>
                            <?php foreach ($all_sizes as $size): ?>
                                <option value="<?php echo $size['id']; ?>">
                                    <?php echo htmlspecialchars($size['ten']); ?> (<?php echo htmlspecialchars($size['gia_tri']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="variant_price" class="form-label">Giá biến thể (để trống sẽ dùng giá sản phẩm)</label>
                        <input type="number" class="form-control" id="variant_price" name="variant_price" min="0" step="1000">
                    </div>
                    
                    <div class="mb-3">
                        <label for="variant_quantity" class="form-label">Số lượng</label>
                        <input type="number" class="form-control" id="variant_quantity" name="variant_quantity" min="0" value="0" required>
                    </div>
                    
                    <div class="text-end">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" class="btn btn-primary">Thêm biến thể</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for handling variant updates -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Show save button on input change
    document.querySelectorAll('.variant-price, .variant-quantity').forEach(input => {
        input.addEventListener('input', function() {
            const row = this.closest('tr');
            const saveButton = row.querySelector('.save-variant');
            const priceInput = row.querySelector('.variant-price');
            const quantityInput = row.querySelector('.variant-quantity');
            
            const priceChanged = priceInput.value !== priceInput.dataset.original;
            const quantityChanged = quantityInput.value !== quantityInput.dataset.original;
            
            if (priceChanged || quantityChanged) {
                saveButton.style.display = 'inline-block';
            } else {
                saveButton.style.display = 'none';
            }
        });
    });
    
    // Handle save button click
    document.querySelectorAll('.save-variant').forEach(button => {
        button.addEventListener('click', function() {
            const row = this.closest('tr');
            const variantId = row.dataset.variantId;
            const price = row.querySelector('.variant-price').value;
            const quantity = row.querySelector('.variant-quantity').value;
            
            // Send AJAX request
            fetch('product_detail.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_variant&variant_id=${variantId}&price=${price}&quantity=${quantity}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update data-original attributes
                    row.querySelector('.variant-price').dataset.original = price;
                    row.querySelector('.variant-quantity').dataset.original = quantity;
                    
                    // Hide save button
                    this.style.display = 'none';
                    
                    // Show success message
                    alert('Cập nhật thành công!');
                } else {
                    alert('Lỗi: ' + data.message);
                }
            })
            .catch(error => {
                alert('Có lỗi xảy ra khi cập nhật dữ liệu');
                console.error(error);
            });
        });
    });
    
    // Handle delete variant button
    document.querySelectorAll('.delete-variant').forEach(button => {
        button.addEventListener('click', function() {
            const row = this.closest('tr');
            const variantId = row.dataset.variantId;
            
            if (confirm('Bạn có chắc chắn muốn xóa biến thể này?')) {
                // Send AJAX request to delete variant
                fetch('product_actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete_variant&variant_id=${variantId}&product_id=<?php echo $product_id; ?>`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remove row from table
                        row.remove();
                        
                        // Show success message
                        alert('Xóa biến thể thành công!');
                    } else {
                        alert('Lỗi: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Có lỗi xảy ra khi xóa biến thể');
                    console.error(error);
                });
            }
        });
    });
    
    // Preview color selection in add variant form
    document.getElementById('color_id').addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const colorHex = selectedOption.dataset.hex;
        
        if (colorHex) {
            // Create or update color preview
            let colorPreview = document.getElementById('color-preview');
            if (!colorPreview) {
                colorPreview = document.createElement('div');
                colorPreview.id = 'color-preview';
                colorPreview.style.width = '30px';
                colorPreview.style.height = '30px';
                colorPreview.style.marginTop = '10px';
                colorPreview.style.borderRadius = '3px';
                colorPreview.style.border = '1px solid #ddd';
                this.parentNode.appendChild(colorPreview);
            }
            
            colorPreview.style.backgroundColor = colorHex;
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
