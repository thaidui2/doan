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

// Code tiếp theo...
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Bug Shop</title>
        
        
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">  
        
    </head>
    
    

<body>
    <?php 
    require_once('includes/head.php');
    require_once('includes/header.php');
    
    ?>
    <link rel="stylesheet" href="css/index.css">
    <main>
        <!-- Hero Banner -->
        <section class="hero-banner">
            <div class="container-fluid p-0">
                <div id="heroCarousel" class="carousel slide" data-bs-ride="carousel">
                    <div class="carousel-indicators">
                        <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="0" class="active"></button>
                        <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="1"></button>
                        <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="2"></button>
                    </div>
                    <div class="carousel-inner">
                        <div class="carousel-item active">
                            <img src="images\banner\banner_giay_2.webp" class="d-block w-100" alt="New Collection">
                            <div class="carousel-caption d-none d-md-block">
                                <h1>Bộ Sưu Tập Mới 2025</h1>
                                <p>Khám phá các mẫu giày mới nhất cho mùa này</p>
                                <a href="#" class="btn btn-primary btn-lg">Mua ngay</a>
                            </div>
                        </div>
                        <div class="carousel-item">
                            <img src="images\banner\banner_giam_gia.webp" class="d-block w-100" alt="Sale">
                            <div class="carousel-caption d-none d-md-block">
                                <h1>Giảm Giá Đến 40%</h1>
                                <p>Ưu đãi đặc biệt cho tất cả các sản phẩm thể thao</p>
                                <a href="#" class="btn btn-danger btn-lg">Xem ngay</a>
                            </div>
                        </div>
                        <div class="carousel-item">
                            <img src="images\banner\banner_giay.png" class="d-block w-100" alt="Limited Edition">
                            <div class="carousel-caption d-none d-md-block">
                                <h1>Phiên Bản Giới Hạn</h1>
                                <p>Sản phẩm độc quyền chỉ có tại Bug Shoes</p>
                                <a href="#" class="btn btn-dark btn-lg">Khám phá</a>
                            </div>
                        </div>
                    </div>
                    <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev" >
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next" >
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
                        // Kết nối database
                        $servername = "localhost";
                        $username = "root";
                        $password = "";
                        $dbname = "shop_vippro";
                        
                        $conn = new mysqli($servername, $username, $password, $dbname);
                        
                        // Kiểm tra kết nối
                        if ($conn->connect_error) {
                            die("Kết nối thất bại: " . $conn->connect_error);
                        }
                        
                        // Set charset
                        $conn->set_charset("utf8mb4");
                        
                        // Truy vấn sản phẩm nổi bật
                        $sql = "SELECT s.*, l.tenloai, AVG(dg.diemdanhgia) as diem_trung_binh 
                                FROM sanpham s 
                                LEFT JOIN danhgia dg ON s.id_sanpham = dg.id_sanpham 
                                LEFT JOIN loaisanpham l ON s.id_loai = l.id_loai
                                WHERE s.trangthai = 1 
                                GROUP BY s.id_sanpham 
                                ORDER BY s.noibat DESC, s.ngaytao DESC 
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
                                
                                // Xử lý đường dẫn hình ảnh
                                if (!empty($row['hinhanh']) && file_exists('uploads/products/' . $row['hinhanh'])) {
                                    $img_path = 'uploads/products/' . $row['hinhanh'];
                                } else {
                                    // Kiểm tra hình ảnh từ bảng mausac_hinhanh
                                    $stmt = $conn->prepare("SELECT hinhanh FROM mausac_hinhanh WHERE id_sanpham = ? LIMIT 1");
                                    $stmt->bind_param("i", $row['id_sanpham']);
                                    $stmt->execute();
                                    $result_img = $stmt->get_result();
                                    
                                    if ($result_img->num_rows > 0) {
                                        $img_row = $result_img->fetch_assoc();
                                        if (file_exists('uploads/colors/' . $img_row['hinhanh'])) {
                                            $img_path = 'uploads/colors/' . $img_row['hinhanh'];
                                        } else {
                                            $img_path = 'images/no-image.jpg';
                                        }
                                    } else {
                                        $img_path = 'images/no-image.jpg';
                                    }
                                }
                                
                                // Xử lý điểm đánh giá
                                $rating = round($row['diem_trung_binh']);
                                if (is_null($rating)) $rating = 0;
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
                                        <a href="product-detail.php?id=<?php echo $row['id_sanpham']; ?>" class="product-img-container">
                                            <img src="<?php echo $img_path; ?>" class="card-img-top product-img" alt="<?php echo htmlspecialchars($row['tensanpham']); ?>" 
                                                 onerror="this.onerror=null; this.src='images/no-image.jpg';">
                                            <div class="overlay-effect"></div>
                                        </a>
                                        <div class="product-action">
                                            <button class="btn btn-light btn-sm rounded-circle wishlist-button" 
                                                    data-product-id="<?php echo $row['id_sanpham']; ?>" 
                                                    title="Thêm vào yêu thích">
                                                <i class="bi bi-heart"></i>
                                            </button>
                                        </div>
                                        <div class="card-body">
                                            <div class="product-category"><?php echo htmlspecialchars($row['tenloai']); ?></div>
                                            <h5 class="card-title product-title">
                                                <a href="product-detail.php?id=<?php echo $row['id_sanpham']; ?>" class="text-decoration-none text-dark">
                                                    <?php echo htmlspecialchars($row['tensanpham']); ?>
                                                </a>
                                            </h5>
                                            <div class="rating">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="bi bi-star<?php echo ($i <= $rating) ? '-fill' : ''; ?> text-warning"></i>
                                                <?php endfor; ?>
                                                <span class="ms-1 text-muted small">(<?php echo $row['soluong_danhgia'] ?? 0; ?>)</span>
                                            </div>
                                            <div class="price-wrapper">
                                                <span class="text-danger fw-bold"><?php echo number_format($row['gia'], 0, ',', '.'); ?>₫</span>
                                                <?php if ($row['giagoc'] > 0 && $row['giagoc'] > $row['gia']): ?>
                                                <small class="text-decoration-line-through text-muted ms-2"><?php echo number_format($row['giagoc'], 0, ',', '.'); ?>₫</small>
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
                        <button id="prevProduct" class="btn btn-sm btn-outline-dark me-2"><i class="bi bi-chevron-left"></i></button>
                        <button id="nextProduct" class="btn btn-sm btn-outline-dark"><i class="bi bi-chevron-right"></i></button>
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
        </div>

        <!-- Promotions -->
        <section class="promotions py-5">
            <div class="container">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="card promo-card text-white">
                            <img src="images/promo1.jpg" class="card-img" alt="Special Offer">
                            <div class="card-img-overlay d-flex flex-column justify-content-center">
                                <h3 class="card-title">Bộ Sưu Tập Giới Hạn</h3>
                                <p class="card-text">Thiết kế độc quyền chỉ có 100 đôi trên toàn thế giới</p>
                                <a href="#" class="btn btn-light">Khám phá ngay</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card promo-card text-white">
                            <img src="images\istockphoto-1220376125-612x612.jpg" class="card-img" alt="New Arrival">
                            <div class="card-img-overlay d-flex flex-column justify-content-center">
                                <h3 class="card-title">Ưu Đãi Cho Khách Hàng VIP</h3>
                                <p class="card-text">Đăng ký hôm nay để nhận giảm giá 15% cho đơn hàng đầu tiên</p>
                                <a href="#" class="btn btn-light">Đăng ký ngay</a>
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
    <script src="/assets/js/bootstrap.bundle.min.js"></script>
    <?php include('includes/footer.php'); ?>
