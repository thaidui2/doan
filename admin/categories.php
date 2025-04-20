<?php
// Kiểm tra đăng nhập và quyền admin
session_start();
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_loai']) || $_SESSION['admin_loai'] < 1) {
    header('Location: login.php');
    exit();
}

// Kết nối database
require_once '../config/config.php';
include 'includes/header.php';
include 'includes/sidebar.php';

// Xử lý xóa danh mục
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $category_id = $_GET['delete'];
    
    // Kiểm tra danh mục tồn tại
    $check_sql = "SELECT ten FROM danhmuc WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param('i', $category_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        $category_name = $result->fetch_assoc()['ten'];
        
        // Kiểm tra xem danh mục có sản phẩm không
        $check_products_sql = "SELECT COUNT(*) AS count FROM sanpham WHERE id_danhmuc = ?";
        $check_products_stmt = $conn->prepare($check_products_sql);
        $check_products_stmt->bind_param('i', $category_id);
        $check_products_stmt->execute();
        $products_count = $check_products_stmt->get_result()->fetch_assoc()['count'];
        
        if ($products_count > 0) {
            header('Location: categories.php?error=Không thể xóa danh mục này vì còn chứa ' . $products_count . ' sản phẩm.');
            exit();
        }
        
        // Kiểm tra xem danh mục có danh mục con không
        $check_children_sql = "SELECT COUNT(*) AS count FROM danhmuc WHERE danhmuc_cha = ?";
        $check_children_stmt = $conn->prepare($check_children_sql);
        $check_children_stmt->bind_param('i', $category_id);
        $check_children_stmt->execute();
        $children_count = $check_children_stmt->get_result()->fetch_assoc()['count'];
        
        if ($children_count > 0) {
            header('Location: categories.php?error=Không thể xóa danh mục này vì còn chứa ' . $children_count . ' danh mục con.');
            exit();
        }
        
        // Bắt đầu giao dịch
        $conn->begin_transaction();
        
        try {
            // Xóa danh mục
            $delete_sql = "DELETE FROM danhmuc WHERE id = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param('i', $category_id);
            $delete_stmt->execute();
            
            // Ghi log
            $log_sql = "INSERT INTO nhat_ky (id_user, hanh_dong, doi_tuong_loai, doi_tuong_id, chi_tiet, ip_address) 
                       VALUES (?, 'delete', 'category', ?, ?, ?)";
            $log_stmt = $conn->prepare($log_sql);
            $admin_id = $_SESSION['admin_id'];
            $detail = "Xóa danh mục: $category_name (ID: $category_id)";
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $log_stmt->bind_param('iiss', $admin_id, $category_id, $detail, $ip);
            $log_stmt->execute();
            
            // Hoàn tất giao dịch
            $conn->commit();
            
            header('Location: categories.php?success=Đã xóa danh mục thành công');
            exit();
            
        } catch (Exception $e) {
            // Rollback nếu có lỗi
            $conn->rollback();
            header('Location: categories.php?error=Không thể xóa danh mục. Lỗi: ' . $e->getMessage());
            exit();
        }
    } else {
        header('Location: categories.php?error=Danh mục không tồn tại');
        exit();
    }
}

// Xử lý toggle trạng thái
if (isset($_GET['toggle_status']) && is_numeric($_GET['toggle_status'])) {
    $category_id = $_GET['toggle_status'];
    
    // Lấy trạng thái hiện tại
    $status_sql = "SELECT trang_thai FROM danhmuc WHERE id = ?";
    $status_stmt = $conn->prepare($status_sql);
    $status_stmt->bind_param('i', $category_id);
    $status_stmt->execute();
    $status_result = $status_stmt->get_result();
    
    if ($status_result->num_rows > 0) {
        $current_status = $status_result->fetch_assoc()['trang_thai'];
        $new_status = $current_status ? 0 : 1;
        
        // Cập nhật trạng thái
        $update_sql = "UPDATE danhmuc SET trang_thai = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param('ii', $new_status, $category_id);
        
        if ($update_stmt->execute()) {
            // Ghi log
            $action = $new_status ? 'show' : 'hide';
            $log_sql = "INSERT INTO nhat_ky (id_user, hanh_dong, doi_tuong_loai, doi_tuong_id, chi_tiet, ip_address) 
                       VALUES (?, ?, 'category', ?, ?, ?)";
            $log_stmt = $conn->prepare($log_sql);
            $admin_id = $_SESSION['admin_id'];
            $detail = ($new_status ? "Hiển thị" : "Ẩn") . " danh mục ID: $category_id";
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $log_stmt->bind_param('isiss', $admin_id, $action, $category_id, $detail, $ip);
            $log_stmt->execute();
            
            header('Location: categories.php?success=Đã cập nhật trạng thái danh mục');
        } else {
            header('Location: categories.php?error=Không thể cập nhật trạng thái danh mục');
        }
        exit();
    }
}

// Thiết lập tìm kiếm và lọc
$search = $_GET['search'] ?? '';
$parent = isset($_GET['parent']) ? $_GET['parent'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$sort = $_GET['sort'] ?? 'id_desc';

// Phân trang
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$items_per_page = 10;
$offset = ($page - 1) * $items_per_page;

// Xây dựng câu truy vấn
$query = "SELECT c.*, p.ten as parent_name,
          (SELECT COUNT(*) FROM danhmuc WHERE danhmuc_cha = c.id) AS subcategory_count,
          (SELECT COUNT(*) FROM sanpham WHERE id_danhmuc = c.id) AS product_count
          FROM danhmuc c
          LEFT JOIN danhmuc p ON c.danhmuc_cha = p.id
          WHERE 1=1";

$count_query = "SELECT COUNT(*) as total FROM danhmuc c 
               LEFT JOIN danhmuc p ON c.danhmuc_cha = p.id
               WHERE 1=1";

$params = [];
$param_types = "";

// Thêm điều kiện tìm kiếm
if (!empty($search)) {
    $search_term = "%$search%";
    $query .= " AND (c.ten LIKE ? OR c.mo_ta LIKE ?)";
    $count_query .= " AND (c.ten LIKE ? OR c.mo_ta LIKE ?)";
    $params = array_merge($params, [$search_term, $search_term]);
    $param_types .= "ss";
}

// Lọc theo danh mục cha
if ($parent !== '') {
    if ($parent == '0') {
        $query .= " AND c.danhmuc_cha IS NULL";
        $count_query .= " AND c.danhmuc_cha IS NULL";
    } else {
        $query .= " AND c.danhmuc_cha = ?";
        $count_query .= " AND c.danhmuc_cha = ?";
        $params[] = $parent;
        $param_types .= "i";
    }
}

// Lọc theo trạng thái
if ($status !== '') {
    $query .= " AND c.trang_thai = ?";
    $count_query .= " AND c.trang_thai = ?";
    $params[] = $status;
    $param_types .= "i";
}

// Sắp xếp
switch ($sort) {
    case 'name_asc':
        $query .= " ORDER BY c.ten ASC";
        break;
    case 'name_desc':
        $query .= " ORDER BY c.ten DESC";
        break;
    case 'newest':
        $query .= " ORDER BY c.ngay_tao DESC";
        break;
    case 'oldest':
        $query .= " ORDER BY c.ngay_tao ASC";
        break;
    default:
        $query .= " ORDER BY c.id DESC";
}

// Thêm phân trang
$query .= " LIMIT ? OFFSET ?";
$params[] = $items_per_page;
$params[] = $offset;
$param_types .= "ii";

// Thực hiện truy vấn đếm tổng số
$count_stmt = $conn->prepare($count_query);
if (!empty($param_types)) {
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
$categories = $stmt->get_result();

// Lấy danh sách danh mục cha cho dropdown lọc
$parent_categories_sql = "SELECT id, ten FROM danhmuc ORDER BY ten";
$parent_categories_result = $conn->query($parent_categories_sql);
$all_categories = [];
while ($cat = $parent_categories_result->fetch_assoc()) {
    $all_categories[$cat['id']] = $cat['ten'];
}

// Hiển thị modal thêm/sửa danh mục
$category_to_edit = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $edit_sql = "SELECT * FROM danhmuc WHERE id = ?";
    $edit_stmt = $conn->prepare($edit_sql);
    $edit_stmt->bind_param('i', $edit_id);
    $edit_stmt->execute();
    $edit_result = $edit_stmt->get_result();
    if ($edit_result->num_rows > 0) {
        $category_to_edit = $edit_result->fetch_assoc();
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý danh mục - Bug Shop Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/categories.css">
</head>
<body>
        
        <!-- Main Content -->
        <div class="col-md-10 col-lg-10 ms-auto">
            <div class="container-fluid px-4 py-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0 text-gray-800">Quản lý danh mục</h1>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#categoryModal">
                        <i class="fas fa-plus me-1"></i> Thêm danh mục
                    </button>
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
                                           placeholder="Tên danh mục, mô tả..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="parent" class="form-label">Danh mục cha</label>
                                    <select class="form-select" id="parent" name="parent">
                                        <option value="">Tất cả danh mục</option>
                                        <option value="0" <?php echo ($parent === '0') ? 'selected' : ''; ?>>Danh mục gốc</option>
                                        <?php foreach ($all_categories as $id => $name): ?>
                                            <option value="<?php echo $id; ?>" <?php echo ($parent == $id) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2 mb-3">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-search me-1"></i> Tìm kiếm
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Lọc nâng cao -->
                            <div class="collapse <?php echo ($status !== '' || $sort != 'id_desc') ? 'show' : ''; ?>" id="filtersCollapse">
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="status" class="form-label">Trạng thái</label>
                                        <select class="form-select" id="status" name="status">
                                            <option value="">Tất cả</option>
                                            <option value="1" <?php echo ($status === '1') ? 'selected' : ''; ?>>Đang hiển thị</option>
                                            <option value="0" <?php echo ($status === '0') ? 'selected' : ''; ?>>Đang ẩn</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="sort" class="form-label">Sắp xếp</label>
                                        <select class="form-select" id="sort" name="sort">
                                            <option value="id_desc" <?php echo ($sort == 'id_desc') ? 'selected' : ''; ?>>Mặc định (Mới nhất)</option>
                                            <option value="name_asc" <?php echo ($sort == 'name_asc') ? 'selected' : ''; ?>>Tên A-Z</option>
                                            <option value="name_desc" <?php echo ($sort == 'name_desc') ? 'selected' : ''; ?>>Tên Z-A</option>
                                            <option value="newest" <?php echo ($sort == 'newest') ? 'selected' : ''; ?>>Mới nhất</option>
                                            <option value="oldest" <?php echo ($sort == 'oldest') ? 'selected' : ''; ?>>Cũ nhất</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3 d-flex align-items-end">
                                        <a href="categories.php" class="btn btn-outline-secondary w-100">
                                            <i class="fas fa-redo me-1"></i> Đặt lại bộ lọc
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Danh sách danh mục -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">
                            Danh sách danh mục 
                            <span class="badge bg-secondary ms-1"><?php echo $total_items; ?> danh mục</span>
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th width="60">ID</th>
                                        <th width="80">Hình ảnh</th>
                                        <th>Tên danh mục</th>
                                        <th>Danh mục cha</th>
                                        <th>Số lượng</th>
                                        <th width="100">Trạng thái</th>
                                        <th width="170">Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($categories && $categories->num_rows > 0): ?>
                                        <?php while ($category = $categories->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $category['id']; ?></td>
                                                <td>
                                                    <?php if (!empty($category['hinhanh'])): ?>
                                                        <img src="../<?php echo htmlspecialchars($category['hinhanh']); ?>" 
                                                             alt="<?php echo htmlspecialchars($category['ten']); ?>" 
                                                             class="category-thumbnail">
                                                    <?php else: ?>
                                                        <div class="text-center text-muted">
                                                            <i class="fas fa-folder fa-2x"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="category-name" title="<?php echo htmlspecialchars($category['ten']); ?>">
                                                        <?php echo htmlspecialchars($category['ten']); ?>
                                                    </div>
                                                    <div class="small text-muted">
                                                        <?php echo htmlspecialchars($category['slug']); ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php 
                                                    if (isset($category['parent_name'])) {
                                                        echo htmlspecialchars($category['parent_name']);
                                                    } else {
                                                        echo '<span class="text-muted">Không có</span>';
                                                    }
                                                    ?>
                                                </td>
                                                <td>
                                                    <div class="small mb-1">
                                                        <span class="badge bg-primary"><?php echo $category['subcategory_count']; ?> danh mục con</span>
                                                    </div>
                                                    <div class="small">
                                                        <span class="badge bg-info"><?php echo $category['product_count']; ?> sản phẩm</span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if ($category['trang_thai']): ?>
                                                        <span class="badge bg-success">Hiển thị</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Ẩn</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="d-flex gap-1 mb-1">
                                                        <a href="categories.php?edit=<?php echo $category['id']; ?>" class="btn btn-sm btn-primary flex-grow-1">
                                                            <i class="fas fa-edit"></i> Sửa
                                                        </a>
                                                    </div>
                                                    <div class="d-flex gap-1">
                                                        <a href="categories.php?toggle_status=<?php echo $category['id']; ?>" 
                                                           class="btn btn-sm <?php echo $category['trang_thai'] ? 'btn-warning' : 'btn-success'; ?> flex-grow-1"
                                                           onclick="return confirm('Bạn có chắc muốn <?php echo $category['trang_thai'] ? 'ẩn' : 'hiển thị'; ?> danh mục này?')">
                                                            <i class="fas <?php echo $category['trang_thai'] ? 'fa-eye-slash' : 'fa-eye'; ?>"></i> <?php echo $category['trang_thai'] ? 'Ẩn' : 'Hiện'; ?>
                                                        </a>
                                                        <a href="categories.php?delete=<?php echo $category['id']; ?>" 
                                                           class="btn btn-sm btn-danger flex-grow-1"
                                                           onclick="return confirm('Bạn có chắc chắn muốn xóa danh mục này? Hành động này không thể hoàn tác!')">
                                                            <i class="fas fa-trash"></i> Xóa
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4">
                                                <div class="text-muted mb-3">
                                                    <i class="fas fa-folder-open fa-3x"></i>
                                                </div>
                                                <h5>Không tìm thấy danh mục nào</h5>
                                                <p>Hãy thử thay đổi tiêu chí tìm kiếm hoặc thêm danh mục mới</p>
                                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#categoryModal">
                                                    <i class="fas fa-plus me-1"></i> Thêm danh mục mới
                                                </button>
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
                                trong <?php echo $total_items; ?> danh mục
                            </div>
                            <nav>
                                <ul class="pagination">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="<?php echo '?page=1' . 
                                                (!empty($search) ? '&search=' . urlencode($search) : '') .
                                                ($parent !== '' ? '&parent=' . urlencode($parent) : '') .
                                                ($status !== '' ? '&status=' . urlencode($status) : '') .
                                                (!empty($sort) ? '&sort=' . urlencode($sort) : ''); ?>">
                                                <i class="fas fa-angle-double-left"></i>
                                            </a>
                                        </li>
                                        <li class="page-item">
                                            <a class="page-link" href="<?php echo '?page=' . ($page - 1) . 
                                                (!empty($search) ? '&search=' . urlencode($search) : '') .
                                                ($parent !== '' ? '&parent=' . urlencode($parent) : '') .
                                                ($status !== '' ? '&status=' . urlencode($status) : '') .
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
                                                ($parent !== '' ? '&parent=' . urlencode($parent) : '') .
                                                ($status !== '' ? '&status=' . urlencode($status) : '') .
                                                (!empty($sort) ? '&sort=' . urlencode($sort) : ''); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="<?php echo '?page=' . ($page + 1) . 
                                                (!empty($search) ? '&search=' . urlencode($search) : '') .
                                                ($parent !== '' ? '&parent=' . urlencode($parent) : '') .
                                                ($status !== '' ? '&status=' . urlencode($status) : '') .
                                                (!empty($sort) ? '&sort=' . urlencode($sort) : ''); ?>">
                                                <i class="fas fa-angle-right"></i>
                                            </a>
                                        </li>
                                        <li class="page-item">
                                            <a class="page-link" href="<?php echo '?page=' . $total_pages . 
                                                (!empty($search) ? '&search=' . urlencode($search) : '') .
                                                ($parent !== '' ? '&parent=' . urlencode($parent) : '') .
                                                ($status !== '' ? '&status=' . urlencode($status) : '') .
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

<!-- Modal Thêm/Sửa Danh Mục -->
<div class="modal fade" id="categoryModal" tabindex="-1" aria-labelledby="categoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form action="category_save.php" method="POST" enctype="multipart/form-data">
                <?php if ($category_to_edit): ?>
                    <input type="hidden" name="id" value="<?php echo $category_to_edit['id']; ?>">
                <?php endif; ?>
                
                <div class="modal-header">
                    <h5 class="modal-title" id="categoryModalLabel">
                        <?php echo $category_to_edit ? 'Chỉnh sửa danh mục' : 'Thêm danh mục mới'; ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="ten" class="form-label">Tên danh mục <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="ten" name="ten" required
                                       value="<?php echo $category_to_edit ? htmlspecialchars($category_to_edit['ten']) : ''; ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="slug" class="form-label">Slug</label>
                                <input type="text" class="form-control" id="slug" name="slug" 
                                       value="<?php echo $category_to_edit ? htmlspecialchars($category_to_edit['slug']) : ''; ?>">
                                <small class="text-muted">Để trống để tự động tạo từ tên</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="danhmuc_cha" class="form-label">Danh mục cha</label>
                                <select class="form-select" id="danhmuc_cha" name="danhmuc_cha">
                                    <option value="">Không có (danh mục gốc)</option>
                                    <?php foreach ($all_categories as $id => $name): ?>
                                        <?php if ($category_to_edit && $id == $category_to_edit['id']) continue; // Không thể chọn chính nó làm cha ?>
                                        <option value="<?php echo $id; ?>" <?php echo ($category_to_edit && $category_to_edit['danhmuc_cha'] == $id) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="mo_ta" class="form-label">Mô tả</label>
                                <textarea class="form-control" id="mo_ta" name="mo_ta" rows="4"><?php echo $category_to_edit ? htmlspecialchars($category_to_edit['mo_ta']) : ''; ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="thu_tu" class="form-label">Thứ tự hiển thị</label>
                                <input type="number" class="form-control" id="thu_tu" name="thu_tu" min="0" 
                                       value="<?php echo $category_to_edit ? htmlspecialchars($category_to_edit['thu_tu']) : '0'; ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="meta_title" class="form-label">Meta Title (SEO)</label>
                                <input type="text" class="form-control" id="meta_title" name="meta_title" 
                                       value="<?php echo $category_to_edit ? htmlspecialchars($category_to_edit['meta_title']) : ''; ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="meta_description" class="form-label">Meta Description (SEO)</label>
                                <textarea class="form-control" id="meta_description" name="meta_description" rows="3"><?php echo $category_to_edit ? htmlspecialchars($category_to_edit['meta_description']) : ''; ?></textarea>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="hinhanh" class="form-label">Hình ảnh danh mục</label>
                                <div class="card p-2 text-center">
                                    <?php if ($category_to_edit && !empty($category_to_edit['hinhanh'])): ?>
                                        <img src="../<?php echo htmlspecialchars($category_to_edit['hinhanh']); ?>" 
                                             class="img-fluid mb-2" style="max-height: 150px; object-fit: contain;">
                                    <?php else: ?>
                                        <div class="py-4 text-muted">
                                            <i class="fas fa-image fa-4x mb-2"></i>
                                            <p>Chưa có hình ảnh</p>
                                        </div>
                                    <?php endif; ?>
                                    <input type="file" class="form-control" id="hinhanh" name="hinhanh" accept="image/*">
                                    <small class="text-muted mt-1">Để trống nếu không muốn thay đổi hình ảnh</small>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="trang_thai" name="trang_thai" value="1"
                                           <?php echo (!$category_to_edit || $category_to_edit['trang_thai'] == 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="trang_thai">Hiển thị danh mục</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                    <button type="submit" class="btn btn-primary">
                        <?php echo $category_to_edit ? 'Cập nhật' : 'Thêm mới'; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/categories.js"></script>
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
    
    // Show modal if edit parameter is present
    <?php if ($category_to_edit): ?>
    const categoryModal = new bootstrap.Modal(document.getElementById('categoryModal'));
    categoryModal.show();
    <?php endif; ?>
    
    // Generate slug from name
    const nameInput = document.getElementById('ten');
    const slugInput = document.getElementById('slug');
    
    if (nameInput && slugInput) {
        nameInput.addEventListener('keyup', function() {
            if (!slugInput.value) {
                slugInput.value = createSlug(nameInput.value);
            }
        });
    }
    
    function createSlug(text) {
        return text.toLowerCase()
            .replace(/[áàảãạăắằẳẵặâấầẩẫậ]/g, 'a')
            .replace(/[éèẻẽẹêếềểễệ]/g, 'e')
            .replace(/[íìỉĩị]/g, 'i')
            .replace(/[óòỏõọôốồổỗộơớờởỡợ]/g, 'o')
            .replace(/[úùủũụưứừửữự]/g, 'u')
            .replace(/[ýỳỷỹỵ]/g, 'y')
            .replace(/đ/g, 'd')
            .replace(/\s+/g, '-')
            .replace(/[^\w\-]+/g, '')
            .replace(/\-\-+/g, '-')
            .replace(/^-+/, '')
            .replace(/-+$/, '');
    }
});
</script>
</body>
</html>
