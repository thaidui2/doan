<?php
// Set page title
$page_title = 'Dashboard';

// Include header (which now includes functions.php)
include('includes/header.php');

// Include database connection
include('../config/config.php');

// Get statistics from database
$stats = [
    'orders' => 0,
    'revenue' => 0,
    'products' => 0,
    'customers' => 0
];

// Total orders
$orders_query = $conn->query("SELECT COUNT(*) as total FROM donhang");
if ($orders_query) {
    $stats['orders'] = $orders_query->fetch_assoc()['total'];
}

// Total revenue from completed orders
$revenue_query = $conn->query("SELECT SUM(tongtien) as total FROM donhang WHERE trangthai = 4");
if ($revenue_query) {
    $result = $revenue_query->fetch_assoc();
    $stats['revenue'] = $result['total'] ?? 0;
}

// Total products
$products_query = $conn->query("SELECT COUNT(*) as total FROM sanpham");
if ($products_query) {
    $stats['products'] = $products_query->fetch_assoc()['total'];
}

// Total customers
$customers_query = $conn->query("SELECT COUNT(*) as total FROM users WHERE loai_user = 0");
if ($customers_query) {
    $stats['customers'] = $customers_query->fetch_assoc()['total'];
}

// Recent orders for dashboard
$recent_orders_query = $conn->query("
    SELECT dh.*, u.tenuser 
    FROM donhang dh
    LEFT JOIN users u ON dh.id_nguoidung = u.id_user
    ORDER BY dh.ngaytao DESC
    LIMIT 5
");
?>

<!-- Include the sidebar -->
<?php include('includes/sidebar.php'); ?>

<!-- Main content -->
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Dashboard</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['admin_username']); ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton">
                    <li><a class="dropdown-item" href="profile.php">Hồ sơ</a></li>
                    <li><a class="dropdown-item" href="settings.php">Cài đặt tài khoản</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="logout.php">Đăng xuất</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Dashboard Stats -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100 card-dashboard border-left-primary">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col">
                            <div class="text-xs text-uppercase mb-1 text-muted">Đơn hàng</div>
                            <div class="h5 mb-0 font-weight-bold"><?php echo number_format($stats['orders']); ?></div>
                            <div class="mt-2 small text-success">
                                <i class="bi bi-arrow-up"></i> 12% so với tháng trước
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-cart3 text-gray-300" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100 card-dashboard border-left-warning">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col">
                            <div class="text-xs text-uppercase mb-1 text-muted">Doanh thu</div>
                            <div class="h5 mb-0 font-weight-bold"><?php echo number_format($stats['revenue']); ?>₫</div>
                            <div class="mt-2 small text-success">
                                <i class="bi bi-arrow-up"></i> 8% so với tháng trước
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-currency-dollar text-gray-300" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100 card-dashboard border-left-success">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col">
                            <div class="text-xs text-uppercase mb-1 text-muted">Sản phẩm</div>
                            <div class="h5 mb-0 font-weight-bold"><?php echo number_format($stats['products']); ?></div>
                            <div class="mt-2 small text-info">
                                <i class="bi bi-info-circle"></i> 5 sắp hết hàng
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-box text-gray-300" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100 card-dashboard border-left-info">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col">
                            <div class="text-xs text-uppercase mb-1 text-muted">Khách hàng</div>
                            <div class="h5 mb-0 font-weight-bold"><?php echo number_format($stats['customers']); ?></div>
                            <div class="mt-2 small text-success">
                                <i class="bi bi-person-plus"></i> 18 mới trong tuần
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-people text-gray-300" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Orders -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header py-3 bg-white">
            <h6 class="m-0 font-weight-bold">Đơn hàng gần đây</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Khách hàng</th>
                            <th>Ngày đặt</th>
                            <th>Tổng tiền</th>
                            <th>Trạng thái</th>
                            <th>Tác vụ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $status_labels = [
                            1 => ['text' => 'Chờ xác nhận', 'class' => 'warning text-dark'],
                            2 => ['text' => 'Đang xử lý', 'class' => 'info'],
                            3 => ['text' => 'Đang giao', 'class' => 'primary'],
                            4 => ['text' => 'Hoàn thành', 'class' => 'success'],
                            5 => ['text' => 'Đã hủy', 'class' => 'danger'],
                            6 => ['text' => 'Hoàn trả', 'class' => 'secondary']
                        ];
                        
                        if ($recent_orders_query && $recent_orders_query->num_rows > 0):
                            while ($order = $recent_orders_query->fetch_assoc()): 
                                $status = $status_labels[$order['trangthai']] ?? ['text' => 'Không xác định', 'class' => 'secondary'];
                        ?>
                        <tr>
                            <td>#<?php echo $order['id_donhang']; ?></td>
                            <td><?php echo htmlspecialchars($order['tennguoinhan'] ?: ($order['tenuser'] ?? 'Không xác định')); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($order['ngaytao'])); ?></td>
                            <td><?php echo number_format($order['tongtien']); ?>₫</td>
                            <td><span class="badge bg-<?php echo $status['class']; ?>"><?php echo $status['text']; ?></span></td>
                            <td>
                                <a href="order-detail.php?id=<?php echo $order['id_donhang']; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php 
                            endwhile;
                        else:
                        ?>
                        <tr>
                            <td colspan="6" class="text-center py-4">Không có đơn hàng nào</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="text-end mt-2">
                <a href="orders.php" class="btn btn-sm btn-primary">
                    <i class="bi bi-eye"></i> Xem tất cả đơn hàng
                </a>
            </div>
        </div>
    </div>
</main>

<?php include('includes/footer.php'); ?>
