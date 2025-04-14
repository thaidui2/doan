<?php
// Đặt tiêu đề trang
$page_title = 'Sản phẩm của người bán';

// Include header (sẽ kiểm tra đăng nhập)
include('includes/header.php');

// Include kết nối database
include('../config/config.php');

// Lấy ID người bán từ URL
$seller_id = isset($_GET['seller_id']) ? (int)$_GET['seller_id'] : 0;

// Kiểm tra ID người bán hợp lệ
if ($seller_id <= 0) {
    echo '<script>alert("ID người bán không hợp lệ!"); window.location.href = "customers.php";</script>';
    exit();
}

// Lấy thông tin người bán
$seller_query = $conn->prepare("SELECT u.*, 
    CASE WHEN u.loai_user = 1 THEN
        (SELECT COUNT(s.id_sanpham) FROM sanpham s WHERE s.id_nguoiban = u.id_user)
    ELSE 0 END AS so_san_pham
    FROM users u WHERE u.id_user = ? AND u.loai_user = 1");
$seller_query->bind_param("i", $seller_id);
$seller_query->execute();
$seller_result = $seller_query->get_result();

// Kiểm tra có phải người bán hay không
if ($seller_result->num_rows === 0) {
    echo '<script>alert("Không tìm thấy thông tin người bán!"); window.location.href = "customers.php";</script>';
    exit();
}

$seller = $seller_result->fetch_assoc();

// Thiết lập các biến lọc và tìm kiếm
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$status_filter = isset($_GET['status']) ? (int)$_GET['status'] : -1; // -1 là tất cả trạng thái
$search_keyword = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Thiết lập phân trang
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10; // Số sản phẩm trên mỗi trang
$offset = ($current_page - 1) * $per_page;

// Xây dựng truy vấn cơ bản
$query = "SELECT s.*, l.tenloai FROM sanpham s 
          LEFT JOIN loaisanpham l ON s.id_loai = l.id_loai
          WHERE s.id_nguoiban = ?";

// Thêm điều kiện lọc
$params = array($seller_id);
$param_types = "i";

// Lọc theo danh mục
if ($category_filter > 0) {
    $query .= " AND s.id_loai = ?";
    $params[] = $category_filter;
    $param_types .= "i";
}

// Lọc theo trạng thái
if ($status_filter != -1) {
    $query .= " AND s.trangthai = ?";
    $params[] = $status_filter;
    $param_types .= "i";
}

// Tìm kiếm theo từ khóa
if (!empty($search_keyword)) {
    $search_term = "%" . $search_keyword . "%";
    $query .= " AND (s.tensanpham LIKE ? OR s.mota LIKE ? OR s.id_sanpham LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $param_types .= "sss";
}

// Đếm tổng số sản phẩm (cho phân trang)
$count_query = "SELECT COUNT(*) AS total FROM sanpham s WHERE s.id_nguoiban = ?";
$count_params = array($seller_id);
$count_param_types = "i";

// Thêm các điều kiện lọc cho truy vấn đếm
if ($category_filter > 0) {
    $count_query .= " AND s.id_loai = ?";
    $count_params[] = $category_filter;
    $count_param_types .= "i";
}

if ($status_filter != -1) {
    $count_query .= " AND s.trangthai = ?";
    $count_params[] = $status_filter;
    $count_param_types .= "i";
}

if (!empty($search_keyword)) {
    $count_query .= " AND (s.tensanpham LIKE ? OR s.mota LIKE ? OR s.id_sanpham LIKE ?)";
    $count_params[] = $search_term;
    $count_params[] = $search_term;
    $count_params[] = $search_term;
    $count_param_types .= "sss";
}

$count_stmt = $conn->prepare($count_query);
if ($count_stmt) {
    $count_stmt->bind_param($count_param_types, ...$count_params);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total_products = $count_result->fetch_assoc()['total'];
    $total_pages = ceil($total_products / $per_page);
} else {
    // Xử lý lỗi
    $total_products = 0;
    $total_pages = 0;
}

// Sắp xếp
if ($sort_by == 'newest') {
    $query .= " ORDER BY s.id_sanpham DESC";
} elseif ($sort_by == 'oldest') {
    $query .= " ORDER BY s.id_sanpham ASC";
} elseif ($sort_by == 'price_asc') {
    $query .= " ORDER BY s.gia ASC";
} elseif ($sort_by == 'price_desc') {
    $query .= " ORDER BY s.gia DESC";
} elseif ($sort_by == 'name_asc') {
    $query .= " ORDER BY s.tensanpham ASC";
} elseif ($sort_by == 'name_desc') {
    $query .= " ORDER BY s.tensanpham DESC";
} elseif ($sort_by == 'views') {
    $query .= " ORDER BY s.luotxem DESC";
}

// Thêm giới hạn cho phân trang
$query .= " LIMIT ?, ?";
$params[] = $offset;
$params[] = $per_page;
$param_types .= "ii";

// Thực hiện truy vấn chính
$stmt = $conn->prepare($query);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Lấy danh sách danh mục cho bộ lọc
$categories_query = $conn->query("SELECT id_loai, tenloai FROM loaisanpham ORDER BY tenloai");
$categories = [];
while ($cat = $categories_query->fetch_assoc()) {
    $categories[$cat['id_loai']] = $cat['tenloai'];
}
?>

<!-- Include sidebar -->
<?php include('includes/sidebar.php'); ?>

<!-- Main content -->
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Trang chủ</a></li>
            <li class="breadcrumb-item"><a href="customers.php">Quản lý khách hàng</a></li>
            <li class="breadcrumb-item"><a href="customer-detail.php?id=<?php echo $seller_id; ?>"><?php echo htmlspecialchars($seller['tenuser']); ?></a></li>
            <li class="breadcrumb-item active" aria-current="page">Sản phẩm</li>
        </ol>
    </nav>
    
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Sản phẩm của <?php echo htmlspecialchars($seller['tenuser']); ?></h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="customer-detail.php?id=<?php echo $seller_id; ?>" class="btn btn-sm btn-outline-secondary me-2">
                <i class="bi bi-arrow-left"></i> Quay lại
            </a>
            <a href="add_product.php?seller_id=<?php echo $seller_id; ?>" class="btn btn-sm btn-primary">
                <i class="bi bi-plus-lg"></i> Thêm sản phẩm mới
            </a>
        </div>
    </div>
    
    <!-- Thông tin tổng quan người bán -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body text-center">
                    <h5 class="fw-bold">Tổng sản phẩm</h5>
                    <div class="display-6 text-primary"><?php echo $total_products; ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body text-center">
                    <h5 class="fw-bold">Sản phẩm hiển thị</h5>
                    <?php
                    $active_count_query = $conn->prepare("SELECT COUNT(*) AS count FROM sanpham WHERE id_nguoiban = ? AND trangthai = 1");
                    $active_count_query->bind_param("i", $seller_id);
                    $active_count_query->execute();
                    $active_count = $active_count_query->get_result()->fetch_assoc()['count'];
                    ?>
                    <div class="display-6 text-success"><?php echo $active_count; ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body text-center">
                    <h5 class="fw-bold">Sản phẩm ẩn</h5>
                    <?php
                    $inactive_count_query = $conn->prepare("SELECT COUNT(*) AS count FROM sanpham WHERE id_nguoiban = ? AND trangthai = 0");
                    $inactive_count_query->bind_param("i", $seller_id);
                    $inactive_count_query->execute();
                    $inactive_count = $inactive_count_query->get_result()->fetch_assoc()['count'];
                    ?>
                    <div class="display-6 text-warning"><?php echo $inactive_count; ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body text-center">
                    <h5 class="fw-bold">Tổng lượt xem</h5>
                    <?php
                    $views_query = $conn->prepare("SELECT SUM(luotxem) AS total_views FROM sanpham WHERE id_nguoiban = ?");
                    $views_query->bind_param("i", $seller_id);
                    $views_query->execute();
                    $views_result = $views_query->get_result();
                    $total_views = $views_result->fetch_assoc()['total_views'] ?? 0;
                    ?>
                    <div class="display-6 text-info"><?php echo number_format($total_views); ?></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filter and search -->
    <div class="row g-3 mb-4">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form action="" method="get" class="row g-2">
                        <input type="hidden" name="seller_id" value="<?php echo $seller_id; ?>">
                        
                        <div class="col-md-3">
                            <select name="category" class="form-select">
                                <option value="0">Tất cả danh mục</option>
                                <?php foreach ($categories as $id => $name): ?>
                                    <option value="<?php echo $id; ?>" <?php echo $category_filter == $id ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <select name="status" class="form-select">
                                <option value="-1" <?php echo $status_filter == -1 ? 'selected' : ''; ?>>Tất cả trạng thái</option>
                                <option value="1" <?php echo $status_filter == 1 ? 'selected' : ''; ?>>Đang hiển thị</option>
                                <option value="0" <?php echo $status_filter == 0 ? 'selected' : ''; ?>>Đang ẩn</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <select name="sort" class="form-select">
                                <option value="newest" <?php echo $sort_by == 'newest' ? 'selected' : ''; ?>>Mới nhất</option>
                                <option value="oldest" <?php echo $sort_by == 'oldest' ? 'selected' : ''; ?>>Cũ nhất</option>
                                <option value="price_asc" <?php echo $sort_by == 'price_asc' ? 'selected' : ''; ?>>Giá tăng dần</option>
                                <option value="price_desc" <?php echo $sort_by == 'price_desc' ? 'selected' : ''; ?>>Giá giảm dần</option>
                                <option value="name_asc" <?php echo $sort_by == 'name_asc' ? 'selected' : ''; ?>>Tên A-Z</option>
                                <option value="name_desc" <?php echo $sort_by == 'name_desc' ? 'selected' : ''; ?>>Tên Z-A</option>
                                <option value="views" <?php echo $sort_by == 'views' ? 'selected' : ''; ?>>Lượt xem</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-filter"></i> Lọc
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form action="" method="get" class="d-flex">
                        <input type="hidden" name="seller_id" value="<?php echo $seller_id; ?>">
                        <?php if ($category_filter > 0): ?>
                            <input type="hidden" name="category" value="<?php echo $category_filter; ?>">
                        <?php endif; ?>
                        <?php if ($status_filter != -1): ?>
                            <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
                        <?php endif; ?>
                        <?php if ($sort_by): ?>
                            <input type="hidden" name="sort" value="<?php echo $sort_by; ?>">
                        <?php endif; ?>
                        
                        <input type="text" name="search" class="form-control me-2" placeholder="Tìm kiếm sản phẩm..." value="<?php echo htmlspecialchars($search_keyword); ?>">
                        <button type="submit" class="btn btn-outline-primary">
                            <i class="bi bi-search"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Products table -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Danh sách sản phẩm</h5>
                <span class="badge bg-primary"><?php echo $result->num_rows; ?> sản phẩm</span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col" width="70">ID</th>
                            <th scope="col">Tên sản phẩm</th>
                            <th scope="col">Danh mục</th>
                            <th scope="col">Giá</th>
                            <th scope="col">Số lượng</th>
                            <th scope="col">Lượt xem</th>
                            <th scope="col">Trạng thái</th>
                            <th scope="col" width="150">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($product = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $product['id_sanpham']; ?></td>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($product['tensanpham']); ?></div>
                                    </td>
                                    <td><?php echo htmlspecialchars($product['tenloai']); ?></td>
                                    <td>
                                        <?php if ($product['gia'] < $product['giagoc']): ?>
                                            <div class="fw-bold text-danger"><?php echo number_format($product['gia'], 0, ',', '.'); ?> ₫</div>
                                            <div class="small text-decoration-line-through"><?php echo number_format($product['giagoc'], 0, ',', '.'); ?> ₫</div>
                                            <?php 
                                            $discount_percent = round(($product['giagoc'] - $product['gia']) / $product['giagoc'] * 100);
                                            ?>
                                            <span class="badge bg-danger">-<?php echo $discount_percent; ?>%</span>
                                        <?php else: ?>
                                            <div class="fw-bold"><?php echo number_format($product['gia'], 0, ',', '.'); ?> ₫</div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($product['soluong'] > 0): ?>
                                            <span class="badge bg-success"><?php echo $product['soluong']; ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Hết hàng</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo number_format($product['luotxem']); ?></td>
                                    <td>
                                        <?php 
                                        $status_info = getProductStatusInfo($product['trangthai']);
                                        ?>
                                        <span class="badge bg-<?php echo $status_info['class']; ?>">
                                            <?php echo $status_info['text']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="../sanpham-chitiet.php?id=<?php echo $product['id_sanpham']; ?>" target="_blank" class="btn btn-outline-primary">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="edit_product.php?id=<?php echo $product['id_sanpham']; ?>" class="btn btn-outline-secondary">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-danger toggle-product-status" 
                                                    data-id="<?php echo $product['id_sanpham']; ?>" 
                                                    data-status="<?php echo $product['trangthai']; ?>"
                                                    data-name="<?php echo htmlspecialchars($product['tensanpham']); ?>">
                                                <?php if ($product['trangthai'] == 1): ?>
                                                    <i class="bi bi-eye-slash"></i>
                                                <?php else: ?>
                                                    <i class="bi bi-eye"></i>
                                                <?php endif; ?>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <i class="bi bi-inbox display-4 text-muted d-block mb-3"></i>
                                    <p class="text-muted">Không tìm thấy sản phẩm nào.</p>
                                    <a href="seller_products.php?seller_id=<?php echo $seller_id; ?>" class="btn btn-sm btn-outline-primary">Xem tất cả sản phẩm</a>
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
    <div class="d-flex justify-content-center mt-4">
        <nav aria-label="Phân trang">
            <ul class="pagination">
                <?php if ($current_page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?seller_id=<?php echo $seller_id; ?>&page=1<?php echo $category_filter > 0 ? '&category='.$category_filter : ''; ?><?php echo $status_filter != -1 ? '&status='.$status_filter : ''; ?><?php echo !empty($search_keyword) ? '&search='.urlencode($search_keyword) : ''; ?><?php echo !empty($sort_by) ? '&sort='.$sort_by : ''; ?>" aria-label="Trang đầu">
                            <span aria-hidden="true">&laquo;&laquo;</span>
                        </a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="?seller_id=<?php echo $seller_id; ?>&page=<?php echo $current_page - 1; ?><?php echo $category_filter > 0 ? '&category='.$category_filter : ''; ?><?php echo $status_filter != -1 ? '&status='.$status_filter : ''; ?><?php echo !empty($search_keyword) ? '&search='.urlencode($search_keyword) : ''; ?><?php echo !empty($sort_by) ? '&sort='.$sort_by : ''; ?>" aria-label="Trang trước">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                <?php endif; ?>
                
                <?php 
                // Hiển thị tối đa 5 trang gần nhất
                $start_page = max(1, $current_page - 2);
                $end_page = min($total_pages, $current_page + 2);
                
                for ($i = $start_page; $i <= $end_page; $i++): 
                ?>
                    <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                        <a class="page-link" href="?seller_id=<?php echo $seller_id; ?>&page=<?php echo $i; ?><?php echo $category_filter > 0 ? '&category='.$category_filter : ''; ?><?php echo $status_filter != -1 ? '&status='.$status_filter : ''; ?><?php echo !empty($search_keyword) ? '&search='.urlencode($search_keyword) : ''; ?><?php echo !empty($sort_by) ? '&sort='.$sort_by : ''; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                
                <?php if ($current_page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?seller_id=<?php echo $seller_id; ?>&page=<?php echo $current_page + 1; ?><?php echo $category_filter > 0 ? '&category='.$category_filter : ''; ?><?php echo $status_filter != -1 ? '&status='.$status_filter : ''; ?><?php echo !empty($search_keyword) ? '&search='.urlencode($search_keyword) : ''; ?><?php echo !empty($sort_by) ? '&sort='.$sort_by : ''; ?>" aria-label="Trang sau">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="?seller_id=<?php echo $seller_id; ?>&page=<?php echo $total_pages; ?><?php echo $category_filter > 0 ? '&category='.$category_filter : ''; ?><?php echo $status_filter != -1 ? '&status='.$status_filter : ''; ?><?php echo !empty($search_keyword) ? '&search='.urlencode($search_keyword) : ''; ?><?php echo !empty($sort_by) ? '&sort='.$sort_by : ''; ?>" aria-label="Trang cuối">
                            <span aria-hidden="true">&raquo;&raquo;</span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</main>

<!-- Xác nhận modal -->
<div class="modal fade" id="confirmStatusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Xác nhận thay đổi trạng thái</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="modalBody">
                Bạn có chắc chắn muốn thay đổi trạng thái sản phẩm này?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" id="confirmStatusBtn">Xác nhận</button>
            </div>
        </div>
    </div>
</div>

<?php 
// JavaScript cho trang này
$page_specific_js = '
<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Xử lý toggle trạng thái sản phẩm
        var statusModal = new bootstrap.Modal(document.getElementById("confirmStatusModal"));
        var productIdToToggle = 0;
        var currentStatus = 0;
        
        document.querySelectorAll(".toggle-product-status").forEach(function(button) {
            button.addEventListener("click", function() {
                var productId = this.getAttribute("data-id");
                var status = parseInt(this.getAttribute("data-status"));
                var productName = this.getAttribute("data-name");
                
                productIdToToggle = productId;
                currentStatus = status;
                
                var newStatus = status === 1 ? "ẩn" : "hiển thị";
                document.getElementById("modalTitle").textContent = "Xác nhận " + newStatus + " sản phẩm";
                document.getElementById("modalBody").innerHTML = `Bạn có chắc chắn muốn <strong>${newStatus}</strong> sản phẩm <strong>${productName}</strong>?`;
                
                statusModal.show();
            });
        });
        
        // Xử lý khi nhấn nút Xác nhận trong modal
        document.getElementById("confirmStatusBtn").addEventListener("click", function() {
            if (productIdToToggle > 0) {
                // Chuyển đổi trạng thái
                var newStatus = currentStatus === 1 ? 0 : 1;
                
                // Gửi yêu cầu AJAX để cập nhật trạng thái sản phẩm
                fetch("ajax_toggle_product_status.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded",
                    },
                    body: `product_id=${productIdToToggle}&status=${newStatus}`,
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Reload trang để cập nhật dữ liệu
                        window.location.reload();
                    } else {
                        alert("Lỗi: " + data.message);
                    }
                })
                .catch(error => {
                    console.error("Error:", error);
                    alert("Đã xảy ra lỗi khi cập nhật trạng thái sản phẩm.");
                });
                
                statusModal.hide();
            }
        });
    });
</script>
';

// Include footer
include('includes/footer.php');
?>