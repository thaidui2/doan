<?php
session_start();

// Kiểm tra đăng nhập với namespace hoặc prefix
$user_logged_in = false;
$username = '';

// Nếu dùng phương pháp 1 (prefix)
if (isset($_SESSION['user_username'])) {
    $user_logged_in = true;
    $username = $_SESSION['user_username'];
}

// Hoặc nếu dùng phương pháp 2 (namespace)
if (isset($_SESSION['user']['logged_in']) && $_SESSION['user']['logged_in'] === true) {
    $user_logged_in = true;
    $username = $_SESSION['user']['username'];
}

// Thiết lập các tham số cho head động
$page_title = 'Trang chủ';
$page_css = ['css/index.css']; // Mảng chứa các file CSS cần load
$page_js = ['js/index.js']; // Mảng chứa các file JS cần load

// Có thể thêm head content tùy chỉnh nếu cần
$head_custom = '
<!-- Meta tags tùy chỉnh cho SEO -->
<meta name="description" content="Bug Shop - Cửa hàng giày dép chất lượng cao">
<meta name="keywords" content="giày, sneakers, giày thể thao, giày nam, giày nữ">
';

// Include head.php chứa phần head động
require_once('includes/head.php');
require_once('includes/header.php');
?>

<body>
    <main>
        <!-- Hero Banner -->
        <section class="hero-banner">
            <div class="container-fluid p-0">
                <div id="heroCarousel" class="carousel slide" data-bs-ride="carousel">
                    <div class="carousel-indicators">
                        <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="0"
                            class="active"></button>
                        <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="1"></button>
                        <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="2"></button>
                    </div>
                    <div class="carousel-inner">
                        <div class="carousel-item active">
                            <img src="images\banner\banner_giay_2.webp" class="d-block w-100" alt="New Collection">
                            <div class="carousel-caption d-none d-md-block">
                                <h1 class="display-4 fw-bold text-shadow">Bộ Sưu Tập Mới <span
                                        class="highlight">2025</span></h1>
                                <p class="lead fw-semibold mb-4 text-shadow">Khám phá các mẫu giày mới nhất cho mùa này
                                </p>
                                <a href="#" class="btn btn-primary btn-lg">Mua ngay</a>
                            </div>
                        </div>
                        <div class="carousel-item">
                            <img src="images\banner\banner_giam_gia.webp" class="d-block w-100" alt="Sale">
                            <div class="carousel-caption d-none d-md-block">
                                <h1 class="display-4 fw-bold text-shadow">Giảm Giá Đến 40%</h1>
                                <p class="lead fw-semibold mb-4 text-shadow">Ưu đãi đặc biệt cho tất cả các sản phẩm thể
                                    thao</p>
                                <a href="#" class="btn btn-danger btn-lg">Xem ngay</a>
                            </div>
                        </div>
                        <div class="carousel-item">
                            <img src="images\banner\banner_giay.png" class="d-block w-100" alt="Limited Edition">
                            <div class="carousel-caption d-none d-md-block">
                                <h1 class="display-4 fw-bold text-shadow">Phiên Bản Giới Hạn</h1>
                                <p class="lead fw-semibold mb-4 text-shadow">Sản phẩm độc quyền chỉ có tại Bug Shoes</p>
                                <a href="#" class="btn btn-dark btn-lg">Khám phá</a>
                            </div>
                        </div>
                    </div>
                    <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel"
                        data-bs-slide="prev">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel"
                        data-bs-slide="next">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    </button>
                </div>
            </div>
        </section>

        <!-- Categories Section -->
        <section class="categories py-5">
            <div class="container">
                <h2 class="text-center mb-5">Danh Mục Sản Phẩm</h2>
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="card category-card text-white">
                            <img src="images\giay_nam.png" class="card-img" alt="Men's Shoes">
                            <div class="card-img-overlay d-flex align-items-center justify-content-center">
                                <h3 class="card-title">Giày Nam</h3>
                                <a href="sanpham.php?loai=1" class="stretched-link"></a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card category-card text-white">
                            <img src="images\giay_nu.jpg" class="card-img" alt="Women's Shoes">
                            <div class="card-img-overlay d-flex align-items-center justify-content-center">
                                <h3 class="card-title">Giày Nữ</h3>
                                <a href="#" class="stretched-link"></a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card category-card text-white">
                            <img src="images\giay_thethao.jpg" class="card-img" alt="Sports Shoes">
                            <div class="card-img-overlay d-flex align-items-center justify-content-center">
                                <h3 class="card-title">Giày Thể Thao</h3>
                                <a href="#" class="stretched-link"></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Featured Products -->
        <section class="featured-products py-5 bg-light">
            <div class="container">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="section-title position-relative">
                        <span class="bg-light pe-3">Sản Phẩm Nổi Bật</span>
                        <div class="title-line"></div>
                    </h2>
                    <a href="products.php" class="btn btn-outline-dark">Xem tất cả <i class="bi bi-arrow-right"></i></a>
                </div>

                <div class="product-carousel">
                    <div class="row g-4" id="featured-products">
                        <?php
                        // Kết nối database - Cập nhật tên database
                        $servername = "localhost";
                        $username = "root";
                        $password = "";
                        $dbname = "shop_vippro_1"; // Đã sửa thành tên database mới
                        
                        $conn = new mysqli($servername, $username, $password, $dbname);

                        // Kiểm tra kết nối
                        if ($conn->connect_error) {
                            die("Kết nối thất bại: " . $conn->connect_error);
                        }

                        // Set charset
                        $conn->set_charset("utf8mb4");

                        // Truy vấn sản phẩm nổi bật - Cập nhật tên bảng và trường
                        $sql = "SELECT s.*, d.ten as tendanhmuc, AVG(dg.diem) as diem_trung_binh 
                                FROM sanpham s 
                                LEFT JOIN danhgia dg ON s.id = dg.id_sanpham 
                                LEFT JOIN danhmuc d ON s.id_danhmuc = d.id
                                WHERE s.trangthai = 1 
                                GROUP BY s.id 
                                ORDER BY s.noibat DESC, s.ngay_tao DESC 
                                LIMIT 8";

                        $result = $conn->query($sql);

                        // Kiểm tra và hiển thị sản phẩm
                        if ($result && $result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                // Tính phần trăm giảm giá
                                $discount_percent = 0;
                                if ($row['giagoc'] > 0 && $row['giagoc'] > $row['gia']) {
                                    $discount_percent = round(100 - ($row['gia'] / $row['giagoc'] * 100));
                                }

                                // Xử lý đường dẫn hình ảnh - sử dụng trực tiếp từ bảng sanpham_hinhanh
                                $img_path = 'images/no-image.jpg';

                                // Kiểm tra hình ảnh từ trường hinhanh của bảng sanpham
                                if (!empty($row['hinhanh'])) {
                                    $product_image = $row['hinhanh'];
                                    // Nếu đường dẫn không bắt đầu bằng uploads/, thêm vào
                                    if (strpos($product_image, 'uploads/') !== 0) {
                                        $product_image = 'uploads/products/' . $product_image;
                                    }

                                    // Kiểm tra file có tồn tại không với đường dẫn server
                                    $server_path = $_SERVER['DOCUMENT_ROOT'] . '/bug_shop/' . $product_image;

                                    if (file_exists($server_path)) {
                                        $img_path = $product_image;
                                    } else {
                                        // Thử với đường dẫn không có bug_shop/
                                        $server_path = $_SERVER['DOCUMENT_ROOT'] . '/' . $product_image;
                                        if (file_exists($server_path)) {
                                            $img_path = $product_image;
                                        }
                                    }
                                }

                                // Nếu không tìm thấy từ sanpham, tìm trong sanpham_hinhanh
                                if ($img_path == 'images/no-image.jpg') {
                                    $stmt = $conn->prepare("SELECT hinhanh FROM sanpham_hinhanh WHERE id_sanpham = ? ORDER BY la_anh_chinh DESC LIMIT 1");
                                    $stmt->bind_param("i", $row['id']);
                                    $stmt->execute();
                                    $result_img = $stmt->get_result();

                                    if ($result_img->num_rows > 0) {
                                        $img_row = $result_img->fetch_assoc();
                                        $product_image = $img_row['hinhanh'];

                                        // Tương tự, kiểm tra và điều chỉnh đường dẫn
                                        if (strpos($product_image, 'uploads/') !== 0) {
                                            $product_image = 'uploads/products/' . $product_image;
                                        }

                                        $img_path = $product_image;
                                    }
                                }

                                // Xử lý điểm đánh giá
                                $rating = round($row['diem_trung_binh']);
                                if (is_null($rating))
                                    $rating = 0;

                                // Đếm số lượng đánh giá
                                $count_reviews = $conn->prepare("SELECT COUNT(*) as count FROM danhgia WHERE id_sanpham = ?");
                                $count_reviews->bind_param("i", $row['id']);
                                $count_reviews->execute();
                                $review_result = $count_reviews->get_result();
                                $review_count = $review_result->fetch_assoc()['count'] ?? 0;
                                ?>
                                <div class="col-6 col-md-3 product-item">
                                    <div class="card product-card h-100">
                                        <div class="product-badge-container">
                                            <?php if ($discount_percent > 0): ?>
                                                <div class="product-badge bg-danger text-white">
                                                    <i class="bi bi-tags-fill me-1"></i>-<?php echo $discount_percent; ?>%
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($row['noibat'] == 1): ?>
                                                <div class="product-badge bg-primary text-white">
                                                    <i class="bi bi-star-fill me-1"></i>HOT
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <a href="product-detail.php?id=<?php echo $row['id']; ?>" class="product-img-container">
                                            <img src="<?php echo $img_path; ?>" class="card-img-top product-img"
                                                alt="<?php echo htmlspecialchars($row['tensanpham']); ?>"
                                                onerror="this.onerror=null; this.src='images/no-image.jpg';">
                                            <div class="overlay-effect"></div>
                                        </a>
                                        <div class="product-action">
                                            <button class="btn btn-light btn-sm rounded-circle wishlist-button"
                                                data-product-id="<?php echo $row['id']; ?>" title="Thêm vào yêu thích">
                                                <i class="bi bi-heart"></i>
                                            </button>
                                        </div>
                                        <div class="card-body">
                                            <div class="product-category"><?php echo htmlspecialchars($row['tendanhmuc']); ?>
                                            </div>
                                            <h5 class="card-title product-title">
                                                <a href="product-detail.php?id=<?php echo $row['id']; ?>"
                                                    class="text-decoration-none text-dark">
                                                    <?php echo htmlspecialchars($row['tensanpham']); ?>
                                                </a>
                                            </h5>
                                            <div class="rating">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i
                                                        class="bi bi-star<?php echo ($i <= $rating) ? '-fill' : ''; ?> text-warning"></i>
                                                <?php endfor; ?>
                                                <span class="ms-1 text-muted small">(<?php echo $review_count; ?>)</span>
                                            </div>
                                            <div class="price-wrapper">
                                                <span
                                                    class="text-danger fw-bold"><?php echo number_format($row['gia'], 0, ',', '.'); ?>₫</span>
                                                <?php if ($row['giagoc'] > 0 && $row['giagoc'] > $row['gia']): ?>
                                                    <small
                                                        class="text-decoration-line-through text-muted ms-2"><?php echo number_format($row['giagoc'], 0, ',', '.'); ?>₫</small>
                                                <?php endif; ?>
                                            </div>

                                        </div>
                                    </div>
                                </div>
                                <?php
                            }
                        } else {
                            echo '<div class="col-12 text-center py-5">
                                    <div class="empty-state">
                                        <i class="bi bi-exclamation-circle display-4 text-muted"></i>
                                        <h4 class="mt-3">Không có sản phẩm nổi bật</h4>
                                        <p class="text-muted">Hiện chưa có sản phẩm nào được đánh dấu nổi bật.</p>
                                    </div>
                                  </div>';
                        }

                        // Đóng kết nối
                        $conn->close();
                        ?>
                    </div>

                    <!-- Nút điều hướng -->
                    <div class="carousel-navigation mt-4 text-center">
                        <button id="prevProduct" class="btn btn-sm btn-outline-dark me-2"><i
                                class="bi bi-chevron-left"></i></button>
                        <button id="nextProduct" class="btn btn-sm btn-outline-dark"><i
                                class="bi bi-chevron-right"></i></button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Modal Xem nhanh sản phẩm -->
        <div class="modal fade" id="quickViewModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Xem nhanh sản phẩm</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="quickViewContent">
                        <div class="text-center">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">Đang tải...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div> <!-- Promotions -->
        <section class="promotions py-4">
            <div class="container">
                <h4 class="text-center mb-4 position-relative pb-2">
                    <span>SẢN PHẨM & KHÁCH HÀNG NỔI BẬT</span>
                    <div class="title-underline"></div>
                </h4>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="card h-100 promotion-card shadow-sm">
                            <div class="card-header bg-danger text-white d-flex align-items-center">
                                <i class="bi bi-graph-up-arrow fs-5 me-2"></i>
                                <h5 class="card-title mb-0 flex-grow-1">Top 3 Sản Phẩm Bán Chạy</h5>
                                <span class="badge bg-light text-danger rounded-pill py-1 px-2">HOT</span>
                            </div>
                            <div class="card-body">
                                <?php
                                // Kết nối database - sử dụng lại kết nối đã có ở trên
                                $conn = new mysqli($servername, $username, $password, $dbname);

                                if ($conn->connect_error) {
                                    die("Kết nối thất bại: " . $conn->connect_error);
                                }                                // Truy vấn lấy top 3 sản phẩm bán chạy nhất
                                $top_products_sql = "SELECT s.id, s.tensanpham, s.gia, s.hinhanh,
                                                  (SELECT COALESCE(SUM(dc.soluong), 0) 
                                                   FROM donhang_chitiet dc 
                                                   JOIN donhang d ON dc.id_donhang = d.id 
                                                   WHERE dc.id_sanpham = s.id AND d.trang_thai_don_hang = 4) as da_ban
                                           FROM sanpham s
                                           WHERE s.trangthai = 1
                                           ORDER BY da_ban DESC
                                           LIMIT 3";

                                $top_result = $conn->query($top_products_sql);
                                if ($top_result && $top_result->num_rows > 0) {
                                    echo '<div class="bestseller-list">';

                                    $rank = 1;
                                    while ($product = $top_result->fetch_assoc()) {
                                        $img_path = 'images/no-image.jpg';

                                        // Kiểm tra hình ảnh từ trường hinhanh của bảng sanpham
                                        if (!empty($product['hinhanh'])) {
                                            $product_image = $product['hinhanh'];
                                            // Nếu đường dẫn không bắt đầu bằng uploads/, thêm vào
                                            if (strpos($product_image, 'uploads/') !== 0) {
                                                $product_image = 'uploads/products/' . $product_image;
                                            }

                                            $img_path = $product_image;
                                        }

                                        $badge_class = ($rank <= 3) ? ['text-warning', 'text-silver', 'text-bronze'][$rank - 1] : 'text-info';
                                        $medal_icon = ($rank <= 3) ? ['bi-trophy-fill', 'bi-award-fill', 'bi-star-fill'][$rank - 1] : 'bi-award';
                                        $product_badge_icon = '';
                                        if ($rank == 1) {
                                            $product_badge_icon = '<i class="bi bi-trophy-fill text-warning me-1"></i>';
                                        } elseif ($rank == 2) {
                                            $product_badge_icon = '<i class="bi bi-award-fill text-silver me-1"></i>';
                                        } elseif ($rank == 3) {
                                            $product_badge_icon = '<i class="bi bi-star-fill text-bronze me-1"></i>';
                                        }

                                        echo '<div class="bestseller-item d-flex align-items-center position-relative hover-scale">
                                                <div class="rank-number-simple me-2">' . $rank . '</div>
                                                <div class="bestseller-image me-2">
                                                    <img src="' . $img_path . '" class="rounded" alt="' . htmlspecialchars($product['tensanpham']) . '"
                                                         onerror="this.onerror=null; this.src=\'images/no-image.jpg\';">
                                                </div>
                                                <div class="bestseller-info flex-grow-1">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <a href="product-detail.php?id=' . $product['id'] . '" class="product-title-small text-decoration-none text-dark">
                                                            ' . htmlspecialchars($product['tensanpham']) . '
                                                        </a>
                                                        <small class="product-rank-tag ' . $badge_class . '">' . $product_badge_icon . '</small>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <span class="price text-danger small fw-bold">' . number_format($product['gia'], 0, ',', '.') . '₫</span>
                                                        <span class="sold-count text-muted" style="font-size:10px">' . $product['da_ban'] . ' đã bán</span>
                                                    </div>
                                                </div>
                                            </div>';
                                        $rank++;
                                    }
                                    echo '</div>';
                                    echo '<div class="text-center mt-3">
                                        <a href="sanpham.php?sort=bestseller" class="btn btn-sm btn-outline-danger btn-view-all py-1 px-3">
                                            <i class="bi bi-grid-3x3-gap-fill me-1"></i>Xem tất cả
                                        </a>
                                    </div>';
                                } else {                                    // Không có dữ liệu
                                    echo '<div class="text-center py-3">
                                            <div class="empty-state">
                                                <i class="bi bi-bar-chart-line-fill text-muted fs-3 mb-2"></i>
                                                <h5>Chưa có sản phẩm bán chạy</h5>
                                                <p class="text-muted small">Dữ liệu sẽ được cập nhật khi có đơn hàng hoàn thành.</p>
                                            </div>
                                          </div>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100 promotion-card shadow-sm">
                            <div class="card-header bg-primary text-white d-flex align-items-center">
                                <i class="bi bi-people-fill fs-5 me-2"></i>
                                <h5 class="card-title mb-0 flex-grow-1">Top 3 Khách Hàng VIP</h5>
                                <span class="badge bg-light text-primary rounded-pill py-1 px-2">VIP</span>
                            </div>
                            <div class="card-body">
                                <?php
                                // Kết nối database - sử dụng lại kết nối đã có ở trên
                                $conn = new mysqli($servername, $username, $password, $dbname);

                                if ($conn->connect_error) {
                                    die("Kết nối thất bại: " . $conn->connect_error);
                                }                                // Truy vấn lấy top 3 khách hàng có số tiền mua hàng nhiều nhất
                                $top_customers_sql = "SELECT u.id, u.ten, COUNT(d.id) as order_count, 
                                                     SUM(d.thanh_tien) as total_spent
                                              FROM users u
                                              JOIN donhang d ON u.id = d.id_user
                                              WHERE d.trang_thai_don_hang = 4 
                                              GROUP BY u.id
                                              ORDER BY total_spent DESC
                                              LIMIT 3";

                                $top_result = $conn->query($top_customers_sql);
                                if ($top_result && $top_result->num_rows > 0) {
                                    echo '<div class="loyal-customers">';

                                    $rank = 1;
                                    while ($customer = $top_result->fetch_assoc()) {
                                        $badge_class = ($rank <= 3) ? ['text-warning', 'text-silver', 'text-bronze'][$rank - 1] : 'text-info';
                                        $vip_icon = ($rank <= 3) ? ['bi-gem', 'bi-gem', 'bi-gem'][$rank - 1] : 'bi-person-check-fill';
                                        $vip_level = ($rank <= 3) ? ['Diamond', 'Platinum', 'Gold'][$rank - 1] : 'Silver';
                                        $vip_badge_icon = '';
                                        if ($rank == 1) {
                                            $vip_badge_icon = '<i class="bi bi-gem text-warning me-1"></i>';
                                        } elseif ($rank == 2) {
                                            $vip_badge_icon = '<i class="bi bi-gem text-silver me-1"></i>';
                                        } elseif ($rank == 3) {
                                            $vip_badge_icon = '<i class="bi bi-gem text-bronze me-1"></i>';
                                        }

                                        echo '<div class="customer-item d-flex align-items-center hover-scale">
                                                <div class="rank-number-simple me-2">' . $rank . '</div>
                                                <div class="customer-mini-avatar me-2 ' . $badge_class . '">
                                                    ' . strtoupper(substr($customer['ten'], 0, 1)) . '
                                                </div>
                                                <div class="customer-info flex-grow-1">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <span class="fw-medium" style="font-size:13px">' . htmlspecialchars($customer['ten']) . '</span>
                                                        <small class="vip-tag ' . $badge_class . '">' . $vip_badge_icon . $vip_level . '</small>
                                                    </div>
                                                    <div class="customer-stats-simple text-muted" style="font-size:10px">
                                                        ' . $customer['order_count'] . ' đơn · ' . number_format($customer['total_spent'], 0, ',', '.') . '₫
                                                    </div>
                                                </div>
                                            </div>';
                                        $rank++;
                                    }
                                    echo '</div>';
                                    if (!$user_logged_in) {
                                        echo '<div class="simple-banner mt-2 text-center">
                                                <a href="dangky.php" class="btn btn-sm btn-primary btn-sm py-1 px-3">
                                                    <i class="bi bi-person-plus me-1"></i>Tham gia
                                                </a>
                                            </div>';
                                    } else {
                                        echo '<div class="simple-banner mt-2 text-center">
                                                <a href="khuyenmai.php" class="btn btn-sm btn-outline-primary btn-sm py-1 px-3">
                                                    <i class="bi bi-tags me-1"></i>Ưu đãi
                                                </a>
                                            </div>';
                                    }

                                } else {                                    // Không có dữ liệu
                                    echo '<div class="text-center py-3">
                                            <div class="empty-state">
                                                <i class="bi bi-people text-muted fs-3 mb-2"></i>
                                                <h5>Chưa có khách hàng thân thiết</h5>
                                                <p class="text-muted mb-2 small">Hãy là người đầu tiên tham gia chương trình!</p>
                                                <a href="dangky.php" class="btn btn-primary btn-sm">
                                                    <i class="bi bi-person-plus me-1"></i>Đăng ký
                                                </a>
                                            </div>
                                          </div>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Brands Section -->
        <section class="brands py-5 bg-light">
            <div class="container">
                <h2 class="text-center mb-5">Thương Hiệu Nổi Bật</h2>
                <div class="row align-items-center">
                    <div class="col-4 col-md-2 mb-4">
                        <img src="images\brands\002-nike-logos-swoosh-white.jpg" class="img-fluid" alt="Brand 1">
                    </div>
                    <div class="col-4 col-md-2 mb-4">
                        <img src="images\brands\Adidas_Logo.svg" class="img-fluid" alt="Brand 2">
                    </div>
                    <div class="col-4 col-md-2 mb-4">
                        <img src="images\brands\Ananas-Thumbnail.png" class="img-fluid" alt="Brand 3">
                    </div>
                    <div class="col-4 col-md-2 mb-4">
                        <img src="images\brands\Bitis_logo.svg.png" class="img-fluid" alt="Brand 4">
                    </div>
                    <div class="col-4 col-md-2 mb-4">
                        <img src="images\brands\logo-12.jpg" class="img-fluid" alt="Brand 5">
                    </div>
                    <div class="col-4 col-md-2 mb-4">
                        <img src="images\brands\vans-logo_2.jpg" class="img-fluid" alt="Brand 6">
                    </div>
                </div>
            </div>
        </section>

        <!-- Newsletter -->

    </main>

    <!-- Include the external JS file -->
    <script src="js/index.js"></script>

    <?php include('includes/footer.php'); ?>
</body>

</html>