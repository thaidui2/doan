<?php
// Kiểm tra đăng nhập và quyền admin
session_start();
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_loai']) || $_SESSION['admin_loai'] < 1) {
    header('Location: login.php');
    exit();
}

// Kết nối database
require_once '../config/config.php';
$current_page = 'reviews';
$page_title = 'Quản lý đánh giá';
$page_css = ['css/reviews.css']; // Liên kết với file CSS riêng

// Xử lý các action: hiện, ẩn, xóa đánh giá
if (isset($_GET['action']) && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $review_id = intval($_GET['id']);
    $action = $_GET['action'];
    $admin_id = $_SESSION['admin_id'];
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    
    // Kiểm tra đánh giá tồn tại
    $check_sql = "SELECT id, id_sanpham FROM danhgia WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param('i', $review_id);
    $check_stmt->execute();
    $review_result = $check_stmt->get_result();
    
    if ($review_result->num_rows > 0) {
        $review_data = $review_result->fetch_assoc();
        $product_id = $review_data['id_sanpham'];
        
        switch ($action) {
            case 'show':
                $update_sql = "UPDATE danhgia SET trang_thai = 1 WHERE id = ?";
                $log_action = 'show';
                $log_detail = "Đã hiển thị đánh giá ID: $review_id";
                $success_message = "Đã hiển thị đánh giá thành công";
                break;
                
            case 'hide':
                $update_sql = "UPDATE danhgia SET trang_thai = 0 WHERE id = ?";
                $log_action = 'hide';
                $log_detail = "Ẩn đánh giá ID: $review_id";
                $success_message = "Đã ẩn đánh giá thành công";
                break;
                
            case 'delete':
                $update_sql = "DELETE FROM danhgia WHERE id = ?";
                $log_action = 'delete';
                $log_detail = "Đã xóa đánh giá ID: $review_id";
                $success_message = "Đã xóa đánh giá thành công";
                break;
                
            default:
                header('Location: reviews.php?error=Hành động không hợp lệ');
                exit();
        }
        
        // Thực hiện cập nhật
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param('i', $review_id);
        
        if ($update_stmt->execute()) {
            // Ghi log hoạt động
            $log_sql = "INSERT INTO nhat_ky (id_user, hanh_dong, doi_tuong_loai, doi_tuong_id, chi_tiet, ip_address) 
                      VALUES (?, ?, 'review', ?, ?, ?)";
            $log_stmt = $conn->prepare($log_sql);
            $log_stmt->bind_param('isiss', $admin_id, $log_action, $review_id, $log_detail, $ip);
            $log_stmt->execute();
            
            header("Location: reviews.php?success=$success_message");
            exit();
        } else {
            header('Location: reviews.php?error=Có lỗi xảy ra khi thực hiện hành động');
            exit();
        }
    } else {
        header('Location: reviews.php?error=Đánh giá không tồn tại');
        exit();
    }
}

// Thiết lập tham số tìm kiếm và lọc
$search = $_GET['search'] ?? '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$rating = isset($_GET['rating']) ? $_GET['rating'] : '';
$product = isset($_GET['product']) ? intval($_GET['product']) : '';
$customer = isset($_GET['customer']) ? intval($_GET['customer']) : '';
$sort = $_GET['sort'] ?? 'newest';

// Phân trang
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$items_per_page = 10;
$offset = ($page - 1) * $items_per_page;

// Xây dựng câu truy vấn
$query = "SELECT d.*, s.tensanpham, s.hinhanh AS product_image, s.slug AS product_slug, 
          u.ten AS customer_name, u.email AS customer_email 
          FROM danhgia d
          JOIN sanpham s ON d.id_sanpham = s.id
          JOIN users u ON d.id_user = u.id
          WHERE 1=1 ";

$count_query = "SELECT COUNT(*) AS total 
               FROM danhgia d
               JOIN sanpham s ON d.id_sanpham = s.id
               JOIN users u ON d.id_user = u.id
               WHERE 1=1 ";

$params = [];
$param_types = "";

// Thêm điều kiện tìm kiếm
if (!empty($search)) {
    $search_term = "%$search%";
    $query .= " AND (s.tensanpham LIKE ? OR d.noi_dung LIKE ? OR u.ten LIKE ? OR u.email LIKE ?)";
    $count_query .= " AND (s.tensanpham LIKE ? OR d.noi_dung LIKE ? OR u.ten LIKE ? OR u.email LIKE ?)";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
    $param_types .= "ssss";
}

// Lọc theo trạng thái
if ($status !== '') {
    $query .= " AND d.trang_thai = ?";
    $count_query .= " AND d.trang_thai = ?";
    $params[] = $status;
    $param_types .= "i";
}

// Lọc theo điểm đánh giá
if ($rating !== '') {
    $query .= " AND d.diem = ?";
    $count_query .= " AND d.diem = ?";
    $params[] = $rating;
    $param_types .= "i";
}

// Lọc theo sản phẩm
if ($product !== '') {
    $query .= " AND d.id_sanpham = ?";
    $count_query .= " AND d.id_sanpham = ?";
    $params[] = $product;
    $param_types .= "i";
}

// Lọc theo khách hàng
if ($customer !== '') {
    $query .= " AND d.id_user = ?";
    $count_query .= " AND d.id_user = ?";
    $params[] = $customer;
    $param_types .= "i";
}

// Sắp xếp
switch ($sort) {
    case 'oldest':
        $query .= " ORDER BY d.ngay_danhgia ASC";
        break;
    case 'highest':
        $query .= " ORDER BY d.diem DESC";
        break;
    case 'lowest':
        $query .= " ORDER BY d.diem ASC";
        break;
    default: // newest
        $query .= " ORDER BY d.ngay_danhgia DESC";
}

// Thêm phân trang
$query .= " LIMIT ? OFFSET ?";
$params[] = $items_per_page;
$params[] = $offset;
$param_types .= "ii";

// Thực hiện truy vấn đếm tổng số
$count_stmt = $conn->prepare($count_query);
if (!empty($param_types)) {
    // Xóa 2 tham số cuối (limit và offset) vì query đếm không cần
    $count_param_types = substr($param_types, 0, -2);
    $count_params = array_slice($params, 0, -2);
    
    // Chỉ bind_param nếu có parameter types
    if (!empty($count_param_types)) {
        $count_stmt->bind_param($count_param_types, ...$count_params);
    }
}
$count_stmt->execute();
$total_items = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_items / $items_per_page);

// Thực hiện truy vấn danh sách
$stmt = $conn->prepare($query);
if (!empty($param_types)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$reviews = $stmt->get_result();

// Lấy danh sách sản phẩm cho dropdown lọc
$products_sql = "SELECT id, tensanpham FROM sanpham ORDER BY tensanpham";
$products_result = $conn->query($products_sql);

// Lấy danh sách khách hàng cho dropdown lọc
$customers_sql = "SELECT DISTINCT u.id, u.ten, u.email 
                 FROM users u
                 JOIN danhgia d ON u.id = d.id_user
                 ORDER BY u.ten";
$customers_result = $conn->query($customers_sql);

// Include header và sidebar
include 'includes/header.php';
include 'includes/sidebar.php';
?>
    
    <!-- Main Content -->
    <div class="col-md-10 col-lg-10 ms-auto">
        <div class="container-fluid px-4 py-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0 text-gray-800">Quản lý đánh giá</h1>
            </div>
            
            <!-- Thông báo -->
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($_GET['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($_GET['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <!-- Tìm kiếm và lọc -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Tìm kiếm và lọc</h6>
                    <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#filtersCollapse">
                        <i class="fas fa-filter me-1"></i> Lọc nâng cao
                    </button>
                </div>
                <div class="card-body">
                    <form method="GET" action="">
                        <div class="row">
                            <div class="col-md-10 mb-3">
                                <label for="search" class="form-label">Tìm kiếm</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       placeholder="Tên sản phẩm, nội dung đánh giá, tên khách hàng..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-2 mb-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search me-1"></i> Tìm kiếm
                                </button>
                            </div>
                        </div>
                        
                        <!-- Lọc nâng cao -->
                        <div class="collapse <?php echo ($status !== '' || $rating !== '' || $product !== '' || $customer !== '' || $sort != 'newest') ? 'show' : ''; ?>" id="filtersCollapse">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label for="status" class="form-label">Trạng thái</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="">Tất cả</option>
                                        <option value="1" <?php echo ($status === '1') ? 'selected' : ''; ?>>Hiển thị</option>
                                        <option value="0" <?php echo ($status === '0') ? 'selected' : ''; ?>>Đã ẩn</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="rating" class="form-label">Số sao</label>
                                    <select class="form-select" id="rating" name="rating">
                                        <option value="">Tất cả</option>
                                        <option value="5" <?php echo ($rating === '5') ? 'selected' : ''; ?>>5 sao</option>
                                        <option value="4" <?php echo ($rating === '4') ? 'selected' : ''; ?>>4 sao</option>
                                        <option value="3" <?php echo ($rating === '3') ? 'selected' : ''; ?>>3 sao</option>
                                        <option value="2" <?php echo ($rating === '2') ? 'selected' : ''; ?>>2 sao</option>
                                        <option value="1" <?php echo ($rating === '1') ? 'selected' : ''; ?>>1 sao</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="product" class="form-label">Sản phẩm</label>
                                    <select class="form-select" id="product" name="product">
                                        <option value="">Tất cả sản phẩm</option>
                                        <?php while ($prod = $products_result->fetch_assoc()): ?>
                                            <option value="<?php echo $prod['id']; ?>" <?php echo ($product == $prod['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($prod['tensanpham']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="customer" class="form-label">Khách hàng</label>
                                    <select class="form-select" id="customer" name="customer">
                                        <option value="">Tất cả khách hàng</option>
                                        <?php while ($cust = $customers_result->fetch_assoc()): ?>
                                            <option value="<?php echo $cust['id']; ?>" <?php echo ($customer == $cust['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cust['ten']) . ' (' . htmlspecialchars($cust['email']) . ')'; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label for="sort" class="form-label">Sắp xếp</label>
                                    <select class="form-select" id="sort" name="sort">
                                        <option value="newest" <?php echo ($sort === 'newest') ? 'selected' : ''; ?>>Mới nhất</option>
                                        <option value="oldest" <?php echo ($sort === 'oldest') ? 'selected' : ''; ?>>Cũ nhất</option>
                                        <option value="highest" <?php echo ($sort === 'highest') ? 'selected' : ''; ?>>Cao đến thấp (5→1 sao)</option>
                                        <option value="lowest" <?php echo ($sort === 'lowest') ? 'selected' : ''; ?>>Thấp đến cao (1→5 sao)</option>
                                    </select>
                                </div>
                                <div class="col-md-9 mb-3 d-flex align-items-end">
                                    <a href="reviews.php" class="btn btn-outline-secondary me-2">
                                        <i class="fas fa-redo me-1"></i> Đặt lại bộ lọc
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Danh sách đánh giá -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        Danh sách đánh giá
                        <span class="badge bg-secondary ms-1"><?php echo $total_items; ?> đánh giá</span>
                    </h6>
                    <?php if (!empty($search) || $status !== '' || $rating !== '' || $product !== '' || $customer !== ''): ?>
                        <span class="badge bg-info">Đã lọc</span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if ($reviews && $reviews->num_rows > 0): ?>
                        <div class="reviews-container">
                            <?php while ($review = $reviews->fetch_assoc()): ?>
                                <div class="review-item card mb-3 <?php echo $review['trang_thai'] ? '' : 'review-hidden'; ?>">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div>
                                                <h5 class="card-title"><?php echo htmlspecialchars($review['customer_name']); ?></h5>
                                                <div class="small text-muted"><?php echo htmlspecialchars($review['customer_email']); ?></div>
                                                <div class="review-date small"><?php echo date('d/m/Y H:i', strtotime($review['ngay_danhgia'])); ?></div>
                                            </div>
                                            <div class="d-flex">
                                                <?php if ($review['trang_thai']): ?>
                                                    <span class="badge bg-success me-2">Hiển thị</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary me-2">Đã ẩn</span>
                                                <?php endif; ?>
                                                <div class="rating">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <?php if ($i <= $review['diem']): ?>
                                                            <i class="fas fa-star"></i>
                                                        <?php else: ?>
                                                            <i class="far fa-star"></i>
                                                        <?php endif; ?>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex">
                                            <div class="product-info me-3">
                                                <?php if (!empty($review['product_image'])): ?>
                                                    <img src="../<?php echo htmlspecialchars($review['product_image']); ?>" alt="<?php echo htmlspecialchars($review['tensanpham']); ?>" 
                                                         class="product-thumbnail">
                                                <?php else: ?>
                                                    <div class="product-thumbnail-placeholder">
                                                        <i class="fas fa-image"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="product-name">
                                                    <a href="../product.php?slug=<?php echo htmlspecialchars($review['product_slug']); ?>" target="_blank" title="<?php echo htmlspecialchars($review['tensanpham']); ?>">
                                                        <?php echo htmlspecialchars($review['tensanpham']); ?>
                                                    </a>
                                                </div>
                                            </div>
                                            <div class="review-content flex-grow-1">
                                                <p class="mb-2"><?php echo nl2br(htmlspecialchars($review['noi_dung'])); ?></p>
                                                
                                                <?php if (!empty($review['hinh_anh'])): ?>
                                                    <div>
                                                        <?php 
                                                        // Direct image path handling - images are stored in uploads/reviews/
                                                        $image_path = $review['hinh_anh'];
                                                        $url_path = "../uploads/reviews/{$image_path}";
                                                        ?>
                                                        <a href="<?php echo $url_path; ?>" target="_blank" class="review-image-link">
                                                            <img src="<?php echo $url_path; ?>" alt="Review image" 
                                                                 class="review-image"
                                                                 onerror="this.onerror=null; this.src='../assets/img/no-image.png'; this.classList.add('img-error');">
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if ($review['khuyen_dung']): ?>
                                                    <div class="mt-2">
                                                        <span class="badge bg-info">
                                                            <i class="fas fa-thumbs-up me-1"></i> Khuyên dùng
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="text-end mt-3">
                                            <div class="btn-group">
                                                <?php if ($review['trang_thai']): ?>
                                                    <a href="reviews.php?action=hide&id=<?php echo $review['id']; ?>" class="btn btn-sm btn-warning" 
                                                       onclick="return confirm('Bạn có chắc muốn ẩn đánh giá này?')">
                                                        <i class="fas fa-eye-slash me-1"></i> Ẩn
                                                    </a>
                                                <?php else: ?>
                                                    <a href="reviews.php?action=show&id=<?php echo $review['id']; ?>" class="btn btn-sm btn-success"
                                                       onclick="return confirm('Bạn có chắc muốn hiển thị đánh giá này?')">
                                                        <i class="fas fa-eye me-1"></i> Hiện
                                                    </a>
                                                <?php endif; ?>
                                                <a href="reviews.php?action=delete&id=<?php echo $review['id']; ?>" class="btn btn-sm btn-danger"
                                                   onclick="return confirm('Bạn có chắc chắn muốn xóa đánh giá này? Hành động này không thể hoàn tác!')">
                                                    <i class="fas fa-trash-alt me-1"></i> Xóa
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <div class="text-muted mb-3">
                                <i class="fas fa-star fa-3x"></i>
                            </div>
                            <h5>Không tìm thấy đánh giá nào</h5>
                            <p>Thử thay đổi tiêu chí tìm kiếm hoặc bộ lọc</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Phân trang -->
                <?php if ($total_pages > 1): ?>
                    <div class="card-footer d-flex justify-content-between align-items-center">
                        <div>
                            Hiển thị <?php echo min(($page - 1) * $items_per_page + 1, $total_items); ?> - 
                            <?php echo min($page * $items_per_page, $total_items); ?> 
                            trong <?php echo $total_items; ?> đánh giá
                        </div>
                        <nav>
                            <ul class="pagination">
                                <?php 
                                $query_params = http_build_query(array_filter([
                                    'search' => $search,
                                    'status' => $status,
                                    'rating' => $rating,
                                    'product' => $product,
                                    'customer' => $customer,
                                    'sort' => $sort
                                ]));
                                $query_string = !empty($query_params) ? '&' . $query_params : '';
                                ?>
                                
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=1<?php echo $query_string; ?>">
                                            <i class="fas fa-angle-double-left"></i>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo ($page - 1) . $query_string; ?>">
                                            <i class="fas fa-angle-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php
                                $start_page = max(1, min($page - 2, $total_pages - 4));
                                $end_page = min($total_pages, max($page + 2, 5));
                                
                                for ($i = $start_page; $i <= $end_page; $i++):
                                ?>
                                    <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i . $query_string; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo ($page + 1) . $query_string; ?>">
                                            <i class="fas fa-angle-right"></i>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $total_pages . $query_string; ?>">
                                            <i class="fas fa-angle-double-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

<!-- Thêm footer -->
<?php include 'includes/footer.php'; ?>
<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/reviews.js"></script>
</body>
</html>
