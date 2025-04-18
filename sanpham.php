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

// Lọc theo danh mục sản phẩm
$category_id = null;
if (isset($_GET['loai'])) {
    $category_id = (int)$_GET['loai'];
}
// Nếu có tham số category, cũng xử lý như loai (để tương thích ngược)
elseif (isset($_GET['category'])) {
    $category_id = (int)$_GET['category'];
}
// Nếu có tham số id_loai, cũng xử lý tương tự
elseif (isset($_GET['id_loai'])) {
    $category_id = (int)$_GET['id_loai'];
}

// Nếu có category_id, thêm điều kiện vào câu truy vấn
if ($category_id) {
    // Thêm điều kiện WHERE vào câu SQL của bạn
    $sql_conditions[] = "sp.id_danhmuc = ?";
    $param_types .= "i";
    $params[] = $category_id;
    
    // Lấy tên danh mục để hiển thị
    $cat_name_stmt = $conn->prepare("SELECT ten FROM danhmuc WHERE id = ?");
    $cat_name_stmt->bind_param("i", $category_id);
    $cat_name_stmt->execute();
    $cat_result = $cat_name_stmt->get_result();
    if ($cat_result->num_rows > 0) {
        $category_info = $cat_result->fetch_assoc();
        $page_title = "Sản phẩm " . $category_info['ten'];
    }
}

$brand = isset($_GET['brand']) ? (int)$_GET['brand'] : 0;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Comment out brand filter since thuonghieu table doesn't exist
/* if($brand > 0) {
    $sql_conditions[] = "sp.id_thuonghieu = ?";
    $params[] = $brand;
    $param_types .= "i";
} */

// Cập nhật câu truy vấn SQL để lấy thêm thông tin đánh giá
$sql = "SELECT sp.*, dm.ten as tendanhmuc, 
        (SELECT COALESCE(SUM(dhct.soluong), 0)
         FROM donhang_chitiet dhct
         JOIN donhang dh ON dhct.id_donhang = dh.id
         WHERE dhct.id_sanpham = sp.id
         AND dh.trang_thai_don_hang >= 3) AS da_ban,
        (SELECT AVG(dg.diem) FROM danhgia dg WHERE dg.id_sanpham = sp.id) AS diem_trung_binh,
        (SELECT COUNT(*) FROM danhgia dg WHERE dg.id_sanpham = sp.id) AS soluong_danhgia
        FROM sanpham sp
        LEFT JOIN danhmuc dm ON sp.id_danhmuc = dm.id";

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
        $sql .= " ORDER BY sp.ngay_tao DESC";
}

// Phân trang
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = 12;
$offset = ($page - 1) * $items_per_page;

// Lấy tổng số sản phẩm để tính toán phân trang
$count_sql = "SELECT COUNT(*) as total FROM sanpham sp";
if($category_id > 0) { // Thay đổi từ $category thành $category_id
    $count_sql .= " LEFT JOIN danhmuc dm ON sp.id_danhmuc = dm.id";
}
/* if($brand > 0) { // Comment out the brand join since thuonghieu table doesn't exist
    $count_sql .= " LEFT JOIN thuonghieu th ON sp.id_thuonghieu = th.id";
} */
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
$categories = $conn->query("SELECT * FROM danhmuc WHERE trang_thai = 1 ORDER BY ten");
// Lấy danh sách thương hiệu
/* $brands = $conn->query("SELECT * FROM thuonghieu WHERE trangthai = 1 ORDER BY tenthuonghieu"); */
if (!isset($category)) {
    $category = [];
    // Nếu có tham số loại/category, lấy thông tin danh mục
    if (isset($_GET['loai'])) {
        $category_id = (int)$_GET['loai'];
        $cat_query = $conn->prepare("SELECT * FROM danhmuc WHERE id = ?");
        $cat_query->bind_param("i", $category_id);
        $cat_query->execute();
        $category_result = $cat_query->get_result();
        if ($category_result->num_rows > 0) {
            $category = $category_result->fetch_assoc();
        }
    } elseif (isset($_GET['category'])) {
        $category_id = (int)$_GET['category'];
        $cat_query = $conn->prepare("SELECT * FROM danhmuc WHERE id = ?");
        $cat_query->bind_param("i", $category_id);
        $cat_query->execute();
        $category_result = $cat_query->get_result();
        if ($category_result->num_rows > 0) {
            $category = $category_result->fetch_assoc();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sản phẩm - Bug Shop</title>
    <link rel="stylesheet" href="node_modules/bootstrap/dist/css/bootstrap.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">  
    <link rel="stylesheet" href="css/sanpham.css">
</head>
<body>
<?php 
    require_once('includes/head.php');
    require_once('includes/header.php');
    ?>
    
    <!-- Thay đổi phần container chính -->
    <div class="container mt-4">
        <!-- Breadcrumb cải tiến -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Trang chủ</a></li>
                <li class="breadcrumb-item active" aria-current="page">
                    <?php if(!empty($search)): ?>
                        Kết quả tìm kiếm: "<?php echo htmlspecialchars($search); ?>"
                    <?php else: ?>
                        Sản phẩm
                    <?php endif; ?>
                </li>
            </ol>
        </nav>
        <!-- Tiêu đề trang và thông tin hiển thị -->
        <div class="d-flex justify-content-between align-items-center flex-wrap mb-4">
            <div>
                <?php if(!empty($search)): ?>
                    <h1 class="h2 mb-2">Kết quả tìm kiếm: "<?php echo htmlspecialchars($search); ?>"</h1>
                    <p class="text-muted">Tìm thấy <?php echo $total_items; ?> sản phẩm</p>
                <?php else: ?>
                    <h1 class="h2 mb-0">Sản phẩm</h1>
                <?php endif; ?>
            </div>
            <!-- Nút chuyển đổi kiểu hiển thị và sắp xếp -->
            <div class="d-flex align-items-center">
                <div class="d-none d-md-block">
                    <label class="me-2">Sắp xếp theo:</label>
                    <select class="form-select form-select-sm d-inline-block w-auto" id="sort-select">
                        <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Mới nhất</option>
                        <option value="bestseller" <?php echo $sort == 'bestseller' ? 'selected' : ''; ?>>Bán chạy nhất</option>
                        <option value="price_asc" <?php echo $sort == 'price_asc' ? 'selected' : ''; ?>>Giá: Thấp đến cao</option>
                        <option value="price_desc" <?php echo $sort == 'price_desc' ? 'selected' : ''; ?>>Giá: Cao đến thấp</option>
                    </select>
                </div>
                <div class="view-switcher ms-3">
                    <button type="button" class="view-btn active" data-view="grid" title="Grid view">
                        <i class="bi bi-grid-3x3-gap"></i>
                    </button>
                    <button type="button" class="view-btn" data-view="list" title="List view">
                        <i class="bi bi-list-ul"></i>
                    </button>
                </div>
            </div>
        </div>
        <!-- Nút hiển thị/ẩn bộ lọc trên mobile -->
        <div class="filter-toggle d-lg-none">
            <button class="btn btn-outline-primary w-100" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse">
                <i class="bi bi-funnel me-2"></i> Hiển thị bộ lọc
            </button>
        </div>
        <div class="row">
            <!-- Sidebar / Filters -->
            <div class="col-lg-3">
                <div class="filters-container" id="filterCollapse">
                    <div class="filter-section shadow-sm">
                        <h4>Bộ lọc sản phẩm</h4>
                        <form action="" method="GET" id="filter-form">
                            <!-- Search Box -->
                            <div class="mb-3">
                                <label for="search" class="form-label">Tìm kiếm</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Tên sản phẩm...">
                                    <button class="btn btn-outline-secondary" type="submit">
                                        <i class="bi bi-search"></i>
                                    </button>
                                </div>
                            </div>
                            <!-- Price range filter -->
                            <div class="mb-3">
                                <label class="form-label">Khoảng giá</label>
                                <div class="price-range-inputs">
                                    <div class="price-input">
                                        <label class="form-label small">Từ</label>
                                        <input type="number" class="form-control form-control-sm" name="min_price" value="" min="0" placeholder="0 ₫">
                                    </div>
                                    <div class="price-input">
                                        <label class="form-label small">Đến</label>
                                        <input type="number" class="form-control form-control-sm" name="max_price" value="" placeholder="1.000.000 ₫">
                                    </div>
                                </div>
                            </div>
                            <!-- Categories Filter -->
                            <div class="mb-3">
                                <label class="form-label">Danh mục</label>
                                <div class="scrollable-list">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="category" id="cat-all" value="0" <?php echo $category_id == 0 ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="cat-all">Tất cả danh mục</label>
                                    </div>
                                    <?php 
                                    // Reset con trỏ kết quả về vị trí đầu tiên
                                    if ($categories && $categories->num_rows > 0) {
                                        $categories->data_seek(0); 
                                        while($cat = $categories->fetch_assoc()): 
                                            $cat_name = isset($cat['ten']) ? $cat['ten'] : (isset($cat['tenloai']) ? $cat['tenloai'] : 'Unknown');
                                    ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="category" id="cat-<?php echo $cat['id']; ?>" value="<?php echo $cat['id']; ?>" <?php echo $category_id == $cat['id'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="cat-<?php echo $cat['id']; ?>">
                                            <?php echo htmlspecialchars($cat_name); ?>
                                        </label>
                                    </div>
                                    <?php endwhile; 
                                    }
                                    ?>
                                </div>
                            </div>
                            <!-- Brands Filter -->
                            <div class="mb-3">
                                <label class="form-label">Thương hiệu</label>
                                <div class="scrollable-list">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="brand" id="brand-all" value="0" checked>
                                        <label class="form-check-label" for="brand-all">Tất cả thương hiệu</label>
                                    </div>
                                    <?php 
                                    // Brand iteration is commented out since thuonghieu table doesn't exist
                                    // We previously tried to use $brands which is undefined
                                    /*
                                    if (isset($brands) && $brands->num_rows > 0) {
                                        $brands->data_seek(0);
                                        while($brand_item = $brands->fetch_assoc()): 
                                    ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="brand" id="brand-<?php echo $brand_item['id_thuonghieu']; ?>" value="<?php echo $brand_item['id_thuonghieu']; ?>" <?php echo $brand == $brand_item['id_thuonghieu'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="brand-<?php echo $brand_item['id_thuonghieu']; ?>">
                                            <?php echo htmlspecialchars($brand_item['tenthuonghieu']); ?>
                                        </label>
                                    </div>
                                    <?php 
                                        endwhile;
                                    }
                                    */
                                    ?>
                                </div>
                            </div>
                            
                            <!-- Sort (Hidden on desktop, shown on mobile) -->
                            <div class="mb-3 d-md-none">
                                <label for="sort-mobile" class="form-label">Sắp xếp theo</label>
                                <select class="form-select" id="sort-mobile" name="sort">
                                    <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Mới nhất</option>
                                    <option value="bestseller" <?php echo $sort == 'bestseller' ? 'selected' : ''; ?>>Bán chạy nhất</option>
                                    <option value="price_asc" <?php echo $sort == 'price_asc' ? 'selected' : ''; ?>>Giá: Thấp đến cao</option>
                                    <option value="price_desc" <?php echo $sort == 'price_desc' ? 'selected' : ''; ?>>Giá: Cao đến thấp</option>
                                    <option value="name_asc" <?php echo $sort == 'name_asc' ? 'selected' : ''; ?>>Tên: A-Z</option>
                                    <option value="name_desc" <?php echo $sort == 'name_desc' ? 'selected' : ''; ?>>Tên: Z-A</option>
                                    <option value="popular" <?php echo $sort == 'popular' ? 'selected' : ''; ?>>Phổ biến nhất</option>
                                </select>
                            </div>
                            
                            <!-- Hidden sort for desktop -->
                            <input type="hidden" id="sort-hidden" name="sort" value="<?php echo $sort; ?>">
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-filter me-1"></i> Lọc sản phẩm
                                </button>
                                <a href="sanpham.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-circle me-1"></i> Xóa bộ lọc
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Product Grid -->
            <div class="col-lg-9">
                <?php if($products->num_rows > 0): ?>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <p class="m-0 text-muted">Hiển thị <?php echo $products->num_rows; ?> / <?php echo $total_items; ?> sản phẩm</p>
                    </div>
                    
                    <div class="row g-3 grid-view" id="products-container">
                        <?php while($product = $products->fetch_assoc()): ?>
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
        // Kiểm tra hình ảnh từ bảng sanpham_hinhanh thay vì mausac_hinhanh
        $img_stmt = $conn->prepare("SELECT hinhanh FROM sanpham_hinhanh WHERE id_sanpham = ? LIMIT 1");
        $img_stmt->bind_param("i", $product['id']);
        $img_stmt->execute();
        $img_result = $img_stmt->get_result();
        
        if ($img_result->num_rows > 0) {
            $img_row = $img_result->fetch_assoc();
            if (file_exists('uploads/products/' . $img_row['hinhanh'])) {
                $img_path = 'uploads/products/' . $img_row['hinhanh'];
            } else {
                $img_path = 'images/no-image.jpg';
            }
        } else {
            $img_path = 'images/no-image.jpg';
        }
    }
    ?>
    <div class="col-6 col-md-4">
    <div class="card product-card h-100">
        <div class="product-badge-container">
            <?php if ($discount_percent > 0): ?>
            <div class="product-badge bg-danger text-white">
                <i class="bi bi-tags-fill me-1"></i>-<?php echo $discount_percent; ?>%
            </div>
            <?php endif; ?>
            <?php if ($product['da_ban'] > 10): ?>
            <div class="product-badge bg-primary text-white">
                <i class="bi bi-star-fill me-1"></i>HOT
            </div>
            <?php endif; ?>
        </div>
        <a href="product-detail.php?id=<?php echo $product['id']; ?>" class="product-img-container">
            <img src="<?php echo $img_path; ?>" class="card-img-top product-img" alt="<?php echo htmlspecialchars($product['tensanpham']); ?>" 
                 onerror="this.onerror=null; this.src='images/no-image.jpg';">
            <div class="overlay-effect"></div>
        </a>
        <div class="product-action">
            <button class="btn btn-light btn-sm rounded-circle wishlist-button" 
                    data-product-id="<?php echo $product['id']; ?>" 
                    title="Thêm vào yêu thích">
                <i class="bi bi-heart"></i>
            </button>
        </div>
        <div class="card-body">
            <div class="product-category"><?php echo htmlspecialchars($product['tendanhmuc']); ?></div>
            <h5 class="card-title product-title">
                <a href="product-detail.php?id=<?php echo $product['id']; ?>" class="text-decoration-none text-dark">
                    <?php echo htmlspecialchars($product['tensanpham']); ?>
                </a>
            </h5>
            <div class="rating">
                <?php 
                // Lấy điểm rating nếu có
                $rating = isset($product['diem_trung_binh']) ? round($product['diem_trung_binh']) : 0;
                for ($i = 1; $i <= 5; $i++): 
                ?>
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
    </div>
</div>
                        <?php endwhile; ?>
                    </div>
                    
                    <!-- Phân trang cải tiến -->
                    <?php if($total_pages > 1): ?>
                    <nav class="mt-4" aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_id; ?>&brand=<?php echo $brand; ?>&sort=<?php echo $sort; ?>" aria-label="Previous">
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            </li>
                            
                            <?php
                            $max_pages_show = 5; // Số trang hiển thị tối đa
                            $pages_to_show = min($total_pages, $max_pages_show);
                            $half_max = floor($pages_to_show / 2);
                            
                            // Tính start và end page
                            $start_page = max(1, $page - $half_max);
                            $end_page = min($total_pages, $start_page + $pages_to_show - 1);
                            
                            // Điều chỉnh lại start_page nếu cần
                            $start_page = max(1, $end_page - $pages_to_show + 1);
                            
                            // Hiển thị "..." và trang đầu tiên nếu cần
                            if ($start_page > 1) {
                                echo '<li class="page-item"><a class="page-link" href="?page=1&search=' . urlencode($search) . '&category=' . $category_id . '&brand=' . $brand . '&sort=' . $sort . '">1</a></li>';
                                if ($start_page > 2) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                            }
                            
                            // Hiển thị các trang giữa
                            for ($i = $start_page; $i <= $end_page; $i++) {
                                echo '<li class="page-item ' . ($i == $page ? 'active' : '') . '"><a class="page-link" href="?page=' . $i . '&search=' . urlencode($search) . '&category=' . $category_id . '&brand=' . $brand . '&sort=' . $sort . '">' . $i . '</a></li>';
                            }
                            
                            // Hiển thị "..." và trang cuối cùng nếu cần
                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&search=' . urlencode($search) . '&category=' . $category_id . '&brand=' . $brand . '&sort=' . $sort . '">' . $total_pages . '</a></li>';
                            }
                            ?>
                            
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_id; ?>&brand=<?php echo $brand; ?>&sort=<?php echo $sort; ?>" aria-label="Next">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <!-- Empty state -->
                    <div class="alert alert-info text-center py-5">
                        <i class="bi bi-search display-4 d-block mb-3"></i>
                        <h4>Không tìm thấy sản phẩm nào</h4>
                        <p class="mb-0">Vui lòng thử lại với bộ lọc khác hoặc xem <a href="sanpham.php" class="alert-link">tất cả sản phẩm</a></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal Quick View -->
    <div class="modal fade" id="quickViewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Xem nhanh sản phẩm</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="spinner-container">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                    <div id="quickViewContent" style="display:none"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="js\sanpham.js"></script>
    <?php include('includes/footer.php'); ?>
</body>
</html>
