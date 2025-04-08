<?php
// Thiết lập tiêu đề trang
$page_title = "Quản Lý Đánh Giá";

// Include header
include('includes/header.php');

// Xử lý phân trang
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Xử lý lọc
$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$rating = isset($_GET['rating']) ? (int)$_GET['rating'] : 0; // 0 = tất cả
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Xây dựng câu query
$sql_conditions = ["sp.id_nguoiban = ?"]; // Chỉ lấy đánh giá sản phẩm của người bán này
$params = [$user_id];
$param_types = "i";

if ($product_id > 0) {
    $sql_conditions[] = "dg.id_sanpham = ?";
    $params[] = $product_id;
    $param_types .= "i";
}

if ($rating > 0 && $rating <= 5) {
    $sql_conditions[] = "dg.diemdanhgia = ?";
    $params[] = $rating;
    $param_types .= "i";
}

// Xây dựng câu query đếm tổng số đánh giá
$count_sql = "
    SELECT COUNT(dg.id_danhgia) as total
    FROM danhgia dg
    JOIN sanpham sp ON dg.id_sanpham = sp.id_sanpham
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

// Xây dựng câu query lấy danh sách đánh giá
$sql = "
    SELECT 
        dg.*,
        sp.tensanpham,
        sp.hinhanh as product_image,
        u.tenuser,
        u.anh_dai_dien
    FROM danhgia dg
    JOIN sanpham sp ON dg.id_sanpham = sp.id_sanpham
    JOIN users u ON dg.id_user = u.id_user
    WHERE " . implode(" AND ", $sql_conditions) . "
";

// Thêm sắp xếp
switch ($sort) {
    case 'rating_high':
        $sql .= " ORDER BY dg.diemdanhgia DESC";
        break;
    case 'rating_low':
        $sql .= " ORDER BY dg.diemdanhgia ASC";
        break;
    case 'oldest':
        $sql .= " ORDER BY dg.ngaydanhgia ASC";
        break;
    default: // newest
        $sql .= " ORDER BY dg.ngaydanhgia DESC";
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
$reviews = $stmt->get_result();

// Lấy danh sách sản phẩm của người bán để hiển thị dropdown lọc
$products_query = $conn->prepare("SELECT id_sanpham, tensanpham FROM sanpham WHERE id_nguoiban = ? ORDER BY tensanpham");
$products_query->bind_param("i", $user_id);
$products_query->execute();
$products_result = $products_query->get_result();
$products = [];
while ($product = $products_result->fetch_assoc()) {
    $products[] = $product;
}

// Lấy thống kê đánh giá theo số sao
$rating_stats_query = $conn->prepare("
    SELECT 
        dg.diemdanhgia, 
        COUNT(*) as count 
    FROM danhgia dg
    JOIN sanpham sp ON dg.id_sanpham = sp.id_sanpham
    WHERE sp.id_nguoiban = ?
    GROUP BY dg.diemdanhgia
    ORDER BY dg.diemdanhgia DESC
");

$rating_stats_query->bind_param("i", $user_id);
$rating_stats_query->execute();
$rating_stats_result = $rating_stats_query->get_result();

$rating_stats = [
    5 => 0,
    4 => 0,
    3 => 0,
    2 => 0,
    1 => 0
];

while ($stat = $rating_stats_result->fetch_assoc()) {
    $rating_stats[$stat['diemdanhgia']] = $stat['count'];
}

$total_reviews = array_sum($rating_stats);
$average_rating = 0;

if ($total_reviews > 0) {
    $rating_sum = 0;
    foreach ($rating_stats as $rating => $count) {
        $rating_sum += $rating * $count;
    }
    $average_rating = $rating_sum / $total_reviews;
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Quản lý đánh giá sản phẩm</h1>
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

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title">Tổng quan đánh giá</h5>
                <div class="d-flex align-items-center mb-4">
                    <div class="display-4 me-3 fw-bold"><?php echo number_format($average_rating, 1); ?></div>
                    <div>
                        <div class="rating">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <?php if ($i <= floor($average_rating)): ?>
                                    <i class="bi bi-star-fill text-warning"></i>
                                <?php elseif ($i - $average_rating < 1 && $i - $average_rating > 0): ?>
                                    <i class="bi bi-star-half text-warning"></i>
                                <?php else: ?>
                                    <i class="bi bi-star text-warning"></i>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>
                        <div class="text-muted"><?php echo $total_reviews; ?> đánh giá</div>
                    </div>
                </div>
                
                <!-- Rating distribution -->
                <?php for ($star = 5; $star >= 1; $star--): ?>
                    <?php 
                    $count = $rating_stats[$star];
                    $percentage = $total_reviews > 0 ? ($count / $total_reviews * 100) : 0;
                    ?>
                    <div class="mb-2">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <div class="d-flex align-items-center">
                                <span class="me-2"><?php echo $star; ?></span>
                                <i class="bi bi-star-fill text-warning me-2"></i>
                            </div>
                            <div class="small text-muted"><?php echo $count; ?> đánh giá</div>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-warning" role="progressbar" style="width: <?php echo $percentage; ?>%" 
                                aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Lọc đánh giá</h5>
            </div>
            <div class="card-body">
                <form action="" method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label for="product_id" class="form-label">Sản phẩm</label>
                        <select class="form-select" id="product_id" name="product_id">
                            <option value="0">Tất cả sản phẩm</option>
                            <?php foreach ($products as $product): ?>
                            <option value="<?php echo $product['id_sanpham']; ?>" <?php echo $product_id == $product['id_sanpham'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($product['tensanpham']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="rating" class="form-label">Đánh giá</label>
                        <select class="form-select" id="rating" name="rating">
                            <option value="0" <?php echo $rating == 0 ? 'selected' : ''; ?>>Tất cả sao</option>
                            <option value="5" <?php echo $rating == 5 ? 'selected' : ''; ?>>5 sao</option>
                            <option value="4" <?php echo $rating == 4 ? 'selected' : ''; ?>>4 sao</option>
                            <option value="3" <?php echo $rating == 3 ? 'selected' : ''; ?>>3 sao</option>
                            <option value="2" <?php echo $rating == 2 ? 'selected' : ''; ?>>2 sao</option>
                            <option value="1" <?php echo $rating == 1 ? 'selected' : ''; ?>>1 sao</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="sort" class="form-label">Sắp xếp</label>
                        <select class="form-select" id="sort" name="sort">
                            <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Mới nhất</option>
                            <option value="oldest" <?php echo $sort == 'oldest' ? 'selected' : ''; ?>>Cũ nhất</option>
                            <option value="rating_high" <?php echo $sort == 'rating_high' ? 'selected' : ''; ?>>Đánh giá cao nhất</option>
                            <option value="rating_low" <?php echo $sort == 'rating_low' ? 'selected' : ''; ?>>Đánh giá thấp nhất</option>
                        </select>
                    </div>
                    <div class="col-12 text-end">
                        <a href="danh-gia.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-repeat"></i> Đặt lại
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-filter"></i> Lọc đánh giá
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Reviews list -->
<div class="card">
    <div class="card-header bg-white">
        <h5 class="mb-0">Danh sách đánh giá</h5>
    </div>
    <div class="card-body p-0">
        <?php if ($reviews->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Sản phẩm</th>
                            <th>Khách hàng</th>
                            <th>Đánh giá</th>
                            <th>Nhận xét</th>
                            <th>Ngày đánh giá</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($review = $reviews->fetch_assoc()): ?>
                            <tr>
                                <td class="text-nowrap">
                                    <a href="../product-detail.php?id=<?php echo $review['id_sanpham']; ?>" class="d-flex align-items-center text-decoration-none" target="_blank">
                                        <img src="<?php echo !empty($review['product_image']) ? '../uploads/products/' . $review['product_image'] : '../images/no-image.png'; ?>" 
                                             alt="<?php echo htmlspecialchars($review['tensanpham']); ?>" 
                                             class="rounded me-2" style="width: 40px; height: 40px; object-fit: cover;">
                                        <div class="text-truncate" style="max-width: 200px;">
                                            <?php echo htmlspecialchars($review['tensanpham']); ?>
                                        </div>
                                    </a>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if (!empty($review['anh_dai_dien'])): ?>
                                            <img src="../uploads/users/<?php echo $review['anh_dai_dien']; ?>" 
                                                 class="rounded-circle me-2" style="width: 32px; height: 32px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center me-2" 
                                                 style="width: 32px; height: 32px;">
                                                <i class="bi bi-person"></i>
                                            </div>
                                        <?php endif; ?>
                                        <span><?php echo htmlspecialchars($review['tenuser']); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <?php if ($i <= $review['diemdanhgia']): ?>
                                                <i class="bi bi-star-fill text-warning"></i>
                                            <?php else: ?>
                                                <i class="bi bi-star text-muted"></i>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                    </div>
                                </td>
                                <td class="text-wrap">
                                    <div style="max-width: 300px;">
                                        <?php echo nl2br(htmlspecialchars($review['noidung'])); ?>
                                        
                                        <?php if (!empty($review['hinhanh'])): ?>
                                            <div class="mt-2">
                                                <a href="../uploads/reviews/<?php echo $review['hinhanh']; ?>" 
                                                   data-lightbox="review-<?php echo $review['id_danhgia']; ?>"
                                                   class="btn btn-sm btn-outline-secondary">
                                                    <i class="bi bi-image"></i> Xem hình ảnh
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($review['ngaydanhgia'])); ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-primary reply-review-btn" 
                                            data-id="<?php echo $review['id_danhgia']; ?>"
                                            data-bs-toggle="modal"
                                            data-bs-target="#replyReviewModal">
                                        <i class="bi bi-reply"></i> Phản hồi
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-star text-muted" style="font-size: 3rem;"></i>
                <h5 class="mt-3">Chưa có đánh giá nào</h5>
                <p class="text-muted">Hiện chưa có đánh giá nào cho sản phẩm của bạn.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="card-footer bg-white">
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center mb-0">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&product_id=<?php echo $product_id; ?>&rating=<?php echo $rating; ?>&sort=<?php echo $sort; ?>">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    </li>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                            <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&product_id=<?php echo $product_id; ?>&rating=<?php echo $rating; ?>&sort=<?php echo $sort; ?>">
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
                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&product_id=<?php echo $product_id; ?>&rating=<?php echo $rating; ?>&sort=<?php echo $sort; ?>">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<!-- Reply to Review Modal -->
<div class="modal fade" id="replyReviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Phản hồi đánh giá</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="replyReviewForm" action="xu-ly-danh-gia.php" method="post">
                    <input type="hidden" name="action" value="reply_review">
                    <input type="hidden" name="review_id" id="reviewId" value="">
                    
                    <div class="mb-3">
                        <label for="replyContent" class="form-label">Nội dung phản hồi</label>
                        <textarea class="form-control" id="replyContent" name="reply_content" rows="4" required></textarea>
                        <div class="form-text">Phản hồi của bạn sẽ được hiển thị công khai cho khách hàng.</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="submit" form="replyReviewForm" class="btn btn-primary">Gửi phản hồi</button>
            </div>
        </div>
    </div>
</div>

<?php
$page_specific_js = "
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle review reply buttons
    const replyButtons = document.querySelectorAll('.reply-review-btn');
    
    replyButtons.forEach(button => {
        button.addEventListener('click', function() {
            const reviewId = this.getAttribute('data-id');
            document.getElementById('reviewId').value = reviewId;
        });
    });
});
</script>
";

include('includes/footer.php');
?>
