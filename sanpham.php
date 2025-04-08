<?php
session_start();
include('config/config.php');

// Xử lý tìm kiếm và lọc
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Xây dựng câu truy vấn SQL với điều kiện lọc
$sql_conditions = ["sp.trangthai = 1"]; // Chỉ lấy sản phẩm đang bán
$params = [];
$param_types = "";

if(!empty($search)) {
    $sql_conditions[] = "(sp.tensanpham LIKE ? OR sp.mota LIKE ?)";
    $search_param = "%" . $search . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= "ss";
}

$category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$brand = isset($_GET['brand']) ? (int)$_GET['brand'] : 0;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

if($category > 0) {
    $sql_conditions[] = "sp.id_loai = ?";
    $params[] = $category;
    $param_types .= "i";
}

if($brand > 0) {
    $sql_conditions[] = "sp.id_thuonghieu = ?";
    $params[] = $brand;
    $param_types .= "i";
}

// Thay thế truy vấn hiện tại bằng truy vấn sử dụng donhang_chitiet
$sql = "SELECT sp.*, lsp.tenloai, th.tenthuonghieu,
        (SELECT COALESCE(SUM(dhct.soluong), 0)
         FROM donhang_chitiet dhct
         JOIN donhang dh ON dhct.id_donhang = dh.id_donhang
         WHERE dhct.id_sanpham = sp.id_sanpham
         AND dh.trangthai >= 3) AS da_ban
        FROM sanpham sp
        LEFT JOIN loaisanpham lsp ON sp.id_loai = lsp.id_loai
        LEFT JOIN thuonghieu th ON sp.id_thuonghieu = th.id_thuonghieu";

if(!empty($sql_conditions)) {
    $sql .= " WHERE " . implode(" AND ", $sql_conditions);
}

// Sắp xếp sản phẩm
switch($sort) {
    case 'price_asc':
        $sql .= " ORDER BY sp.gia ASC";
        break;
    case 'price_desc':
        $sql .= " ORDER BY sp.gia DESC";
        break;
    case 'name_asc':
        $sql .= " ORDER BY sp.tensanpham ASC";
        break;
    case 'name_desc':
        $sql .= " ORDER BY sp.tensanpham DESC";
        break;
    case 'popular':
        $sql .= " ORDER BY sp.luotxem DESC";
        break;
    case 'bestseller':
        $sql .= " ORDER BY da_ban DESC";
        break;
    default: // newest
        $sql .= " ORDER BY sp.ngaytao DESC";
}

// Phân trang
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = 12;
$offset = ($page - 1) * $items_per_page;

// Lấy tổng số sản phẩm để tính toán phân trang
$count_sql = "SELECT COUNT(*) as total FROM sanpham sp";
if($category > 0) {
    $count_sql .= " LEFT JOIN loaisanpham lsp ON sp.id_loai = lsp.id_loai";
}
if($brand > 0) {
    $count_sql .= " LEFT JOIN thuonghieu th ON sp.id_thuonghieu = th.id_thuonghieu";
}

if(!empty($sql_conditions)) {
    $count_sql .= " WHERE " . implode(" AND ", $sql_conditions);
}

$count_stmt = $conn->prepare($count_sql);
if(!empty($params)) {
    $count_stmt->bind_param($param_types, ...$params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$row = $count_result->fetch_assoc();
$total_items = $row['total'];
$total_pages = ceil($total_items / $items_per_page);

// Truy vấn lấy sản phẩm với phân trang
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

// Lấy danh sách danh mục
$categories = $conn->query("SELECT * FROM loaisanpham WHERE trangthai = 1 ORDER BY tenloai");

// Lấy danh sách thương hiệu
$brands = $conn->query("SELECT * FROM thuonghieu WHERE trangthai = 1 ORDER BY tenthuonghieu");
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sản phẩm - Bug Shop</title>
    <link rel="stylesheet" href="node_modules/bootstrap/dist/css/bootstrap.css">
    
    <link rel="stylesheet" href="css/sanpham.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">  
    
</head>
<body>
    <?php include('includes/header.php'); ?>
    
    <div class="container mt-5">
        <?php if(!empty($search)): ?>
            <h1 class="mb-4">Kết quả tìm kiếm: "<?php echo htmlspecialchars($search); ?>"</h1>
        <?php else: ?>
            <h1 class="mb-4">Tất cả sản phẩm</h1>
        <?php endif; ?>
        
        <!-- Hiện số lượng kết quả tìm thấy -->
        <?php if(!empty($search)): ?>
            <div class="alert alert-info">
                Tìm thấy <?php echo $total_items; ?> sản phẩm cho từ khóa "<?php echo htmlspecialchars($search); ?>"
            </div>
        <?php endif; ?>
        
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Trang chủ</a></li>
                <li class="breadcrumb-item active" aria-current="page">Sản phẩm</li>
            </ol>
        </nav>
        
        <div class="row">
            <!-- Sidebar / Filters -->
            <div class="col-lg-3 mb-4">
                <div class="filter-section shadow-sm">
                    <h4>Bộ lọc sản phẩm</h4>
                    <form action="" method="GET">
                        <!-- Search Box -->
                        <div class="mb-3">
                            <label for="search" class="form-label">Tìm kiếm</label>
                            <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Tên sản phẩm...">
                        </div>
                        
                        <!-- Categories Filter -->
                        <div class="mb-3">
                            <label for="category" class="form-label">Danh mục</label>
                            <select class="form-select" id="category" name="category">
                                <option value="0">Tất cả danh mục</option>
                                <?php 
                                // Reset con trỏ kết quả về vị trí đầu tiên
                                $categories->data_seek(0); 
                                $category_count = $categories->num_rows;
                                echo "<!-- DEBUG: Tìm thấy $category_count danh mục -->";
                                while($cat = $categories->fetch_assoc()): 
                                ?>
                                <option value="<?php echo $cat['id_loai']; ?>" <?php echo $category == $cat['id_loai'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['tenloai']); ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <!-- Brands Filter -->
                        <div class="mb-3">
                            <label for="brand" class="form-label">Thương hiệu</label>
                            <select class="form-select" id="brand" name="brand">
                                <option value="0">Tất cả thương hiệu</option>
                                <?php while($brand_item = $brands->fetch_assoc()): ?>
                                <option value="<?php echo $brand_item['id_thuonghieu']; ?>" <?php echo $brand == $brand_item['id_thuonghieu'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($brand_item['tenthuonghieu']); ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <!-- Sort -->
                        <div class="mb-3">
                            <label for="sort" class="form-label">Sắp xếp theo</label>
                            <select class="form-select" id="sort" name="sort">
                                <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Mới nhất</option>
                                <option value="bestseller" <?php echo $sort == 'bestseller' ? 'selected' : ''; ?>>Bán chạy nhất</option>
                                <option value="price_asc" <?php echo $sort == 'price_asc' ? 'selected' : ''; ?>>Giá: Thấp đến cao</option>
                                <option value="price_desc" <?php echo $sort == 'price_desc' ? 'selected' : ''; ?>>Giá: Cao đến thấp</option>
                                <option value="name_asc" <?php echo $sort == 'name_asc' ? 'selected' : ''; ?>>Tên: A-Z</option>
                                <option value="name_desc" <?php echo $sort == 'name_desc' ? 'selected' : ''; ?>>Tên: Z-A</option>
                                <option value="popular" <?php echo $sort == 'popular' ? 'selected' : ''; ?>>Phổ biến nhất</option>
                            </select>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Lọc sản phẩm</button>
                            <a href="sanpham.php" class="reset-filter text-center">Xóa bộ lọc</a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Product Grid -->
            <div class="col-lg-9">
                <?php if($products->num_rows > 0): ?>
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <p class="m-0">Hiển thị <?php echo $products->num_rows; ?> / <?php echo $total_items; ?> sản phẩm</p>
                    </div>
                    
                    <div class="row">
                        <?php while($product = $products->fetch_assoc()): ?>
                        <div class="col-md-4">
                            <div class="card h-100 product-card">
                                <?php if(!empty($product['giagoc']) && $product['giagoc'] > $product['gia']): ?>
                                    <?php $discount = round(($product['giagoc'] - $product['gia']) / $product['giagoc'] * 100); ?>
                                    <span class="badge-discount">-<?php echo $discount; ?>%</span>
                                <?php endif; ?>
                                
                                <?php if(!empty($product['da_ban']) && $product['da_ban'] >= 50): ?>
                                    <span class="hot-selling">
                                        <i class="bi bi-fire"></i> Bán chạy
                                    </span>
                                <?php endif; ?>
                                
                                <a href="product-detail.php?id=<?php echo $product['id_sanpham']; ?>">
                                    <?php if(!empty($product['hinhanh'])): ?>
                                        <img src="uploads/products/<?php echo $product['hinhanh']; ?>" class="card-img-top" alt="<?php echo htmlspecialchars($product['tensanpham']); ?>">
                                    <?php else: ?>
                                        <img src="images/no-image.png" class="card-img-top" alt="No Image">
                                    <?php endif; ?>
                                </a>
                                
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <a href="product-detail.php?id=<?php echo $product['id_sanpham']; ?>" class="text-decoration-none text-dark">
                                            <?php echo htmlspecialchars($product['tensanpham']); ?>
                                        </a>
                                    </h6>
                                    <div class="text-muted small"><?php echo htmlspecialchars($product['tenloai'] ?? ''); ?></div>
                                    
                                    <!-- Hiển thị đánh giá sao -->
                                    <div class="rating">
                                        <?php
                                        // Lấy điểm đánh giá từ database hoặc gán giá trị mặc định là 0
                                        $rating = isset($product['diemdanhgia_tb']) ? floatval($product['diemdanhgia_tb']) : 0;
                                        $review_count = isset($product['soluong_danhgia']) ? intval($product['soluong_danhgia']) : 0;
                                        
                                        // Hiển thị các ngôi sao dựa trên rating
                                        for ($i = 1; $i <= 5; $i++) {
                                            if ($i <= $rating) {
                                                echo '<i class="bi bi-star-fill"></i>'; // Sao đầy đủ
                                            } elseif ($i - $rating < 1 && $i - $rating > 0) {
                                                echo '<i class="bi bi-star-half"></i>'; // Nửa sao
                                            } else {
                                                echo '<i class="bi bi-star"></i>'; // Sao rỗng
                                            }
                                        }
                                        
                                        // Hiển thị số lượng đánh giá
                                        if ($review_count > 0) {
                                            echo '<span class="review-count">(' . $review_count . ')</span>';
                                        }
                                        ?>
                                    </div>
                                    
                                    <!-- Hiển thị giá và % giảm giá -->
                                    <div class="price-section">
                                        <div class="price-wrapper">
                                            <span class="price-sale"><?php echo number_format($product['gia'], 0, ',', '.'); ?>₫</span>
                                            <?php if(!empty($product['giagoc']) && $product['giagoc'] > $product['gia']): ?>
                                                <span class="price-original"><?php echo number_format($product['giagoc'], 0, ',', '.'); ?>₫</span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if(!empty($product['giagoc']) && $product['giagoc'] > $product['gia']): ?>
                                            <?php $discount_percent = round(($product['giagoc'] - $product['gia']) / $product['giagoc'] * 100); ?>
                                            <span class="badge bg-danger">-<?php echo $discount_percent; ?>%</span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Hiển thị số lượng đã bán -->
                                    <div class="mt-1">
                                        <?php if(!empty($product['da_ban']) && $product['da_ban'] > 0): ?>
                                            <span class="small text-muted product-sold">
                                                <i class="bi bi-box-seam"></i> Đã bán: <?php echo number_format($product['da_ban']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="small text-muted product-sold">
                                                <i class="bi bi-box-seam"></i> Sản phẩm mới
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="card-footer bg-white border-top-0">
                                    <div class="d-grid">
                                        <a class="btn btn-outline-dark btn-sm add-to-cart" data-id="<?php echo $product['id_sanpham']; ?>">
                                            <i class="bi bi-cart-plus"></i> Thêm vào giỏ
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if($total_pages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category; ?>&brand=<?php echo $brand; ?>&sort=<?php echo $sort; ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            
                            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category; ?>&brand=<?php echo $brand; ?>&sort=<?php echo $sort; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category; ?>&brand=<?php echo $brand; ?>&sort=<?php echo $sort; ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="alert alert-info text-center py-5">
                        <i class="bi bi-search display-4 d-block mb-3"></i>
                        <h4>Không tìm thấy sản phẩm nào</h4>
                        <p class="mb-0">Vui lòng thử lại với bộ lọc khác hoặc xem <a href="sanpham.php" class="alert-link">tất cả sản phẩm</a></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="js\sanpham.js"></script>
    <?php include('includes/footer.php'); ?>
    
    
</body>
</html>
