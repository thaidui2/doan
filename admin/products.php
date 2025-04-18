<?php
// Set page title
$page_title = 'Quản lý sản phẩm';

// Include the header (which includes session check and common elements)
include('includes/header.php');

// Include database connection
include('../config/config.php');

// Products per page for pagination
$products_per_page = 10;

// Determine current page
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $products_per_page;

// Search filters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$status = isset($_GET['status']) ? $_GET['status'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'id_desc';

// Base query - Sửa để phù hợp với cấu trúc DB mới
$query = "SELECT sp.*, dm.ten as tendanhmuc FROM sanpham sp 
          LEFT JOIN danhmuc dm ON sp.id_danhmuc = dm.id 
          WHERE 1=1";

// Apply filters
if (!empty($search)) {
    $search_term = "%" . $conn->real_escape_string($search) . "%";
    $query .= " AND (sp.tensanpham LIKE '$search_term' OR sp.id LIKE '$search_term')";
}

if ($category > 0) {
    $query .= " AND sp.id_danhmuc = $category";
}

if ($status !== '') {
    $status = (int)$status;
    $query .= " AND sp.trangthai = $status";
}

// Count total products for pagination
$count_result = $conn->query($query);
$total_products = $count_result->num_rows;
$total_pages = ceil($total_products / $products_per_page);

// Apply sorting - Cập nhật tên trường
$sort_options = [
    'id_asc' => 'sp.id ASC',
    'id_desc' => 'sp.id DESC',
    'name_asc' => 'sp.tensanpham ASC',
    'name_desc' => 'sp.tensanpham DESC',
    'price_asc' => 'sp.gia ASC',
    'price_desc' => 'sp.gia DESC',
    'newest' => 'sp.ngay_tao DESC',
    'oldest' => 'sp.ngay_tao ASC'
];

$sort_sql = $sort_options[$sort] ?? $sort_options['id_desc'];
$query .= " ORDER BY $sort_sql LIMIT $offset, $products_per_page";

// Execute query
$products_result = $conn->query($query);

// Get categories for filter - Sửa truy vấn danh mục
$categories_query = "SELECT id, ten FROM danhmuc WHERE trang_thai = 1";
$categories_result = $conn->query($categories_query);
$categories = [];
while ($row = $categories_result->fetch_assoc()) {
    $categories[$row['id']] = $row['ten'];
}
?>

<!-- Main content -->
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Quản lý sản phẩm</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="add_product.php" class="btn btn-sm btn-primary">
                <i class="bi bi-plus-lg"></i> Thêm sản phẩm mới
            </a>
        </div>
    </div>
    
    <!-- Search and filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-4">
                    <label for="search" class="form-label">Tìm kiếm</label>
                    <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Tên hoặc ID sản phẩm...">
                </div>
                
                <div class="col-md-3">
                    <label for="category" class="form-label">Danh mục</label>
                    <select class="form-select" id="category" name="category">
                        <option value="0">Tất cả danh mục</option>
                        <?php foreach ($categories as $id => $name): ?>
                            <option value="<?php echo $id; ?>" <?php echo $category == $id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="status" class="form-label">Trạng thái</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Tất cả</option>
                        <option value="1" <?php echo $status === '1' ? 'selected' : ''; ?>>Còn hàng</option>
                        <option value="0" <?php echo $status === '0' ? 'selected' : ''; ?>>Hết hàng</option>
                        <option value="2" <?php echo $status === '2' ? 'selected' : ''; ?>>Ngừng kinh doanh</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="sort" class="form-label">Sắp xếp</label>
                    <select class="form-select" id="sort" name="sort">
                        <option value="id_desc" <?php echo $sort === 'id_desc' ? 'selected' : ''; ?>>Mã giảm dần</option>
                        <option value="id_asc" <?php echo $sort === 'id_asc' ? 'selected' : ''; ?>>Mã tăng dần</option>
                        <option value="name_asc" <?php echo $sort === 'name_asc' ? 'selected' : ''; ?>>Tên A-Z</option>
                        <option value="name_desc" <?php echo $sort === 'name_desc' ? 'selected' : ''; ?>>Tên Z-A</option>
                        <option value="price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>Giá tăng dần</option>
                        <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Giá giảm dần</option>
                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Mới nhất</option>
                        <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Cũ nhất</option>
                    </select>
                </div>
                
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Lọc</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Products List -->
    <div class="card">
        <div class="card-header bg-white py-3">
            <div class="row justify-content-between align-items-center">
                <div class="col">
                    <h5 class="mb-0">Danh sách sản phẩm</h5>
                </div>
                <div class="col-auto">
                    <span class="text-muted">Hiển thị <?php echo min($total_products, $products_per_page); ?> trên <?php echo $total_products; ?> sản phẩm</span>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle border-0 mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="5%">ID</th>
                            <th width="8%">Hình ảnh</th>
                            <th width="25%">Tên sản phẩm</th>
                            <th width="10%">Danh mục</th>
                            <th width="10%">Giá</th>
                            <th width="8%">Kho</th>
                            <th width="8%">Trạng thái</th>
                            <th width="12%">Ngày tạo</th>
                            <th width="14%">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($products_result->num_rows > 0): ?>
                            <?php while ($product = $products_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $product['id']; ?></td>
                                    <td>
                                        <?php if (!empty($product['hinhanh'])): ?>
                                            <img src="../<?php echo $product['hinhanh']; ?>" alt="<?php echo htmlspecialchars($product['tensanpham']); ?>" class="product-image" style="width: 50px; height: 50px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="bg-light text-center p-2">
                                                <i class="bi bi-image text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-medium"><?php echo htmlspecialchars($product['tensanpham']); ?></div>
                                        <?php if ($product['noibat'] == 1): ?>
                                            <span class="badge bg-warning text-dark">Nổi bật</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($product['tendanhmuc'] ?? 'Không có'); ?></td>
                                    <td>
                                        <div class="text-danger"><?php echo number_format($product['gia'], 0, ',', '.'); ?>₫</div>
                                        <?php if (!empty($product['giagoc']) && $product['giagoc'] > $product['gia']): ?>
                                            <del class="small text-muted"><?php echo number_format($product['giagoc'], 0, ',', '.'); ?>₫</del>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($product['so_luong'] <= 5 && $product['so_luong'] > 0): ?>
                                            <span class="text-warning"><?php echo $product['so_luong']; ?></span>
                                        <?php elseif ($product['so_luong'] == 0): ?>
                                            <span class="text-danger">0</span>
                                        <?php else: ?>
                                            <?php echo $product['so_luong']; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($product['trangthai'] == 1): ?>
                                            <span class="badge bg-success">Còn hàng</span>
                                        <?php elseif ($product['trangthai'] == 0): ?>
                                            <span class="badge bg-danger">Hết hàng</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Ngừng kinh doanh</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($product['ngay_tao'])); ?></td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <a href="../product-detail.php?id=<?php echo $product['id']; ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Xem trên website">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="edit_product.php?id=<?php echo $product['id']; ?>" class="btn btn-sm btn-outline-dark" title="Sửa">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-outline-danger delete-product" title="Xóa" data-id="<?php echo $product['id']; ?>" data-name="<?php echo htmlspecialchars($product['tensanpham']); ?>">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center py-4">
                                    <div class="py-5">
                                        <i class="bi bi-search display-6 text-muted"></i>
                                        <p class="mt-3">Không tìm thấy sản phẩm nào</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="card-footer bg-white border-0 py-3">
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center mb-0">
                        <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $current_page - 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category; ?>&status=<?php echo $status; ?>&sort=<?php echo $sort; ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($current_page == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category; ?>&status=<?php echo $status; ?>&sort=<?php echo $sort; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $current_page + 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category; ?>&status=<?php echo $status; ?>&sort=<?php echo $sort; ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Xác nhận xóa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Bạn có chắc chắn muốn xóa sản phẩm "<span id="productName"></span>"?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <form method="POST" action="delete_product.php" id="deleteForm">
                    <input type="hidden" name="product_id" id="deleteProductId" value="">
                    <button type="submit" class="btn btn-danger">Xóa</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php 
// Add specific JavaScript for this page
$page_specific_js = '
<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Delete product confirmation
        const deleteButtons = document.querySelectorAll(".delete-product");
        deleteButtons.forEach(button => {
            button.addEventListener("click", function() {
                const productId = this.dataset.id;
                const productName = this.dataset.name;
                
                document.getElementById("deleteProductId").value = productId;
                document.getElementById("productName").textContent = productName;
                
                const deleteModal = new bootstrap.Modal(document.getElementById("deleteModal"));
                deleteModal.show();
            });
        });
    });
</script>';

// Include footer
include('includes/footer.php');
?>
