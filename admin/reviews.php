<?php
// Set page title
$page_title = 'Quản lý đánh giá';

// Include header (which includes authentication checks)
include('includes/header.php');

// Include database connection
include('../config/config.php');

// Variables for filtering and searching
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$rating = isset($_GET['rating']) ? (int)$_GET['rating'] : 0;
$status = isset($_GET['status']) ? (int)$_GET['status'] : -1;
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Determine current page for pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Build base query with JOINs to related tables - updated table and column names
$query = "SELECT r.*, s.tensanpham, u.taikhoan, u.ten AS tenuser
          FROM danhgia r
          JOIN sanpham s ON r.id_sanpham = s.id
          JOIN users u ON r.id_user = u.id
          WHERE 1=1";

// Apply search filter
if (!empty($search_term)) {
    $search_param = '%' . $conn->real_escape_string($search_term) . '%';
    $query .= " AND (s.tensanpham LIKE ? OR r.noi_dung LIKE ? OR u.ten LIKE ?)";
}

// Apply product filter
if ($product_id > 0) {
    $query .= " AND r.id_sanpham = $product_id";
}

// Apply rating filter
if ($rating > 0) {
    $query .= " AND r.diem = $rating";
}

// Apply status filter
if ($status !== -1) {
    $query .= " AND r.trang_thai = $status";
}

// Add sorting - updated column names
switch ($sort_by) {
    case 'oldest':
        $query .= " ORDER BY r.ngay_danhgia ASC";
        break;
    case 'highest_rating':
        $query .= " ORDER BY r.diem DESC, r.ngay_danhgia DESC";
        break;
    case 'lowest_rating':
        $query .= " ORDER BY r.diem ASC, r.ngay_danhgia DESC";
        break;
    case 'product_name':
        $query .= " ORDER BY s.tensanpham ASC";
        break;
    default:
        $query .= " ORDER BY r.ngay_danhgia DESC"; // Default: newest
        break;
}

// Count total reviews for pagination
$count_query = str_replace("SELECT r.*, s.tensanpham, u.taikhoan, u.ten AS tenuser", "SELECT COUNT(*) AS total", $query);

// Prepare statement for count
$stmt_count = $conn->prepare($count_query);
if (!empty($search_term)) {
    $stmt_count->bind_param("sss", $search_param, $search_param, $search_param);
}
$stmt_count->execute();
$count_result = $stmt_count->get_result();
$count_row = $count_result->fetch_assoc();
$total_reviews = $count_row['total'];

// Calculate total pages
$total_pages = ceil($total_reviews / $limit);

// Add pagination to main query
$query .= " LIMIT ?, ?";

// Prepare statement for data
$stmt = $conn->prepare($query);
if (!empty($search_term)) {
    $stmt->bind_param("sssi", $search_param, $search_param, $search_param, $offset, $limit);
} else {
    $stmt->bind_param("ii", $offset, $limit);
}
$stmt->execute();
$reviews = $stmt->get_result();

// Get products for filter dropdown - updated column name
$products_query = "SELECT id, tensanpham FROM sanpham ORDER BY tensanpham ASC";
$products_result = $conn->query($products_query);
$products = [];
while ($product = $products_result->fetch_assoc()) {
    $products[$product['id']] = $product['tensanpham'];
}

?>

<!-- Main content -->
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Quản lý đánh giá</h1>
    </div>

    <?php
    // Display success or error messages
    if (isset($_SESSION['success_message'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                ' . $_SESSION['success_message'] . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>';
        unset($_SESSION['success_message']);
    }

    if (isset($_SESSION['error_message'])) {
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                ' . $_SESSION['error_message'] . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>';
        unset($_SESSION['error_message']);
    }
    ?>

    <!-- Filter form -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-4">
                    <label for="search" class="form-label">Tìm kiếm</label>
                    <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="Nhập tên sản phẩm, nội dung đánh giá...">
                </div>
                
                <div class="col-md-3">
                    <label for="product_id" class="form-label">Sản phẩm</label>
                    <select class="form-select" id="product_id" name="product_id">
                        <option value="0">Tất cả sản phẩm</option>
                        <?php foreach ($products as $id => $name): ?>
                            <option value="<?php echo $id; ?>" <?php echo ($product_id == $id) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="rating" class="form-label">Số sao</label>
                    <select class="form-select" id="rating" name="rating">
                        <option value="0">Tất cả</option>
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <option value="<?php echo $i; ?>" <?php echo ($rating == $i) ? 'selected' : ''; ?>>
                                <?php echo $i; ?> sao
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="status" class="form-label">Trạng thái</label>
                    <select class="form-select" id="status" name="status">
                        <option value="-1" <?php echo ($status === -1) ? 'selected' : ''; ?>>Tất cả</option>
                        <option value="1" <?php echo ($status === 1) ? 'selected' : ''; ?>>Đang hiển thị</option>
                        <option value="0" <?php echo ($status === 0) ? 'selected' : ''; ?>>Đã ẩn</option>
                    </select>
                </div>
                
                <div class="col-md-1">
                    <label for="sort" class="form-label">Sắp xếp</label>
                    <select class="form-select" id="sort" name="sort">
                        <option value="newest" <?php echo ($sort_by == 'newest') ? 'selected' : ''; ?>>Mới nhất</option>
                        <option value="oldest" <?php echo ($sort_by == 'oldest') ? 'selected' : ''; ?>>Cũ nhất</option>
                        <option value="highest_rating" <?php echo ($sort_by == 'highest_rating') ? 'selected' : ''; ?>>Đánh giá cao</option>
                        <option value="lowest_rating" <?php echo ($sort_by == 'lowest_rating') ? 'selected' : ''; ?>>Đánh giá thấp</option>
                        <option value="product_name" <?php echo ($sort_by == 'product_name') ? 'selected' : ''; ?>>Theo sản phẩm</option>
                    </select>
                </div>
                
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-filter"></i> Lọc
                    </button>
                    <a href="reviews.php" class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i> Xóa bộ lọc
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Reviews list -->
    <div class="card">
        <div class="card-header bg-white">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Danh sách đánh giá</h5>
                <span class="badge bg-primary"><?php echo $total_reviews; ?> đánh giá</span>
            </div>
        </div>
        <div class="card-body p-0">
            <?php if ($reviews->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">ID</th>
                                <th scope="col">Sản phẩm</th>
                                <th scope="col">Khách hàng</th>
                                <th scope="col">Đánh giá</th>
                                <th scope="col">Nội dung</th>
                                <th scope="col">Ngày đánh giá</th>
                                <th scope="col">Trạng thái</th>
                                <th scope="col">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($review = $reviews->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $review['id']; ?></td>
                                    <td>
                                        <a href="../product-detail.php?id=<?php echo $review['id_sanpham']; ?>" target="_blank" class="text-decoration-none" title="Xem sản phẩm">
                                            <?php echo htmlspecialchars($review['tensanpham']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <a href="customer-detail.php?id=<?php echo $review['id_user']; ?>" class="text-decoration-none" title="Xem khách hàng">
                                            <?php echo htmlspecialchars($review['tenuser']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div class="rating">
                                            <?php
                                            for ($i = 1; $i <= 5; $i++) {
                                                if ($i <= $review['diem']) {
                                                    echo '<i class="bi bi-star-fill text-warning"></i>';
                                                } else {
                                                    echo '<i class="bi bi-star text-muted"></i>';
                                                }
                                            }
                                            ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="text-truncate" style="max-width: 200px;" title="<?php echo htmlspecialchars($review['noi_dung']); ?>">
                                            <?php echo htmlspecialchars($review['noi_dung']); ?>
                                        </div>
                                        
                                        <?php if (!empty($review['hinh_anh'])): ?>
                                            <small class="text-muted">
                                                <i class="bi bi-image"></i> Có hình ảnh
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($review['ngay_danhgia'])); ?></td>
                                    <td>
                                        <?php if ($review['trang_thai'] == 1): ?>
                                            <span class="badge bg-success">Hiển thị</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Đã ẩn</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="review-detail.php?id=<?php echo $review['id']; ?>" class="btn btn-outline-info">
                                                <i class="bi bi-eye"></i> Xem
                                            </a>
                                            <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="bi bi-three-dots"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <?php if ($review['trang_thai'] == 1): ?>
                                                    <li>
                                                        <a class="dropdown-item text-warning toggle-review-status" href="#" data-id="<?php echo $review['id']; ?>" data-action="hide">
                                                            <i class="bi bi-eye-slash"></i> Ẩn đánh giá
                                                        </a>
                                                    </li>
                                                <?php else: ?>
                                                    <li>
                                                        <a class="dropdown-item text-success toggle-review-status" href="#" data-id="<?php echo $review['id']; ?>" data-action="show">
                                                            <i class="bi bi-eye"></i> Hiển thị đánh giá
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <a class="dropdown-item text-danger delete-review" href="#" data-id="<?php echo $review['id']; ?>">
                                                        <i class="bi bi-trash"></i> Xóa đánh giá
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="p-4 text-center">
                    <p class="text-muted mb-0">Không tìm thấy đánh giá nào</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="card-footer bg-white">
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center mb-0">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=1<?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>&product_id=<?php echo $product_id; ?>&rating=<?php echo $rating; ?>&status=<?php echo $status; ?>&sort=<?php echo $sort_by; ?>">
                                    <i class="bi bi-chevron-double-left"></i>
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>&product_id=<?php echo $product_id; ?>&rating=<?php echo $rating; ?>&status=<?php echo $status; ?>&sort=<?php echo $sort_by; ?>">
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php 
                            $start_page = max(1, $page - 2);
                            $end_page = min($start_page + 4, $total_pages);
                            $start_page = max(1, $end_page - 4);
                            
                            for ($i = $start_page; $i <= $end_page; $i++): 
                        ?>
                            <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>&product_id=<?php echo $product_id; ?>&rating=<?php echo $rating; ?>&status=<?php echo $status; ?>&sort=<?php echo $sort_by; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>&product_id=<?php echo $product_id; ?>&rating=<?php echo $rating; ?>&status=<?php echo $status; ?>&sort=<?php echo $sort_by; ?>">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $total_pages; ?><?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>&product_id=<?php echo $product_id; ?>&rating=<?php echo $rating; ?>&status=<?php echo $status; ?>&sort=<?php echo $sort_by; ?>">
                                    <i class="bi bi-chevron-double-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Toggle status confirmation modal -->
<div class="modal fade" id="toggleStatusModal" tabindex="-1" aria-labelledby="toggleStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="toggleStatusModalLabel">Xác nhận thay đổi trạng thái</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="toggleStatusMessage">
                <!-- Message will be set dynamically -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <form id="toggleStatusForm" action="update_review_status.php" method="post">
                    <input type="hidden" name="review_id" id="toggleReviewId" value="">
                    <input type="hidden" name="action" id="toggleAction" value="">
                    <button type="submit" class="btn btn-primary">Xác nhận</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Delete confirmation modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Xác nhận xóa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Bạn có chắc chắn muốn xóa đánh giá này? Hành động này không thể hoàn tác.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <form id="deleteReviewForm" action="delete_review.php" method="post">
                    <input type="hidden" name="review_id" id="deleteReviewId" value="">
                    <button type="submit" class="btn btn-danger">Xóa</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for handling modals and actions -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Setup status toggle
        document.querySelectorAll('.toggle-review-status').forEach(function(element) {
            element.addEventListener('click', function(e) {
                e.preventDefault();
                
                const reviewId = this.dataset.id;
                const action = this.dataset.action;
                const message = action === 'hide' ? 
                    'Bạn có chắc chắn muốn ẩn đánh giá này?' : 
                    'Bạn có chắc chắn muốn hiển thị đánh giá này?';
                
                document.getElementById('toggleStatusMessage').textContent = message;
                document.getElementById('toggleReviewId').value = reviewId;
                document.getElementById('toggleAction').value = action;
                
                const modal = new bootstrap.Modal(document.getElementById('toggleStatusModal'));
                modal.show();
            });
        });
        
        // Setup delete review
        document.querySelectorAll('.delete-review').forEach(function(element) {
            element.addEventListener('click', function(e) {
                e.preventDefault();
                
                document.getElementById('deleteReviewId').value = this.dataset.id;
                
                const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
                modal.show();
            });
        });
    });
</script>

<?php
// Include footer
include('includes/footer.php');
?>
