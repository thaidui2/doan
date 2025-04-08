<?php
// Thiết lập tiêu đề trang
$page_title = "Quản Lý Đơn Hàng";

// Include header
include('includes/header.php');

// Xử lý phân trang
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Xử lý lọc trạng thái
$status = isset($_GET['status']) ? (int)$_GET['status'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

// Xây dựng câu query
$sql_conditions = [];
$params = [];
$param_types = "";

// Điều kiện chỉ lấy đơn hàng có sản phẩm của người bán này
$sql_conditions[] = "EXISTS (
    SELECT 1 
    FROM donhang_chitiet dc 
    JOIN sanpham sp ON dc.id_sanpham = sp.id_sanpham 
    WHERE dc.id_donhang = dh.id_donhang 
    AND sp.id_nguoiban = ?
)";
$params[] = $user_id;
$param_types .= "i";

if ($status > 0) {
    $sql_conditions[] = "dh.trangthai = ?";
    $params[] = $status;
    $param_types .= "i";
}

if (!empty($search)) {
    $sql_conditions[] = "(dh.id_donhang = ? OR dh.tennguoinhan LIKE ? OR dh.sodienthoai LIKE ?)";
    $params[] = $search; // Tìm theo ID đơn hàng
    $search_param = "%" . $search . "%";
    $params[] = $search_param; // Tìm theo tên người nhận
    $params[] = $search_param; // Tìm theo số điện thoại
    $param_types .= "iss";
}

if (!empty($date_from)) {
    $sql_conditions[] = "DATE(dh.ngaytao) >= ?";
    $params[] = $date_from;
    $param_types .= "s";
}

if (!empty($date_to)) {
    $sql_conditions[] = "DATE(dh.ngaytao) <= ?";
    $params[] = $date_to;
    $param_types .= "s";
}

// Xây dựng câu query đếm tổng số đơn hàng
$count_sql = "
    SELECT COUNT(DISTINCT dh.id_donhang) as total
    FROM donhang dh
    WHERE " . implode(" AND ", $sql_conditions);

$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($param_types, ...$params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_items = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_items / $limit);

// Xây dựng câu query lấy danh sách đơn hàng
$sql = "
    SELECT 
        dh.*,
        (
            SELECT GROUP_CONCAT(DISTINCT sp.id_sanpham)
            FROM donhang_chitiet dc 
            JOIN sanpham sp ON dc.id_sanpham = sp.id_sanpham
            WHERE dc.id_donhang = dh.id_donhang
            AND sp.id_nguoiban = ?
        ) as seller_product_ids,
        (
            SELECT COUNT(DISTINCT dc.id_sanpham)
            FROM donhang_chitiet dc 
            JOIN sanpham sp ON dc.id_sanpham = sp.id_sanpham
            WHERE dc.id_donhang = dh.id_donhang
            AND sp.id_nguoiban = ?
        ) as seller_product_count,
        (
            SELECT SUM(dc.thanh_tien)
            FROM donhang_chitiet dc 
            JOIN sanpham sp ON dc.id_sanpham = sp.id_sanpham
            WHERE dc.id_donhang = dh.id_donhang
            AND sp.id_nguoiban = ?
        ) as seller_subtotal
    FROM donhang dh
    WHERE " . implode(" AND ", $sql_conditions) . "
    ORDER BY dh.ngaytao DESC
    LIMIT ? OFFSET ?
";

// Thêm tham số cho người bán và phân trang
$params = array_merge([$user_id, $user_id, $user_id], $params, [$limit, $offset]);
$param_types .= "iiiii";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$orders = $stmt->get_result();

// Mảng trạng thái đơn hàng
$order_statuses = [
    1 => ['name' => 'Chờ xác nhận', 'badge' => 'warning text-dark'],
    2 => ['name' => 'Đang xử lý', 'badge' => 'info text-dark'],
    3 => ['name' => 'Đang giao hàng', 'badge' => 'primary'],
    4 => ['name' => 'Đã giao', 'badge' => 'success'],
    5 => ['name' => 'Đã hủy', 'badge' => 'danger'],
    6 => ['name' => 'Hoàn trả', 'badge' => 'secondary']
];
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Quản lý đơn hàng</h1>
</div>

<?php if (isset($_SESSION['success_message'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle me-2"></i> <?php echo $_SESSION['success_message']; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-triangle me-2"></i> <?php echo $_SESSION['error_message']; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php unset($_SESSION['error_message']); ?>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-body">
        <form action="" method="get" class="row g-3">
            <div class="col-md-3">
                <div class="input-group">
                    <input type="text" class="form-control" name="search" placeholder="Tìm theo mã đơn, tên, SĐT..." value="<?php echo htmlspecialchars($search); ?>">
                    <button class="btn btn-outline-secondary" type="submit">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </div>
            
            <div class="col-md-2">
                <select class="form-select" name="status">
                    <option value="0">Tất cả trạng thái</option>
                    <?php foreach ($order_statuses as $key => $value): ?>
                    <option value="<?php echo $key; ?>" <?php echo $status == $key ? 'selected' : ''; ?>>
                        <?php echo $value['name']; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <input type="date" class="form-control" name="date_from" placeholder="Từ ngày" value="<?php echo $date_from; ?>">
            </div>
            
            <div class="col-md-2">
                <input type="date" class="form-control" name="date_to" placeholder="Đến ngày" value="<?php echo $date_to; ?>">
            </div>
            
            <div class="col-md-3">
                <div class="d-grid gap-2 d-md-flex">
                    <button class="btn btn-primary me-md-2" type="submit">
                        <i class="bi bi-filter"></i> Lọc
                    </button>
                    <a href="don-hang.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-repeat"></i> Đặt lại
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if ($orders->num_rows > 0): ?>
    <div class="card">
        <div class="card-header bg-white">
            <span>Hiển thị <?php echo $orders->num_rows; ?> / <?php echo $total_items; ?> đơn hàng</span>
        </div>
        
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Mã đơn</th>
                        <th>Người nhận</th>
                        <th>Ngày đặt</th>
                        <th>Sản phẩm của bạn</th>
                        <th>Tổng tiền đơn</th>
                        <th>Tiền về bạn</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($order = $orders->fetch_assoc()): ?>
                        <tr>
                            <td>#<?php echo $order['id_donhang']; ?></td>
                            <td>
                                <div><?php echo htmlspecialchars($order['tennguoinhan']); ?></div>
                                <div class="small text-muted"><?php echo htmlspecialchars($order['sodienthoai']); ?></div>
                            </td>
                            <td><?php echo date('d/m/Y H:i', strtotime($order['ngaytao'])); ?></td>
                            <td>
                                <span class="badge bg-secondary"><?php echo $order['seller_product_count']; ?> sản phẩm</span>
                            </td>
                            <td><?php echo number_format($order['tongtien'], 0, ',', '.'); ?>₫</td>
                            <td class="fw-bold text-success"><?php echo number_format($order['seller_subtotal'], 0, ',', '.'); ?>₫</td>
                            <td>
                                <span class="badge bg-<?php echo $order_statuses[$order['trangthai']]['badge']; ?>">
                                    <?php echo $order_statuses[$order['trangthai']]['name']; ?>
                                </span>
                            </td>
                            <td>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        Tác vụ
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li>
                                            <a class="dropdown-item" href="don-hang-chi-tiet.php?id=<?php echo $order['id_donhang']; ?>">
                                                <i class="bi bi-eye me-2"></i> Xem chi tiết
                                            </a>
                                        </li>
                                        
                                        <?php if ($order['trangthai'] == 1): ?>
                                        <li>
                                            <form action="xu-ly-don-hang.php" method="post" class="d-inline">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id_donhang']; ?>">
                                                <input type="hidden" name="new_status" value="2">
                                                <button type="submit" class="dropdown-item">
                                                    <i class="bi bi-check-circle me-2"></i> Xác nhận đơn hàng
                                                </button>
                                            </form>
                                        </li>
                                        <li>
                                            <form action="xu-ly-don-hang.php" method="post" class="d-inline">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id_donhang']; ?>">
                                                <input type="hidden" name="new_status" value="5">
                                                <input type="hidden" name="note" value="Đơn hàng bị hủy bởi người bán">
                                                <button type="submit" class="dropdown-item text-danger" onclick="return confirm('Bạn có chắc muốn hủy đơn hàng này?');">
                                                    <i class="bi bi-x-circle me-2"></i> Hủy đơn hàng
                                                </button>
                                            </form>
                                        </li>
                                        <?php endif; ?>
                                        
                                        <?php if ($order['trangthai'] == 2): ?>
                                        <li>
                                            <form action="xu-ly-don-hang.php" method="post" class="d-inline">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id_donhang']; ?>">
                                                <input type="hidden" name="new_status" value="3">
                                                <button type="submit" class="dropdown-item">
                                                    <i class="bi bi-truck me-2"></i> Đánh dấu đã giao vận chuyển
                                                </button>
                                            </form>
                                        </li>
                                        <?php endif; ?>
                                        
                                        <?php if ($order['trangthai'] == 3): ?>
                                        <li>
                                            <form action="xu-ly-don-hang.php" method="post" class="d-inline">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id_donhang']; ?>">
                                                <input type="hidden" name="new_status" value="4">
                                                <button type="submit" class="dropdown-item">
                                                    <i class="bi bi-check2-all me-2"></i> Đánh dấu đã giao hàng
                                                </button>
                                            </form>
                                        </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($total_pages > 1): ?>
            <div class="card-footer bg-white">
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center mb-0">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if ($i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                                <li class="page-item disabled">
                                    <span class="page-link">...</span>
                                </li>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
        
    </div>
<?php else: ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle me-2"></i> Không tìm thấy đơn hàng nào phù hợp với điều kiện tìm kiếm.
    </div>
<?php endif; ?>

<?php include('includes/footer.php'); ?>
