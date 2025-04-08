<?php
// Set page title
$page_title = 'Quản Lý Đánh Giá';

// Include header (will check for login)
include('includes/header.php');

// Include database connection
include('../config/config.php');

// Check if user has permission to view reviews
checkPermissionRedirect('product_view');

// Variables for filtering and searching
$search_keyword = isset($_GET['search']) ? trim($_GET['search']) : '';
$rating_filter = isset($_GET['rating']) ? (int)$_GET['rating'] : 0; // 0 means all ratings
$status_filter = isset($_GET['status']) ? (int)$_GET['status'] : -1; // -1 means all statuses
$product_filter = isset($_GET['product']) ? (int)$_GET['product'] : 0;
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'ngaydanhgia';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// Build query
$query = "SELECT dg.*, sp.tensanpham, u.tenuser, u.taikhoan
          FROM danhgia dg
          JOIN sanpham sp ON dg.id_sanpham = sp.id_sanpham
          JOIN users u ON dg.id_user = u.id_user";

// Add search conditions
$where_conditions = [];
if (!empty($search_keyword)) {
    $search_keyword = $conn->real_escape_string($search_keyword);
    $where_conditions[] = "(dg.noidung LIKE '%$search_keyword%' OR sp.tensanpham LIKE '%$search_keyword%' OR u.tenuser LIKE '%$search_keyword%' OR u.taikhoan LIKE '%$search_keyword%')";
}

if ($rating_filter > 0) {
    $where_conditions[] = "dg.diemdanhgia = $rating_filter";
}

if ($status_filter !== -1) {
    $where_conditions[] = "dg.trangthai = $status_filter";
}

if ($product_filter > 0) {
    $where_conditions[] = "dg.id_sanpham = $product_filter";
}

// Combine conditions
if (!empty($where_conditions)) {
    $query .= " WHERE " . implode(" AND ", $where_conditions);
}

// Add sorting
$valid_sort_columns = ['id_danhgia', 'diemdanhgia', 'ngaydanhgia', 'trangthai'];
if (!in_array($sort_by, $valid_sort_columns)) {
    $sort_by = 'ngaydanhgia';
}

$sort_order = ($sort_order === 'ASC') ? 'ASC' : 'DESC';
$query .= " ORDER BY dg.$sort_by $sort_order";

// Pagination
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($current_page - 1) * $per_page;

// Count total rows for pagination
$count_result = $conn->query(str_replace("dg.*, sp.tensanpham, u.tenuser, u.taikhoan", "COUNT(*) as total", $query));
$total_rows = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $per_page);

// Add limit for pagination
$query .= " LIMIT $offset, $per_page";

// Execute query
$result = $conn->query($query);

// Get products for filter dropdown
$products_query = "SELECT id_sanpham, tensanpham FROM sanpham ORDER BY tensanpham";
$products_result = $conn->query($products_query);

// Process approve/hide action if requested via AJAX (will be handled by separate ajax file)
?>

<!-- Include sidebar -->
<?php include('includes/sidebar.php'); ?>

<!-- Main content -->
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Quản Lý Đánh Giá</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="exportReviews">
                <i class="bi bi-download"></i> Xuất CSV
            </button>
        </div>
    </div>

    <?php
    // Display success/error messages if they exist
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

    <!-- Search and filter form -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-4">
                    <label for="search" class="form-label">Tìm kiếm</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           value="<?php echo htmlspecialchars($search_keyword); ?>" 
                           placeholder="Nội dung, tên sản phẩm, tên khách hàng...">
                </div>
                <div class="col-md-2">
                    <label for="rating" class="form-label">Đánh giá</label>
                    <select class="form-select" id="rating" name="rating">
                        <option value="0" <?php echo $rating_filter === 0 ? 'selected' : ''; ?>>Tất cả sao</option>
                        <option value="5" <?php echo $rating_filter === 5 ? 'selected' : ''; ?>>5 sao</option>
                        <option value="4" <?php echo $rating_filter === 4 ? 'selected' : ''; ?>>4 sao</option>
                        <option value="3" <?php echo $rating_filter === 3 ? 'selected' : ''; ?>>3 sao</option>
                        <option value="2" <?php echo $rating_filter === 2 ? 'selected' : ''; ?>>2 sao</option>
                        <option value="1" <?php echo $rating_filter === 1 ? 'selected' : ''; ?>>1 sao</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label">Trạng thái</label>
                    <select class="form-select" id="status" name="status">
                        <option value="-1" <?php echo $status_filter === -1 ? 'selected' : ''; ?>>Tất cả trạng thái</option>
                        <option value="1" <?php echo $status_filter === 1 ? 'selected' : ''; ?>>Hiển thị</option>
                        <option value="0" <?php echo $status_filter === 0 ? 'selected' : ''; ?>>Đã ẩn</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="product" class="form-label">Sản phẩm</label>
                    <select class="form-select" id="product" name="product">
                        <option value="0">Tất cả sản phẩm</option>
                        <?php while ($product = $products_result->fetch_assoc()): ?>
                            <option value="<?php echo $product['id_sanpham']; ?>" <?php echo $product_filter == $product['id_sanpham'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($product['tensanpham']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="bi bi-search"></i> Tìm kiếm
                    </button>
                    <a href="reviews.php" class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i> Xóa bộ lọc
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Reviews table -->
    <div class="card">
        <div class="card-header bg-white">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Danh sách đánh giá</h5>
                <span class="badge bg-secondary"><?php echo $total_rows; ?> đánh giá</span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">ID</th>
                            <th scope="col">Sản phẩm</th>
                            <th scope="col">Khách hàng</th>
                            <th scope="col">Đánh giá</th>
                            <th scope="col">Nội dung</th>
                            <th scope="col">Ngày</th>
                            <th scope="col">Trạng thái</th>
                            <th scope="col">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($review = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $review['id_danhgia']; ?></td>
                                    <td>
                                        <a href="../product-detail.php?id=<?php echo $review['id_sanpham']; ?>" target="_blank" class="text-decoration-none">
                                            <?php echo htmlspecialchars($review['tensanpham']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($review['tenuser'] . ' (' . $review['taikhoan'] . ')'); ?></td>
                                    <td>
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="bi bi-star<?php echo ($i <= $review['diemdanhgia']) ? '-fill text-warning' : ''; ?>"></i>
                                        <?php endfor; ?>
                                    </td>
                                    <td>
                                        <div class="review-content">
                                            <?php 
                                            if (strlen($review['noidung']) > 50) {
                                                echo htmlspecialchars(substr($review['noidung'], 0, 50) . '...');
                                                echo '<a href="#" class="show-full-review text-primary ms-1" data-content="' . htmlspecialchars($review['noidung']) . '">Xem thêm</a>';
                                            } else {
                                                echo htmlspecialchars($review['noidung'] ?: 'N/A');
                                            }
                                            ?>
                                        </div>
                                        <?php if (!empty($review['hinhanh'])): ?>
                                            <div class="mt-1">
                                                <a href="../uploads/reviews/<?php echo $review['hinhanh']; ?>" target="_blank">
                                                    <img src="../uploads/reviews/<?php echo $review['hinhanh']; ?>" class="img-thumbnail" alt="Review image" style="height: 40px;">
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($review['ngaydanhgia'])); ?></td>
                                    <td>
                                        <?php if ($review['trangthai'] == 1): ?>
                                            <span class="badge bg-success">Hiển thị</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Đã ẩn</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <?php if (hasPermission('product_edit')): ?>
                                                <?php if ($review['trangthai'] == 1): ?>
                                                    <button type="button" class="btn btn-outline-secondary toggle-status" 
                                                        data-id="<?php echo $review['id_danhgia']; ?>" 
                                                        data-status="0" 
                                                        title="Ẩn đánh giá">
                                                        <i class="bi bi-eye-slash"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-outline-success toggle-status" 
                                                        data-id="<?php echo $review['id_danhgia']; ?>" 
                                                        data-status="1" 
                                                        title="Hiển thị đánh giá">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            
                                            <?php if (hasPermission('product_delete')): ?>
                                                <button type="button" class="btn btn-outline-danger delete-review" 
                                                    data-id="<?php echo $review['id_danhgia']; ?>" 
                                                    title="Xóa đánh giá">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <div class="text-muted">Không tìm thấy đánh giá nào</div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $current_page - 1; ?>&search=<?php echo urlencode($search_keyword); ?>&rating=<?php echo $rating_filter; ?>&status=<?php echo $status_filter; ?>&product=<?php echo $product_filter; ?>&sort=<?php echo $sort_by; ?>&order=<?php echo $sort_order; ?>">
                        <i class="bi bi-chevron-left"></i> Trước
                    </a>
                </li>
                
                <?php
                $start_page = max(1, $current_page - 2);
                $end_page = min($total_pages, $current_page + 2);
                
                if ($start_page > 1) {
                    echo '<li class="page-item"><a class="page-link" href="?page=1&search=' . urlencode($search_keyword) . '&rating=' . $rating_filter . '&status=' . $status_filter . '&product=' . $product_filter . '&sort=' . $sort_by . '&order=' . $sort_order . '">1</a></li>';
                    if ($start_page > 2) {
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                }
                
                for ($i = $start_page; $i <= $end_page; $i++) {
                    echo '<li class="page-item ' . (($i == $current_page) ? 'active' : '') . '">';
                    echo '<a class="page-link" href="?page=' . $i . '&search=' . urlencode($search_keyword) . '&rating=' . $rating_filter . '&status=' . $status_filter . '&product=' . $product_filter . '&sort=' . $sort_by . '&order=' . $sort_order . '">' . $i . '</a>';
                    echo '</li>';
                }
                
                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) {
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                    echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&search=' . urlencode($search_keyword) . '&rating=' . $rating_filter . '&status=' . $status_filter . '&product=' . $product_filter . '&sort=' . $sort_by . '&order=' . $sort_order . '">' . $total_pages . '</a></li>';
                }
                ?>
                
                <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $current_page + 1; ?>&search=<?php echo urlencode($search_keyword); ?>&rating=<?php echo $rating_filter; ?>&status=<?php echo $status_filter; ?>&product=<?php echo $product_filter; ?>&sort=<?php echo $sort_by; ?>&order=<?php echo $sort_order; ?>">
                        Tiếp <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</main>

<!-- Full Review Modal -->
<div class="modal fade" id="reviewDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Chi tiết đánh giá</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="fullReviewContent"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div class="modal fade" id="confirmationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Xác nhận</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="modalBody">
                Bạn có chắc chắn muốn thực hiện hành động này?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" id="confirmAction">Xác nhận</button>
            </div>
        </div>
    </div>
</div>

<!-- Page specific JavaScript -->
<?php 
$page_specific_js = '
<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Handle "Show more" functionality for review content
        document.querySelectorAll(".show-full-review").forEach(link => {
            link.addEventListener("click", function(e) {
                e.preventDefault();
                const content = this.getAttribute("data-content");
                document.getElementById("fullReviewContent").textContent = content;
                const modal = new bootstrap.Modal(document.getElementById("reviewDetailModal"));
                modal.show();
            });
        });

        // Toggle review status (show/hide)
        document.querySelectorAll(".toggle-status").forEach(button => {
            button.addEventListener("click", function(e) {
                e.preventDefault();
                const reviewId = this.getAttribute("data-id");
                const newStatus = this.getAttribute("data-status");
                const action = newStatus == "1" ? "hiển thị" : "ẩn";
                
                // Set modal content
                document.getElementById("modalTitle").textContent = newStatus == "1" ? "Hiển thị đánh giá" : "Ẩn đánh giá";
                document.getElementById("modalBody").innerHTML = `Bạn có chắc chắn muốn <strong>${action}</strong> đánh giá này?`;
                
                // Set confirm button style
                document.getElementById("confirmAction").className = `btn ${newStatus == "1" ? "btn-success" : "btn-secondary"}`;
                
                // Show confirmation modal
                const modal = new bootstrap.Modal(document.getElementById("confirmationModal"));
                modal.show();
                
                // Set up action for confirm button
                document.getElementById("confirmAction").onclick = function() {
                    // Close the modal
                    modal.hide();
                    
                    // Send AJAX request to update status
                    updateReviewStatus(reviewId, newStatus);
                };
            });
        });
        
        // Delete review
        document.querySelectorAll(".delete-review").forEach(button => {
            button.addEventListener("click", function(e) {
                e.preventDefault();
                const reviewId = this.getAttribute("data-id");
                
                // Set modal content
                document.getElementById("modalTitle").textContent = "Xóa đánh giá";
                document.getElementById("modalBody").innerHTML = "Bạn có chắc chắn muốn <strong>xóa</strong> đánh giá này? Hành động này không thể hoàn tác.";
                
                // Set confirm button style
                document.getElementById("confirmAction").className = "btn btn-danger";
                
                // Show confirmation modal
                const modal = new bootstrap.Modal(document.getElementById("confirmationModal"));
                modal.show();
                
                // Set up action for confirm button
                document.getElementById("confirmAction").onclick = function() {
                    // Close the modal
                    modal.hide();
                    
                    // Send AJAX request to delete review
                    deleteReview(reviewId);
                };
            });
        });
        
        // Function to update review status via AJAX
        function updateReviewStatus(reviewId, status) {
            fetch("ajax/update-review-status.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                },
                body: `id=${reviewId}&status=${status}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast("Cập nhật trạng thái thành công!", "success");
                    // Reload page after 1 second
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showToast(`Lỗi: ${data.message}`, "danger");
                }
            })
            .catch(error => {
                console.error("Error:", error);
                showToast("Đã xảy ra lỗi khi cập nhật trạng thái!", "danger");
            });
        }
        
        // Function to delete review via AJAX
        function deleteReview(reviewId) {
            fetch("ajax/delete-review.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                },
                body: `id=${reviewId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast("Xóa đánh giá thành công!", "success");
                    // Reload page after 1 second
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showToast(`Lỗi: ${data.message}`, "danger");
                }
            })
            .catch(error => {
                console.error("Error:", error);
                showToast("Đã xảy ra lỗi khi xóa đánh giá!", "danger");
            });
        }
        
        // Function to show toast notification
        function showToast(message, type = "info") {
            const toastContainer = document.querySelector(".toast-container");
            if (!toastContainer) return;
            
            const toastId = `toast-${Date.now()}`;
            
            const toastHtml = `
                <div id="${toastId}" class="toast align-items-center text-bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body">
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            `;
            
            toastContainer.insertAdjacentHTML("beforeend", toastHtml);
            
            const toastElement = document.getElementById(toastId);
            const toast = new bootstrap.Toast(toastElement, {
                autohide: true,
                delay: 3000
            });
            toast.show();
            
            toastElement.addEventListener("hidden.bs.toast", function() {
                toastElement.remove();
            });
        }
        
        // Export reviews to CSV
        document.getElementById("exportReviews").addEventListener("click", function() {
            window.location.href = "export-reviews.php" + window.location.search;
        });
    });
</script>
';

// Include toast container for notifications
echo '<div class="toast-container position-fixed bottom-0 end-0 p-3"></div>';

// Include footer
include('includes/footer.php');
?>
