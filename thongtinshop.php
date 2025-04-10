<?php
session_start();

// Kết nối database
require_once('config/config.php');

// Kiểm tra ID shop
$shop_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($shop_id <= 0) {
    header('Location: index.php');
    exit();
}

// Lấy thông tin shop - cập nhật truy vấn để lấy thêm logo_shop
$shop_query = $conn->prepare("
    SELECT u.*, u.logo_shop, COUNT(DISTINCT sp.id_sanpham) as product_count
    FROM users u
    LEFT JOIN sanpham sp ON u.id_user = sp.id_nguoiban AND sp.trangthai = 1
    WHERE u.id_user = ? AND u.loai_user = 1 AND u.trang_thai = 1
    GROUP BY u.id_user
");
$shop_query->bind_param("i", $shop_id);
$shop_query->execute();
$shop = $shop_query->get_result()->fetch_assoc();

// Nếu không tìm thấy shop hoặc không phải người bán
if (!$shop) {
    header('Location: index.php');
    exit();
}

// Lấy đánh giá trung bình của shop
$rating_query = $conn->prepare("
    SELECT AVG(dg.diemdanhgia) as avg_rating, COUNT(DISTINCT dg.id_danhgia) as rating_count
    FROM danhgia dg
    JOIN sanpham sp ON dg.id_sanpham = sp.id_sanpham
    WHERE sp.id_nguoiban = ? AND dg.trangthai = 1
");
$rating_query->bind_param("i", $shop_id);
$rating_query->execute();
$rating_result = $rating_query->get_result()->fetch_assoc();

$shop_rating = $rating_result['avg_rating'] ?? 0;
$rating_count = $rating_result['rating_count'] ?? 0;

// Lấy số lượng đơn hàng đã hoàn thành
$order_query = $conn->prepare("
    SELECT COUNT(DISTINCT dh.id_donhang) as order_count
    FROM donhang dh
    JOIN donhang_chitiet dc ON dh.id_donhang = dc.id_donhang
    JOIN sanpham sp ON dc.id_sanpham = sp.id_sanpham
    WHERE sp.id_nguoiban = ? AND dh.trangthai = 4
");
$order_query->bind_param("i", $shop_id);
$order_query->execute();
$order_result = $order_query->get_result()->fetch_assoc();
$order_count = $order_result['order_count'] ?? 0;

// Các tham số lọc và sắp xếp sản phẩm
$category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = 12;
$offset = ($page - 1) * $items_per_page;

// Xây dựng câu truy vấn
$sql_conditions = ["sp.trangthai = 1", "sp.id_nguoiban = $shop_id"];
$params = [];
$param_types = "";

// Lọc theo danh mục
if ($category > 0) {
    $sql_conditions[] = "sp.id_loai = ?";
    $params[] = $category;
    $param_types .= "i";
}

// Đếm tổng số sản phẩm (cho phân trang)
$count_sql = "SELECT COUNT(*) as total FROM sanpham sp WHERE " . implode(" AND ", $sql_conditions);
if (!empty($params)) {
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param($param_types, ...$params);
    $count_stmt->execute();
    $total_items = $count_stmt->get_result()->fetch_assoc()['total'];
} else {
    $total_items = $conn->query($count_sql)->fetch_assoc()['total'];
}

// Tính tổng số trang
$total_pages = ceil($total_items / $items_per_page);

// Truy vấn sản phẩm với phân trang
$sql = "SELECT sp.*, lsp.tenloai, 
               (SELECT AVG(dg.diemdanhgia) FROM danhgia dg WHERE dg.id_sanpham = sp.id_sanpham) as diem_trung_binh,
               (SELECT COUNT(*) FROM danhgia dg WHERE dg.id_sanpham = sp.id_sanpham) as soluong_danhgia
        FROM sanpham sp
        LEFT JOIN loaisanpham lsp ON sp.id_loai = lsp.id_loai
        WHERE " . implode(" AND ", $sql_conditions);

// Sắp xếp
switch($sort) {
    case 'price_asc':
        $sql .= " ORDER BY sp.gia ASC";
        break;
    case 'price_desc':
        $sql .= " ORDER BY sp.gia DESC";
        break;
    case 'popular':
        $sql .= " ORDER BY sp.luotxem DESC";
        break;
    case 'bestseller':
        $sql .= " ORDER BY (SELECT SUM(dc.soluong) FROM donhang_chitiet dc JOIN donhang d ON dc.id_donhang = d.id_donhang 
                  WHERE dc.id_sanpham = sp.id_sanpham AND d.trangthai = 4) DESC";
        break;
    case 'rating':
        $sql .= " ORDER BY diem_trung_binh DESC";
        break;
    case 'newest':
    default:
        $sql .= " ORDER BY sp.ngaytao DESC";
}

$sql .= " LIMIT ? OFFSET ?";
$params[] = $items_per_page;
$params[] = $offset;
$param_types .= "ii";

$stmt = $conn->prepare($sql);
if(!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$products = $stmt->get_result();

// Lấy danh sách danh mục có sản phẩm của shop này
$categories_query = $conn->prepare("
    SELECT lsp.id_loai, lsp.tenloai, COUNT(sp.id_sanpham) as product_count
    FROM loaisanpham lsp
    JOIN sanpham sp ON lsp.id_loai = sp.id_loai
    WHERE sp.id_nguoiban = ? AND sp.trangthai = 1
    GROUP BY lsp.id_loai
    ORDER BY product_count DESC
");
$categories_query->bind_param("i", $shop_id);
$categories_query->execute();
$categories = $categories_query->get_result();

// Thiết lập tiêu đề trang
$page_title = $shop['ten_shop'] ? $shop['ten_shop'] : $shop['tenuser'];
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Bug Shop</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/index.css">
    
    <style>
        .shop-header {
            background-color: #f8f9fa;
            padding: 30px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .shop-avatar {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border: 5px solid #fff;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        .shop-stats {
            border-radius: 8px;
            padding: 15px;
            background-color: #fff;
            box-shadow: 0 0 15px rgba(0,0,0,0.05);
        }
        
        .stat-item {
            text-align: center;
            padding: 10px;
            border-right: 1px solid #e9ecef;
        }
        
        .stat-item:last-child {
            border-right: none;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #0d6efd;
        }
        
        .stat-label {
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        .category-filter .active {
            background-color: #0d6efd;
            color: white;
        }
        
        .shop-logo {
            width: 140px;
            height: 140px;
            object-fit: contain;
            background-color: white;
            padding: 10px;
            border: 5px solid #fff;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <?php include('includes/header.php'); ?>
    
    <main>
        <!-- Shop Header -->
        <section class="shop-header">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-3 text-center text-md-start">
                        <div class="shop-branding">
                            <?php if (!empty($shop['logo_shop'])): ?>
                                <img src="uploads/shops/<?php echo $shop['logo_shop']; ?>" alt="<?php echo htmlspecialchars($shop['ten_shop']); ?>" class="shop-logo mb-3">
                            <?php endif; ?>
                            
                            <div class="d-flex align-items-center justify-content-center justify-content-md-start">
                                <?php if (!empty($shop['anh_dai_dien'])): ?>
                                    <img src="uploads/users/<?php echo $shop['anh_dai_dien']; ?>" alt="<?php echo htmlspecialchars($shop['tenuser']); ?>" class="rounded-circle me-2" style="width: 40px; height: 40px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="rounded-circle d-flex align-items-center justify-content-center bg-secondary text-white me-2" style="width: 40px; height: 40px;">
                                        <i class="bi bi-person"></i>
                                    </div>
                                <?php endif; ?>
                                <span class="small text-muted">Chủ shop: <?php echo htmlspecialchars($shop['tenuser']); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h1 class="mb-2">
                            <?php echo !empty($shop['ten_shop']) ? htmlspecialchars($shop['ten_shop']) : htmlspecialchars($shop['tenuser']); ?>
                            <i class="bi bi-patch-check-fill text-primary ms-2"></i>
                        </h1>
                        <div class="d-flex align-items-center mb-2">
                            <div class="rating">
                                <?php 
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($i <= $shop_rating) {
                                        echo '<i class="bi bi-star-fill text-warning"></i>';
                                    } elseif ($i - $shop_rating < 1 && $i - $shop_rating > 0) {
                                        echo '<i class="bi bi-star-half text-warning"></i>';
                                    } else {
                                        echo '<i class="bi bi-star text-warning"></i>';
                                    }
                                }
                                ?>
                                <span class="ms-2"><?php echo number_format($shop_rating, 1); ?>/5</span>
                                <span class="text-muted ms-2">(<?php echo $rating_count; ?> đánh giá)</span>
                            </div>
                        </div>
                        <?php if (!empty($shop['mo_ta_shop'])): ?>
                            <p class="text-muted"><?php echo htmlspecialchars($shop['mo_ta_shop']); ?></p>
                        <?php endif; ?>
                        <div class="mt-3">
                            <?php if (!empty($shop['sdt'])): ?>
                                <span class="badge bg-light text-dark me-2">
                                    <i class="bi bi-telephone me-1"></i> <?php echo htmlspecialchars($shop['sdt']); ?>
                                </span>
                            <?php endif; ?>
                            
                            <?php if (!empty($shop['email_shop'])): ?>
                                <span class="badge bg-light text-dark me-2">
                                    <i class="bi bi-envelope me-1"></i> <?php echo htmlspecialchars($shop['email_shop']); ?>
                                </span>
                            <?php endif; ?>
                            
                            <?php if (!empty($shop['diachi'])): ?>
                                <span class="badge bg-light text-dark">
                                    <i class="bi bi-geo-alt me-1"></i> <?php echo htmlspecialchars($shop['diachi']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-3 mt-3 mt-md-0">
                        <div class="shop-stats">
                            <div class="row g-0">
                                <div class="col-4 stat-item">
                                    <div class="stat-value"><?php echo number_format($shop['product_count']); ?></div>
                                    <div class="stat-label">Sản phẩm</div>
                                </div>
                                <div class="col-4 stat-item">
                                    <div class="stat-value"><?php echo number_format($order_count); ?></div>
                                    <div class="stat-label">Đơn hàng</div>
                                </div>
                                <div class="col-4 stat-item">
                                    <div class="stat-value"><?php echo $shop['ngay_tao'] ? date('Y', strtotime($shop['ngay_tao'])) : date('Y'); ?></div>
                                    <div class="stat-label">Tham gia</div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3">
                            <a href="chat.php?seller=<?php echo $shop_id; ?>" class="btn btn-outline-primary w-100">
                                <i class="bi bi-chat-dots me-2"></i> Chat với người bán
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        
        <!-- Shop Products Section -->
        <section class="shop-products py-5">
            <div class="container">
                <!-- Filter and Sort Controls -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="category-filter d-flex flex-wrap gap-2">
                            <a href="thongtinshop.php?id=<?php echo $shop_id; ?>&sort=<?php echo $sort; ?>" 
                               class="btn btn-sm <?php echo $category == 0 ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                Tất cả
                            </a>
                            
                            <?php while ($cat = $categories->fetch_assoc()): ?>
                            <a href="thongtinshop.php?id=<?php echo $shop_id; ?>&category=<?php echo $cat['id_loai']; ?>&sort=<?php echo $sort; ?>" 
                               class="btn btn-sm <?php echo $category == $cat['id_loai'] ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                <?php echo htmlspecialchars($cat['tenloai']); ?> (<?php echo $cat['product_count']; ?>)
                            </a>
                            <?php endwhile; ?>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex justify-content-end">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="sortDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bi bi-sort-down me-1"></i> Sắp xếp
                                </button>
                                <ul class="dropdown-menu" aria-labelledby="sortDropdown">
                                    <li>
                                        <a class="dropdown-item <?php echo $sort == 'newest' ? 'active' : ''; ?>" 
                                           href="thongtinshop.php?id=<?php echo $shop_id; ?>&category=<?php echo $category; ?>&sort=newest">
                                            Mới nhất
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item <?php echo $sort == 'price_asc' ? 'active' : ''; ?>" 
                                           href="thongtinshop.php?id=<?php echo $shop_id; ?>&category=<?php echo $category; ?>&sort=price_asc">
                                            Giá tăng dần
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item <?php echo $sort == 'price_desc' ? 'active' : ''; ?>" 
                                           href="thongtinshop.php?id=<?php echo $shop_id; ?>&category=<?php echo $category; ?>&sort=price_desc">
                                            Giá giảm dần
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item <?php echo $sort == 'popular' ? 'active' : ''; ?>" 
                                           href="thongtinshop.php?id=<?php echo $shop_id; ?>&category=<?php echo $category; ?>&sort=popular">
                                            Phổ biến nhất
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item <?php echo $sort == 'bestseller' ? 'active' : ''; ?>" 
                                           href="thongtinshop.php?id=<?php echo $shop_id; ?>&category=<?php echo $category; ?>&sort=bestseller">
                                            Bán chạy nhất
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item <?php echo $sort == 'rating' ? 'active' : ''; ?>" 
                                           href="thongtinshop.php?id=<?php echo $shop_id; ?>&category=<?php echo $category; ?>&sort=rating">
                                            Đánh giá cao nhất
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Product Grid -->
                <div class="row">
                    <?php if ($products->num_rows > 0): ?>
                        <?php while ($product = $products->fetch_assoc()): ?>
                            <?php
                            // Tính phần trăm giảm giá
                            $discount_percent = 0;
                            if ($product['giagoc'] > 0 && $product['giagoc'] > $product['gia']) {
                                $discount_percent = round(100 - ($product['gia'] / $product['giagoc'] * 100));
                            }
                            
                            // Xử lý đường dẫn hình ảnh
                            if (!empty($product['hinhanh']) && file_exists('uploads/products/' . $product['hinhanh'])) {
                                $img_path = 'uploads/products/' . $product['hinhanh'];
                            } else {
                                // Kiểm tra hình ảnh từ bảng mausac_hinhanh
                                $img_stmt = $conn->prepare("SELECT hinhanh FROM mausac_hinhanh WHERE id_sanpham = ? LIMIT 1");
                                $img_stmt->bind_param("i", $product['id_sanpham']);
                                $img_stmt->execute();
                                $img_result = $img_stmt->get_result();
                                
                                if ($img_result->num_rows > 0) {
                                    $img_row = $img_result->fetch_assoc();
                                    if (file_exists('uploads/colors/' . $img_row['hinhanh'])) {
                                        $img_path = 'uploads/colors/' . $img_row['hinhanh'];
                                    } else {
                                        $img_path = 'images/no-image.jpg';
                                    }
                                } else {
                                    $img_path = 'images/no-image.jpg';
                                }
                            }
                            
                            // Xử lý đánh giá
                            $rating = round($product['diem_trung_binh']);
                            if (is_null($rating)) $rating = 0;
                            ?>
                            <div class="col-6 col-md-3 mb-4">
                                <div class="card product-card h-100">
                                    <div class="product-badge-container">
                                        <?php if ($discount_percent > 0): ?>
                                        <div class="product-badge bg-danger text-white">
                                            <i class="bi bi-tags-fill me-1"></i>-<?php echo $discount_percent; ?>%
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($product['noibat'] == 1): ?>
                                        <div class="product-badge bg-primary text-white">
                                            <i class="bi bi-star-fill me-1"></i>HOT
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <a href="product-detail.php?id=<?php echo $product['id_sanpham']; ?>" class="product-img-container">
                                        <img src="<?php echo $img_path; ?>" class="card-img-top product-img" alt="<?php echo htmlspecialchars($product['tensanpham']); ?>" 
                                             onerror="this.onerror=null; this.src='images/no-image.jpg';">
                                        <div class="overlay-effect"></div>
                                    </a>
                                    <div class="product-action">
                                        <button class="btn btn-light btn-sm rounded-circle wishlist-button" 
                                                data-product-id="<?php echo $product['id_sanpham']; ?>" 
                                                title="Thêm vào yêu thích">
                                            <i class="bi bi-heart"></i>
                                        </button>
                                    </div>
                                    <div class="card-body">
                                        <div class="product-category"><?php echo htmlspecialchars($product['tenloai']); ?></div>
                                        <h5 class="card-title product-title">
                                            <a href="product-detail.php?id=<?php echo $product['id_sanpham']; ?>" class="text-decoration-none text-dark">
                                                <?php echo htmlspecialchars($product['tensanpham']); ?>
                                            </a>
                                        </h5>
                                        <div class="rating">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="bi bi-star<?php echo ($i <= $rating) ? '-fill' : ''; ?> text-warning"></i>
                                            <?php endfor; ?>
                                            <span class="ms-1 text-muted small">(<?php echo $product['soluong_danhgia'] ?? 0; ?>)</span>
                                        </div>
                                        <div class="price-wrapper">
                                            <span class="text-danger fw-bold"><?php echo number_format($product['gia'], 0, ',', '.'); ?>₫</span>
                                            <?php if ($product['giagoc'] > 0 && $product['giagoc'] > $product['gia']): ?>
                                            <small class="text-decoration-line-through text-muted ms-2"><?php echo number_format($product['giagoc'], 0, ',', '.'); ?>₫</small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="card-footer bg-white border-top-0">
                                        <button class="btn btn-sm btn-outline-primary w-100 add-to-cart-btn" data-product-id="<?php echo $product['id_sanpham']; ?>">
                                            <i class="bi bi-cart-plus me-1"></i> Thêm vào giỏ
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="alert alert-info text-center">
                                <i class="bi bi-info-circle me-2"></i> Không tìm thấy sản phẩm nào.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="row mt-4">
                    <div class="col-12">
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="thongtinshop.php?id=<?php echo $shop_id; ?>&category=<?php echo $category; ?>&sort=<?php echo $sort; ?>&page=<?php echo $page-1; ?>">
                                        <i class="bi bi-chevron-left"></i>
                                    </a>
                                </li>
                                
                                <?php for($i = max(1, $page-2); $i <= min($page+2, $total_pages); $i++): ?>
                                <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                    <a class="page-link" href="thongtinshop.php?id=<?php echo $shop_id; ?>&category=<?php echo $category; ?>&sort=<?php echo $sort; ?>&page=<?php echo $i; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="thongtinshop.php?id=<?php echo $shop_id; ?>&category=<?php echo $category; ?>&sort=<?php echo $sort; ?>&page=<?php echo $page+1; ?>">
                                        <i class="bi bi-chevron-right"></i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </section>
    </main>
    
    <?php include('includes/footer.php'); ?>
    
    <!-- Bootstrap JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Add to Cart JavaScript -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Xử lý thêm vào giỏ hàng
        const addToCartButtons = document.querySelectorAll('.add-to-cart-btn');
        addToCartButtons.forEach(button => {
            button.addEventListener('click', function() {
                const productId = this.getAttribute('data-product-id');
                const button = this;
                
                button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Đang thêm...';
                button.disabled = true;
                
                // Gửi yêu cầu AJAX
                fetch('api/cart-add.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `product_id=${productId}&quantity=1`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        button.innerHTML = '<i class="bi bi-check-lg me-1"></i> Đã thêm';
                        
                        // Cập nhật số lượng giỏ hàng trong header
                        const cartCountElement = document.querySelector('.cart-count');
                        if (cartCountElement) {
                            cartCountElement.textContent = data.cart_count;
                            cartCountElement.style.display = 'inline';
                        }
                        
                        // Sau 2 giây, đặt lại nút
                        setTimeout(() => {
                            button.innerHTML = '<i class="bi bi-cart-plus me-1"></i> Thêm vào giỏ';
                            button.disabled = false;
                        }, 2000);
                    } else {
                        alert(data.message || 'Đã xảy ra lỗi khi thêm sản phẩm vào giỏ hàng');
                        button.innerHTML = '<i class="bi bi-cart-plus me-1"></i> Thêm vào giỏ';
                        button.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Đã xảy ra lỗi khi thêm sản phẩm vào giỏ hàng');
                    button.innerHTML = '<i class="bi bi-cart-plus me-1"></i> Thêm vào giỏ';
                    button.disabled = false;
                });
            });
        });
        
        // Xử lý wishlist
        const wishlistButtons = document.querySelectorAll('.wishlist-button');
        wishlistButtons.forEach(button => {
            button.addEventListener('click', function() {
                const productId = this.getAttribute('data-product-id');
                // Gửi yêu cầu AJAX đến tệp xử lý wishlist
                // Mã xử lý tương tự như trong file wishlist.js
            });
        });
    });
    </script>
</body>
</html>