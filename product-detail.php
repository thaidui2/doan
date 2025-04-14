<?php
session_start();
include('config/config.php');
if (empty($color_images)) {
    echo "<!-- DEBUG: Không tìm thấy ảnh màu nào cho sản phẩm này trong bảng mausac_hinhanh -->";
}
// Kiểm tra và tạo bảng mausac_hinhanh nếu chưa tồn tại
$check_table = $conn->query("SHOW TABLES LIKE 'mausac_hinhanh'");
if ($check_table->num_rows == 0) {
    $create_table = "CREATE TABLE IF NOT EXISTS `mausac_hinhanh` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `id_sanpham` int(11) NOT NULL,
      `id_mausac` int(11) NOT NULL,
      `hinhanh` varchar(255) NOT NULL,
      PRIMARY KEY (`id`),
      KEY `id_sanpham` (`id_sanpham`),
      KEY `id_mausac` (`id_mausac`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    $conn->query($create_table);
}
// Kiểm tra và tự động thêm cột hinhanh_mau nếu chưa tồn tại
$check_column = $conn->query("SHOW COLUMNS FROM sanpham_chitiet LIKE 'hinhanh_mau'");
if ($check_column->num_rows == 0) {
    $alter_table = "ALTER TABLE sanpham_chitiet ADD hinhanh_mau VARCHAR(255) NULL AFTER soluong";
    $conn->query($alter_table);
}

// Lấy ID sản phẩm từ URL
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Kiểm tra nếu ID không hợp lệ
if ($product_id <= 0) {
    header('Location: sanpham.php');
    exit();
}

// Cập nhật lượt xem sản phẩm
$update_view = $conn->prepare("UPDATE sanpham SET luotxem = luotxem + 1 WHERE id_sanpham = ?");
$update_view->bind_param("i", $product_id);
$update_view->execute();

// Modify query to remove references to seller information (id_nguoiban)
$product_stmt = $conn->prepare("
    SELECT sp.*, lsp.tenloai, th.tenthuonghieu, th.logo AS thuonghieu_logo
    FROM sanpham sp
    LEFT JOIN loaisanpham lsp ON sp.id_loai = lsp.id_loai
    LEFT JOIN thuonghieu th ON sp.id_thuonghieu = th.id_thuonghieu
    WHERE sp.id_sanpham = ? AND sp.trangthai = 1
");
$product_stmt->bind_param("i", $product_id);
$product_stmt->execute();
$result = $product_stmt->get_result();

// Kiểm tra sản phẩm tồn tại
if ($result->num_rows === 0) {
    header('Location: sanpham.php');
    exit();
}

$product = $result->fetch_assoc();

// Thêm vào file product-detail.php sau khi lấy thông tin sản phẩm

// Lấy các biến thể sản phẩm (kích thước, màu sắc, hình ảnh)
$variants_stmt = $conn->prepare("
    SELECT spct.*, kt.tenkichthuoc, ms.tenmau, ms.mamau
    FROM sanpham_chitiet spct
    JOIN kichthuoc kt ON spct.id_kichthuoc = kt.id_kichthuoc
    JOIN mausac ms ON spct.id_mausac = ms.id_mausac
    WHERE spct.id_sanpham = ?
    ORDER BY kt.tenkichthuoc, ms.tenmau
");
$variants_stmt->bind_param("i", $product_id);
$variants_stmt->execute();
$variants_result = $variants_stmt->get_result();

$product_variants = [];
$available_sizes_by_color = []; // Lưu trữ sizes có sẵn cho từng màu
$color_images = []; // Lưu trữ hình ảnh cho từng màu
$color_info = []; // Thông tin về màu sắc (tên, mã màu)

while ($variant = $variants_result->fetch_assoc()) {
    $product_variants[] = $variant;
    
    // Lưu size có sẵn cho từng màu
    if (!isset($available_sizes_by_color[$variant['id_mausac']])) {
        $available_sizes_by_color[$variant['id_mausac']] = [];
    }
    $available_sizes_by_color[$variant['id_mausac']][] = $variant['id_kichthuoc'];
    
    // Lưu thông tin màu
    $color_info[$variant['id_mausac']] = [
        'name' => $variant['tenmau'],
        'code' => $variant['mamau']
    ];
}

// Truy vấn bảng mausac_hinhanh để lấy ảnh màu
$color_images_stmt = $conn->prepare("
    SELECT id_mausac, hinhanh 
    FROM mausac_hinhanh 
    WHERE id_sanpham = ?
");
$color_images_stmt->bind_param("i", $product_id);
$color_images_stmt->execute();
$color_images_result = $color_images_stmt->get_result();

// Đọc dữ liệu ảnh màu
while ($color_image = $color_images_result->fetch_assoc()) {
    $color_images[$color_image['id_mausac']] = $color_image['hinhanh'];
}

// Lấy các kích thước có sẵn
$sizes_stmt = $conn->prepare("
    SELECT DISTINCT kt.id_kichthuoc, kt.tenkichthuoc
    FROM sanpham_chitiet spct
    JOIN kichthuoc kt ON spct.id_kichthuoc = kt.id_kichthuoc
    WHERE spct.id_sanpham = ? AND spct.soluong > 0
    ORDER BY kt.tenkichthuoc
");
$sizes_stmt->bind_param("i", $product_id);
$sizes_stmt->execute();
$sizes_result = $sizes_stmt->get_result();

// Lấy các màu có sẵn
$colors_stmt = $conn->prepare("
    SELECT DISTINCT ms.id_mausac, ms.tenmau, ms.mamau
    FROM sanpham_chitiet spct
    JOIN mausac ms ON spct.id_mausac = ms.id_mausac
    WHERE spct.id_sanpham = ? AND spct.soluong > 0
    ORDER BY ms.tenmau
");
$colors_stmt->bind_param("i", $product_id);
$colors_stmt->execute();
$colors_result = $colors_stmt->get_result();

// Lấy đánh giá sản phẩm
$reviews_stmt = $conn->prepare("
    SELECT dg.*, u.tenuser, u.anh_dai_dien
    FROM danhgia dg
    JOIN users u ON dg.id_user = u.id_user
    WHERE dg.id_sanpham = ? AND dg.trangthai = 1
    ORDER BY dg.ngaydanhgia DESC
    LIMIT 10
");
$reviews_stmt->bind_param("i", $product_id);
$reviews_stmt->execute();
$reviews_result = $reviews_stmt->get_result();

// Lấy số lượng đánh giá theo số sao
$rating_counts = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
$rating_stmt = $conn->prepare("
    SELECT diemdanhgia, COUNT(*) as count
    FROM danhgia
    WHERE id_sanpham = ? AND trangthai = 1
    GROUP BY diemdanhgia
");
$rating_stmt->bind_param("i", $product_id);
$rating_stmt->execute();
$rating_result = $rating_stmt->get_result();

while ($row = $rating_result->fetch_assoc()) {
    $rating_counts[$row['diemdanhgia']] = $row['count'];
}

$total_ratings = array_sum($rating_counts);

// Lấy sản phẩm liên quan (cùng danh mục)
$related_stmt = $conn->prepare("
    SELECT id_sanpham, tensanpham, gia, giagoc, hinhanh, diemdanhgia_tb
    FROM sanpham
    WHERE id_loai = ? AND id_sanpham != ? AND trangthai = 1
    ORDER BY RAND()
    LIMIT 4
");
$related_stmt->bind_param("ii", $product['id_loai'], $product_id);
$related_stmt->execute();
$related_result = $related_stmt->get_result();

// Xử lý các hình ảnh phụ
$images = [];
if (!empty($product['hinhanh'])) {
    $images[] = $product['hinhanh'];
}
if (!empty($product['hinhanh_phu'])) {
    $additional_images = explode('|', $product['hinhanh_phu']);
    foreach ($additional_images as $img) {
        if (!empty(trim($img))) {
            $images[] = trim($img);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['tensanpham']); ?> - Bug Shop</title>
    <link rel="stylesheet" href="node_modules/bootstrap/dist/css/bootstrap.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/product-detail.css">
    <style>
        /* Thêm vào phần <head> của trang */
        .stock-info {
            padding: 8px 12px;
            border-radius: 4px;
            background-color: #f8f9fa;
            border-left: 3px solid #6c757d;
            font-size: 0.9rem;
            margin-top: 8px;
        }
        
        .stock-high {
            border-left-color: #28a745;
            background-color: #f0fff0;
        }
        
        .stock-medium {
            border-left-color: #ffc107;
            background-color: #fffbf0;
        }
        
        .stock-low {
            border-left-color: #dc3545;
            background-color: #fff5f5;
        }
    </style>
</head>
<body>
<?php 
    require_once('includes/head.php');
    require_once('includes/header.php');
    
    ?>
    
    <!-- Data để JavaScript có thể sử dụng -->
    <script type="application/json" id="available-sizes-data">
        <?php echo json_encode($available_sizes_by_color); ?>
    </script>
    
    <script type="application/json" id="color-images-data">
        <?php echo json_encode($color_images); ?>
    </script>
    
    <!-- Thêm dữ liệu tồn kho cho từng biến thể -->
    <script type="application/json" id="variant-stock-data">
        <?php 
            $variant_stock = [];
            $variants_result->data_seek(0);
            while ($variant = $variants_result->fetch_assoc()) {
                $variant_stock[$variant['id_kichthuoc']][$variant['id_mausac']] = $variant['soluong'];
            }
            echo json_encode($variant_stock);
        ?>
    </script>
    
    <div class="container mt-5 mb-5">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Trang chủ</a></li>
                <li class="breadcrumb-item"><a href="sanpham.php">Sản phẩm</a></li>
                <li class="breadcrumb-item"><a href="sanpham.php?category=<?php echo $product['id_loai']; ?>"><?php echo htmlspecialchars($product['tenloai']); ?></a></li>
                <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($product['tensanpham']); ?></li>
            </ol>
        </nav>
        
        <div class="row">
            <!-- Product Images -->
            <div class="col-md-6 mb-4">
                <div class="card shadow-sm">
                    <div class="card-body p-3">
                        <div class="main-image-container position-relative" id="image-container">
                            <img src="uploads/products/<?php echo !empty($product['hinhanh']) ? $product['hinhanh'] : 'no-image.png'; ?>" 
                                 id="main-product-image" class="img-fluid" 
                                 alt="<?php echo htmlspecialchars($product['tensanpham']); ?>">
                        </div>
                        
                        <!-- Thumbnail Gallery -->
                        <div class="d-flex flex-wrap gap-2">
                            <!-- Hiển thị ảnh màu sắc với indicator -->
                            <?php if (!empty($color_images)): ?>
                                <?php foreach ($color_images as $color_id => $img): ?>
                                    <div class="thumbnail-wrapper" data-type="color" data-color-id="<?php echo $color_id; ?>">
                                        <img src="uploads/colors/<?php echo $img; ?>" 
                                             class="thumbnail-image color-thumbnail" 
                                             alt="<?php echo htmlspecialchars($color_info[$color_id]['name']); ?>"
                                             data-color-id="<?php echo $color_id; ?>" 
                                             title="<?php echo htmlspecialchars($color_info[$color_id]['name']); ?>">
                                        <div class="color-thumbnail-indicator" style="background-color: <?php echo htmlspecialchars($color_info[$color_id]['code']); ?>"></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            
                            <!-- Hiển thị ảnh mặc định -->
                            <?php if (count($images) > 0): ?>
                                <div class="thumbnail-wrapper active" data-type="default">
                                    <img src="uploads/products/<?php echo $images[0]; ?>" 
                                         class="thumbnail-image default-thumbnail" 
                                         alt="Default Image" 
                                         data-default="true" 
                                         title="Ảnh mặc định">
                                </div>
                                
                                <!-- Hiển thị ảnh phụ với cải tiến -->
                                <?php if(count($images) > 1): ?>
                                    <div class="product-thumbnails">
                                        <div class="thumbnail-header mb-2">
                                            <h6 class="mb-0"><small>Hình ảnh khác (<?php echo count($images) - 1; ?>)</small></h6>
                                        </div>
                                        
                                        <div class="thumbnails-container d-flex flex-wrap gap-2">
                                            <?php for($i=1; $i < count($images); $i++): ?>
                                                <div class="thumbnail-wrapper position-relative" data-type="additional" data-index="<?php echo $i; ?>">
                                                    <img src="uploads/products/<?php echo $images[$i]; ?>" 
                                                         class="thumbnail-image img-thumbnail" 
                                                         alt="Product Image <?php echo $i+1; ?>" 
                                                         title="Hình ảnh <?php echo $i+1; ?> - Click để xem">
                                                    <div class="thumbnail-overlay">
                                                        <span class="image-number badge rounded-pill bg-dark"><?php echo $i+1; ?>/<?php echo count($images); ?></span>
                                                    </div>
                                                </div>
                                            <?php endfor; ?>
                                            
                                            <?php if(count($images) > 5): ?>
                                                <button class="btn btn-outline-secondary btn-sm more-images" type="button" data-bs-toggle="modal" data-bs-target="#allImagesModal">
                                                    <i class="bi bi-images"></i> Xem tất cả
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <?php if(count($images) > 5): ?>
                                    <!-- Modal hiển thị tất cả hình ảnh -->
                                    <div class="modal fade" id="allImagesModal" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-lg modal-dialog-centered">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Tất cả hình ảnh sản phẩm</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="row g-2">
                                                        <?php foreach($images as $index => $img): ?>
                                                            <div class="col-6 col-md-4">
                                                                <div class="product-gallery-item">
                                                                    <img src="uploads/products/<?php echo $img; ?>" 
                                                                         class="img-fluid rounded w-100 gallery-image" 
                                                                         data-index="<?php echo $index; ?>"
                                                                         alt="<?php echo htmlspecialchars($product['tensanpham']); ?>">
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Product Details -->
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <?php if ($product['noibat'] == 1): ?>
                            <div class="mb-2">
                                <span class="badge bg-warning text-dark">Sản phẩm nổi bật</span>
                            </div>
                        <?php endif; ?>
                        
                        <h2 class="mb-2"><?php echo htmlspecialchars($product['tensanpham']); ?></h2>
                        
                        <!-- Category and Brand -->
                        <div class="mb-3">
                            <span class="text-muted">Danh mục: <a href="sanpham.php?category=<?php echo $product['id_loai']; ?>"><?php echo htmlspecialchars($product['tenloai']); ?></a></span>
                            
                            <?php if (!empty($product['tenthuonghieu'])): ?>
                            <span class="mx-2">|</span>
                            <span class="text-muted">Thương hiệu: <a href="sanpham.php?brand=<?php echo $product['id_thuonghieu']; ?>"><?php echo htmlspecialchars($product['tenthuonghieu']); ?></a></span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Rating -->
                        <div class="mb-3 d-flex align-items-center">
                            <div class="rating">
                                <?php
                                $rating = $product['diemdanhgia_tb'];
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($i <= $rating) {
                                        echo '<i class="bi bi-star-fill"></i>';
                                    } elseif ($i - $rating < 1 && $i - $rating > 0) {
                                        echo '<i class="bi bi-star-half"></i>';
                                    } else {
                                        echo '<i class="bi bi-star"></i>';
                                    }
                                }
                                ?>
                            </div>
                            <span class="ms-2"><?php echo number_format($rating, 1); ?>/5</span>
                            <span class="ms-2 text-muted">(<?php echo $product['soluong_danhgia']; ?> đánh giá)</span>
                        </div>
                        
                        <!-- Stock Status -->
                        <div class="mb-3">
                            <?php if ($product['soluong'] > 0): ?>
                                <span class="badge bg-success">Còn hàng</span>
                                <span class="ms-2 text-muted">Còn <strong><?php echo $product['soluong']; ?></strong> sản phẩm</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Hết hàng</span>
                            <?php endif; ?>
                            <span class="ms-2 text-muted small">Đã bán: <?php 
                            // Truy vấn lấy tổng số lượng đã bán từ đơn hàng hoàn thành (trạng thái 4)
                            $sold_query = $conn->prepare("
                                SELECT COALESCE(SUM(dc.soluong), 0) as total_sold
                                FROM donhang_chitiet dc
                                JOIN donhang d ON dc.id_donhang = d.id_donhang
                                WHERE dc.id_sanpham = ? AND d.trangthai = 4
                            ");
                            $sold_query->bind_param("i", $product_id);
                            $sold_query->execute();
                            $sold_result = $sold_query->get_result()->fetch_assoc();
                            echo $sold_result['total_sold']; 
                            ?> sản phẩm</span>
                        </div>
                        
                        <!-- Price -->
                        <div class="mb-4">
                            <div class="h3 mb-0">
                                <span class="text-danger fw-bold"><?php echo number_format($product['gia'], 0, ',', '.'); ?>₫</span>
                                <?php if (!empty($product['giagoc']) && $product['giagoc'] > $product['gia']): ?>
                                    <span class="original-price ms-2"><?php echo number_format($product['giagoc'], 0, ',', '.'); ?>₫</span>
                                    <?php $discount = round(($product['giagoc'] - $product['gia']) / $product['giagoc'] * 100); ?>
                                    <span class="discount-badge">-<?php echo $discount; ?>%</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <hr>

                        <hr>
                        
                        <!-- Size Selection -->
                        <?php if ($sizes_result->num_rows > 0): ?>
                        <div class="mb-4">
                            <h5 class="mb-2">Kích thước: <span class="selected-size-value text-muted">Chưa chọn</span></h5>
                            <div id="size-options" class="d-flex flex-wrap gap-2">
                                <?php 
                                $sizes_result->data_seek(0);
                                while ($size = $sizes_result->fetch_assoc()): 
                                ?>
                                    <button type="button" 
                                            class="btn btn-outline-dark size-btn" 
                                            data-size-id="<?php echo $size['id_kichthuoc']; ?>"
                                            data-size-name="<?php echo htmlspecialchars($size['tenkichthuoc']); ?>">
                                        <?php echo htmlspecialchars($size['tenkichthuoc']); ?>
                                    </button>
                                <?php endwhile; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Color Selection -->
                        <?php if ($colors_result->num_rows > 0): ?>
                        <div class="mb-4">
                            <h5 class="mb-2">Màu sắc:</h5>
                            <div class="color-selector">
                                <?php $colors_result->data_seek(0); ?>
                                <?php while ($color = $colors_result->fetch_assoc()): ?>
                                    <div class="color-option" 
                                         data-color-id="<?php echo $color['id_mausac']; ?>"
                                         data-color-name="<?php echo htmlspecialchars($color['tenmau']); ?>"
                                         data-color-code="<?php echo htmlspecialchars($color['mamau']); ?>"
                                         <?php if (!isset($available_sizes_by_color[$color['id_mausac']])): ?>
                                         data-disabled="true"
                                         <?php endif; ?>>
                                        <div class="color-circle" style="background-color: <?php echo htmlspecialchars($color['mamau']); ?>"></div>
                                        <span class="color-name"><?php echo htmlspecialchars($color['tenmau']); ?></span>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                            
                            <div class="selected-color-info mt-2">
                                <span class="selected-color-label">Màu đã chọn:</span>
                                <span class="selected-color-value">Chưa chọn</span>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Quantity -->
                        <div class="mb-4">
                            <h5 class="mb-2">Số lượng:</h5>
                            <div class="quantity-control">
                                <div class="quantity-btn" id="decreaseBtn">-</div>
                                <input type="number" id="quantity" class="quantity-input" value="1" min="1" max="<?php echo $product['soluong']; ?>">
                                <div class="quantity-btn" id="increaseBtn">+</div>
                            </div>
                        </div>
                        
                        <!-- Hiển thị số lượng tồn kho cho biến thể đã chọn -->
                        <div id="variant-stock-info" class="stock-info d-none mt-3">
                            <i class="bi bi-box-seam me-2"></i>
                            <span>Số lượng trong kho: <strong id="variant-stock-count">0</strong></span>
                            <span id="stock-status-message"></span>
                        </div>
                        
                        <!-- Add to Cart and Buy Buttons -->
                        <div class="d-grid gap-2 d-md-flex mt-4">
                            <button type="button" class="btn btn-outline-dark btn-lg flex-grow-1" id="addToCartBtn">
                                <i class="bi bi-cart-plus"></i> Thêm vào giỏ
                            </button>
                            <button type="button" class="btn btn-danger btn-lg flex-grow-1" id="buyNowBtn">
                                Mua ngay
                            </button>
                        </div>
                        
                        <!-- Thêm vào phần thông tin sản phẩm -->
                        
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Product Description and Reviews Tabs -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <ul class="nav nav-tabs card-header-tabs" id="productTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="description-tab" data-bs-toggle="tab" data-bs-target="#description" type="button" role="tab" aria-controls="description" aria-selected="true">
                                    Mô tả sản phẩm
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="reviews-tab" data-bs-toggle="tab" data-bs-target="#reviews" type="button" role="tab" aria-controls="reviews" aria-selected="false">
                                    Đánh giá (<?php echo $product['soluong_danhgia']; ?>)
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content" id="productTabContent">
                            <!-- Description Tab -->
                            <div class="tab-pane fade show active" id="description" role="tabpanel" aria-labelledby="description-tab">
                                <?php if (!empty($product['mota'])): ?>
                                    <?php echo nl2br(htmlspecialchars($product['mota'])); ?>
                                <?php else: ?>
                                    <p class="text-muted">Chưa có mô tả chi tiết cho sản phẩm này.</p>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Reviews Tab -->
                            <div class="tab-pane fade" id="reviews" role="tabpanel" aria-labelledby="reviews-tab">
                                <!-- Rating Summary -->
                                <div class="row mb-4">
                                    <div class="col-md-4">
                                        <div class="text-center mb-4">
                                            <div class="display-4 fw-bold"><?php echo number_format($rating, 1); ?></div>
                                            <div class="rating mt-2">
                                                <?php
                                                for ($i = 1; $i <= 5; $i++) {
                                                    if ($i <= $rating) {
                                                        echo '<i class="bi bi-star-fill"></i>';
                                                    } elseif ($i - $rating < 1 && $i - $rating > 0) {
                                                        echo '<i class="bi bi-star-half"></i>';
                                                    } else {
                                                        echo '<i class="bi bi-star"></i>';
                                                    }
                                                }
                                                ?>
                                            </div>
                                            <div class="text-muted mt-1"><?php echo $total_ratings; ?> đánh giá</div>
                                        </div>
                                    </div>
                                    <div class="col-md-8">
                                        <?php for ($i = 5; $i >= 1; $i--): ?>
                                        <div class="d-flex align-items-center mb-2">
                                            <div class="me-2"><?php echo $i; ?> <i class="bi bi-star-fill"></i></div>
                                            <div class="flex-grow-1 me-3">
                                                <div class="rating-bar">
                                                    <div class="rating-bar-fill" style="width: <?php echo $total_ratings > 0 ? ($rating_counts[$i] / $total_ratings * 100) : 0; ?>%"></div>
                                                </div>
                                            </div>
                                            <div><?php echo $rating_counts[$i]; ?></div>
                                        </div>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <!-- Review List -->
                                <div class="review-list mt-4">
                                    <?php if ($reviews_result->num_rows > 0): ?>
                                        <?php while ($review = $reviews_result->fetch_assoc()): ?>
                                        <div class="card mb-3">
                                            <div class="card-body">
                                                <div class="d-flex mb-3">
                                                    <div class="me-3">
                                                        <?php if (!empty($review['anh_dai_dien'])): ?>
                                                            <img src="uploads/users/<?php echo $review['anh_dai_dien']; ?>" alt="<?php echo htmlspecialchars($review['tenuser']); ?>" class="rounded-circle" width="50" height="50">
                                                        <?php else: ?>
                                                            <div class="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px">
                                                                <i class="bi bi-person fs-4"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($review['tenuser']); ?></h6>
                                                        <div class="d-flex align-items-center small">
                                                            <div class="rating">
                                                                <?php
                                                                for ($i = 1; $i <= 5; $i++) {
                                                                    echo $i <= $review['diemdanhgia'] ? '<i class="bi bi-star-fill"></i>' : '<i class="bi bi-star"></i>';
                                                                }
                                                                ?>
                                                            </div>
                                                            <span class="text-muted ms-2"><?php echo date('d/m/Y', strtotime($review['ngaydanhgia'])); ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <p><?php echo nl2br(htmlspecialchars($review['noidung'])); ?></p>
                                                
                                                <?php if (!empty($review['hinhanh'])): ?>
                                                <div class="mt-2">
                                                    <img src="uploads/reviews/<?php echo $review['hinhanh']; ?>" alt="Review Image" class="img-thumbnail" style="max-height: 150px;">
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <div class="alert alert-info">
                                            Sản phẩm này chưa có đánh giá nào. Hãy là người đầu tiên đánh giá sản phẩm này!
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if (isset($_SESSION['user'])): ?>
                                <div class="mt-4">
                                    <a href="write-review.php?product_id=<?php echo $product_id; ?>" class="btn btn-outline-primary">Viết đánh giá</a>
                                </div>
                                <?php else: ?>
                                <div class="mt-4 alert alert-secondary">
                                    <i class="bi bi-info-circle me-2"></i>
                                    Bạn cần <a href="dangnhap.php">đăng nhập</a> để đánh giá sản phẩm
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Related Products -->
        <div class="mt-5">
            <h3 class="mb-4">Sản phẩm tương tự</h3>
            <div class="row">
                <?php if ($related_result->num_rows > 0): ?>
                    <?php while ($related = $related_result->fetch_assoc()): ?>
                    <div class="col-md-3 mb-4">
                        <div class="card related-product-card h-100">
                            <a href="product-detail.php?id=<?php echo $related['id_sanpham']; ?>" class="text-decoration-none">
                                <img src="<?php echo !empty($related['hinhanh']) ? 'uploads/products/' . $related['hinhanh'] : 'images/no-image.png'; ?>" class="card-img-top" alt="<?php echo htmlspecialchars($related['tensanpham']); ?>">
                            </a>
                            <div class="card-body">
                                <h6 class="card-title">
                                    <a href="product-detail.php?id=<?php echo $related['id_sanpham']; ?>" class="text-decoration-none text-dark">
                                        <?php echo htmlspecialchars($related['tensanpham']); ?>
                                    </a>
                                </h6>
                                <div class="small mb-2">
                                    <div class="rating">
                                        <?php
                                        $rel_rating = $related['diemdanhgia_tb'];
                                        for ($i = 1; $i <= 5; $i++) {
                                            if ($i <= $rel_rating) {
                                                echo '<i class="bi bi-star-fill"></i>';
                                            } elseif ($i - $rel_rating < 1 && $i - $rel_rating > 0) {
                                                echo '<i class="bi bi-star-half"></i>';
                                            } else {
                                                echo '<i class="bi bi-star"></i>';
                                            }
                                        }
                                        ?>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong class="text-danger"><?php echo number_format($related['gia'], 0, ',', '.'); ?>₫</strong>
                                    <?php if (!empty($related['giagoc']) && $related['giagoc'] > $related['gia']): ?>
                                    <span class="text-decoration-line-through small text-muted"><?php echo number_format($related['giagoc'], 0, ',', '.'); ?>₫</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-footer bg-white border-top-0">
                                <button class="btn btn-sm btn-outline-dark w-100 add-to-cart-btn" data-product-id="<?php echo $related['id_sanpham']; ?>">
                                    <i class="bi bi-cart-plus"></i> Thêm vào giỏ
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="alert alert-info">Không có sản phẩm tương tự</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
     
    <?php include('includes/footer.php'); ?>
    
    
    <script>
    // Image zoom functionality
    document.addEventListener('DOMContentLoaded', function() {
        // Lấy các phần tử DOM
        const zoomContainer = document.getElementById('image-zoom-container');
        const mainImage = document.getElementById('main-product-image');
        const zoomLens = document.getElementById('zoom-lens');
        const zoomResult = document.getElementById('zoom-result');
        
        if (!zoomContainer || !mainImage || !zoomLens || !zoomResult) {
            console.warn('Các phần tử zoom không tồn tại trong trang');
            return;
        }
        
        // Biến lưu trạng thái zoom
        let zoomActive = false;
        let zoomScale = 2; // Mức độ zoom mặc định
        let isFullScreen = false;
        
        // Tạo nút đóng fullscreen
        const closeButton = document.createElement('div');
        closeButton.className = 'fullscreen-close d-none';
        closeButton.innerHTML = '<i class="bi bi-x"></i>';
        document.body.appendChild(closeButton);
        
        // Lấy các nút điều khiển zoom
        const zoomInBtn = zoomContainer.querySelector('.zoom-in-btn');
        const zoomOutBtn = zoomContainer.querySelector('.zoom-out-btn');
        const fullScreenBtn = zoomContainer.querySelector('.fullscreen-btn');
        const zoomIndicator = zoomContainer.querySelector('.zoom-indicator');
        
        // Hiện thông tin zoom level
        function updateZoomIndicator() {
            if (zoomIndicator) {
                const zoomLevelText = document.getElementById('zoom-level');
                if (zoomLevelText) {
                    zoomLevelText.textContent = `Zoom ${zoomScale}x`;
                }
                zoomIndicator.classList.remove('d-none');
                
                // Tự động ẩn sau 2 giây
                setTimeout(() => {
                    if (!zoomActive && !isFullScreen) {
                        zoomIndicator.classList.add('d-none');
                    }
                }, 2000);
            }
        }
        
        // Xử lý sự kiện mouseover
        mainImage.addEventListener('mouseover', function() {
            // Chỉ áp dụng cho màn hình lớn và khi không ở chế độ toàn màn hình
            if (window.innerWidth < 768 || isFullScreen) return;
            
            zoomLens.classList.remove('d-none');
            zoomResult.classList.remove('d-none');
            zoomIndicator.classList.remove('d-none');
            zoomActive = true;
            
            // Thiết lập ban đầu cho zoom result
            zoomResult.style.backgroundImage = `url(${mainImage.src})`;
        });
        
        // Xử lý sự kiện mouseout
        mainImage.addEventListener('mouseout', function() {
            if (isFullScreen) return;
            
            zoomLens.classList.add('d-none');
            zoomResult.classList.add('d-none');
            
            // Ẩn thông tin zoom sau một khoảng thời gian
            setTimeout(() => {
                if (!zoomActive) {
                    zoomIndicator.classList.add('d-none');
                }
            }, 1000);
            
            zoomActive = false;
        });
        
        // Xử lý sự kiện mousemove
        mainImage.addEventListener('mousemove', function(e) {
            if (!zoomActive) return;
            
            // Lấy kích thước và vị trí của ảnh
            const rect = mainImage.getBoundingClientRect();
            
            // Tính toán vị trí chuột tương đối so với ảnh
            let x = e.clientX - rect.left;
            let y = e.clientY - rect.top;
            
            // Giới hạn vị trí của lens để không vượt ra ngoài ảnh
            const lensHalfWidth = zoomLens.offsetWidth / 2;
            const lensHalfHeight = zoomLens.offsetHeight / 2;
            
            // Đảm bảo lens không vượt ra ngoài ảnh
            if (x < lensHalfWidth) x = lensHalfWidth;
            if (x > rect.width - lensHalfWidth) x = rect.width - lensHalfWidth;
            if (y < lensHalfHeight) y = lensHalfHeight;
            if (y > rect.height - lensHalfHeight) y = rect.height - lensHalfHeight;
            
            // Di chuyển lens theo chuột
            zoomLens.style.left = `${x - lensHalfWidth}px`;
            zoomLens.style.top = `${y - lensHalfHeight}px`;
            
            // Tính toán tỷ lệ zoom
            const cx = rect.width / zoomLens.offsetWidth * zoomScale;
            const cy = rect.height / zoomLens.offsetHeight * zoomScale;
            
            // Tính toán vị trí background trong kết quả zoom
            const backgroundPositionX = -((x * cx) / zoomScale - zoomResult.offsetWidth / 2);
            const backgroundPositionY = -((y * cy) / zoomScale - zoomResult.offsetHeight / 2);
            
            // Cập nhật kết quả zoom
            zoomResult.style.backgroundImage = `url(${mainImage.src})`;
            zoomResult.style.backgroundSize = `${rect.width * cx / zoomScale}px ${rect.height * cy / zoomScale}px`;
            zoomResult.style.backgroundPosition = `${backgroundPositionX}px ${backgroundPositionY}px`;
        });
        
        // Xử lý nút zoom in
        if (zoomInBtn) {
            zoomInBtn.addEventListener('click', function() {
                if (zoomScale < 4) {
                    zoomScale += 0.5;
                    updateZoomIndicator();
                }
            });
        }
        
        // Xử lý nút zoom out
        if (zoomOutBtn) {
            zoomOutBtn.addEventListener('click', function() {
                if (zoomScale > 1) {
                    zoomScale -= 0.5;
                    updateZoomIndicator();
                }
            });
        }
        
        // Xử lý nút fullscreen
        if (fullScreenBtn) {
            fullScreenBtn.addEventListener('click', function() {
                toggleFullScreen();
            });
        }
        
        // Xử lý đóng fullscreen
        closeButton.addEventListener('click', function() {
            if (isFullScreen) {
                toggleFullScreen();
            }
        });
        
        // Xử lý phím ESC để thoát fullscreen
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && isFullScreen) {
                toggleFullScreen();
            }
        });
        
        // Hàm bật/tắt chế độ toàn màn hình
        function toggleFullScreen() {
            isFullScreen = !isFullScreen;
            
            if (isFullScreen) {
                document.body.style.overflow = 'hidden';
                document.body.classList.add('fullscreen-mode');
                zoomContainer.classList.add('position-fixed', 'top-0', 'start-0', 'w-100', 'h-100', 'd-flex', 'align-items-center', 'justify-content-center', 'bg-dark');
                closeButton.classList.remove('d-none');
                zoomLens.classList.add('d-none');
                zoomResult.classList.add('d-none');
            } else {
                document.body.style.overflow = '';
                document.body.classList.remove('fullscreen-mode');
                zoomContainer.classList.remove('position-fixed', 'top-0', 'start-0', 'w-100', 'h-100', 'd-flex', 'align-items-center', 'justify-content-center', 'bg-dark');
                closeButton.classList.add('d-none');
            }
        }
        
        // Zoom với bánh xe chuột
        mainImage.addEventListener('wheel', function(e) {
            if (zoomActive || isFullScreen) {
                e.preventDefault();
                
                // Zoom in khi cuộn lên, zoom out khi cuộn xuống
                if (e.deltaY < 0 && zoomScale < 4) {
                    zoomScale += 0.25;
                } else if (e.deltaY > 0 && zoomScale > 1) {
                    zoomScale -= 0.25;
                }
                
                // Giới hạn mức zoom
                zoomScale = Math.min(Math.max(zoomScale, 1), 4);
                updateZoomIndicator();
            }
        }, { passive: false });
        
        // Khởi tạo hiển thị mức độ zoom
        updateZoomIndicator();
    });
    </script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Xử lý chuyển đổi hình ảnh khi click vào ảnh nhỏ
    const thumbnails = document.querySelectorAll('.thumbnail-image');
    const mainImage = document.getElementById('main-product-image');
    
    if (thumbnails.length > 0 && mainImage) {
        thumbnails.forEach(thumbnail => {
            thumbnail.addEventListener('click', function() {
                // Đổi ảnh chính
                mainImage.src = this.src;
                
                // Đánh dấu thumbnail đang active
                thumbnails.forEach(thumb => {
                    thumb.parentElement.classList.remove('active');
                });
                this.parentElement.classList.add('active');
            });
        });
    }
    
    // Xử lý chuyển ảnh theo màu sắc
    const colorOptions = document.querySelectorAll('.color-option');
    if (colorOptions.length > 0) {
        colorOptions.forEach(option => {
            option.addEventListener('click', function() {
                if (this.dataset.disabled === 'true') return;
                
                // Đánh dấu màu đang chọn
                colorOptions.forEach(opt => opt.classList.remove('active'));
                this.classList.add('active');
                
                // Hiển thị tên màu đã chọn
                const selectedColorValue = document.querySelector('.selected-color-value');
                if (selectedColorValue) {
                    selectedColorValue.textContent = this.dataset.colorName || 'Chưa chọn';
                    if (this.dataset.colorCode) {
                        selectedColorValue.innerHTML += ` <span class="color-preview" style="background-color: ${this.dataset.colorCode}"></span>`;
                    }
                }
                
                // Cập nhật thông tin tồn kho nếu cả size và màu đã được chọn
                updateStockInfo();
            });
        });
    }
    
    // Xử lý nút kích thước
    const sizeButtons = document.querySelectorAll('.size-btn');
    if (sizeButtons.length > 0) {
        sizeButtons.forEach(button => {
            button.addEventListener('click', function() {
                // Đánh dấu kích thước đang chọn
                sizeButtons.forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');
                
                // Hiển thị tên kích thước đã chọn
                const selectedSizeValue = document.querySelector('.selected-size-value');
                if (selectedSizeValue) {
                    selectedSizeValue.textContent = this.dataset.sizeName || 'Chưa chọn';
                }
                
                // Cập nhật thông tin tồn kho nếu cả size và màu đã được chọn
                updateStockInfo();
            });
        });
    }

    // Hàm cập nhật thông tin tồn kho
    function updateStockInfo() {
        const selectedSize = document.querySelector('.size-btn.active');
        const selectedColor = document.querySelector('.color-option.active');
        const stockInfo = document.getElementById('variant-stock-info');
        
        if (selectedSize && selectedColor && stockInfo) {
            const sizeId = selectedSize.dataset.sizeId;
            const colorId = selectedColor.dataset.colorId;
            
            // Lấy dữ liệu tồn kho từ dataset
            const variantStockData = JSON.parse(document.getElementById('variant-stock-data').textContent);
            
            if (variantStockData[sizeId] && variantStockData[sizeId][colorId] !== undefined) {
                const stock = variantStockData[sizeId][colorId];
                stockInfo.classList.remove('d-none', 'stock-high', 'stock-medium', 'stock-low');
                
                if (stock > 10) {
                    stockInfo.classList.add('stock-high');
                    stockInfo.innerHTML = `<i class="bi bi-check-circle-fill text-success me-2"></i> Còn hàng (${stock} sản phẩm)`;
                } else if (stock > 5) {
                    stockInfo.classList.add('stock-medium');
                    stockInfo.innerHTML = `<i class="bi bi-info-circle-fill text-warning me-2"></i> Còn ${stock} sản phẩm`;
                } else if (stock > 0) {
                    stockInfo.classList.add('stock-low');
                    stockInfo.innerHTML = `<i class="bi bi-exclamation-triangle-fill text-danger me-2"></i> Chỉ còn ${stock} sản phẩm`;
                } else {
                    stockInfo.classList.add('stock-low');
                    stockInfo.innerHTML = `<i class="bi bi-x-circle-fill text-danger me-2"></i> Hết hàng`;
                }
                
                // Cập nhật số lượng tối đa có thể mua
                const quantityInput = document.getElementById('quantity');
                if (quantityInput) {
                    quantityInput.max = stock;
                    if (parseInt(quantityInput.value) > stock) {
                        quantityInput.value = stock > 0 ? stock : 1;
                    }
                }
            }
        }
    }
    
    // Xử lý nút tăng/giảm số lượng
    const decreaseBtn = document.getElementById('decreaseBtn');
    const increaseBtn = document.getElementById('increaseBtn');
    const quantityInput = document.getElementById('quantity');
    
    if (decreaseBtn && increaseBtn && quantityInput) {
        decreaseBtn.addEventListener('click', function() {
            const currentValue = parseInt(quantityInput.value);
            if (currentValue > 1) {
                quantityInput.value = currentValue - 1;
            }
        });
        
        increaseBtn.addEventListener('click', function() {
            const currentValue = parseInt(quantityInput.value);
            const maxValue = parseInt(quantityInput.max);
            if (currentValue < maxValue) {
                quantityInput.value = currentValue + 1;
            }
        });
    }
    
    // Thêm vào cuối script để đảm bảo chức năng Mua ngay vẫn hoạt động
    const buyNowBtn = document.getElementById('buyNowBtn');
    
    if (buyNowBtn) {
        buyNowBtn.addEventListener('click', function() {
            // Kiểm tra các lựa chọn mà không cần kiểm tra đăng nhập
            const selection = validateSelection();
            if (!selection) return;
            
            // Tạo form ẩn để submit dữ liệu
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'ajax/mua_ngay.php';
            
            // Thêm các trường dữ liệu vào form
            for (const key in selection) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = selection[key];
                form.appendChild(input);
            }
            
            // Thêm form vào body và submit
            document.body.appendChild(form);
            form.submit();
        });
    }

    // Hàm kiểm tra lựa chọn
    function validateSelection() {
        const selectedSize = document.querySelector('.size-btn.active');
        const selectedColor = document.querySelector('.color-option.active');
        const quantity = document.getElementById('quantity').value;
        
        // Kiểm tra đã chọn size chưa
        if (!selectedSize) {
            showToast('Vui lòng chọn kích thước', 'warning');
            return null;
        }
        
        // Kiểm tra đã chọn màu chưa
        if (!selectedColor) {
            showToast('Vui lòng chọn màu sắc', 'warning');
            return null;
        }
        
        // Kiểm tra số lượng hợp lệ
        if (!quantity || parseInt(quantity) < 1) {
            showToast('Vui lòng chọn số lượng hợp lệ', 'warning');
            return null;
        }
        
        // Trả về đối tượng với các thông tin đã chọn
        return {
            productId: <?php echo $product_id; ?>,
            quantity: quantity,
            sizeId: selectedSize.dataset.sizeId,
            colorId: selectedColor.dataset.colorId
        };
    }

    // Thêm đoạn sau trong phần <script> hiện có
    const addToCartBtn = document.getElementById('addToCartBtn');
    if (addToCartBtn) {
        addToCartBtn.addEventListener('click', function() {
            console.log('Add to cart button clicked');
            const selection = validateSelection();
            if (!selection) {
                return; // Hàm validateSelection đã hiển thị thông báo lỗi
            }
            
            // Hiển thị spinner hoặc thông báo đang xử lý
            addToCartBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Đang xử lý...';
            addToCartBtn.disabled = true;
            
            // Gửi dữ liệu tới server
            fetch('ajax/them_vao_gio.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(selection)
            })
            .then(response => response.json())
            .then(data => {
                console.log('Response:', data);
                
                // Khôi phục nút
                addToCartBtn.innerHTML = '<i class="bi bi-cart-plus"></i> Thêm vào giỏ';
                addToCartBtn.disabled = false;
                
                if (data.success) {
                    // Cập nhật số lượng trong giỏ hàng hiển thị trên header
                    const cartCountElement = document.getElementById('cartCount');
                    if (cartCountElement && data.cartCount) {
                        cartCountElement.textContent = data.cartCount;
                        // Hiệu ứng nhấp nháy
                        cartCountElement.classList.add('cart-update-animation');
                        setTimeout(() => {
                            cartCountElement.classList.remove('cart-update-animation');
                        }, 1000);
                    }
                    
                    // Hiển thị thông báo thành công
                    showToast(data.message, 'success');
                } else {
                    // Hiển thị thông báo lỗi
                    showToast(data.message || 'Có lỗi xảy ra khi thêm vào giỏ hàng', 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                addToCartBtn.innerHTML = '<i class="bi bi-cart-plus"></i> Thêm vào giỏ';
                addToCartBtn.disabled = false;
                showToast('Lỗi kết nối đến máy chủ', 'danger');
            });
        });
    }
});

// Kiểm tra nếu chưa có hàm showToast
if (typeof showToast !== 'function') {
    function showToast(message, type = 'info') {
        // Kiểm tra nếu chưa có container
        let toastContainer = document.getElementById('toastContainer');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'toastContainer';
            toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            toastContainer.style.zIndex = '1050';
            document.body.appendChild(toastContainer);
        }
        
        const toastId = 'toast-' + Date.now();
        const toastHTML = `
            <div class="toast" role="alert" aria-live="assertive" aria-atomic="true" id="${toastId}">
                <div class="toast-header">
                    <strong class="me-auto">Thông báo</strong>
                    <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body ${type === 'success' ? 'bg-success' : type === 'danger' ? 'bg-danger' : type === 'warning' ? 'bg-warning' : 'bg-info'} text-white">
                    ${message}
                </div>
            </div>
        `;
        
        toastContainer.insertAdjacentHTML('beforeend', toastHTML);
        const toastElement = document.getElementById(toastId);
        const toast = new bootstrap.Toast(toastElement, { autohide: true, delay: 3000 });
        toast.show();
    }
}
</script>

<!-- Thêm container cho hiển thị toast message -->
<div id="toastContainer" class="toast-container position-fixed bottom-0 end-0 p-3"></div>
</body>
</html>
