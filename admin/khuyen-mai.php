<?php
// Set page title
$page_title = 'Quản lý khuyến mãi';

// Include header and authentication
include('includes/header.php');

// Include database connection
include('../config/config.php');

// Determine current action
$action = isset($_GET['action']) ? $_GET['action'] : 'list';

// Initialize filter variables to avoid undefined variable errors
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_status = isset($_GET['status']) ? (int)$_GET['status'] : -1;
$filter_date = isset($_GET['date']) ? $_GET['date'] : '';
$filter_type = isset($_GET['type']) ? $_GET['type'] : '';

// Get current page for pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Check admin permissions - use the user loai_user column instead of roles table
if ($admin_level < 1) { // Only allow admins and managers
    $_SESSION['error_message'] = "Bạn không có quyền truy cập trang này.";
    header('Location: index.php');
    exit;
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ... existing code for form submissions ...
}

// Get total promotions for pagination
$count_query = "SELECT COUNT(*) as total FROM khuyen_mai";
$count_result = $conn->query($count_query);
$total_rows = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);

// Initialize $result to avoid undefined variable error
$result = null;

// If in list mode, get the promotions
if ($action === 'list') {
    // Base query
    $query = "SELECT * FROM khuyen_mai";
    
    // Apply filters if provided
    $where_clauses = [];
    
    if (!empty($search)) {
        $search_term = '%' . $conn->real_escape_string($search) . '%';
        $where_clauses[] = "(ten LIKE '$search_term' OR ma_khuyenmai LIKE '$search_term')";
    }
    
    if ($filter_status !== -1) {
        $where_clauses[] = "trang_thai = $filter_status";
    }
    
    if (!empty($filter_date)) {
        // Parse date range or specific date logic here
        // For example: $where_clauses[] = "ngay_bat_dau <= '$filter_date' AND ngay_ket_thuc >= '$filter_date'";
    }
    
    // Combine where clauses
    if (!empty($where_clauses)) {
        $query .= " WHERE " . implode(' AND ', $where_clauses);
    }
    
    // Add sorting and pagination - use 'id' instead of 'ngay_tao'
    $query .= " ORDER BY id DESC LIMIT $offset, $limit";
    
    // Execute query
    $result = $conn->query($query);
    
    // For displaying counts in the UI, set $total_records to match $total_rows
    $total_records = $total_rows;
}

// Main content based on action
?>

<!-- Main content -->
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="bi bi-ticket-perforated me-2"></i>Quản lý khuyến mãi</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <!-- Always show the Add Promotion button -->
            <a href="them-khuyen-mai.php" class="btn btn-sm btn-primary">
                <i class="bi bi-plus-lg"></i> Thêm mã giảm giá mới
            </a>
        </div>
    </div>
    
    <?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i><?php echo $_SESSION['success_message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i><?php echo $_SESSION['error_message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>
    
    <!-- Filter & Search -->
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h5 class="card-title mb-0">
                <i class="bi bi-funnel me-2"></i>Bộ lọc và tìm kiếm
            </h5>
        </div>
        <div class="card-body">
            <form method="GET" action="khuyen-mai.php" class="row g-3">
                <div class="col-md-3">
                    <label for="status" class="form-label">Trạng thái</label>
                    <select class="form-select" id="status" name="status">
                        <option value="-1">Tất cả trạng thái</option>
                        <option value="1" <?php echo $filter_status === 1 ? 'selected' : ''; ?>>Hoạt động</option>
                        <option value="0" <?php echo $filter_status === 0 ? 'selected' : ''; ?>>Không hoạt động</option>
                        <option value="2" <?php echo $filter_status === 2 ? 'selected' : ''; ?>>Hết hạn</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="type" class="form-label">Loại khuyến mãi</label>
                    <select class="form-select" id="type" name="type">
                        <option value="">Tất cả loại</option>
                        <option value="0" <?php echo $filter_type === '0' ? 'selected' : ''; ?>>Phần trăm</option>
                        <option value="1" <?php echo $filter_type === '1' ? 'selected' : ''; ?>>Số tiền cố định</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="search" class="form-label">Tìm kiếm</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="search" name="search" placeholder="Mã code hoặc mô tả..." value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-outline-secondary" type="submit">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <a href="khuyen-mai.php" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-arrow-counterclockwise"></i> Đặt lại
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col" class="ps-3">ID</th>
                            <th scope="col">Mã code</th>
                            <th scope="col">Loại</th>
                            <th scope="col">Giá trị</th>
                            <th scope="col">Thời gian</th>
                            <th scope="col">Sử dụng</th>
                            <th scope="col">Áp dụng</th>
                            <th scope="col" class="text-center">Trạng thái</th>
                            <th scope="col" class="text-end pe-3">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($promo = $result->fetch_assoc()): ?>
                                <?php
                                // Tính trạng thái hiển thị của khuyến mãi
                                $now = new DateTime();
                                $start_date = new DateTime($promo['ngay_bat_dau']);
                                $end_date = new DateTime($promo['ngay_ket_thuc']);
                                $status_class = 'bg-secondary';
                                $status_text = 'Không hoạt động';
                                if ($promo['trang_thai'] == 1) {
                                    if ($now > $end_date) {
                                        $status_class = 'bg-danger';
                                        $status_text = 'Hết hạn';
                                    } elseif ($now < $start_date) {
                                        $status_class = 'bg-info';
                                        $status_text = 'Sắp diễn ra';
                                    } else {
                                        $status_class = 'bg-success';
                                        $status_text = 'Đang hoạt động';
                                    }
                                } else {
                                    $status_class = 'bg-secondary';
                                    $status_text = 'Không kích hoạt';
                                }
                                ?>
                                <tr>
                                    <td class="ps-3"><?php echo $promo['id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($promo['ma_khuyenmai']); ?></strong></td>
                                    <td>
                                        <?php if ($promo['loai_giamgia'] == 1): ?>
                                            <span class="badge bg-primary">Số tiền</span>
                                        <?php else: ?>
                                            <span class="badge bg-info">Phần trăm</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($promo['loai_giamgia'] == 0): ?>
                                            <span class="fw-bold"><?php echo number_format($promo['gia_tri'], 0); ?>%</span>
                                            <?php /* No max discount field in current schema
                                            if ($promo['gia_tri_giam_toi_da'] > 0): ?>
                                                <span class="text-muted small d-block">
                                                    Tối đa: <?php echo number_format($promo['gia_tri_giam_toi_da'], 0); ?>₫
                                                </span>
                                            <?php endif; */ ?>
                                        <?php else: ?>
                                            <span class="fw-bold"><?php echo number_format($promo['gia_tri'], 0); ?>₫</span>
                                        <?php endif; ?>
                                        <?php if (isset($promo['dieu_kien_toithieu']) && $promo['dieu_kien_toithieu'] > 0): ?>
                                            <span class="text-muted small d-block">
                                                Đơn tối thiểu: <?php echo number_format($promo['dieu_kien_toithieu'], 0); ?>₫
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column">
                                            <span class="small">
                                                <i class="bi bi-calendar-event me-1"></i>
                                                <?php echo date('d/m/Y', strtotime($promo['ngay_bat_dau'])); ?>
                                            </span>
                                            <span class="small">
                                                <i class="bi bi-calendar-x me-1"></i>
                                                <?php echo date('d/m/Y', strtotime($promo['ngay_ket_thuc'])); ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="progress" style="height: 6px;">
                                            <?php
                                            $usage_percent = $promo['so_luong'] > 0 ? 
                                                ($promo['da_su_dung'] / $promo['so_luong']) * 100 : 0;
                                            ?>
                                            <div class="progress-bar bg-<?php echo $usage_percent >= 80 ? 'danger' : 'primary'; ?>" 
                                                 role="progressbar" 
                                                 style="width: <?php echo $usage_percent; ?>%" 
                                                 aria-valuenow="<?php echo $usage_percent; ?>" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="100"></div>
                                        </div>
                                        <span class="small mt-1 d-block">
                                            <?php echo $promo['da_su_dung']; ?> / <?php echo $promo['so_luong']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        // These fields don't exist in current schema - provide fallback
                                        $ap_dung_sanpham = isset($promo['ap_dung_sanpham']) ? $promo['ap_dung_sanpham'] : false;
                                        $ap_dung_loai = isset($promo['ap_dung_loai']) ? $promo['ap_dung_loai'] : false;
                                        $so_san_pham = isset($promo['so_san_pham']) ? $promo['so_san_pham'] : 0;
                                        ?>
                                        <?php if ($ap_dung_sanpham): ?>
                                            <span class="badge bg-info"><?php echo $so_san_pham; ?> sản phẩm</span>
                                        <?php endif; ?>
                                        <?php if ($ap_dung_loai): ?>
                                            <?php
                                            // Check if khuyen_mai_loai table exists before querying
                                            $table_exists = $conn->query("SHOW TABLES LIKE 'khuyen_mai_loai'")->num_rows > 0;
                                            $cat_count = 0;
                                            if ($table_exists) {
                                                $cat_query = "SELECT COUNT(*) as cat_count FROM khuyen_mai_loai WHERE id_khuyen_mai = " . $promo['id'];
                                                $cat_result = $conn->query($cat_query);
                                                if ($cat_result) {
                                                    $cat_count = $cat_result->fetch_assoc()['cat_count'];
                                                }
                                            }
                                            ?>
                                            <span class="badge bg-warning text-dark"><?php echo $cat_count; ?> danh mục</span>
                                        <?php endif; ?>
                                        <?php if (!$ap_dung_sanpham && !$ap_dung_loai): ?>
                                            <span class="badge bg-success">Tất cả sản phẩm</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                    </td>
                                    <td class="text-end pe-3">
                                        <div class="btn-group btn-group-sm">
                                            <!-- Nút Xem -->
                                            <a href="xem-khuyen-mai.php?id=<?php echo $promo['id']; ?>" class="btn btn-outline-primary" title="Xem chi tiết">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <!-- Nút Sửa -->
                                            <?php if (hasPermission('promo_edit')): ?>
                                            <a href="chinh-sua-khuyen-mai.php?id=<?php echo $promo['id']; ?>" class="btn btn-outline-secondary" title="Chỉnh sửa">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <?php endif; ?>
                                            <!-- Nút Kích hoạt/Vô hiệu hóa -->
                                            <?php if (hasPermission('promo_edit')): ?>
                                            <a href="khuyen-mai.php?action=toggle_status&id=<?php echo $promo['id']; ?>" 
                                               class="btn btn-outline-<?php echo $promo['trang_thai'] ? 'warning' : 'success'; ?>"
                                               title="<?php echo $promo['trang_thai'] ? 'Vô hiệu hóa' : 'Kích hoạt'; ?>" 
                                               onclick="return confirm('Bạn có chắc muốn <?php echo $promo['trang_thai'] ? 'vô hiệu hóa' : 'kích hoạt'; ?> mã khuyến mãi này?');">
                                                <i class="bi bi-<?php echo $promo['trang_thai'] ? 'x-circle' : 'check-circle'; ?>"></i>
                                            </a>
                                            <?php endif; ?>
                                            <!-- Nút Xóa -->
                                            <?php if (hasPermission('promo_delete')): ?>
                                            <a href="khuyen-mai.php?action=delete&id=<?php echo $promo['id']; ?>" 
                                               class="btn btn-outline-danger" 
                                               title="Xóa"
                                               onclick="return confirm('Bạn có chắc muốn xóa mã khuyến mãi này? Hành động này không thể hoàn tác!');">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center py-4">
                                    <div class="text-muted">
                                        <i class="bi bi-exclamation-circle display-6 d-block mb-3"></i>
                                        <p>Không tìm thấy mã khuyến mãi nào.</p>
                                        <?php if (!empty($search) || $filter_status !== '' || $filter_type !== ''): ?>
                                            <p>Hãy thử thay đổi điều kiện tìm kiếm hoặc xóa bộ lọc.</p>
                                            <a href="khuyen-mai.php" class="btn btn-sm btn-outline-primary mt-2">
                                                <i class="bi bi-arrow-counterclockwise"></i> Đặt lại bộ lọc
                                            </a>
                                        <?php else: ?>
                                            <a href="them-khuyen-mai.php" class="btn btn-sm btn-primary mt-2">
                                                <i class="bi bi-plus-lg"></i> Tạo mã khuyến mãi mới
                                            </a>
                                        <?php endif; ?>
                                    </div>
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
    <nav aria-label="Page navigation" class="mt-4">
        <ul class="pagination justify-content-center">
            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $page-1; ?>&status=<?php echo $filter_status; ?>&type=<?php echo $filter_type; ?>&search=<?php echo urlencode($search); ?>" aria-label="Previous">
                    <span aria-hidden="true">&laquo;</span>
                </a>
            </li>
            <?php
            // Hiển thị tối đa 5 số trang
            $start_page = max(1, $page - 2);
            $end_page = min($start_page + 4, $total_pages);
            if ($end_page - $start_page < 4) {
                $start_page = max(1, $end_page - 4);
            }
            for ($i = $start_page; $i <= $end_page; $i++): ?>
                <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $filter_status; ?>&type=<?php echo $filter_type; ?>&search=<?php echo urlencode($search); ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
            <?php endfor; ?>
            <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                <a class="page-link" href="?page=<?php echo $page+1; ?>&status=<?php echo $filter_status; ?>&type=<?php echo $filter_type; ?>&search=<?php echo urlencode($search); ?>" aria-label="Next">
                    <span aria-hidden="true">&raquo;</span>
                </a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>
    <!-- Thống kê nhỏ -->
    <div class="d-flex justify-content-between align-items-center mt-4 text-muted small">
        <div>
            Hiển thị <?php echo min(($page - 1) * $limit + 1, $total_records); ?> - 
            <?php echo min($page * $limit, $total_records); ?> của <?php echo $total_records; ?> mã khuyến mãi
        </div>
        <div>
            <?php
            $active_count = $conn->query("SELECT COUNT(*) as count FROM khuyen_mai WHERE trang_thai = 1 AND ngay_bat_dau <= NOW() AND ngay_ket_thuc >= NOW()")->fetch_assoc()['count'];
            $upcoming_count = $conn->query("SELECT COUNT(*) as count FROM khuyen_mai WHERE trang_thai = 1 AND ngay_bat_dau > NOW()")->fetch_assoc()['count'];
            $expired_count = $conn->query("SELECT COUNT(*) as count FROM khuyen_mai WHERE (trang_thai = 1 AND ngay_ket_thuc < NOW()) OR trang_thai = 2")->fetch_assoc()['count'];
            ?>
            <span class="badge bg-success me-2"><?php echo $active_count; ?> đang hoạt động</span>
            <span class="badge bg-info me-2"><?php echo $upcoming_count; ?> sắp diễn ra</span>
            <span class="badge bg-danger"><?php echo $expired_count; ?> đã hết hạn</span>
        </div>
    </div>
</main>
<?php include('includes/footer.php'); ?>