<?php
// Kiểm tra đăng nhập và quyền admin
session_start();
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_loai']) || $_SESSION['admin_loai'] < 1) {
    header('Location: login.php');
    exit();
}

// Kết nối database - sử dụng config.php
require_once '../config/config.php';
include 'includes/header.php';
include 'includes/sidebar.php';
// Xử lý xóa sản phẩm
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $product_id = $_GET['delete'];
    
    // Kiểm tra sản phẩm tồn tại
    $check_sql = "SELECT tensanpham FROM sanpham WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param('i', $product_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        $product_name = $result->fetch_assoc()['tensanpham'];
        
        // Bắt đầu giao dịch
        $conn->begin_transaction();
        
        try {
            // Xóa các bản ghi liên quan
            $tables = [
                "sanpham_hinhanh" => "id_sanpham",
                "sanpham_bien_the" => "id_sanpham",
                "sanpham" => "id"
            ];
            
            foreach ($tables as $table => $column) {
                $delete_sql = "DELETE FROM $table WHERE $column = ?";
                $delete_stmt = $conn->prepare($delete_sql);
                $delete_stmt->bind_param('i', $product_id);
                $delete_stmt->execute();
            }
            
            // Ghi log
            $log_sql = "INSERT INTO nhat_ky (id_user, hanh_dong, doi_tuong_loai, doi_tuong_id, chi_tiet, ip_address) 
                       VALUES (?, 'delete', 'product', ?, ?, ?)";
            $log_stmt = $conn->prepare($log_sql);
            $admin_id = $_SESSION['admin_id'];
            $detail = "Xóa sản phẩm: $product_name (ID: $product_id)";
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $log_stmt->bind_param('iiss', $admin_id, $product_id, $detail, $ip);
            $log_stmt->execute();
            
            // Hoàn tất giao dịch
            $conn->commit();
            
            // Redirect với thông báo thành công
            header('Location: products.php?success=Đã xóa sản phẩm thành công');
            exit();
            
        } catch (Exception $e) {
            // Rollback nếu có lỗi
            $conn->rollback();
            header('Location: products.php?error=Không thể xóa sản phẩm. Lỗi: ' . $e->getMessage());
            exit();
        }
    } else {
        header('Location: products.php?error=Sản phẩm không tồn tại');
        exit();
    }
}

// Xử lý thay đổi trạng thái
if (isset($_GET['toggle_status']) && is_numeric($_GET['toggle_status'])) {
    $product_id = $_GET['toggle_status'];
    
    // Lấy trạng thái hiện tại
    $status_sql = "SELECT trangthai FROM sanpham WHERE id = ?";
    $status_stmt = $conn->prepare($status_sql);
    $status_stmt->bind_param('i', $product_id);
    $status_stmt->execute();
    $status_result = $status_stmt->get_result();
    
    if ($status_result->num_rows > 0) {
        $current_status = $status_result->fetch_assoc()['trangthai'];
        $new_status = $current_status ? 0 : 1;
        
        // Cập nhật trạng thái
        $update_sql = "UPDATE sanpham SET trangthai = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param('ii', $new_status, $product_id);
        
        if ($update_stmt->execute()) {
            // Ghi log
            $action = $new_status ? 'show' : 'hide';
            $log_sql = "INSERT INTO nhat_ky (id_user, hanh_dong, doi_tuong_loai, doi_tuong_id, chi_tiet, ip_address) 
                       VALUES (?, ?, 'product', ?, ?, ?)";
            $log_stmt = $conn->prepare($log_sql);
            $admin_id = $_SESSION['admin_id'];
            $detail = ($new_status ? "Hiển thị" : "Ẩn") . " sản phẩm ID: $product_id";
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $log_stmt->bind_param('isiss', $admin_id, $action, $product_id, $detail, $ip);
            $log_stmt->execute();
            
            header('Location: products.php?success=Đã cập nhật trạng thái sản phẩm');
        } else {
            header('Location: products.php?error=Không thể cập nhật trạng thái sản phẩm');
        }
        exit();
    }
}

// Xử lý thay đổi nổi bật
if (isset($_GET['toggle_featured']) && is_numeric($_GET['toggle_featured'])) {
    $product_id = $_GET['toggle_featured'];
    
    // Lấy trạng thái hiện tại
    $featured_sql = "SELECT noibat FROM sanpham WHERE id = ?";
    $featured_stmt = $conn->prepare($featured_sql);
    $featured_stmt->bind_param('i', $product_id);
    $featured_stmt->execute();
    $featured_result = $featured_stmt->get_result();
    
    if ($featured_result->num_rows > 0) {
        $current_featured = $featured_result->fetch_assoc()['noibat'];
        $new_featured = $current_featured ? 0 : 1;
        
        // Cập nhật trạng thái nổi bật
        $update_sql = "UPDATE sanpham SET noibat = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param('ii', $new_featured, $product_id);
        
        if ($update_stmt->execute()) {
            // Ghi log
            $action = $new_featured ? 'feature' : 'unfeature';
            $log_sql = "INSERT INTO nhat_ky (id_user, hanh_dong, doi_tuong_loai, doi_tuong_id, chi_tiet, ip_address) 
                       VALUES (?, ?, 'product', ?, ?, ?)";
            $log_stmt = $conn->prepare($log_sql);
            $admin_id = $_SESSION['admin_id'];
            $detail = ($new_featured ? "Đặt" : "Bỏ") . " nổi bật cho sản phẩm ID: $product_id";
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $log_stmt->bind_param('isiss', $admin_id, $action, $product_id, $detail, $ip);
            $log_stmt->execute();
            
            header('Location: products.php?success=Đã cập nhật trạng thái nổi bật');
        } else {
            header('Location: products.php?error=Không thể cập nhật trạng thái nổi bật');
        }
        exit();
    }
}

// Thiết lập tìm kiếm và lọc
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$featured = isset($_GET['featured']) ? $_GET['featured'] : '';
$sort = $_GET['sort'] ?? 'id_desc';

// Phân trang
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$items_per_page = 10;
$offset = ($page - 1) * $items_per_page;

// Xây dựng câu truy vấn
$query = "SELECT sp.*, dm.ten as danhmuc_ten, th.ten as thuonghieu_ten,
         (SELECT COUNT(*) FROM sanpham_bien_the WHERE id_sanpham = sp.id) AS so_bien_the,
         (SELECT SUM(so_luong) FROM sanpham_bien_the WHERE id_sanpham = sp.id) AS tong_ton_kho
         FROM sanpham sp
         LEFT JOIN danhmuc dm ON sp.id_danhmuc = dm.id
         LEFT JOIN thuong_hieu th ON sp.thuonghieu = th.id
         WHERE 1=1";

$count_query = "SELECT COUNT(*) as total FROM sanpham sp 
                LEFT JOIN danhmuc dm ON sp.id_danhmuc = dm.id
                LEFT JOIN thuong_hieu th ON sp.thuonghieu = th.id
                WHERE 1=1";

$params = [];
$param_types = "";

// Thêm điều kiện tìm kiếm
if (!empty($search)) {
    $search_term = "%$search%";
    $query .= " AND (sp.tensanpham LIKE ? OR sp.id LIKE ? OR dm.ten LIKE ? OR th.ten LIKE ?)";
    $count_query .= " AND (sp.tensanpham LIKE ? OR sp.id LIKE ? OR dm.ten LIKE ? OR th.ten LIKE ?)";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
    $param_types .= "ssss";
}

// Lọc theo danh mục
if (!empty($category)) {
    $query .= " AND sp.id_danhmuc = ?";
    $count_query .= " AND sp.id_danhmuc = ?";
    $params[] = $category;
    $param_types .= "i";
}

// Lọc theo trạng thái
if ($status !== '') {
    $query .= " AND sp.trangthai = ?";
    $count_query .= " AND sp.trangthai = ?";
    $params[] = $status;
    $param_types .= "i";
}

// Lọc theo nổi bật
if ($featured !== '') {
    $query .= " AND sp.noibat = ?";
    $count_query .= " AND sp.noibat = ?";
    $params[] = $featured;
    $param_types .= "i";
}

// Sắp xếp
switch ($sort) {
    case 'name_asc':
        $query .= " ORDER BY sp.tensanpham ASC";
        break;
    case 'name_desc':
        $query .= " ORDER BY sp.tensanpham DESC";
        break;
    case 'price_asc':
        $query .= " ORDER BY sp.gia ASC";
        break;
    case 'price_desc':
        $query .= " ORDER BY sp.gia DESC";
        break;
    case 'newest':
        $query .= " ORDER BY sp.ngay_tao DESC";
        break;
    case 'oldest':
        $query .= " ORDER BY sp.ngay_tao ASC";
        break;
    default:
        $query .= " ORDER BY sp.id DESC";
}

// Thêm phân trang
$query .= " LIMIT ? OFFSET ?";
$params[] = $items_per_page;
$params[] = $offset;
$param_types .= "ii";

// Thực hiện truy vấn đếm tổng số
$count_stmt = $conn->prepare($count_query);
if (!empty($params)) {
    // Xóa 2 tham số cuối (limit và offset) vì query đếm không cần
    $count_param_types = substr($param_types, 0, -2);
    $count_params = array_slice($params, 0, -2);
    
    // Chỉ bind_param nếu có parameter types
    if (!empty($count_param_types)) {
        $count_stmt->bind_param($count_param_types, ...$count_params);
    }
}
$count_stmt->execute();
$total_items = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_items / $items_per_page);

// Thực hiện truy vấn danh sách
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$products = $stmt->get_result();

// Lấy danh sách danh mục cho dropdown lọc
$categories_sql = "SELECT id, ten FROM danhmuc ORDER BY ten";
$categories_result = $conn->query($categories_sql);

// Format tiền VNĐ
function formatVND($amount) {
    return number_format($amount, 0, ',', '.') . ' ₫';
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý sản phẩm - Bug Shop Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #858796;
            --success-color: #1cc88a;
            --info-color: #36b9cc;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
        }
        
        body {
            background-color: #f8f9fc;
            font-family: "Nunito", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, var(--primary-color) 10%, #224abe 100%);
            color: white;
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 0.75rem 1rem;
        }
        
        .sidebar .nav-link:hover {
            color: #fff;
        }
        
        .sidebar .nav-link.active {
            font-weight: bold;
            color: #fff;
        }
        
        .sidebar-brand {
            height: 4.375rem;
            font-size: 1.2rem;
            font-weight: 800;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.05rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .product-thumbnail {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px;
        }
        
        .product-name {
            max-width: 250px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .btn-action {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }
        
        .badge-stock {
            font-size: 0.8rem;
            padding: 0.35em 0.65em;
        }
        
        .alert-dismissible {
            padding-right: 1rem;
        }
        
        .filter-container {
            background-color: #fff;
            border-radius: 0.35rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 1.5rem;
            padding: 1rem;
        }
        
        .filters-collapse {
            padding: 1rem 0;
        }
        
        .pagination {
            margin-bottom: 0;
        }
    </style>
</head>
<body>


        
        <!-- Main Content -->
        <div class="col-md-10 col-lg-10 ms-auto">
            <div class="container-fluid px-4 py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0 text-gray-800">Quản lý sản phẩm</h1>
                    <a href="product_add.php" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i> Thêm sản phẩm
                    </a>
                </div>
                
                <!-- Thông báo -->
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($_GET['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($_GET['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Tìm kiếm và lọc -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">Tìm kiếm và lọc</h6>
                        <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#filtersCollapse">
                            <i class="fas fa-filter me-1"></i> Lọc nâng cao
                        </button>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="">
                            <div class="row align-items-end">
                                <div class="col-md-6 mb-3">
                                    <label for="search" class="form-label">Tìm kiếm</label>
                                    <input type="text" class="form-control" id="search" name="search" 
                                           placeholder="Tên sản phẩm, mã, thương hiệu..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="category" class="form-label">Danh mục</label>
                                    <select class="form-select" id="category" name="category">
                                        <option value="">Tất cả danh mục</option>
                                        <?php while ($cat = $categories_result->fetch_assoc()): ?>
                                            <option value="<?php echo $cat['id']; ?>" <?php echo ($category == $cat['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cat['ten']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-search me-1"></i> Tìm kiếm
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Lọc nâng cao -->
                            <div class="collapse <?php echo (!empty($status) || !empty($featured) || $sort != 'id_desc') ? 'show' : ''; ?>" id="filtersCollapse">
                                <div class="row">
                                    <div class="col-md-3 mb-3">
                                        <label for="status" class="form-label">Trạng thái</label>
                                        <select class="form-select" id="status" name="status">
                                            <option value="">Tất cả</option>
                                            <option value="1" <?php echo ($status === '1') ? 'selected' : ''; ?>>Đang hiển thị</option>
                                            <option value="0" <?php echo ($status === '0') ? 'selected' : ''; ?>>Đang ẩn</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label for="featured" class="form-label">Nổi bật</label>
                                        <select class="form-select" id="featured" name="featured">
                                            <option value="">Tất cả</option>
                                            <option value="1" <?php echo ($featured === '1') ? 'selected' : ''; ?>>Nổi bật</option>
                                            <option value="0" <?php echo ($featured === '0') ? 'selected' : ''; ?>>Không nổi bật</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label for="sort" class="form-label">Sắp xếp</label>
                                        <select class="form-select" id="sort" name="sort">
                                            <option value="id_desc" <?php echo ($sort == 'id_desc') ? 'selected' : ''; ?>>Mặc định (Mới nhất)</option>
                                            <option value="name_asc" <?php echo ($sort == 'name_asc') ? 'selected' : ''; ?>>Tên A-Z</option>
                                            <option value="name_desc" <?php echo ($sort == 'name_desc') ? 'selected' : ''; ?>>Tên Z-A</option>
                                            <option value="price_asc" <?php echo ($sort == 'price_asc') ? 'selected' : ''; ?>>Giá tăng dần</option>
                                            <option value="price_desc" <?php echo ($sort == 'price_desc') ? 'selected' : ''; ?>>Giá giảm dần</option>
                                            <option value="newest" <?php echo ($sort == 'newest') ? 'selected' : ''; ?>>Mới nhất</option>
                                            <option value="oldest" <?php echo ($sort == 'oldest') ? 'selected' : ''; ?>>Cũ nhất</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 mb-3 d-flex align-items-end">
                                        <a href="products.php" class="btn btn-outline-secondary w-100">
                                            <i class="fas fa-redo me-1"></i> Đặt lại bộ lọc
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Danh sách sản phẩm -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">
                            Danh sách sản phẩm 
                            <span class="badge bg-secondary ms-1"><?php echo $total_items; ?> sản phẩm</span>
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th width="60">ID</th>
                                        <th width="80">Hình ảnh</th>
                                        <th>Tên sản phẩm</th>
                                        <th>Danh mục</th>
                                        <th>Thương hiệu</th>
                                        <th>Giá</th>
                                        <th>Tồn kho</th>
                                        <th width="100">Trạng thái</th>
                                        <th width="170">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($products && $products->num_rows > 0): ?>
                                        <?php while ($product = $products->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $product['id']; ?></td>
                                                <td>
                                                    <?php if (!empty($product['hinhanh'])): ?>
                                                        <img src="../<?php echo htmlspecialchars($product['hinhanh']); ?>" 
                                                             alt="<?php echo htmlspecialchars($product['tensanpham']); ?>" 
                                                             class="product-thumbnail">
                                                    <?php else: ?>
                                                        <div class="text-center text-muted">
                                                            <i class="fas fa-image fa-2x"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="product-name" title="<?php echo htmlspecialchars($product['tensanpham']); ?>">
                                                        <?php echo htmlspecialchars($product['tensanpham']); ?>
                                                    </div>
                                                    <?php if ($product['noibat']): ?>
                                                        <span class="badge bg-warning text-dark">Nổi bật</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($product['danhmuc_ten'] ?? 'N/A'); ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($product['thuonghieu_ten'] ?? 'N/A'); ?>
                                                </td>
                                                <td>
                                                    <div><?php echo formatVND($product['gia']); ?></div>
                                                    <?php if ($product['giagoc'] > $product['gia']): ?>
                                                        <del class="text-muted small"><?php echo formatVND($product['giagoc']); ?></del>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $stock = $product['tong_ton_kho'];
                                                    if ($stock <= 0) {
                                                        echo '<span class="badge bg-danger badge-stock">Hết hàng</span>';
                                                    } elseif ($stock <= 10) {
                                                        echo '<span class="badge bg-warning text-dark badge-stock">Sắp hết: ' . $stock . '</span>';
                                                    } else {
                                                        echo '<span class="badge bg-success badge-stock">Còn hàng: ' . $stock . '</span>';
                                                    }
                                                    ?>
                                                    <div class="small mt-1">
                                                        <?php echo $product['so_bien_the']; ?> biến thể
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if ($product['trangthai']): ?>
                                                        <span class="badge bg-success">Hiển thị</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Ẩn</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="d-flex gap-1 mb-1">
                                                        <a href="product_edit.php?id=<?php echo $product['id']; ?>" class="btn btn-sm btn-primary flex-grow-1">
                                                            <i class="fas fa-edit"></i> Sửa
                                                        </a>
                                                        <a href="product_detail.php?id=<?php echo $product['id']; ?>" class="btn btn-sm btn-info flex-grow-1">
                                                            <i class="fas fa-eye"></i> Chi tiết
                                                        </a>
                                                    </div>
                                                    <div class="d-flex gap-1">
                                                        <a href="products.php?toggle_status=<?php echo $product['id']; ?>" 
                                                           class="btn btn-sm <?php echo $product['trangthai'] ? 'btn-warning' : 'btn-success'; ?> flex-grow-1"
                                                           onclick="return confirm('Bạn có chắc muốn <?php echo $product['trangthai'] ? 'ẩn' : 'hiển thị'; ?> sản phẩm này?')">
                                                            <i class="fas <?php echo $product['trangthai'] ? 'fa-eye-slash' : 'fa-eye'; ?>"></i> <?php echo $product['trangthai'] ? 'Ẩn' : 'Hiện'; ?>
                                                        </a>
                                                        <a href="products.php?toggle_featured=<?php echo $product['id']; ?>" 
                                                           class="btn btn-sm <?php echo $product['noibat'] ? 'btn-secondary' : 'btn-warning'; ?> flex-grow-1"
                                                           title="<?php echo $product['noibat'] ? 'Bỏ nổi bật' : 'Đánh dấu nổi bật'; ?>">
                                                            <i class="fas fa-star"></i>
                                                        </a>
                                                        <a href="products.php?delete=<?php echo $product['id']; ?>" 
                                                           class="btn btn-sm btn-danger flex-grow-1"
                                                           onclick="return confirm('Bạn có chắc chắn muốn xóa sản phẩm này? Hành động này không thể hoàn tác!')">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="text-center py-4">
                                                <div class="text-muted mb-3">
                                                    <i class="fas fa-box fa-3x"></i>
                                                </div>
                                                <h5>Không tìm thấy sản phẩm nào</h5>
                                                <p>Hãy thử thay đổi tiêu chí tìm kiếm hoặc thêm sản phẩm mới</p>
                                                <a href="product_add.php" class="btn btn-primary">
                                                    <i class="fas fa-plus me-1"></i> Thêm sản phẩm mới
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Phân trang -->
                    <?php if ($total_pages > 1): ?>
                        <div class="card-footer d-flex justify-content-between align-items-center">
                            <div>
                                Hiển thị <?php echo min(($page - 1) * $items_per_page + 1, $total_items); ?> - 
                                <?php echo min($page * $items_per_page, $total_items); ?> 
                                trong <?php echo $total_items; ?> sản phẩm
                            </div>
                            <nav>
                                <ul class="pagination">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="<?php echo '?page=1' . 
                                                (!empty($search) ? '&search=' . urlencode($search) : '') .
                                                (!empty($category) ? '&category=' . urlencode($category) : '') .
                                                ($status !== '' ? '&status=' . urlencode($status) : '') .
                                                ($featured !== '' ? '&featured=' . urlencode($featured) : '') .
                                                (!empty($sort) ? '&sort=' . urlencode($sort) : ''); ?>">
                                                <i class="fas fa-angle-double-left"></i>
                                            </a>
                                        </li>
                                        <li class="page-item">
                                            <a class="page-link" href="<?php echo '?page=' . ($page - 1) . 
                                                (!empty($search) ? '&search=' . urlencode($search) : '') .
                                                (!empty($category) ? '&category=' . urlencode($category) : '') .
                                                ($status !== '' ? '&status=' . urlencode($status) : '') .
                                                ($featured !== '' ? '&featured=' . urlencode($featured) : '') .
                                                (!empty($sort) ? '&sort=' . urlencode($sort) : ''); ?>">
                                                <i class="fas fa-angle-left"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $start_page = max(1, min($page - 2, $total_pages - 4));
                                    $end_page = min($total_pages, max($page + 2, 5));
                                    
                                    for ($i = $start_page; $i <= $end_page; $i++):
                                    ?>
                                        <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                            <a class="page-link" href="<?php echo '?page=' . $i . 
                                                (!empty($search) ? '&search=' . urlencode($search) : '') .
                                                (!empty($category) ? '&category=' . urlencode($category) : '') .
                                                ($status !== '' ? '&status=' . urlencode($status) : '') .
                                                ($featured !== '' ? '&featured=' . urlencode($featured) : '') .
                                                (!empty($sort) ? '&sort=' . urlencode($sort) : ''); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="<?php echo '?page=' . ($page + 1) . 
                                                (!empty($search) ? '&search=' . urlencode($search) : '') .
                                                (!empty($category) ? '&category=' . urlencode($category) : '') .
                                                ($status !== '' ? '&status=' . urlencode($status) : '') .
                                                ($featured !== '' ? '&featured=' . urlencode($featured) : '') .
                                                (!empty($sort) ? '&sort=' . urlencode($sort) : ''); ?>">
                                                <i class="fas fa-angle-right"></i>
                                            </a>
                                        </li>
                                        <li class="page-item">
                                            <a class="page-link" href="<?php echo '?page=' . $total_pages . 
                                                (!empty($search) ? '&search=' . urlencode($search) : '') .
                                                (!empty($category) ? '&category=' . urlencode($category) : '') .
                                                ($status !== '' ? '&status=' . urlencode($status) : '') .
                                                ($featured !== '' ? '&featured=' . urlencode($featured) : '') .
                                                (!empty($sort) ? '&sort=' . urlencode($sort) : ''); ?>">
                                                <i class="fas fa-angle-double-right"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto dismiss alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
});
</script>
</body>
</html>
