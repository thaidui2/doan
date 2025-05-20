<?php
session_start();
include('config/config.php'); // Use the shared config file with the correct database connection

// Kiểm tra đăng nhập
$user_logged_in = false;
$username = '';

if (isset($_SESSION['user_username'])) {
    $user_logged_in = true;
    $username = $_SESSION['user_username'];
}

if (isset($_SESSION['user']['logged_in']) && $_SESSION['user']['logged_in'] === true) {
    $user_logged_in = true;
    $username = $_SESSION['user']['username'];
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Khuyến Mãi - Bug Shop</title>
    <link rel="stylesheet" href="node_modules\bootstrap\dist\css\bootstrap.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="css/khuyenmai.css">
</head>

<body>
    <?php
    include('includes/head.php'); // Include navbar for user login/logout and cart icon
    require_once('includes/header.php'); // Use only header which already includes the proper head content
    ?>

    <main>
        <!-- Banner Khuyến Mãi -->
        <section class="promotion-banner">
            <div class="container-fluid p-0">
                <div class="position-relative">
                    <img src="images/banner/promotion_banner.jpg" class="w-100" alt="Khuyến Mãi"
                        onerror="this.src='images/banner/banner_giam_gia.webp';">
                    <div class="position-absolute top-50 start-50 translate-middle text-center text-white">
                        <h1 class="display-4 fw-bold">KHUYẾN MÃI HOT</h1>
                        <p class="fs-5">Săn ngay những sản phẩm giảm giá hấp dẫn!</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Sản phẩm khuyến mãi -->
        <section class="promotion-products py-5 bg-light">
            <div class="container">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="section-title position-relative">
                        <span class="bg-light pe-3">Sản Phẩm Khuyến Mãi</span>
                        <div class="title-line"></div>
                    </h2>
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="sortDropdown"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            Sắp xếp theo
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="sortDropdown">
                            <li><a class="dropdown-item" href="?sort=discount_desc">% Giảm giá cao nhất</a></li>
                            <li><a class="dropdown-item" href="?sort=price_asc">Giá tăng dần</a></li>
                            <li><a class="dropdown-item" href="?sort=price_desc">Giá giảm dần</a></li>
                            <li><a class="dropdown-item" href="?sort=new">Mới nhất</a></li>
                        </ul>
                    </div>
                </div>

                <div class="row g-4" id="promotion-products">
                    <?php
                    // Removed direct database connection code that was using the wrong database "shop_vippro"
                    
                    // Xử lý sắp xếp
                    $sort = isset($_GET['sort']) ? $_GET['sort'] : 'discount_desc';

                    // Thiết lập ORDER BY dựa trên tham số sắp xếp
                    $order_by = "";
                    switch ($sort) {
                        case 'price_asc':
                            $order_by = "ORDER BY s.gia ASC";
                            break;
                        case 'price_desc':
                            $order_by = "ORDER BY s.gia DESC";
                            break;
                        case 'new':
                            $order_by = "ORDER BY s.ngay_tao DESC";
                            break;
                        case 'discount_desc':
                        default:
                            $order_by = "ORDER BY discount_percent DESC";
                            break;
                    }

                    // Updated query to match new database schema
                    $sql = "SELECT s.*, d.ten as tenloai, AVG(dg.diem) as diem_trung_binh,
                            (1 - s.gia/s.giagoc) * 100 as discount_percent
                            FROM sanpham s 
                            LEFT JOIN danhgia dg ON s.id = dg.id_sanpham 
                            LEFT JOIN danhmuc d ON s.id_danhmuc = d.id
                            WHERE s.trangthai = 1 AND s.giagoc > s.gia AND s.giagoc > 0
                            GROUP BY s.id 
                            $order_by";

                    $result = $conn->query($sql);

                    // Kiểm tra và hiển thị sản phẩm
                    if ($result && $result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            // Tính phần trăm giảm giá
                            $discount_percent = round(100 - ($row['gia'] / $row['giagoc'] * 100));

                            // Xử lý đường dẫn hình ảnh
                            if (!empty($row['hinhanh'])) {
                                // First check if the path already contains 'uploads/'
                                if (strpos($row['hinhanh'], 'uploads/') === 0) {
                                    $img_path = $row['hinhanh'];
                                } else if (file_exists('uploads/products/' . $row['hinhanh'])) {
                                    $img_path = 'uploads/products/' . $row['hinhanh'];
                                } else {
                                    $img_path = 'images/no-image.png';
                                }
                            } else {
                                $img_path = 'images/no-image.png';
                            }

                            // Xử lý điểm đánh giá
                            $rating = round($row['diem_trung_binh']);
                            if (is_null($rating))
                                $rating = 0;
                            ?>
                            <div class="col-6 col-md-3 product-item">
                                <div class="card product-card h-100">
                                    <div class="product-badge-container">
                                        <div class="product-badge bg-danger text-white">
                                            <i class="bi bi-tags-fill me-1"></i>-<?php echo $discount_percent; ?>%
                                        </div>
                                        <?php if ($row['noibat'] == 1): ?>
                                            <div class="product-badge bg-primary text-white">
                                                <i class="bi bi-star-fill me-1"></i>HOT
                                            </div>
                                        <?php endif; ?>
                                    </div> <a href="product-detail.php?id=<?php echo $row['id']; ?>"
                                        class="product-img-container">
                                        <img src="<?php echo $img_path; ?>" class="card-img-top product-img"
                                            alt="<?php echo htmlspecialchars($row['tensanpham']); ?>"
                                            onerror="this.onerror=null; this.src='images/no-image.jpg';">
                                        <div class="overlay-effect"></div>
                                    </a>
                                    <div class="product-action" style="top: 90px;"> <!-- Di chuyển nút yêu thích xuống dưới -->
                                        <button class="btn btn-light btn-sm rounded-circle wishlist-button"
                                            data-product-id="<?php echo $row['id']; ?>" title="Yêu thích">
                                            <i class="bi bi-heart"></i>
                                        </button>
                                    </div>
                                    <div class="sale-ribbon">
                                        <span>SALE</span>
                                    </div>
                                    <div class="card-body">
                                        <div class="product-category"><?php echo htmlspecialchars($row['tenloai'] ?? ''); ?>
                                        </div>
                                        <h5 class="card-title product-title">
                                            <a href="product-detail.php?id=<?php echo $row['id']; ?>"
                                                class="text-decoration-none text-dark">
                                                <?php echo htmlspecialchars($row['tensanpham']); ?>
                                            </a>
                                        </h5>
                                        <div class="rating">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="bi bi-star<?php echo ($i <= $rating) ? '-fill' : ''; ?> text-warning"></i>
                                            <?php endfor; ?>
                                            <span
                                                class="ms-1 text-muted small">(<?php echo $row['soluong_danhgia'] ?? 0; ?>)</span>
                                        </div>
                                        <div class="price-wrapper">
                                            <span
                                                class="text-danger fw-bold"><?php echo number_format($row['gia'], 0, ',', '.'); ?>₫</span>
                                            <small
                                                class="text-decoration-line-through text-muted ms-2"><?php echo number_format($row['giagoc'], 0, ',', '.'); ?>₫</small>
                                        </div>
                                    </div>
                                    <div class="card-footer">
                                        <a href="product-detail.php?id=<?php echo $row['id']; ?>" class="btn btn-primary w-100">
                                            <i class="bi bi-info-circle me-2"></i> Xem chi tiết
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php
                        }
                    } else {
                        echo '<div class="col-12 text-center py-5">
                                <div class="empty-state">
                                    <i class="bi bi-tag-fill display-4 text-muted"></i>
                                    <h4 class="mt-3">Không có sản phẩm khuyến mãi</h4>
                                    <p class="text-muted">Hiện chưa có sản phẩm nào đang giảm giá.</p>
                                </div>
                              </div>';
                    }
                    ?>
                </div>

                <!-- Phân trang (nếu cần) -->
                <nav class="mt-5">
                    <ul class="pagination justify-content-center">
                        <li class="page-item disabled">
                            <a class="page-link" href="#" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        <li class="page-item active"><a class="page-link" href="#">1</a></li>
                        <li class="page-item"><a class="page-link" href="#">2</a></li>
                        <li class="page-item"><a class="page-link" href="#">3</a></li>
                        <li class="page-item">
                            <a class="page-link" href="#" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        </section>

        <!-- Countdown khuyến mãi -->
        <section class="countdown-section py-5">
            <div class="container">
                <div class="row">
                    <div class="col-lg-6">
                        <div class="flash-sale-card p-4 text-center text-lg-start">
                            <h3 class="mb-3">Flash Sale Sắp Kết Thúc!</h3>
                            <p>Nhanh tay mua ngay kẻo lỡ! Giảm giá đặc biệt lên đến 50%.</p>

                            <div class="countdown d-flex justify-content-center justify-content-lg-start"
                                id="countdown">
                                <div class="countdown-item">
                                    <span id="days">00</span>
                                    <span class="countdown-label">Ngày</span>
                                </div>
                                <div class="countdown-item">
                                    <span id="hours">00</span>
                                    <span class="countdown-label">Giờ</span>
                                </div>
                                <div class="countdown-item">
                                    <span id="minutes">00</span>
                                    <span class="countdown-label">Phút</span>
                                </div>
                                <div class="countdown-item">
                                    <span id="seconds">00</span>
                                    <span class="countdown-label">Giây</span>
                                </div>
                            </div>

                            <a href="#promotion-products" class="btn btn-danger mt-3">Mua Ngay</a>
                        </div>
                    </div>
                    <div class="col-lg-6 mt-4 mt-lg-0">
                        <div class="promo-code-card p-4">
                            <h3 class="mb-3">Mã Giảm Giá</h3>
                            <p>Sử dụng mã giảm giá sau để nhận thêm ưu đãi:</p>

                            <div class="coupon-container mb-3">
                                <div class="coupon d-flex align-items-center justify-content-between">
                                    <div>
                                        <span class="coupon-code">SUMMER2025</span>
                                        <small class="d-block">Giảm 10% cho đơn hàng trên 500K</small>
                                    </div>
                                    <button class="btn btn-sm btn-outline-primary copy-code" data-code="SUMMER2025">Sao
                                        chép</button>
                                </div>
                            </div>

                            <div class="coupon-container">
                                <div class="coupon d-flex align-items-center justify-content-between">
                                    <div>
                                        <span class="coupon-code">NEWUSER</span>
                                        <small class="d-block">Giảm 50K cho khách hàng mới</small>
                                    </div>
                                    <button class="btn btn-sm btn-outline-primary copy-code" data-code="NEWUSER">Sao
                                        chép</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php include('includes/footer.php'); ?>

    <script src="js/khuyenmai.js"></script>
</body>

</html>