<?php
// Thêm đoạn này vào đầu file, trước mọi output
ob_start();

// Thêm dòng này để debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Thiết lập tiêu đề trang
$page_title = 'Quản lý khuyến mãi';

// Include header (kiểm tra đăng nhập)
include('includes/header.php');

// Include database connection
include('../config/config.php');

// Debug: Hiển thị thông tin vai trò và quyền của admin đang đăng nhập
$admin_id = $_SESSION['admin_id'];
$roles_query = $conn->prepare("
    SELECT r.* FROM roles r 
    JOIN admin_roles ar ON r.id_role = ar.id_role 
    WHERE ar.id_admin = ?
");
$roles_query->bind_param("i", $admin_id);
$roles_query->execute();
$roles_result = $roles_query->get_result();
$admin_roles = [];
while ($role = $roles_result->fetch_assoc()) {
    $admin_roles[] = $role;
}

$permissions_query = $conn->prepare("
    SELECT p.* FROM permissions p
    JOIN role_permissions rp ON p.id_permission = rp.id_permission
    JOIN admin_roles ar ON rp.id_role = ar.id_role
    WHERE ar.id_admin = ? AND p.nhom_permission = 'promos'
");
$permissions_query->bind_param("i", $admin_id);
$permissions_query->execute();
$permissions_result = $permissions_query->get_result();
$admin_permissions = [];
while ($perm = $permissions_result->fetch_assoc()) {
    $admin_permissions[] = $perm;
}

// Lưu debug info vào session để hiển thị
$_SESSION['debug_info'] = [
    'roles' => $admin_roles,
    'permissions' => $admin_permissions,
    'can_add' => hasPermission('promo_add'),
    'can_edit' => hasPermission('promo_edit'),
    'can_delete' => hasPermission('promo_delete'),
    'can_view' => hasPermission('promo_view')
];

// Xử lý phân trang
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10; // Số mục trên mỗi trang
$offset = ($page - 1) * $limit;

// Xử lý bộ lọc
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_type = isset($_GET['type']) ? $_GET['type'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Xây dựng câu lệnh WHERE cho các bộ lọc
$where_clauses = ['1=1']; // Luôn đúng để bắt đầu

if ($filter_status !== '') {
    $where_clauses[] = "trang_thai = " . (int)$filter_status;
}

if ($filter_type !== '') {
    $where_clauses[] = "loai_giam_gia = " . (int)$filter_type;
}

if (!empty($search)) {
    $search = $conn->real_escape_string($search);
    $where_clauses[] = "(ma_code LIKE '%$search%' OR mo_ta LIKE '%$search%')";
}

$where_clause = implode(' AND ', $where_clauses);

// Đếm tổng số bản ghi để phân trang
$count_query = "SELECT COUNT(*) as total FROM khuyen_mai WHERE $where_clause";
$count_result = $conn->query($count_query);
$total_records = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// Lấy dữ liệu khuyến mãi
$query = "
    SELECT km.*, u.tenuser AS ten_nguoiban, 
           COUNT(kms.id_sanpham) AS so_san_pham
    FROM khuyen_mai km
    LEFT JOIN users u ON km.id_nguoiban = u.id_user
    LEFT JOIN khuyen_mai_sanpham kms ON km.id = kms.id_khuyen_mai
    WHERE $where_clause
    GROUP BY km.id
    ORDER BY km.ngay_bat_dau DESC
    LIMIT $offset, $limit
";
$result = $conn->query($query);

// Xử lý kích hoạt/vô hiệu hóa khuyến mãi
if (isset($_GET['action']) && $_GET['action'] === 'toggle_status' && isset($_GET['id'])) {
    $promo_id = (int)$_GET['id'];
    
    // Kiểm tra quyền
    if (!hasPermission('promo_edit')) {
        $_SESSION['error_message'] = "Bạn không có quyền thực hiện hành động này!";
        echo '<script>window.location.href = "khuyen-mai.php";</script>';
        exit();
    }
    
    // Lấy trạng thái hiện tại
    $status_query = $conn->prepare("SELECT trang_thai FROM khuyen_mai WHERE id = ?");
    $status_query->bind_param("i", $promo_id);
    $status_query->execute();
    $result = $status_query->get_result();
    
    if ($result->num_rows > 0) {
        $promo = $result->fetch_assoc();
        $new_status = $promo['trang_thai'] == 1 ? 0 : 1;
        
        // Cập nhật trạng thái
        $update_query = $conn->prepare("UPDATE khuyen_mai SET trang_thai = ?, ngay_capnhat = NOW() WHERE id = ?");
        $update_query->bind_param("ii", $new_status, $promo_id);
        
        if ($update_query->execute()) {
            $_SESSION['success_message'] = "Đã " . ($new_status == 1 ? "kích hoạt" : "vô hiệu hóa") . " mã giảm giá thành công!";
        } else {
            $_SESSION['error_message'] = "Có lỗi xảy ra khi cập nhật trạng thái!";
        }
    } else {
        $_SESSION['error_message'] = "Không tìm thấy mã giảm giá!";
    }
    
    // Thay hàm header() bằng JavaScript redirect
    echo '<script>window.location.href = "khuyen-mai.php";</script>';
    exit();
}

// Xử lý xóa khuyến mãi
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $promo_id = (int)$_GET['id'];
    
    // Kiểm tra quyền
    if (!hasPermission('promo_delete')) {
        $_SESSION['error_message'] = "Bạn không có quyền thực hiện hành động này!";
        header('Location: khuyen-mai.php');
        exit();
    }
    
    // Bắt đầu transaction
    $conn->begin_transaction();
    
    try {
        // Xóa các liên kết với sản phẩm
        $delete_products = $conn->prepare("DELETE FROM khuyen_mai_sanpham WHERE id_khuyen_mai = ?");
        $delete_products->bind_param("i", $promo_id);
        $delete_products->execute();
        
        // Xóa các liên kết với loại sản phẩm
        $delete_categories = $conn->prepare("DELETE FROM khuyen_mai_loai WHERE id_khuyen_mai = ?");
        $delete_categories->bind_param("i", $promo_id);
        $delete_categories->execute();
        
        // Xóa mã khuyến mãi
        $delete_promo = $conn->prepare("DELETE FROM khuyen_mai WHERE id = ?");
        $delete_promo->bind_param("i", $promo_id);
        $delete_promo->execute();
        
        // Commit transaction
        $conn->commit();
        
        // Ghi log hoạt động
        logAdminActivity($conn, $_SESSION['admin_id'], 'delete', 'promo', $promo_id, 'Đã xóa mã giảm giá #' . $promo_id);
        
        $_SESSION['success_message'] = "Đã xóa mã giảm giá thành công!";
    } catch (Exception $e) {
        // Rollback nếu có lỗi
        $conn->rollback();
        $_SESSION['error_message'] = "Có lỗi xảy ra: " . $e->getMessage();
    }
    
    header('Location: khuyen-mai.php');
    exit();
}
?>

<!-- Include sidebar -->
<?php include('includes/sidebar.php'); ?>

<!-- Main content -->
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="bi bi-ticket-perforated me-2"></i>Quản lý khuyến mãi</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <?php if (hasPermission('promo_add')): ?>
            <a href="them-khuyen-mai.php" class="btn btn-sm btn-primary">
                <i class="bi bi-plus-lg"></i> Thêm mã giảm giá mới
            </a>
            <?php endif; ?>
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
                        <option value="">Tất cả trạng thái</option>
                        <option value="1" <?php echo $filter_status === '1' ? 'selected' : ''; ?>>Hoạt động</option>
                        <option value="0" <?php echo $filter_status === '0' ? 'selected' : ''; ?>>Không hoạt động</option>
                        <option value="2" <?php echo $filter_status === '2' ? 'selected' : ''; ?>>Hết hạn</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="type" class="form-label">Loại khuyến mãi</label>
                    <select class="form-select" id="type" name="type">
                        <option value="">Tất cả loại</option>
                        <option value="1" <?php echo $filter_type === '1' ? 'selected' : ''; ?>>Phần trăm</option>
                        <option value="2" <?php echo $filter_type === '2' ? 'selected' : ''; ?>>Số tiền cố định</option>
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
                                    <td><strong><?php echo htmlspecialchars($promo['ma_code']); ?></strong></td>
                                    <td>
                                        <?php if ($promo['loai_giam_gia'] == 1): ?>
                                            <span class="badge bg-primary">Phần trăm</span>
                                        <?php else: ?>
                                            <span class="badge bg-info">Số tiền</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($promo['loai_giam_gia'] == 1): ?>
                                            <span class="fw-bold"><?php echo number_format($promo['gia_tri'], 0); ?>%</span>
                                            <?php if ($promo['gia_tri_giam_toi_da'] > 0): ?>
                                                <span class="text-muted small d-block">
                                                    Tối đa: <?php echo number_format($promo['gia_tri_giam_toi_da'], 0); ?>₫
                                                </span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="fw-bold"><?php echo number_format($promo['gia_tri'], 0); ?>₫</span>
                                        <?php endif; ?>
                                        
                                        <?php if ($promo['gia_tri_don_toi_thieu'] > 0): ?>
                                            <span class="text-muted small d-block">
                                                Đơn tối thiểu: <?php echo number_format($promo['gia_tri_don_toi_thieu'], 0); ?>₫
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
                                                ($promo['so_luong_da_dung'] / $promo['so_luong']) * 100 : 0;
                                            ?>
                                            <div class="progress-bar bg-<?php echo $usage_percent >= 80 ? 'danger' : 'primary'; ?>" 
                                                 role="progressbar" 
                                                 style="width: <?php echo $usage_percent; ?>%" 
                                                 aria-valuenow="<?php echo $usage_percent; ?>" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="100"></div>
                                        </div>
                                        <span class="small mt-1 d-block">
                                            <?php echo $promo['so_luong_da_dung']; ?> / <?php echo $promo['so_luong']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($promo['ap_dung_sanpham']): ?>
                                            <span class="badge bg-info"><?php echo $promo['so_san_pham']; ?> sản phẩm</span>
                                        <?php endif; ?>
                                        
                                        <?php if ($promo['ap_dung_loai']): ?>
                                            <?php
                                            $cat_query = "SELECT COUNT(*) as cat_count FROM khuyen_mai_loai WHERE id_khuyen_mai = " . $promo['id'];
                                            $cat_result = $conn->query($cat_query);
                                            $cat_count = $cat_result->fetch_assoc()['cat_count'];
                                            ?>
                                            <span class="badge bg-warning text-dark"><?php echo $cat_count; ?> danh mục</span>
                                        <?php endif; ?>
                                        
                                        <?php if (!$promo['ap_dung_sanpham'] && !$promo['ap_dung_loai']): ?>
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

<?php
// Include footer
include('includes/footer.php');

// Thêm dòng này ở cuối file, sau tất cả các output
ob_end_flush();
?>