<?php
session_start();
include('config/config.php');
include('includes/init.php'); // Include init.php for helper functions

// Lấy ID sản phẩm từ URL - Fix the parsing
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Debug the product ID to verify it's correct
error_log("Product ID from URL: " . $product_id);

// Kiểm tra nếu ID không hợp lệ
if ($product_id <= 0) {
    header('Location: sanpham.php');
    exit();
}

// Cập nhật lượt xem sản phẩm (nếu cột tồn tại)
$check_column = $conn->query("SHOW COLUMNS FROM sanpham LIKE 'luotxem'");
if ($check_column->num_rows > 0) {
    $update_view = $conn->prepare("UPDATE sanpham SET luotxem = luotxem + 1 WHERE id = ?");
    $update_view->bind_param("i", $product_id);
    $update_view->execute();
}

// Cập nhật truy vấn để phù hợp với cấu trúc bảng mới và lấy thông tin thương hiệu
$product_stmt = $conn->prepare("
    SELECT sp.*, dm.ten as ten_danhmuc, th.ten as tenthuonghieu, th.id as id_thuonghieu
    FROM sanpham sp
    LEFT JOIN danhmuc dm ON sp.id_danhmuc = dm.id
    LEFT JOIN thuong_hieu th ON sp.thuonghieu = th.id
    WHERE sp.id = ? AND sp.trangthai = 1
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

// Lấy các biến thể sản phẩm (kích thước, màu sắc)
$variants_stmt = $conn->prepare("
    SELECT sbt.*, 
           kt.gia_tri as ten_kichthuoc, 
           ms.gia_tri as ten_mau, 
           ms.ma_mau as ma_mau
    FROM sanpham_bien_the sbt
    JOIN thuoc_tinh kt ON sbt.id_size = kt.id AND kt.loai = 'size'
    JOIN thuoc_tinh ms ON sbt.id_mau = ms.id AND ms.loai = 'color'
    WHERE sbt.id_sanpham = ?
    ORDER BY kt.gia_tri, ms.gia_tri
");
$variants_stmt->bind_param("i", $product_id);
$variants_stmt->execute();
$variants_result = $variants_stmt->get_result();

$product_variants = [];
$available_sizes_by_color = []; // Lưu trữ sizes có sẵn cho từng màu
$color_info = []; // Thông tin về màu sắc (tên, mã màu)

while ($variant = $variants_result->fetch_assoc()) {
    $product_variants[] = $variant;
    
    // Lưu size có sẵn cho từng màu
    if (!isset($available_sizes_by_color[$variant['id_mau']])) {
        $available_sizes_by_color[$variant['id_mau']] = [];
    }
    $available_sizes_by_color[$variant['id_mau']][] = $variant['id_size'];
    
    // Lưu thông tin màu
    $color_info[$variant['id_mau']] = [
        'name' => $variant['ten_mau'],
        'code' => $variant['ma_mau']
    ];
}

// Lấy hình ảnh theo màu từ bảng sanpham_hinhanh
$color_images = [];
$color_images_stmt = $conn->prepare("
    SELECT sh.id_bienthe, sh.hinhanh, sbt.id_mau
    FROM sanpham_hinhanh sh
    JOIN sanpham_bien_the sbt ON sh.id_bienthe = sbt.id
    WHERE sh.id_sanpham = ?
");
$color_images_stmt->bind_param("i", $product_id);
$color_images_stmt->execute();
$color_images_result = $color_images_stmt->get_result();

// Đọc dữ liệu ảnh màu
while ($color_image = $color_images_result->fetch_assoc()) {
    $color_images[$color_image['id_mau']] = $color_image['hinhanh'];
}

// Lấy các kích thước có sẵn
$sizes_stmt = $conn->prepare("
    SELECT DISTINCT tt.id, tt.gia_tri as ten_kichthuoc
    FROM sanpham_bien_the sbt
    JOIN thuoc_tinh tt ON sbt.id_size = tt.id
    WHERE sbt.id_sanpham = ? AND sbt.so_luong > 0 AND tt.loai = 'size'
    ORDER BY tt.gia_tri
");
$sizes_stmt->bind_param("i", $product_id);
$sizes_stmt->execute();
$sizes_result = $sizes_stmt->get_result();

// Lấy các màu có sẵn
$colors_stmt = $conn->prepare("
    SELECT DISTINCT tt.id, tt.gia_tri as ten_mau, tt.ma_mau
    FROM sanpham_bien_the sbt
    JOIN thuoc_tinh tt ON sbt.id_mau = tt.id
    WHERE sbt.id_sanpham = ? AND sbt.so_luong > 0 AND tt.loai = 'color'
    ORDER BY tt.gia_tri
");
$colors_stmt->bind_param("i", $product_id);
$colors_stmt->execute();
$colors_result = $colors_stmt->get_result();

// Lấy đánh giá sản phẩm
$reviews_stmt = $conn->prepare("
    SELECT dg.*, u.ten as ten_user, u.anh_dai_dien
    FROM danhgia dg
    JOIN users u ON dg.id_user = u.id
    WHERE dg.id_sanpham = ? AND dg.trang_thai = 1
    ORDER BY dg.ngay_danhgia DESC
    LIMIT 10
");
$reviews_stmt->bind_param("i", $product_id);
$reviews_stmt->execute();
$reviews_result = $reviews_stmt->get_result();

// Lấy số lượng đánh giá theo số sao
$rating_counts = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
$rating_stmt = $conn->prepare("
    SELECT diem, COUNT(*) as count
    FROM danhgia
    WHERE id_sanpham = ? AND trang_thai = 1
    GROUP BY diem
");
$rating_stmt->bind_param("i", $product_id);
$rating_stmt->execute();
$rating_result = $rating_stmt->get_result();

while ($row = $rating_result->fetch_assoc()) {
    $rating_counts[$row['diem']] = $row['count'];
}

$total_ratings = array_sum($rating_counts);

// Lấy sản phẩm liên quan (cùng danh mục)
$related_stmt = $conn->prepare("
    SELECT id, tensanpham, gia, giagoc, hinhanh
    FROM sanpham
    WHERE id_danhmuc = ? AND id != ? AND trangthai = 1
    ORDER BY RAND()
    LIMIT 4
");
$related_stmt->bind_param("ii", $product['id_danhmuc'], $product_id);
$related_stmt->execute();
$related_result = $related_stmt->get_result();

// Xử lý các hình ảnh phụ
$images = [];
$images_stmt = $conn->prepare("
    SELECT hinhanh, la_anh_chinh
    FROM sanpham_hinhanh
    WHERE id_sanpham = ? AND id_bienthe IS NULL
    ORDER BY la_anh_chinh DESC
");
$images_stmt->bind_param("i", $product_id);
$images_stmt->execute();
$images_result = $images_stmt->get_result();

while ($img = $images_result->fetch_assoc()) {
    $images[] = $img['hinhanh'];
}

// Thêm hình ảnh chính vào đầu mảng nếu chưa có ảnh nào
if (empty($images) && !empty($product['hinhanh'])) {
    $images[] = $product['hinhanh'];
}

// Tính điểm đánh giá trung bình nếu không có sẵn trong sản phẩm
if (!isset($product['diemdanhgia_tb'])) {
    $avg_rating_query = $conn->prepare("
        SELECT AVG(diem) as avg_rating, COUNT(*) as rating_count
        FROM danhgia 
        WHERE id_sanpham = ? AND trang_thai = 1
    ");
    $avg_rating_query->bind_param("i", $product_id);
    $avg_rating_query->execute();
    $avg_result = $avg_rating_query->get_result()->fetch_assoc();
    
    $avg_rating = $avg_result['avg_rating'] ?? 0;
    $review_count = $avg_result['rating_count'] ?? 0;
} else {
    $avg_rating = $product['diemdanhgia_tb'];
    $review_count = $product['soluong_danhgia'] ?? 0;
}

// Lấy tổng số lượng của sản phẩm từ các biến thể
if (!isset($product['so_luong'])) {
    $total_stock_query = $conn->prepare("
        SELECT SUM(so_luong) as total_stock
        FROM sanpham_bien_the
        WHERE id_sanpham = ?
    ");
    $total_stock_query->bind_param("i", $product_id);
    $total_stock_query->execute();
    $total_stock_result = $total_stock_query->get_result()->fetch_assoc();
    $total_stock = $total_stock_result['total_stock'] ?? 0;
} else {
    $total_stock = $product['so_luong'];
}

// Get product rating data - calculate if not available in product array
$avg_rating = 0;
$rating_count = 0;

// Check if we need to calculate the average rating
if (!isset($product['diemdanhgia_tb']) || $product['diemdanhgia_tb'] === null) {
    // Query to get average rating and count from danhgia table
    $rating_query = $conn->prepare("
        SELECT AVG(diem) AS avg_rating, COUNT(*) AS rating_count 
        FROM danhgia 
        WHERE id_sanpham = ? AND trang_thai = 1
    ");
    $rating_query->bind_param("i", $product_id);
    $rating_query->execute();
    $rating_result = $rating_query->get_result();
    
    if ($rating_result && $rating_data = $rating_result->fetch_assoc()) {
        $avg_rating = round($rating_data['avg_rating'] ?? 0, 1);
        $rating_count = $rating_data['rating_count'] ?? 0;
    }
} else {
    // Use values from product if available
    $avg_rating = round($product['diemdanhgia_tb'] ?? 0, 1);
    $rating_count = $product['soluong_danhgia'] ?? 0;
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
        }    </style>
    <script src="js/product-detail.js" defer></script>
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
                $variant_stock[$variant['id_size']][$variant['id_mau']] = $variant['so_luong'];
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
                <li class="breadcrumb-item"><a href="sanpham.php?category=<?php echo $product['id_danhmuc']; ?>"><?php echo htmlspecialchars($product['ten_danhmuc']); ?></a></li>
                <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($product['tensanpham']); ?></li>
            </ol>
        </nav>
        
        <div class="row">
            <!-- Product Images -->
            <div class="col-md-6 mb-4">
                <div class="card shadow-sm">
                    <div class="card-body p-3">
                        <div class="main-image-container position-relative" id="image-container">                            <?php 
                            // Sử dụng hàm helper để xử lý đường dẫn hình ảnh chính
                            $main_image_path = getProductImagePath($product['hinhanh'], 'images/no-image.png');
                            ?>
                            <img src="<?php echo $main_image_path; ?>" 
                                 id="main-product-image" class="img-fluid" 
                                 alt="<?php echo htmlspecialchars($product['tensanpham']); ?>"
                                 onerror="this.onerror=null; this.src='images/no-image.png';">
                        </div>
                        
                        <!-- Thumbnail Gallery -->
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <!-- Hiển thị ảnh màu sắc với indicator -->
                            <?php if (!empty($color_images)): ?>                                <?php foreach ($color_images as $color_id => $img): ?>
                                    <?php 
                                    // Sử dụng hàm helper để xử lý đường dẫn hình ảnh màu sắc
                                    $color_img_path = getProductImagePath($img, 'images/no-image.png');
                                    ?>
                                    <div class="thumbnail-wrapper" data-type="color" data-color-id="<?php echo $color_id; ?>">
                                        <img src="<?php echo $color_img_path; ?>" 
                                             class="thumbnail-image color-thumbnail" 
                                             alt="<?php echo htmlspecialchars($color_info[$color_id]['name']); ?>"
                                             data-color-id="<?php echo $color_id; ?>" 
                                             title="<?php echo htmlspecialchars($color_info[$color_id]['name']); ?>"
                                             onerror="this.onerror=null; this.src='images/no-image.png';">
                                        <div class="color-thumbnail-indicator" style="background-color: <?php echo htmlspecialchars($color_info[$color_id]['code']); ?>"></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            
                            <!-- Hiển thị ảnh mặc định -->
                            <?php if (count($images) > 0): ?>                                <?php 
                                // Sử dụng hàm helper để xử lý đường dẫn hình ảnh mặc định
                                $default_img_path = getProductImagePath($images[0] ?? '', 'images/no-image.png');
                                ?>
                                <div class="thumbnail-wrapper active" data-type="default">
                                    <img src="<?php echo $default_img_path; ?>" 
                                         class="thumbnail-image default-thumbnail" 
                                         alt="Default Image" 
                                         data-default="true" 
                                         title="Ảnh mặc định"
                                         onerror="this.onerror=null; this.src='images/no-image.png';">
                                </div>
                                
                                <!-- Hiển thị ảnh phụ với cải tiến -->
                                <?php if(count($images) > 1): ?>
                                    <div class="product-thumbnails">
                                        <div class="thumbnail-header mb-2">
                                            <h6 class="mb-0"><small>Hình ảnh khác (<?php echo count($images) - 1; ?>)</small></h6>
                                        </div>
                                        
                                        <div class="thumbnails-container d-flex flex-wrap gap-2">
                                            <?php for($i=1; $i < count($images); $i++): ?>                                                <?php 
                                                // Sử dụng hàm helper để xử lý đường dẫn hình ảnh bổ sung
                                                $additional_img_path = getProductImagePath($images[$i] ?? '', 'images/no-image.png');
                                                ?>
                                                <div class="thumbnail-wrapper position-relative" data-type="additional" data-index="<?php echo $i; ?>">
                                                    <img src="<?php echo $additional_img_path; ?>" 
                                                         class="thumbnail-image img-thumbnail" 
                                                         alt="Product Image <?php echo $i+1; ?>" 
                                                         title="Hình ảnh <?php echo $i+1; ?> - Click để xem"
                                                         onerror="this.onerror=null; this.src='images/no-image.png';">
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
                                                        <?php foreach($images as $index => $img): ?>                                                            <?php 
                                                            // Sử dụng hàm helper để xử lý đường dẫn hình ảnh trong gallery
                                                            $gallery_img_path = getProductImagePath($img, 'images/no-image.png');
                                                            ?>
                                                            <div class="col-6 col-md-4">
                                                                <div class="product-gallery-item">
                                                                    <img src="<?php echo $gallery_img_path; ?>" 
                                                                         class="img-fluid rounded w-100 gallery-image" 
                                                                         data-index="<?php echo $index; ?>"
                                                                         alt="<?php echo htmlspecialchars($product['tensanpham']); ?>"
                                                                         onerror="this.onerror=null; this.src='images/no-image.png';">
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
                        <?php if (isset($product['noibat']) && $product['noibat'] == 1): ?>
                            <div class="mb-2">
                                <span class="badge bg-warning text-dark">Sản phẩm nổi bật</span>
                            </div>
                        <?php endif; ?>
                        
                        <h2 class="mb-2"><?php echo htmlspecialchars($product['tensanpham']); ?></h2>
                        
                        <!-- Category and Brand -->
                        <div class="mb-3">
                            <span class="text-muted">Danh mục: <a href="sanpham.php?category=<?php echo $product['id_danhmuc']; ?>"><?php echo htmlspecialchars($product['ten_danhmuc']); ?></a></span>
                            
                            <?php if (!empty($product['tenthuonghieu']) || !empty($product['id_thuonghieu'])): ?>
                            <span class="mx-2">|</span>
                            <span class="text-muted">Thương hiệu: <a href="sanpham.php?brand=<?php echo $product['id_thuonghieu']; ?>"><?php echo htmlspecialchars($product['tenthuonghieu']); ?></a></span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Rating -->
                        <div class="mb-3 d-flex align-items-center">
                            <div class="rating">
                                <?php
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($i <= $avg_rating) {
                                        echo '<i class="bi bi-star-fill"></i>';
                                    } elseif ($i - $avg_rating < 1 && $i - $avg_rating > 0) {
                                        echo '<i class="bi bi-star-half"></i>';
                                    } else {
                                        echo '<i class="bi bi-star"></i>';
                                    }
                                }
                                ?>
                            </div>
                            <span class="ms-2"><?php echo number_format($avg_rating, 1); ?>/5</span>
                            <span class="ms-2 text-muted">(<?php echo $review_count; ?> đánh giá)</span>
                        </div>
                        
                        <!-- Stock Status -->
                        <div class="mb-3">
                            <?php if ($total_stock > 0): ?>
                                <span class="badge bg-success">Còn hàng</span>
                                <span class="ms-2 text-muted">Còn <strong id="total-stock-display"><?php echo $total_stock; ?></strong> sản phẩm</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Hết hàng</span>
                            <?php endif; ?>
                            <span class="ms-2 text-muted small">Đã bán: <?php 
                            // Truy vấn lấy tổng số lượng đã bán từ đơn hàng hoàn thành (trạng thái 4)
                            $sold_query = $conn->prepare("
                                SELECT COALESCE(SUM(dc.soluong), 0) as total_sold
                                FROM donhang_chitiet dc
                                JOIN donhang d ON dc.id_donhang = d.id
                                WHERE dc.id_sanpham = ? AND d.trang_thai_don_hang = 4
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
                        
                        <!-- Hidden field for product ID - Fix the value -->
                        <input type="hidden" id="current-product-id" value="<?php echo (int)$product_id; ?>">
                        
                        <!-- Quantity -->
                        <div class="mb-4">
                            <h5 class="mb-2">Số lượng:</h5>
                            <div class="quantity-control">
                                <div class="quantity-btn" id="decreaseBtn">-</div>
                                <input type="number" id="quantity" class="quantity-input" value="1" min="1" max="<?php echo $total_stock; ?>">
                                <div class="quantity-btn" id="increaseBtn">+</div>
                            </div>
                        </div>
                        
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
                                            data-size-id="<?php echo $size['id']; ?>"
                                            data-size-name="<?php echo htmlspecialchars($size['ten_kichthuoc']); ?>">
                                        <?php echo htmlspecialchars($size['ten_kichthuoc']); ?>
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
                                         data-color-id="<?php echo $color['id']; ?>"
                                         data-color-name="<?php echo htmlspecialchars($color['ten_mau']); ?>"
                                         data-color-code="<?php echo htmlspecialchars($color['ma_mau']); ?>"
                                         <?php if (!isset($available_sizes_by_color[$color['id']])): ?>
                                         data-disabled="true"
                                         <?php endif; ?>>
                                        <div class="color-circle" style="background-color: <?php echo htmlspecialchars($color['ma_mau']); ?>"></div>
                                        <span class="color-name"><?php echo htmlspecialchars($color['ten_mau']); ?></span>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                            
                            <div class="selected-color-info mt-2">
                                <span class="selected-color-label">Màu đã chọn:</span>
                                <span class="selected-color-value">Chưa chọn</span>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Material Information -->
                        <?php
                        $material_info = '';
                        // Get material info from product's common attributes if available
                        if (!empty($product['thuoc_tinh_chung'])) {
                            $common_attrs = json_decode($product['thuoc_tinh_chung'], true);
                            if (is_array($common_attrs) && isset($common_attrs['chat_lieu'])) {
                                $material_info = $common_attrs['chat_lieu'];
                            }
                        }
                        
                        // Alternative: Try to get material from thuoc_tinh table
                        if (empty($material_info)) {
                            // Fixed query that doesn't rely on id_material column
                            $material_query = $conn->prepare("
                                SELECT tt.gia_tri 
                                FROM thuoc_tinh tt
                                WHERE tt.loai = 'material'
                                LIMIT 1
                            ");
                            
                            if ($material_query) {
                                $material_query->execute();
                                $material_result = $material_query->get_result();
                                if ($material_result && $material_result->num_rows > 0) {
                                    $material_info = $material_result->fetch_assoc()['gia_tri'];
                                }
                            }
                        }
                        
                        if (!empty($material_info)):
                        ?>
                        <div class="mb-4">
                            <h5 class="mb-2">Chất liệu:</h5>
                            <p class="mb-0"><?php echo htmlspecialchars($material_info); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Brand Information -->
                        <?php if (!empty($product['tenthuonghieu']) || !empty($product['id_thuonghieu'])): ?>
                        <div class="mb-4">
                            <h5 class="mb-2">Thương hiệu:</h5>
                            <?php if (!empty($product['tenthuonghieu'])): ?>
                                <p class="mb-0">
                                    <?php if (!empty($product['id_thuonghieu'])): ?>
                                        <a href="sanpham.php?brand=<?php echo $product['id_thuonghieu']; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($product['tenthuonghieu']); ?>
                                        </a>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($product['tenthuonghieu']); ?>
                                    <?php endif; ?>
                                </p>
                            <?php else: ?>
                                <?php
                                // Try to get brand info if not already loaded
                                $brand_query = $conn->prepare("
                                    SELECT ten FROM thuong_hieu WHERE id = ? LIMIT 1
                                ");
                                if ($brand_query) {
                                    $brand_query->bind_param("i", $product['id_thuonghieu']);
                                    $brand_query->execute();
                                    $brand_result = $brand_query->get_result();
                                    if ($brand_result && $brand_result->num_rows > 0) {
                                        $brand_name = $brand_result->fetch_assoc()['ten'];
                                        echo '<p class="mb-0"><a href="sanpham.php?brand=' . $product['id_thuonghieu'] . '" class="text-decoration-none">' . 
                                             htmlspecialchars($brand_name) . '</a></p>';
                                    }
                                }
                                ?>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
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
                                    Đánh giá (<?php echo $review_count; ?>)
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
                                            <div class="display-4 fw-bold"><?php echo number_format($avg_rating, 1); ?></div>
                                            <div class="rating mt-2">
                                                <?php
                                                for ($i = 1; $i <= 5; $i++) {
                                                    if ($i <= $avg_rating) {
                                                        echo '<i class="bi bi-star-fill"></i>';
                                                    } elseif ($i - $avg_rating < 1 && $i - $avg_rating > 0) {
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
                                                            <img src="uploads/users/<?php echo $review['anh_dai_dien']; ?>" alt="<?php echo htmlspecialchars($review['ten_user']); ?>" class="rounded-circle" width="50" height="50">
                                                        <?php else: ?>
                                                            <div class="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px">
                                                                <i class="bi bi-person fs-4"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($review['ten_user']); ?></h6>
                                                        <div class="d-flex align-items-center small">
                                                            <div class="rating">
                                                                <?php
                                                                for ($i = 1; $i <= 5; $i++) {
                                                                    echo $i <= $review['diem'] ? '<i class="bi bi-star-fill"></i>' : '<i class="bi bi-star"></i>';
                                                                }
                                                                ?>
                                                            </div>
                                                            <span class="text-muted ms-2"><?php echo date('d/m/Y', strtotime($review['ngay_danhgia'])); ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <p><?php echo nl2br(htmlspecialchars($review['noi_dung'])); ?></p>
                                                
                                                <?php if (!empty($review['hinh_anh'])): ?>
                                                <div class="mt-2">
                                                    <img src="uploads/reviews/<?php echo $review['hinh_anh']; ?>" alt="Review Image" class="img-thumbnail" style="max-height: 150px;">
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
                            <a href="product-detail.php?id=<?php echo $related['id']; ?>" class="text-decoration-none">
                                <?php 
                                // Simplified related image path handling                                // Sử dụng hàm helper để xử lý đường dẫn hình ảnh sản phẩm liên quan
                                $related_image_path = getProductImagePath($related['hinhanh'], 'images/no-image.png');
                                ?>
                                <img src="<?php echo $related_image_path; ?>" 
                                     class="card-img-top" 
                                     alt="<?php echo htmlspecialchars($related['tensanpham']); ?>"
                                     onerror="this.onerror=null; this.src='images/no-image.png';">
                            </a>
                            <div class="card-body">
                                <h6 class="card-title">
                                    <a href="product-detail.php?id=<?php echo $related['id']; ?>" class="text-decoration-none text-dark">
                                        <?php echo htmlspecialchars($related['tensanpham']); ?>
                                    </a>
                                </h6>
                                <div class="small mb-2">
                                    <div class="rating">
                                        <?php
                                        // Fix for undefined diemdanhgia_tb array key
                                        $rel_rating = 0;
                                        
                                        // Try to get the rating from the database if not in product array
                                        if (!isset($related['diemdanhgia_tb'])) {
                                            // Query to get average rating for this related product
                                            $rel_rating_query = $conn->prepare("
                                                SELECT AVG(diem) as avg_rating 
                                                FROM danhgia 
                                                WHERE id_sanpham = ? AND trang_thai = 1
                                            ");
                                            $rel_rating_query->bind_param("i", $related['id']);
                                            $rel_rating_query->execute();
                                            $rel_rating_result = $rel_rating_query->get_result();
                                            
                                            if ($rel_rating_result && $rel_rating_data = $rel_rating_result->fetch_assoc()) {
                                                $rel_rating = round($rel_rating_data['avg_rating'] ?? 0, 1);
                                            }
                                        } else {
                                            $rel_rating = $related['diemdanhgia_tb'];
                                        }
                                        
                                        // Generate the stars based on rating
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
                                <button class="btn btn-sm btn-outline-dark w-100 add-to-cart-btn" data-product-id="<?php echo $related['id']; ?>">
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
    
    <!-- Thêm container cho hiển thị toast message -->
    <div id="toastContainer" class="toast-container position-fixed bottom-0 end-0 p-3"></div>
</body>
</html>
