<?php
// Kiểm tra đăng nhập và quyền admin
session_start();
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_loai']) || $_SESSION['admin_loai'] < 1) {
    header('Location: login.php');
    exit();
}

// Kết nối database
require_once '../config/config.php';

// Thiết lập tiêu đề trang và CSS động
$page_title = 'Quản lý đơn hàng';
$current_page = 'orders';
$page_css = ['css/orders.css']; // CSS riêng cho trang này
$page_js = ['js/orders.js']; // Javascript riêng cho trang này

// Xử lý cập nhật trạng thái đơn hàng
if (isset($_POST['update_status']) && isset($_POST['order_id']) && isset($_POST['new_status'])) {
    $order_id = intval($_POST['order_id']);
    $new_status = intval($_POST['new_status']);
    $note = trim($_POST['note'] ?? '');

    // Kiểm tra đơn hàng tồn tại
    $check_order_sql = "SELECT trang_thai_don_hang FROM donhang WHERE id = ?";
    $check_order_stmt = $conn->prepare($check_order_sql);
    $check_order_stmt->bind_param('i', $order_id);
    $check_order_stmt->execute();
    $check_result = $check_order_stmt->get_result();

    if ($check_result->num_rows > 0) {
        $current_status = $check_result->fetch_assoc()['trang_thai_don_hang'];

        // Cập nhật trạng thái
        $update_sql = "UPDATE donhang SET trang_thai_don_hang = ?, ngay_capnhat = NOW() WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param('ii', $new_status, $order_id);

        if ($update_stmt->execute()) {
            // Xác định tên trạng thái
            $status_name = getOrderStatusName($new_status);

            // Ghi vào lịch sử đơn hàng
            $history_sql = "INSERT INTO donhang_lichsu (id_donhang, hanh_dong, nguoi_thuchien, ghi_chu) 
                          VALUES (?, ?, ?, ?)";
            $history_stmt = $conn->prepare($history_sql);
            $action = "Cập nhật trạng thái";
            $admin_name = $_SESSION['admin_name'] ?? 'Quản trị viên';
            $history_note = "Thay đổi trạng thái từ \"" . getOrderStatusName($current_status) .
                "\" sang \"" . $status_name . "\"" . (!empty($note) ? ". Ghi chú: $note" : "");
            $history_stmt->bind_param('isss', $order_id, $action, $admin_name, $history_note);
            $history_stmt->execute();

            // Ghi log hoạt động
            $log_sql = "INSERT INTO nhat_ky (id_user, hanh_dong, doi_tuong_loai, doi_tuong_id, chi_tiet, ip_address) 
                        VALUES (?, 'update_status', 'order', ?, ?, ?)";
            $log_stmt = $conn->prepare($log_sql);
            $admin_id = $_SESSION['admin_id'];
            $log_detail = "Cập nhật trạng thái đơn hàng #$order_id thành: $status_name";
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $log_stmt->bind_param('iiss', $admin_id, $order_id, $log_detail, $ip);
            $log_stmt->execute();

            header("Location: orders.php?success=Cập nhật trạng thái đơn hàng thành công");
            exit();
        } else {
            header("Location: orders.php?error=Không thể cập nhật trạng thái đơn hàng");
            exit();
        }
    } else {
        header("Location: orders.php?error=Không tìm thấy đơn hàng");
        exit();
    }
}

// Thiết lập tham số tìm kiếm và lọc
$search = trim($_GET['search'] ?? '');
$status = isset($_GET['status']) ? $_GET['status'] : '';
$customer = isset($_GET['customer']) ? intval($_GET['customer']) : '';
$payment_method = isset($_GET['payment_method']) ? $_GET['payment_method'] : '';
$payment_status = isset($_GET['payment_status']) ? $_GET['payment_status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$sort = $_GET['sort'] ?? 'newest';

// Thiết lập phân trang
$items_per_page = 10; // Số đơn hàng mỗi trang
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $items_per_page;

// Xây dựng câu truy vấn cơ bản
$query = "SELECT d.*, u.ten as customer_name, u.email as customer_email, u.sodienthoai as customer_phone 
          FROM donhang d 
          LEFT JOIN users u ON d.id_user = u.id 
          WHERE 1=1";

$count_query = "SELECT COUNT(*) as total 
                FROM donhang d 
                LEFT JOIN users u ON d.id_user = u.id 
                WHERE 1=1";

$params = [];
$param_types = "";

// Thêm điều kiện tìm kiếm
if (!empty($search)) {
    $search_conditions = " AND (
        d.ma_donhang LIKE ? OR 
        d.ho_ten LIKE ? OR 
        d.sodienthoai LIKE ? OR 
        d.email LIKE ? OR
        COALESCE(u.ten, '') LIKE ? OR 
        COALESCE(u.sodienthoai, '') LIKE ? OR 
        COALESCE(u.email, '') LIKE ?
    )";

    $query .= $search_conditions;
    $count_query .= $search_conditions;

    $search_param = "%{$search}%";
    $params = array_merge($params, array_fill(0, 7, $search_param));
    $param_types .= "sssssss";
}

// Thêm debug để kiểm tra
if (!empty($search)) {
    error_log("Search Query: " . $query);
    error_log("Search Params: " . print_r($params, true));
}

// Lọc theo trạng thái đơn hàng
if ($status !== '') {
    $query .= " AND d.trang_thai_don_hang = ?";
    $count_query .= " AND d.trang_thai_don_hang = ?";
    $params[] = $status;
    $param_types .= "i";
}

// Lọc theo khách hàng
if ($customer === 'guest') {
    $query .= " AND d.id_user IS NULL";
    $count_query .= " AND d.id_user IS NULL";
} elseif ($customer !== '') {
    $query .= " AND d.id_user = ?";
    $count_query .= " AND d.id_user = ?";
    $params[] = $customer;
    $param_types .= "i";
}

// Lọc theo phương thức thanh toán
if ($payment_method !== '') {
    $query .= " AND d.phuong_thuc_thanh_toan = ?";
    $count_query .= " AND d.phuong_thuc_thanh_toan = ?";
    $params[] = $payment_method;
    $param_types .= "s";
}

// Lọc theo trạng thái thanh toán
if ($payment_status !== '') {
    $query .= " AND d.trang_thai_thanh_toan = ?";
    $count_query .= " AND d.trang_thai_thanh_toan = ?";
    $params[] = $payment_status;
    $param_types .= "i";
}

// Lọc theo ngày
if (!empty($date_from)) {
    $query .= " AND DATE(d.ngay_dat) >= ?";
    $count_query .= " AND DATE(d.ngay_dat) >= ?";
    $params[] = $date_from;
    $param_types .= "s";
}

if (!empty($date_to)) {
    $query .= " AND DATE(d.ngay_dat) <= ?";
    $count_query .= " AND DATE(d.ngay_dat) <= ?";
    $params[] = $date_to;
    $param_types .= "s";
}

// Sắp xếp
switch ($sort) {
    case 'oldest':
        $query .= " ORDER BY d.ngay_dat ASC";
        break;
    case 'highest':
        $query .= " ORDER BY d.thanh_tien DESC";
        break;
    case 'lowest':
        $query .= " ORDER BY d.thanh_tien ASC";
        break;
    default: // newest
        $query .= " ORDER BY d.ngay_dat DESC";
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

// Thêm debug
if (!$stmt->execute()) {
    echo "Lỗi SQL: " . $stmt->error;
}

$orders = $stmt->get_result();
// Hiển thị debug nếu không tìm thấy kết quả
if ($orders->num_rows === 0 && !empty($search)) {
    echo '<div class="alert alert-info">
            Từ khóa tìm kiếm: ' . htmlspecialchars($search) . '<br>
            Không tìm thấy kết quả nào phù hợp.
          </div>';
}

// Lấy danh sách khách hàng cho dropdown lọc
$customers_sql = "SELECT id, ten, email FROM users WHERE loai_user = 0 ORDER BY ten";
$customers_result = $conn->query($customers_sql);

// Hàm hiển thị trạng thái đơn hàng
function getOrderStatusName($status)
{
    switch ($status) {
        case 1:
            return "Chờ xác nhận";
        case 2:
            return "Đã xác nhận";
        case 3:
            return "Đang giao hàng";
        case 4:
            return "Đã giao";
        case 5:
            return "Đã hủy";
        default:
            return "Không xác định";
    }
}

// Hàm hiển thị trạng thái thanh toán
function getPaymentStatusName($status)
{
    return $status ? "Đã thanh toán" : "Chưa thanh toán";
}

// Hàm hiển thị phương thức thanh toán
function getPaymentMethodName($method)
{
    switch ($method) {
        case 'cod':
            return "Tiền mặt khi nhận hàng";
        case 'vnpay':
            return "VNPAY";
        case 'bank_transfer':
            return "Chuyển khoản ngân hàng";
        default:
            return "COD";
    }
}

// Format tiền VNĐ
function formatVND($amount)
{
    return number_format($amount, 0, ',', '.') . ' ₫';
}

// Include header and sidebar
include 'includes/header.php';
include 'includes/sidebar.php';

// Lấy thống kê đơn hàng theo trạng thái
$stats_sql = "SELECT 
            SUM(CASE WHEN trang_thai_don_hang = 1 THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN trang_thai_don_hang = 2 THEN 1 ELSE 0 END) as confirmed,
            SUM(CASE WHEN trang_thai_don_hang = 3 THEN 1 ELSE 0 END) as shipping,
            SUM(CASE WHEN trang_thai_don_hang = 4 THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN trang_thai_don_hang = 5 THEN 1 ELSE 0 END) as canceled,
            SUM(CASE WHEN trang_thai_thanh_toan = 1 THEN 1 ELSE 0 END) as paid,
            COUNT(*) as total,
            SUM(thanh_tien) as revenue
            FROM donhang";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();
?>

<!-- Main Content -->
<div class="col-md-10 col-lg-10 ms-auto">
    <div class="container-fluid px-4 py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0 text-gray-800">Quản lý đơn hàng</h1>
            <div>
                <a href="order_export.php" class="btn btn-outline-success">
                    <i class="fas fa-file-excel me-1"></i> Xuất Excel
                </a>
            </div>
        </div>

        <!-- Thống kê nhanh -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-primary h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Chờ xác nhận</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo $stats['pending'] ?? 0; ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-clock fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-info h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                    Đang giao hàng</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo $stats['shipping'] ?? 0; ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-truck fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-success h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    Hoàn thành</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo $stats['completed'] ?? 0; ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-warning h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                    Doanh thu</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo formatVND($stats['revenue'] ?? 0); ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
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
                <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse"
                    data-bs-target="#filtersCollapse">
                    <i class="fas fa-filter me-1"></i> Lọc nâng cao
                </button>
            </div>
            <div class="card-body">
                <form method="GET" action="">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="search" class="form-label">Tìm kiếm</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="search" name="search"
                                    placeholder="Mã đơn hàng, tên khách hàng, email, số điện thoại..."
                                    value="<?php echo htmlspecialchars($search); ?>">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search"></i> Tìm
                                </button>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="status" class="form-label">Trạng thái đơn hàng</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">Tất cả trạng thái</option>
                                <option value="1" <?php echo ($status === '1') ? 'selected' : ''; ?>>Chờ xác nhận</option>
                                <option value="2" <?php echo ($status === '2') ? 'selected' : ''; ?>>Đã xác nhận</option>
                                <option value="3" <?php echo ($status === '3') ? 'selected' : ''; ?>>Đang giao hàng
                                </option>
                                <option value="4" <?php echo ($status === '4') ? 'selected' : ''; ?>>Đã giao</option>
                                <option value="5" <?php echo ($status === '5') ? 'selected' : ''; ?>>Đã hủy</option>
                            </select>
                        </div>
                    </div>

                    <!-- Lọc nâng cao -->
                    <div class="collapse <?php echo ($customer !== '' || $payment_method !== '' || $payment_status !== '' || !empty($date_from) || !empty($date_to) || $sort !== 'newest') ? 'show' : ''; ?>"
                        id="filtersCollapse">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label for="customer" class="form-label">Khách hàng</label>
                                <select class="form-select" id="customer" name="customer">
                                    <option value="">Tất cả khách hàng</option>
                                    <option value="guest" <?php echo ($customer === 'guest') ? 'selected' : ''; ?>>Khách
                                        không tài khoản</option>
                                    <?php while ($cust = $customers_result->fetch_assoc()): ?>
                                        <option value="<?php echo $cust['id']; ?>" <?php echo ($customer == $cust['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cust['ten']) . ' (' . htmlspecialchars($cust['email']) . ')'; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="payment_method" class="form-label">Phương thức thanh toán</label>
                                <select class="form-select" id="payment_method" name="payment_method">
                                    <option value="">Tất cả phương thức</option>
                                    <option value="cod" <?php echo ($payment_method === 'cod') ? 'selected' : ''; ?>>Tiền
                                        mặt khi nhận hàng</option>
                                    <option value="vnpay" <?php echo ($payment_method === 'vnpay') ? 'selected' : ''; ?>>
                                        VNPAY</option>
                                    <option value="bank_transfer" <?php echo ($payment_method === 'bank_transfer') ? 'selected' : ''; ?>>Chuyển khoản ngân hàng</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="payment_status" class="form-label">Trạng thái thanh toán</label>
                                <select class="form-select" id="payment_status" name="payment_status">
                                    <option value="">Tất cả</option>
                                    <option value="1" <?php echo ($payment_status === '1') ? 'selected' : ''; ?>>Đã thanh
                                        toán</option>
                                    <option value="0" <?php echo ($payment_status === '0') ? 'selected' : ''; ?>>Chưa
                                        thanh toán</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="sort" class="form-label">Sắp xếp theo</label>
                                <select class="form-select" id="sort" name="sort">
                                    <option value="newest" <?php echo ($sort === 'newest') ? 'selected' : ''; ?>>Mới nhất
                                    </option>
                                    <option value="oldest" <?php echo ($sort === 'oldest') ? 'selected' : ''; ?>>Cũ nhất
                                    </option>
                                    <option value="highest" <?php echo ($sort === 'highest') ? 'selected' : ''; ?>>Giá trị
                                        cao nhất</option>
                                    <option value="lowest" <?php echo ($sort === 'lowest') ? 'selected' : ''; ?>>Giá trị
                                        thấp nhất</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label for="date_from" class="form-label">Từ ngày</label>
                                <input type="date" class="form-control datepicker" id="date_from" name="date_from"
                                    value="<?php echo htmlspecialchars($date_from); ?>">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="date_to" class="form-label">Đến ngày</label>
                                <input type="date" class="form-control datepicker" id="date_to" name="date_to"
                                    value="<?php echo htmlspecialchars($date_to); ?>">
                            </div>
                            <div class="col-md-6 mb-3 d-flex align-items-end">
                                <a href="orders.php" class="btn btn-outline-secondary me-2 flex-grow-1">
                                    <i class="fas fa-redo me-1"></i> Đặt lại bộ lọc
                                </a>
                                <button type="submit" class="btn btn-primary flex-grow-1">
                                    <i class="fas fa-search me-1"></i> Áp dụng lọc
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Danh sách đơn hàng -->
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">
                    Danh sách đơn hàng
                    <span class="badge bg-secondary ms-1"><?php echo $total_items; ?> đơn hàng</span>
                </h6>
                <?php if (!empty($search) || $status !== '' || $customer !== '' || $payment_method !== '' || $payment_status !== '' || !empty($date_from) || !empty($date_to)): ?>
                    <span class="badge bg-info">Đã lọc</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Mã đơn hàng</th>
                                <th>Ngày đặt</th>
                                <th>Khách hàng</th>
                                <th>Thông tin liên hệ</th>
                                <th>Tổng tiền</th>
                                <th>Thanh toán</th>
                                <th>Trạng thái</th>
                                <th width="150">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($orders && $orders->num_rows > 0): ?>
                                <?php while ($order = $orders->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($order['ma_donhang']); ?></strong>
                                        </td>
                                        <td>
                                            <?php echo date('d/m/Y H:i', strtotime($order['ngay_dat'])); ?>
                                        </td>
                                        <td>
                                            <?php if ($order['id_user']): ?>
                                                <a href="customer_detail.php?id=<?php echo $order['id_user']; ?>"
                                                    class="customer-link">
                                                    <?php echo htmlspecialchars($order['customer_name'] ?? $order['ho_ten']); ?>
                                                </a>
                                            <?php else: ?>
                                                <?php echo htmlspecialchars($order['ho_ten']); ?>
                                                <span class="badge bg-secondary">Khách</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div><i class="fas fa-phone fa-sm text-muted me-1"></i>
                                                <?php echo htmlspecialchars($order['sodienthoai']); ?></div>
                                            <?php if (!empty($order['email'])): ?>
                                                <div><i class="fas fa-envelope fa-sm text-muted me-1"></i>
                                                    <?php echo htmlspecialchars($order['email']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <?php echo formatVND($order['thanh_tien']); ?>
                                        </td>
                                        <td>
                                            <div>
                                                <?php
                                                switch ($order['phuong_thuc_thanh_toan']) {
                                                    case 'vnpay':
                                                        echo '<span class="badge bg-primary">VNPAY</span>';
                                                        break;
                                                    case 'bank_transfer':
                                                        echo '<span class="badge bg-info">Chuyển khoản</span>';
                                                        break;
                                                    default:
                                                        echo '<span class="badge bg-secondary">COD</span>';
                                                }
                                                ?>
                                            </div>
                                            <div class="small">
                                                <?php if ($order['trang_thai_thanh_toan']): ?>
                                                    <span class="text-success"><i class="fas fa-check-circle"></i> Đã thanh
                                                        toán</span>
                                                <?php else: ?>
                                                    <span class="text-warning"><i class="fas fa-clock"></i> Chưa thanh toán</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php
                                            switch ($order['trang_thai_don_hang']) {
                                                case 1:
                                                    echo '<span class="badge bg-info">Chờ xác nhận</span>';
                                                    break;
                                                case 2:
                                                    echo '<span class="badge bg-primary">Đã xác nhận</span>';
                                                    break;
                                                case 3:
                                                    echo '<span class="badge bg-warning text-dark">Đang giao hàng</span>';
                                                    break;
                                                case 4:
                                                    echo '<span class="badge bg-success">Đã giao</span>';
                                                    break;
                                                case 5:
                                                    echo '<span class="badge bg-danger">Đã hủy</span>';
                                                    break;
                                                default:
                                                    echo '<span class="badge bg-secondary">Không xác định</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <a href="order_detail.php?id=<?php echo $order['id']; ?>"
                                                    class="btn btn-sm btn-info flex-grow-1" title="Xem chi tiết">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <button type="button"
                                                    class="btn btn-sm btn-primary flex-grow-1 btn-update-status"
                                                    data-bs-toggle="modal" data-bs-target="#updateStatusModal"
                                                    data-order-id="<?php echo $order['id']; ?>"
                                                    data-order-code="<?php echo htmlspecialchars($order['ma_donhang']); ?>"
                                                    data-current-status="<?php echo $order['trang_thai_don_hang']; ?>"
                                                    title="Cập nhật trạng thái">
                                                    <i class="fas fa-sync-alt"></i>
                                                </button>
                                                <a href="order_print.php?id=<?php echo $order['id']; ?>"
                                                    class="btn btn-sm btn-outline-dark flex-grow-1" title="In đơn hàng"
                                                    target="_blank">
                                                    <i class="fas fa-print"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <div class="text-muted mb-3">
                                            <i class="fas fa-shopping-cart fa-3x"></i>
                                        </div>
                                        <h5>Không tìm thấy đơn hàng nào</h5>
                                        <p>Thử thay đổi tiêu chí tìm kiếm hoặc bộ lọc</p>
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
                        trong <?php echo $total_items; ?> đơn hàng
                    </div>
                    <nav>
                        <ul class="pagination">
                            <?php
                            $query_params = http_build_query(array_filter([
                                'search' => $search,
                                'status' => $status,
                                'customer' => $customer,
                                'payment_method' => $payment_method,
                                'payment_status' => $payment_status,
                                'date_from' => $date_from,
                                'date_to' => $date_to,
                                'sort' => $sort
                            ]));
                            $query_string = !empty($query_params) ? '&' . $query_params : '';
                            ?>

                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=1<?php echo $query_string; ?>">
                                        <i class="fas fa-angle-double-left"></i>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo ($page - 1) . $query_string; ?>">
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
                                    <a class="page-link" href="?page=<?php echo $i . $query_string; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo ($page + 1) . $query_string; ?>">
                                        <i class="fas fa-angle-right"></i>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $total_pages . $query_string; ?>">
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

<!-- Modal Cập nhật trạng thái -->
<div class="modal fade" id="updateStatusModal" tabindex="-1" aria-labelledby="updateStatusModalLabel"
    aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <input type="hidden" name="order_id" id="modal_order_id">

                <div class="modal-header">
                    <h5 class="modal-title" id="updateStatusModalLabel">Cập nhật trạng thái đơn hàng</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Đơn hàng: <strong id="modal_order_code"></strong></p>

                    <div class="mb-3">
                        <label for="new_status" class="form-label">Trạng thái mới</label>
                        <select class="form-select" id="new_status" name="new_status" required>
                            <option value="">-- Chọn trạng thái --</option>
                            <option value="1">Chờ xác nhận</option>
                            <option value="2">Đã xác nhận</option>
                            <option value="3">Đang giao hàng</option>
                            <option value="4">Đã giao</option>
                            <option value="5">Đã hủy</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="note" class="form-label">Ghi chú</label>
                        <textarea class="form-control" id="note" name="note" rows="2"
                            placeholder="Nhập ghi chú nếu có..."></textarea>
                    </div>

                    <div class="alert alert-warning" id="cancelWarning" style="display: none;">
                        <i class="fas fa-exclamation-triangle me-1"></i> Lưu ý: Hủy đơn hàng là hành động không thể hoàn
                        tác. Vui lòng xác nhận kỹ trước khi thực hiện.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                    <button type="submit" name="update_status" class="btn btn-primary" id="submitUpdateStatus">
                        <i class="fas fa-save me-1"></i> Cập nhật
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Scripts -->
<?php include 'includes/footer.php'; ?>