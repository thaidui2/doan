<?php
// Set page title
$page_title = 'Chi tiết đánh giá';

// Include header (which includes authentication checks)
include('includes/header.php');
include('../config/config.php');

// Get review ID from URL
$review_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($review_id <= 0) {
    $_SESSION['error_message'] = 'ID đánh giá không hợp lệ';
    header('Location: reviews.php');
    exit;
}

// Fetch review details with joins to related tables
$stmt = $conn->prepare("
    SELECT r.*, s.tensanpham, s.slug AS product_slug, u.ten AS tenuser, u.taikhoan, u.id AS user_id
    FROM danhgia r
    JOIN sanpham s ON r.id_sanpham = s.id
    JOIN users u ON r.id_user = u.id
    WHERE r.id = ?
");
$stmt->bind_param("i", $review_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = 'Không tìm thấy đánh giá với ID này';
    header('Location: reviews.php');
    exit;
}

$review = $result->fetch_assoc();

// Process status toggle if requested
if (isset($_POST['toggle_status'])) {
    $new_status = ($review['trang_thai'] == 1) ? 0 : 1;
    
    $update_stmt = $conn->prepare("UPDATE danhgia SET trang_thai = ? WHERE id = ?");
    $update_stmt->bind_param("ii", $new_status, $review_id);
    
    if ($update_stmt->execute()) {
        $action = ($new_status == 1) ? 'show' : 'hide';
        $status_text = ($new_status == 1) ? 'hiển thị' : 'ẩn';
        $_SESSION['success_message'] = "Đã thay đổi trạng thái đánh giá thành $status_text";
        
        // Log action
        $admin_id = $_SESSION['admin_id'] ?? 1;
        $details = "Đã $status_text đánh giá ID: $review_id";
        logAdminActivity($conn, $admin_id, $action, 'review', $review_id, $details);
        
        // Update review data
        $review['trang_thai'] = $new_status;
    } else {
        $_SESSION['error_message'] = "Lỗi khi cập nhật trạng thái: " . $conn->error;
    }
}

// Process delete if requested
if (isset($_POST['delete_review'])) {
    $delete_stmt = $conn->prepare("DELETE FROM danhgia WHERE id = ?");
    $delete_stmt->bind_param("i", $review_id);
    
    if ($delete_stmt->execute()) {
        // Log deletion
        $admin_id = $_SESSION['admin_id'] ?? 1;
        $details = "Đã xóa đánh giá ID: $review_id";
        logAdminActivity($conn, $admin_id, 'delete', 'review', $review_id, $details);
        
        $_SESSION['success_message'] = "Đã xóa đánh giá thành công";
        header('Location: reviews.php');
        exit;
    } else {
        $_SESSION['error_message'] = "Lỗi khi xóa đánh giá: " . $conn->error;
    }
}

// Get product image
$product_query = $conn->prepare("SELECT hinhanh FROM sanpham WHERE id = ?");
$product_query->bind_param("i", $review['id_sanpham']);
$product_query->execute();
$product_result = $product_query->get_result();
$product_image = '';
if ($product_result->num_rows > 0) {
    $product_data = $product_result->fetch_assoc();
    $product_image = $product_data['hinhanh'];
}
?>

<!-- Main content -->
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="reviews.php">Quản lý đánh giá</a></li>
            <li class="breadcrumb-item active" aria-current="page">Chi tiết đánh giá</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2">Chi tiết đánh giá #<?php echo $review_id; ?></h1>
        <div class="btn-toolbar">
            <a href="reviews.php" class="btn btn-sm btn-outline-secondary me-2">
                <i class="bi bi-arrow-left"></i> Quay lại
            </a>
            
            <form method="post" class="d-inline me-2">
                <?php if ($review['trang_thai'] == 1): ?>
                    <button type="submit" name="toggle_status" class="btn btn-sm btn-warning">
                        <i class="bi bi-eye-slash"></i> Ẩn đánh giá
                    </button>
                <?php else: ?>
                    <button type="submit" name="toggle_status" class="btn btn-sm btn-success">
                        <i class="bi bi-eye"></i> Hiển thị đánh giá
                    </button>
                <?php endif; ?>
            </form>
            
            <form method="post" class="d-inline" onsubmit="return confirm('Bạn có chắc chắn muốn xóa đánh giá này không? Hành động này không thể hoàn tác.');">
                <button type="submit" name="delete_review" class="btn btn-sm btn-danger">
                    <i class="bi bi-trash"></i> Xóa đánh giá
                </button>
            </form>
        </div>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Left column: Product and Review Info -->
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header bg-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Thông tin đánh giá</h5>
                        <span class="badge <?php echo $review['trang_thai'] ? 'bg-success' : 'bg-secondary'; ?>">
                            <?php echo $review['trang_thai'] ? 'Đang hiển thị' : 'Đã ẩn'; ?>
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <div class="d-flex align-items-center mb-3">
                            <h5 class="me-2 mb-0">Đánh giá:</h5>
                            <div class="rating">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="bi <?php echo $i <= $review['diem'] ? 'bi-star-fill text-warning' : 'bi-star text-muted'; ?>"></i>
                                <?php endfor; ?>
                                <span class="ms-2 text-muted">(<?php echo $review['diem']; ?>/5)</span>
                            </div>
                        </div>
                        
                        <!-- Recommendation -->
                        <?php if ($review['khuyen_dung'] == 1): ?>
                            <div class="mb-3">
                                <span class="badge bg-info">
                                    <i class="bi bi-hand-thumbs-up"></i> Khách hàng khuyên dùng sản phẩm này
                                </span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mb-4">
                            <h6>Nội dung đánh giá:</h6>
                            <div class="p-3 bg-light rounded">
                                <?php echo nl2br(htmlspecialchars($review['noi_dung'])); ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($review['hinh_anh'])): ?>
                            <div class="mb-4">
                                <h6>Hình ảnh đính kèm:</h6>
                                <div class="row g-2">
                                    <?php 
                                    $images = explode('|', $review['hinh_anh']);
                                    foreach ($images as $image): 
                                    ?>
                                        <div class="col-md-4">
                                            <a href="../uploads/reviews/<?php echo htmlspecialchars($image); ?>" target="_blank" class="d-block">
                                                <img src="../uploads/reviews/<?php echo htmlspecialchars($image); ?>" class="img-thumbnail" alt="Review image">
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="text-muted small">
                            <div class="mb-1">Ngày đăng: <?php echo date('d/m/Y H:i', strtotime($review['ngay_danhgia'])); ?></div>
                            <div>Đơn hàng: #<?php echo $review['id_donhang']; ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Right column: Customer and Product Info -->
        <div class="col-md-4">
            <!-- Product Info -->
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Thông tin sản phẩm</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <?php if (!empty($product_image)): ?>
                            <img src="../<?php echo htmlspecialchars($product_image); ?>" class="img-thumbnail me-3" style="width: 80px; height: 80px; object-fit: cover;" alt="Product Image">
                        <?php else: ?>
                            <div class="bg-light me-3 d-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                <i class="bi bi-image text-muted" style="font-size: 2rem;"></i>
                            </div>
                        <?php endif; ?>
                        <div>
                            <h6 class="mb-1"><?php echo htmlspecialchars($review['tensanpham']); ?></h6>
                            <a href="../product-detail.php?id=<?php echo $review['id_sanpham']; ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                <i class="bi bi-box-arrow-up-right"></i> Xem sản phẩm
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Customer Info -->
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Thông tin khách hàng</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6><?php echo htmlspecialchars($review['tenuser']); ?></h6>
                        <div class="text-muted"><?php echo htmlspecialchars($review['taikhoan']); ?></div>
                    </div>
                    <a href="customer-detail.php?id=<?php echo $review['user_id']; ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-person"></i> Xem thông tin chi tiết
                    </a>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include('includes/footer.php'); ?>
