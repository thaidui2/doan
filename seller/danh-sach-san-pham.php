<?php
// Thiết lập tiêu đề trang
$page_title = "Quản Lý Sản Phẩm";

// Include header
include('includes/header.php');

// Xử lý phân trang
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Xử lý lọc và tìm kiếm
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$status = isset($_GET['status']) ? (int)$_GET['status'] : -1;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Xây dựng câu query
$sql_conditions = ["sp.id_nguoiban = ?"]; // Chỉ lấy sản phẩm của người bán này
$params = [$user_id];
$param_types = "i";

if (!empty($search)) {
    $sql_conditions[] = "(sp.tensanpham LIKE ? OR sp.id_sanpham = ?)";
    $search_param = "%" . $search . "%";
    $params[] = $search_param;
    $params[] = (int)$search;
    $param_types .= "si";
}

if ($category > 0) {
    $sql_conditions[] = "sp.id_loai = ?";
    $params[] = $category;
    $param_types .= "i";
}

if ($status >= 0) {
    $sql_conditions[] = "sp.trangthai = ?";
    $params[] = $status;
    $param_types .= "i";
}

// Xây dựng câu query đếm tổng số sản phẩm
$count_sql = "
    SELECT COUNT(*) as total
    FROM sanpham sp
    WHERE " . implode(" AND ", $sql_conditions);

$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($param_types, ...$params);
}
$count_stmt->execute();
$result = $count_stmt->get_result();
$row = $result->fetch_assoc();
$total_items = $row['total'];
$total_pages = ceil($total_items / $limit);

// Xây dựng câu query lấy danh sách sản phẩm
$sql = "
    SELECT sp.*, 
           lsp.tenloai,
           (SELECT SUM(spct.soluong) FROM sanpham_chitiet spct WHERE spct.id_sanpham = sp.id_sanpham) as total_inventory,
           (SELECT COUNT(DISTINCT dhct.id_donhang) FROM donhang_chitiet dhct WHERE dhct.id_sanpham = sp.id_sanpham) as total_orders,
           (SELECT COUNT(*) FROM danhgia dg WHERE dg.id_sanpham = sp.id_sanpham) as total_reviews
    FROM sanpham sp
    LEFT JOIN loaisanpham lsp ON sp.id_loai = lsp.id_loai
    WHERE " . implode(" AND ", $sql_conditions) . "
";

// Thêm sắp xếp
switch ($sort) {
    case 'name_asc':
        $sql .= " ORDER BY sp.tensanpham ASC";
        break;
    case 'name_desc':
        $sql .= " ORDER BY sp.tensanpham DESC";
        break;
    case 'price_asc':
        $sql .= " ORDER BY sp.gia ASC";
        break;
    case 'price_desc':
        $sql .= " ORDER BY sp.gia DESC";
        break;
    case 'stock_asc':
        $sql .= " ORDER BY sp.soluong ASC";
        break;
    case 'stock_desc':
        $sql .= " ORDER BY sp.soluong DESC";
        break;
    case 'orders_desc':
        $sql .= " ORDER BY total_orders DESC";
        break;
    default: // newest
        $sql .= " ORDER BY sp.ngaytao DESC";
}

// Thêm phân trang
$sql .= " LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$param_types .= "ii";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$products = $stmt->get_result();

// Lấy danh sách danh mục để hiển thị dropdown lọc
$categories_query = $conn->query("SELECT id_loai, tenloai FROM loaisanpham WHERE trangthai = 1 ORDER BY tenloai");
$categories = [];
while ($category = $categories_query->fetch_assoc()) {
    $categories[] = $category;
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Quản lý sản phẩm</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="them-san-pham.php" class="btn btn-sm btn-primary">
            <i class="bi bi-plus-lg"></i> Thêm sản phẩm mới
        </a>
    </div>
</div>

<?php if (isset($_SESSION['success_message'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle me-2"></i> <?php echo $_SESSION['success_message']; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle me-2"></i> <?php echo $_SESSION['error_message']; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php unset($_SESSION['error_message']); ?>
<?php endif; ?>

<!-- Filtering tools -->
<div class="card mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0">Bộ lọc</h5>
    </div>
    <div class="card-body">
        <form action="" method="GET" class="row g-3">
            <!-- Tìm kiếm -->
            <div class="col-md-4">
                <label for="search" class="form-label">Tìm kiếm</label>
                <input type="text" class="form-control" id="search" name="search" placeholder="Tên hoặc ID sản phẩm..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            
            <!-- Lọc danh mục -->
            <div class="col-md-3">
                <label for="category" class="form-label">Danh mục</label>
                <select class="form-select" id="category" name="category">
                    <option value="0">Tất cả danh mục</option>
                    <?php foreach($categories as $cat): ?>
                    <option value="<?php echo $cat['id_loai']; ?>" <?php echo $category == $cat['id_loai'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat['tenloai']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Lọc trạng thái -->
            <div class="col-md-2">
                <label for="status" class="form-label">Trạng thái</label>
                <select class="form-select" id="status" name="status">
                    <option value="-1" <?php echo $status == -1 ? 'selected' : ''; ?>>Tất cả</option>
                    <option value="1" <?php echo $status == 1 ? 'selected' : ''; ?>>Còn hàng</option>
                    <option value="0" <?php echo $status == 0 ? 'selected' : ''; ?>>Hết hàng</option>
                    <option value="2" <?php echo $status == 2 ? 'selected' : ''; ?>>Ngừng kinh doanh</option>
                </select>
            </div>
            
            <!-- Sắp xếp -->
            <div class="col-md-3">
                <label for="sort" class="form-label">Sắp xếp</label>
                <select class="form-select" id="sort" name="sort">
                    <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Mới nhất</option>
                    <option value="name_asc" <?php echo $sort == 'name_asc' ? 'selected' : ''; ?>>Tên: A-Z</option>
                    <option value="name_desc" <?php echo $sort == 'name_desc' ? 'selected' : ''; ?>>Tên: Z-A</option>
                    <option value="price_asc" <?php echo $sort == 'price_asc' ? 'selected' : ''; ?>>Giá: Thấp đến cao</option>
                    <option value="price_desc" <?php echo $sort == 'price_desc' ? 'selected' : ''; ?>>Giá: Cao đến thấp</option>
                    <option value="stock_asc" <?php echo $sort == 'stock_asc' ? 'selected' : ''; ?>>Tồn kho: Ít nhất</option>
                    <option value="stock_desc" <?php echo $sort == 'stock_desc' ? 'selected' : ''; ?>>Tồn kho: Nhiều nhất</option>
                    <option value="orders_desc" <?php echo $sort == 'orders_desc' ? 'selected' : ''; ?>>Bán chạy nhất</option>
                </select>
            </div>
            
            <div class="col-12 text-end">
                <a href="danh-sach-san-pham.php" class="btn btn-outline-secondary me-2">
                    <i class="bi bi-x-circle"></i> Xóa bộ lọc
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-filter"></i> Lọc sản phẩm
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Bulk actions and product count display -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                Hành động nhóm
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item bulk-action" href="#" data-action="active"><i class="bi bi-eye"></i> Hiển thị sản phẩm</a></li>
                <li><a class="dropdown-item bulk-action" href="#" data-action="inactive"><i class="bi bi-eye-slash"></i> Ẩn sản phẩm</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-danger bulk-action" href="#" data-action="delete"><i class="bi bi-trash"></i> Xóa sản phẩm</a></li>
            </ul>
        </div>
    </div>
    
    <div>
        <span class="text-muted">Hiển thị <?php echo min($limit, $products->num_rows); ?> / <?php echo $total_items; ?> sản phẩm</span>
    </div>
</div>

<!-- Products table -->
<?php if ($products->num_rows > 0): ?>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th width="40px">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="checkAll">
                        </div>
                    </th>
                    <th width="60px">ID</th>
                    <th width="80px">Ảnh</th>
                    <th>Tên sản phẩm</th>
                    <th width="120px">Giá</th>
                    <th width="80px" class="text-center">Tồn kho</th>
                    <th width="120px">Danh mục</th>
                    <th width="90px" class="text-center">Trạng thái</th>
                    <th width="140px">Hành động</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($product = $products->fetch_assoc()): ?>
                <tr>
                    <td>
                        <div class="form-check">
                            <input class="form-check-input product-checkbox" type="checkbox" value="<?php echo $product['id_sanpham']; ?>">
                        </div>
                    </td>
                    <td><?php echo $product['id_sanpham']; ?></td>
                    <td>
                        <img src="<?php echo !empty($product['hinhanh']) ? '../uploads/products/' . $product['hinhanh'] : '../images/no-image.png'; ?>" 
                             class="img-thumbnail" alt="<?php echo htmlspecialchars($product['tensanpham']); ?>"
                             style="width: 50px; height: 50px; object-fit: cover;">
                    </td>
                    <td>
                        <div class="fw-medium"><?php echo htmlspecialchars($product['tensanpham']); ?></div>
                        <div class="small text-muted">
                            <?php echo $product['total_orders']; ?> đơn hàng | 
                            <?php echo $product['total_reviews']; ?> đánh giá
                        </div>
                    </td>
                    <td>
                        <div class="fw-bold"><?php echo number_format($product['gia'], 0, ',', '.'); ?> VNĐ</div>
                        <?php if (!empty($product['giagoc']) && $product['giagoc'] > $product['gia']): ?>
                            <div class="text-muted text-decoration-line-through small">
                                <?php echo number_format($product['giagoc'], 0, ',', '.'); ?> VNĐ
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <?php if ($product['soluong'] <= 0): ?>
                            <span class="badge bg-danger">Hết hàng</span>
                        <?php elseif ($product['soluong'] < 10): ?>
                            <span class="badge bg-warning text-dark"><?php echo $product['soluong']; ?></span>
                        <?php else: ?>
                            <span class="badge bg-success"><?php echo $product['soluong']; ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($product['tenloai']); ?></td>
                    <td class="text-center">
                        <?php if ($product['trangthai'] == 1): ?>
                            <span class="badge bg-success">Còn hàng</span>
                        <?php elseif ($product['trangthai'] == 0): ?>
                            <span class="badge bg-danger">Hết hàng</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Ngừng bán</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="btn-group">
                            <a href="../product-detail.php?id=<?php echo $product['id_sanpham']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="chinh-sua-san-pham.php?id=<?php echo $product['id_sanpham']; ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-pencil"></i>
                            </a>
                            
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
<div class="d-flex justify-content-center mt-4">
    <nav aria-label="Phân trang">
        <ul class="pagination">
            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category; ?>&status=<?php echo $status; ?>&sort=<?php echo $sort; ?>">
                    <i class="bi bi-chevron-left"></i>
                </a>
            </li>
            
            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                <?php if ($i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                    <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category; ?>&status=<?php echo $status; ?>&sort=<?php echo $sort; ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                    <li class="page-item disabled">
                        <span class="page-link">...</span>
                    </li>
                <?php endif; ?>
            <?php endfor; ?>
            
            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category; ?>&status=<?php echo $status; ?>&sort=<?php echo $sort; ?>">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </li>
        </ul>
    </nav>
</div>
<?php endif; ?>

<?php else: ?>
<!-- No products found message -->
<div class="card">
    <div class="card-body text-center py-5">
        <i class="bi bi-box fs-1 text-muted"></i>
        <h5 class="mt-3">Không tìm thấy sản phẩm nào</h5>
        <p class="text-muted">Không có sản phẩm nào phù hợp với tiêu chí tìm kiếm của bạn.</p>
        <a href="them-san-pham.php" class="btn btn-primary mt-3">
            <i class="bi bi-plus-circle me-2"></i> Thêm sản phẩm mới
        </a>
    </div>
</div>
<?php endif; ?>

<!-- Delete product confirmation modal -->
<div class="modal fade" id="deleteProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteProductModalLabel">Xác nhận xóa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Bạn có chắc chắn muốn xóa sản phẩm này không? Hành động này không thể hoàn tác.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <a href="#" id="confirmDelete" class="btn btn-danger">Xóa sản phẩm</a>
            </div>
        </div>
    </div>
</div>

<!-- Bulk action confirmation modal -->
<div class="modal fade" id="bulkActionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bulkActionModalLabel">Xác nhận hành động</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="bulkActionMessage">
                
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <a href="#" id="confirmBulkAction" class="btn btn-primary">Xác nhận</a>
            </div>
        </div>
    </div>
</div>

<?php
$page_specific_js = "
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Single product delete
    const deleteButtons = document.querySelectorAll('.delete-product');
    const deleteModal = document.getElementById('deleteProductModal');
    const confirmDeleteButton = document.getElementById('confirmDelete');
    
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.getAttribute('data-id');
            confirmDeleteButton.href = 'xu-ly-san-pham.php?action=delete&id=' + productId;
            
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteProductModal'));
            deleteModal.show();
        });
    });
    
    // Check all functionality
    const checkAllBox = document.getElementById('checkAll');
    const checkboxes = document.querySelectorAll('.product-checkbox');
    
    checkAllBox.addEventListener('change', function() {
        checkboxes.forEach(checkbox => {
            checkbox.checked = checkAllBox.checked;
        });
    });
    
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            if (!this.checked) {
                checkAllBox.checked = false;
            } else {
                // Check if all checkboxes are checked
                const allChecked = Array.from(checkboxes).every(cb => cb.checked);
                checkAllBox.checked = allChecked;
            }
        });
    });
    
    // Bulk actions
    const bulkActionButtons = document.querySelectorAll('.bulk-action');
    const bulkActionModal = document.getElementById('bulkActionModal');
    const bulkActionMessage = document.getElementById('bulkActionMessage');
    const confirmBulkButton = document.getElementById('confirmBulkAction');
    
    bulkActionButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Get selected products
            const selectedProducts = [];
            checkboxes.forEach(checkbox => {
                if (checkbox.checked) {
                    selectedProducts.push(checkbox.value);
                }
            });
            
            if (selectedProducts.length === 0) {
                alert('Vui lòng chọn ít nhất một sản phẩm để thực hiện hành động này.');
                return;
            }
            
            const action = this.getAttribute('data-action');
            let actionText = '';
            let buttonClass = 'btn-primary';
            
            switch(action) {
                case 'active':
                    actionText = 'hiển thị';
                    buttonClass = 'btn-success';
                    break;
                case 'inactive':
                    actionText = 'ẩn';
                    buttonClass = 'btn-warning';
                    break;
                case 'delete':
                    actionText = 'xóa';
                    buttonClass = 'btn-danger';
                    break;
            }
            
            bulkActionMessage.textContent = `Bạn có chắc chắn muốn \${actionText} \${selectedProducts.length} sản phẩm đã chọn không?`;
            confirmBulkButton.href = `xu-ly-san-pham.php?action=bulk_\${action}&ids=\${selectedProducts.join(',')}`;
            confirmBulkButton.className = `btn \${buttonClass}`;
            
            const bulkModal = new bootstrap.Modal(document.getElementById('bulkActionModal'));
            bulkModal.show();
        });
    });
});
</script>
";

include('includes/footer.php');
?>
