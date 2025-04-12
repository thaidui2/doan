<?php
// Set page title
$page_title = 'Quản lý khách hàng';

// Include header (will check for login)
include('includes/header.php');

// Include database connection
include('../config/config.php');

// Variables for filtering and searching
$search_keyword = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? (int)$_GET['status'] : -1;
$user_type_filter = isset($_GET['user_type']) ? (int)$_GET['user_type'] : -1;
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'id_user';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// Build query
$query = "SELECT u.*, 
            CASE WHEN u.loai_user = 1 THEN
                (SELECT COUNT(s.id_sanpham) FROM sanpham s WHERE s.id_nguoiban = u.id_user)
            ELSE 0 END AS so_san_pham
          FROM users u";

// Add search conditions
$where_conditions = [];
if (!empty($search_keyword)) {
    $search_keyword = $conn->real_escape_string($search_keyword);
    $where_conditions[] = "(taikhoan LIKE '%$search_keyword%' OR email LIKE '%$search_keyword%' OR sdt LIKE '%$search_keyword%' OR tenuser LIKE '%$search_keyword%')";
}

if ($status_filter !== -1) {
    $where_conditions[] = "trang_thai = $status_filter";
}

// Apply user type filter correctly
if ($user_type_filter !== -1) {
    $where_conditions[] = "loai_user = $user_type_filter";
}

// Combine conditions
if (!empty($where_conditions)) {
    $query .= " WHERE " . implode(" AND ", $where_conditions);
}

// Add sorting
$valid_sort_columns = ['id_user', 'taikhoan', 'tenuser', 'email', 'ngay_tao', 'trang_thai'];
if (!in_array($sort_by, $valid_sort_columns)) {
    $sort_by = 'id_user';
}

$sort_order = ($sort_order === 'ASC') ? 'ASC' : 'DESC';
$query .= " ORDER BY $sort_by $sort_order";

// Pagination
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($current_page - 1) * $per_page;

// Get total count for pagination
$count_query = str_replace("SELECT u.*, 
            CASE WHEN u.loai_user = 1 THEN
                (SELECT COUNT(s.id_sanpham) FROM sanpham s WHERE s.id_nguoiban = u.id_user)
            ELSE 0 END AS so_san_pham", "SELECT COUNT(*) as total", $query);
$count_result = $conn->query($count_query);
$total_rows = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $per_page);

// Add limit for pagination
$query .= " LIMIT $offset, $per_page";

// Execute query
$result = $conn->query($query);
?>

<!-- Include sidebar -->
<?php include('includes/sidebar.php'); ?>

<!-- Thêm vào phần head của trang -->
<style>
    .seller-details {
        transition: all 0.3s ease;
        margin: 0 1rem;
    }
    
    .table .seller-details-row {
        background-color: rgba(0, 0, 0, 0.02) !important;
    }
    
    /* Card styling */
    .card {
        transition: all 0.3s ease;
    }
    
    .card:hover {
        box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1) !important;
    }
    
    /* Seller badge styling */
    .badge.bg-primary {
        background-color: #0d6efd !important;
    }
    
    .text-truncate {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 150px;
        display: inline-block;
    }
    
    .toggle-seller-details {
        transition: all 0.2s ease;
    }
    
    .toggle-seller-details:hover {
        transform: translateY(-1px);
    }
</style>

<!-- Main content -->
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Quản lý khách hàng</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="add_customer.php" class="btn btn-sm btn-primary">
                <i class="bi bi-person-plus"></i> Thêm khách hàng mới
            </a>
        </div>
    </div>

    <?php
    // Display success/error messages if they exist
    if (isset($_SESSION['success_message'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                ' . $_SESSION['success_message'] . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>';
        unset($_SESSION['success_message']);
    }

    if (isset($_SESSION['error_message'])) {
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                ' . $_SESSION['error_message'] . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>';
        unset($_SESSION['error_message']);
    }
    ?>

    <!-- Navigation tabs -->
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link <?php echo !isset($_GET['user_type']) || $_GET['user_type'] == -1 ? 'active' : ''; ?>" href="customers.php">
                <i class="bi bi-people"></i> Tất cả người dùng
                <span class="badge bg-secondary ms-1"><?php echo $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count']; ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo isset($_GET['user_type']) && $_GET['user_type'] == 0 ? 'active' : ''; ?>" href="customers.php?user_type=0">
                <i class="bi bi-person"></i> Người mua
                <span class="badge bg-secondary ms-1"><?php echo $conn->query("SELECT COUNT(*) as count FROM users WHERE loai_user = 0")->fetch_assoc()['count']; ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo isset($_GET['user_type']) && $_GET['user_type'] == 1 ? 'active' : ''; ?>" href="customers.php?user_type=1">
                <i class="bi bi-shop"></i> Người bán
                <span class="badge bg-secondary ms-1"><?php echo $conn->query("SELECT COUNT(*) as count FROM users WHERE loai_user = 1")->fetch_assoc()['count']; ?></span>
            </a>
        </li>
    </ul>

    <!-- Search and filter form -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3" id="searchForm">
                <div class="col-md-4">
                    <label for="search" class="form-label">Tìm kiếm</label>
                    <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search_keyword); ?>" placeholder="Tên, email, SĐT...">
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Trạng thái</label>
                    <select class="form-select" id="status" name="status">
                        <option value="-1" <?php echo $status_filter === -1 ? 'selected' : ''; ?>>Tất cả trạng thái</option>
                        <option value="1" <?php echo $status_filter === 1 ? 'selected' : ''; ?>>Đang hoạt động</option>
                        <option value="0" <?php echo $status_filter === 0 ? 'selected' : ''; ?>>Đã khóa</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="user_type" class="form-label">Loại người dùng</label>
                    <select class="form-select" id="user_type" name="user_type">
                        <option value="-1" <?php echo !isset($_GET['user_type']) || $_GET['user_type'] == -1 ? 'selected' : ''; ?>>Tất cả</option>
                        <option value="0" <?php echo isset($_GET['user_type']) && $_GET['user_type'] == 0 ? 'selected' : ''; ?>>Người mua</option>
                        <option value="1" <?php echo isset($_GET['user_type']) && $_GET['user_type'] == 1 ? 'selected' : ''; ?>>Người bán</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="sort" class="form-label">Sắp xếp theo</label>
                    <select class="form-select" id="sort" name="sort">
                        <option value="id_user" <?php echo $sort_by === 'id_user' ? 'selected' : ''; ?>>ID</option>
                        <option value="tenuser" <?php echo $sort_by === 'tenuser' ? 'selected' : ''; ?>>Tên</option>
                        <option value="email" <?php echo $sort_by === 'email' ? 'selected' : ''; ?>>Email</option>
                        <option value="ngay_tao" <?php echo $sort_by === 'ngay_tao' ? 'selected' : ''; ?>>Ngày đăng ký</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="order" class="form-label">Thứ tự</label>
                    <select class="form-select" id="order" name="order">
                        <option value="ASC" <?php echo $sort_order === 'ASC' ? 'selected' : ''; ?>>Tăng dần</option>
                        <option value="DESC" <?php echo $sort_order === 'DESC' ? 'selected' : ''; ?>>Giảm dần</option>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i> Tìm kiếm
                    </button>
                    <a href="customers.php" class="btn btn-secondary ms-2">
                        <i class="bi bi-x-circle"></i> Xóa bộ lọc
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Thêm vào ngay sau nút tìm kiếm và trước bảng -->
    <?php if ($user_type_filter === 1): // Chỉ hiển thị ở trang người bán ?>
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center h-100">
                <div class="card-body">
                    <div class="display-6 text-primary mb-3">
                        <i class="bi bi-people"></i>
                    </div>
                    <?php
                    $total_sellers = $conn->query("SELECT COUNT(*) as count FROM users WHERE loai_user = 1")->fetch_assoc()['count'];
                    ?>
                    <h3 class="card-title h4"><?php echo number_format($total_sellers); ?></h3>
                    <p class="card-text text-muted">Tổng số người bán</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center h-100">
                <div class="card-body">
                    <div class="display-6 text-success mb-3">
                        <i class="bi bi-box-seam"></i>
                    </div>
                    <?php
                    $total_products = $conn->query("SELECT COUNT(*) as count FROM sanpham WHERE id_nguoiban IS NOT NULL")->fetch_assoc()['count'];
                    ?>
                    <h3 class="card-title h4"><?php echo number_format($total_products); ?></h3>
                    <p class="card-text text-muted">Tổng số sản phẩm</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center h-100">
                <div class="card-body">
                    <div class="display-6 text-warning mb-3">
                        <i class="bi bi-shop"></i>
                    </div>
                    <?php
                    $avg_products = $conn->query("SELECT AVG(product_count) as avg FROM (SELECT COUNT(id_sanpham) as product_count FROM sanpham WHERE id_nguoiban IS NOT NULL GROUP BY id_nguoiban) as counts")->fetch_assoc()['avg'];
                    ?>
                    <h3 class="card-title h4"><?php echo number_format($avg_products, 1); ?></h3>
                    <p class="card-text text-muted">Sản phẩm trung bình/người bán</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center h-100">
                <div class="card-body">
                    <div class="display-6 text-danger mb-3">
                        <i class="bi bi-calendar-check"></i>
                    </div>
                    <?php
                    $new_sellers = $conn->query("SELECT COUNT(*) as count FROM users WHERE loai_user = 1 AND ngay_tro_thanh_nguoi_ban >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch_assoc()['count'];
                    ?>
                    <h3 class="card-title h4"><?php echo number_format($new_sellers); ?></h3>
                    <p class="card-text text-muted">Người bán mới (30 ngày)</p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Users table -->
    <div class="card">
        <div class="card-header bg-white">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Danh sách khách hàng</h5>
                <span class="badge bg-secondary"><?php echo $total_rows; ?> khách hàng</span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">ID</th>
                            <th scope="col">Tài khoản</th>
                            <th scope="col">Tên khách hàng</th>
                            <th scope="col">Liên hệ</th>
                            <th scope="col">Ngày đăng ký</th>
                            <th scope="col">Trạng thái</th>
                            <th scope="col">Loại tài khoản</th>
                            <th scope="col">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($customer = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $customer['id_user']; ?></td>
                                    <td><?php echo htmlspecialchars($customer['taikhoan']); ?></td>
                                    <td><?php echo htmlspecialchars($customer['tenuser']); ?></td>
                                    <td>
                                        <div><?php echo htmlspecialchars($customer['email']); ?></div>
                                        <div><?php echo htmlspecialchars($customer['sdt']); ?></div>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($customer['ngay_tao'])); ?></td>
                                    <td>
                                        <?php if ($customer['trang_thai'] == 1): ?>
                                            <span class="badge bg-success">Đang hoạt động</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Đã khóa</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($customer['loai_user'] == 0): ?>
                                            <span class="badge bg-info">Người mua</span>
                                        <?php else: ?>
                                            <span class="badge bg-primary">Người bán</span>
                                            <?php if (!empty($customer['ten_shop'])): ?>
                                                <div class="small mt-1"><strong>Shop:</strong> <?php echo htmlspecialchars($customer['ten_shop']); ?></div>
                                            <?php endif; ?>
                                            <?php if (isset($customer['so_san_pham'])): ?>
                                                <div class="small text-muted"><?php echo $customer['so_san_pham']; ?> sản phẩm</div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <?php if ($customer['loai_user'] == 1): ?>
                                            <button type="button" class="btn btn-outline-info toggle-seller-details" data-id="<?php echo $customer['id_user']; ?>">
                                                <i class="bi bi-shop"></i> Chi tiết shop
                                            </button>
                                            <?php endif; ?>
                                            <a href="customer-detail.php?id=<?php echo $customer['id_user']; ?>" class="btn btn-outline-primary">
                                                <i class="bi bi-eye"></i> Xem
                                            </a>
                                            <button type="button" class="btn btn-outline-dark dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                                                <span class="visually-hidden">Toggle Dropdown</span>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <a class="dropdown-item" href="edit_customer.php?id=<?php echo $customer['id_user']; ?>">
                                                        <i class="bi bi-pencil"></i> Chỉnh sửa
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item toggle-status" href="#" 
                                                       data-id="<?php echo $customer['id_user']; ?>" 
                                                       data-status="<?php echo $customer['trang_thai']; ?>"
                                                       data-username="<?php echo htmlspecialchars($customer['taikhoan']); ?>">
                                                        <?php if ($customer['trang_thai'] == 1): ?>
                                                            <i class="bi bi-lock"></i> Khóa tài khoản
                                                        <?php else: ?>
                                                            <i class="bi bi-unlock"></i> Mở khóa tài khoản
                                                        <?php endif; ?>
                                                    </a>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <a class="dropdown-item change-user-type" href="#" 
                                                       data-id="<?php echo $customer['id_user']; ?>"
                                                       data-type="<?php echo $customer['loai_user']; ?>"
                                                       data-username="<?php echo htmlspecialchars($customer['taikhoan']); ?>">
                                                        <?php if ($customer['loai_user'] == 0): ?>
                                                            <i class="bi bi-shop"></i> Chuyển thành người bán
                                                        <?php else: ?>
                                                            <i class="bi bi-person"></i> Chuyển thành người mua
                                                        <?php endif; ?>
                                                    </a>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <a class="dropdown-item text-danger delete-customer" href="#" 
                                                       data-id="<?php echo $customer['id_user']; ?>"
                                                       data-username="<?php echo htmlspecialchars($customer['taikhoan']); ?>">
                                                        <i class="bi bi-trash"></i> Xóa tài khoản
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                                <?php if ($customer['loai_user'] == 1): ?>
                                <tr id="seller-details-<?php echo $customer['id_user']; ?>" class="seller-details-row" style="display: none;">
                                    <td colspan="8" class="seller-details">
                                        <div class="card border-0 shadow-sm mb-0">
                                            <div class="card-body">
                                                <h5 class="card-title">Thông tin cửa hàng</h5>
                                                <?php
                                                // Lấy thông tin chi tiết cửa hàng
                                                $shop_query = $conn->prepare("SELECT * FROM thongtin_shop WHERE id_nguoiban = ?");
                                                $shop_query->bind_param("i", $customer['id_user']);
                                                $shop_query->execute();
                                                $shop_info = $shop_query->get_result()->fetch_assoc();
                                                ?>
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <p><strong>Tên shop:</strong> <?php echo isset($shop_info['ten_shop']) ? htmlspecialchars($shop_info['ten_shop']) : 'Chưa cập nhật'; ?></p>
                                                        <p><strong>Ngày trở thành người bán:</strong> <?php echo isset($customer['ngay_tro_thanh_nguoi_ban']) ? date('d/m/Y', strtotime($customer['ngay_tro_thanh_nguoi_ban'])) : 'Chưa cập nhật'; ?></p>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <p><strong>Tổng sản phẩm:</strong> <?php echo $customer['so_san_pham']; ?></p>
                                                        <p><strong>Trạng thái shop:</strong> 
                                                            <?php if (isset($shop_info['trang_thai']) && $shop_info['trang_thai'] == 1): ?>
                                                                <span class="badge bg-success">Hoạt động</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary">Chưa hoạt động</span>
                                                            <?php endif; ?>
                                                        </p>
                                                    </div>
                                                    <div class="col-12 mt-2">
                                                        <p><strong>Mô tả:</strong> <?php echo isset($shop_info['mo_ta']) ? htmlspecialchars($shop_info['mo_ta']) : 'Chưa cập nhật'; ?></p>
                                                    </div>
                                                    <div class="col-12 mt-2">
                                                        <a href="seller_products.php?seller_id=<?php echo $customer['id_user']; ?>" class="btn btn-sm btn-primary">
                                                            <i class="bi bi-box-seam"></i> Xem tất cả sản phẩm
                                                        </a>
                                                        <a href="edit_shop.php?id=<?php echo $customer['id_user']; ?>" class="btn btn-sm btn-outline-dark">
                                                            <i class="bi bi-pencil"></i> Chỉnh sửa thông tin shop
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <div class="text-muted">Không tìm thấy khách hàng nào</div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $current_page - 1; ?>&search=<?php echo urlencode($search_keyword); ?>&status=<?php echo $status_filter; ?>&user_type=<?php echo $user_type_filter; ?>&sort=<?php echo $sort_by; ?>&order=<?php echo $sort_order; ?>">
                        <i class="bi bi-chevron-left"></i> Trước
                    </a>
                </li>
                
                <?php
                $start_page = max(1, $current_page - 2);
                $end_page = min($total_pages, $current_page + 2);
                
                if ($start_page > 1) {
                    echo '<li class="page-item"><a class="page-link" href="?page=1&search=' . urlencode($search_keyword) . '&status=' . $status_filter . '&user_type=' . $user_type_filter . '&sort=' . $sort_by . '&order=' . $sort_order . '">1</a></li>';
                    if ($start_page > 2) {
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                }
                
                for ($i = $start_page; $i <= $end_page; $i++) {
                    echo '<li class="page-item ' . (($i == $current_page) ? 'active' : '') . '">';
                    echo '<a class="page-link" href="?page=' . $i . '&search=' . urlencode($search_keyword) . '&status=' . $status_filter . '&user_type=' . $user_type_filter . '&sort=' . $sort_by . '&order=' . $sort_order . '">' . $i . '</a>';
                    echo '</li>';
                }
                
                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) {
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                    echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&search=' . urlencode($search_keyword) . '&status=' . $status_filter . '&user_type=' . $user_type_filter . '&sort=' . $sort_by . '&order=' . $sort_order . '">' . $total_pages . '</a></li>';
                }
                ?>
                
                <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $current_page + 1; ?>&search=<?php echo urlencode($search_keyword); ?>&status=<?php echo $status_filter; ?>&user_type=<?php echo $user_type_filter; ?>&sort=<?php echo $sort_by; ?>&order=<?php echo $sort_order; ?>">
                        Tiếp <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</main>

<!-- Toast container for notifications -->
<div class="toast-container position-fixed bottom-0 end-0 p-3"></div>

<!-- Confirmation Modal -->
<div class="modal fade" id="confirmationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Xác nhận</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="modalBody">
                Bạn có chắc chắn muốn thực hiện hành động này?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" id="confirmAction">Xác nhận</button>
            </div>
        </div>
    </div>
</div>

<!-- Change User Type Modal -->
<div class="modal fade" id="changeUserTypeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="changeTypeTitle">Thay đổi loại người dùng</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="changeTypeBody">
                <p>Bạn có chắc chắn muốn thay đổi loại tài khoản của người dùng này?</p>
                
                <div id="toSellerInfo" class="d-none">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i> 
                        Chuyển tài khoản này thành người bán sẽ cho phép họ đăng bán sản phẩm trên hệ thống.
                    </div>
                    <div class="mb-3">
                        <label for="shop_name" class="form-label">Tên cửa hàng</label>
                        <input type="text" class="form-control" id="shop_name" name="shop_name">
                    </div>
                    <div class="mb-3">
                        <label for="shop_description" class="form-label">Mô tả cửa hàng</label>
                        <textarea class="form-control" id="shop_description" name="shop_description" rows="3"></textarea>
                    </div>
                </div>
                
                <div id="toBuyerInfo" class="d-none">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i> 
                        Chuyển tài khoản này thành người mua thông thường sẽ xóa quyền đăng bán sản phẩm. 
                        Các sản phẩm hiện tại của họ sẽ bị ẩn.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" id="confirmChangeType">
                    Xác nhận thay đổi
                </button>
            </div>
        </div>
    </div>
</div>

<?php 
// Thay thế đoạn JavaScript ở cuối file (dòng ~656+)
$page_specific_js = '
<script>
// Hàm tự triển khai dropdown khi bootstrap không hoạt động
function setupCustomDropdowns() {
    document.querySelectorAll(".dropdown-toggle").forEach(function(dropdownToggle) {
        dropdownToggle.addEventListener("click", function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Đóng tất cả các dropdown đang mở
            document.querySelectorAll(".dropdown-menu.show").forEach(function(openMenu) {
                if (openMenu !== this.nextElementSibling) {
                    openMenu.classList.remove("show");
                }
            });
            
            // Mở/đóng dropdown hiện tại
            const dropdownMenu = this.nextElementSibling;
            dropdownMenu.classList.toggle("show");
            
            // Thêm CSS cần thiết cho dropdown menu
            if (dropdownMenu.classList.contains("show")) {
                dropdownMenu.style.position = "absolute";
                dropdownMenu.style.inset = "0px 0px auto auto";
                dropdownMenu.style.margin = "0px";
                dropdownMenu.style.transform = "translate3d(-1px, 41px, 0px)";
            }
        });
    });
    
    // Đóng dropdown khi click ra ngoài
    document.addEventListener("click", function(e) {
        if (!e.target.matches(".dropdown-toggle") && !e.target.closest(".dropdown-menu")) {
            document.querySelectorAll(".dropdown-menu.show").forEach(function(menu) {
                menu.classList.remove("show");
            });
        }
    });
}

document.addEventListener("DOMContentLoaded", function() {
    // Thử khởi tạo dropdown bằng Bootstrap
    if (typeof bootstrap !== "undefined" && typeof bootstrap.Dropdown !== "undefined") {
        try {
            var dropdownElementList = [].slice.call(document.querySelectorAll(".dropdown-toggle"));
            var dropdownList = dropdownElementList.map(function(dropdownToggleEl) {
                return new bootstrap.Dropdown(dropdownToggleEl);
            });
            console.log("Bootstrap Dropdown đã được khởi tạo");
        } catch (error) {
            console.error("Lỗi khi khởi tạo Bootstrap Dropdown:", error);
            // Nếu bootstrap gặp lỗi, dùng phương pháp thay thế
            setupCustomDropdowns();
        }
    } else {
        console.warn("Bootstrap không được tìm thấy, sử dụng dropdown tùy chỉnh");
        setupCustomDropdowns();
    }
    
    // Code xử lý các nút và chức năng khác giữ nguyên
    document.querySelectorAll(".toggle-seller-details").forEach(button => {
        button.addEventListener("click", function() {
            const sellerId = this.getAttribute("data-id");
            const detailsRow = document.getElementById(`seller-details-${sellerId}`);
            
            if (detailsRow) {
                if (detailsRow.style.display === "none" || detailsRow.style.display === "") {
                    detailsRow.style.display = "table-row";
                    this.innerHTML = \'<i class="bi bi-dash-circle"></i> Ẩn chi tiết\';
                    this.classList.replace("btn-outline-info", "btn-info");
                } else {
                    detailsRow.style.display = "none";
                    this.innerHTML = \'<i class="bi bi-shop"></i> Chi tiết shop\';
                    this.classList.replace("btn-info", "btn-outline-info");
                }
            }
        });
    });
    
    // Phần còn lại giữ nguyên
    document.querySelectorAll(".toggle-status").forEach(link => { /* Code hiện tại */ });
    document.querySelectorAll(".change-user-type").forEach(link => { /* Code hiện tại */ });
    document.querySelectorAll(".delete-customer").forEach(link => { /* Code hiện tại */ });
});
</script>
';

// Include footer
include('includes/footer.php');
?>
