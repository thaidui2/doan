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
    'customers' => 0,
    'orders_change' => 0,
    'revenue_change' => 0,
    'low_stock_count' => 0,
    'new_customers' => 0
];

// Lấy thời gian hiện tại và thời gian tháng trước
$current_month_start = date('Y-m-01 00:00:00');
$current_month_end = date('Y-m-t 23:59:59');
$last_month_start = date('Y-m-01 00:00:00', strtotime('-1 month'));
$last_month_end = date('Y-m-t 23:59:59', strtotime('-1 month'));
$week_ago = date('Y-m-d H:i:s', strtotime('-7 days'));

// Total orders
$orders_query = $conn->query("SELECT COUNT(*) as total FROM donhang");
if ($orders_query) {
    $stats['orders'] = $orders_query->fetch_assoc()['total'];
}

// Orders this month vs last month
$orders_this_month = $conn->query("SELECT COUNT(*) as count FROM donhang WHERE ngaytao BETWEEN '$current_month_start' AND '$current_month_end'");
$orders_last_month = $conn->query("SELECT COUNT(*) as count FROM donhang WHERE ngaytao BETWEEN '$last_month_start' AND '$last_month_end'");

$this_month_orders = $orders_this_month->fetch_assoc()['count'];
$last_month_orders = $orders_last_month->fetch_assoc()['count'];

if ($last_month_orders > 0) {
    $stats['orders_change'] = round(($this_month_orders - $last_month_orders) / $last_month_orders * 100);
} else {
    $stats['orders_change'] = $this_month_orders > 0 ? 100 : 0;
}

// Total revenue from completed orders
$revenue_query = $conn->query("SELECT SUM(tongtien) as total FROM donhang WHERE trangthai = 4");
if ($revenue_query) {
    $result = $revenue_query->fetch_assoc();
    $stats['revenue'] = $result['total'] ?? 0;
}

// Revenue this month vs last month
$revenue_this_month = $conn->query("SELECT SUM(tongtien) as total FROM donhang WHERE trangthai = 4 AND ngaytao BETWEEN '$current_month_start' AND '$current_month_end'");
$revenue_last_month = $conn->query("SELECT SUM(tongtien) as total FROM donhang WHERE trangthai = 4 AND ngaytao BETWEEN '$last_month_start' AND '$last_month_end'");

$this_month_revenue = $revenue_this_month->fetch_assoc()['total'] ?? 0;
$last_month_revenue = $revenue_last_month->fetch_assoc()['total'] ?? 0;

if ($last_month_revenue > 0) {
    $stats['revenue_change'] = round(($this_month_revenue - $last_month_revenue) / $last_month_revenue * 100);
} else {
    $stats['revenue_change'] = $this_month_revenue > 0 ? 100 : 0;
}

// Total products
$products_query = $conn->query("SELECT COUNT(*) as total FROM sanpham");
if ($products_query) {
    $stats['products'] = $products_query->fetch_assoc()['total'];
}

// Count products with low stock
$low_stock_query = $conn->query("
    SELECT COUNT(*) as count 
    FROM sanpham_chitiet 
    WHERE soluong > 0 AND soluong <= 5
");
if ($low_stock_query) {
    $stats['low_stock_count'] = $low_stock_query->fetch_assoc()['count'];
}

// Total customers
$customers_query = $conn->query("SELECT COUNT(*) as total FROM users WHERE loai_user = 0");
if ($customers_query) {
    $stats['customers'] = $customers_query->fetch_assoc()['total'];
}

// Count new customers in the last week
$new_customers_query = $conn->query("
    SELECT COUNT(*) as count 
    FROM users 
    WHERE loai_user = 0 AND ngay_tao >= '$week_ago'
");
if ($new_customers_query) {
    $stats['new_customers'] = $new_customers_query->fetch_assoc()['count'];
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
    

    <!-- Dashboard Stats -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100 card-dashboard border-left-primary">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col">
                            <div class="text-xs text-uppercase mb-1 text-muted">Đơn hàng</div>
                            <div class="h5 mb-0 font-weight-bold"><?php echo number_format($stats['orders']); ?></div>
                            <div class="mt-2 small <?php echo $stats['orders_change'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                <i class="bi bi-arrow-<?php echo $stats['orders_change'] >= 0 ? 'up' : 'down'; ?>"></i>
                                <?php echo abs($stats['orders_change']); ?>% so với tháng trước
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
                            <div class="mt-2 small <?php echo $stats['revenue_change'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                <i class="bi bi-arrow-<?php echo $stats['revenue_change'] >= 0 ? 'up' : 'down'; ?>"></i>
                                <?php echo abs($stats['revenue_change']); ?>% so với tháng trước
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
                                <i class="bi bi-info-circle"></i> 
                                <?php echo $stats['low_stock_count']; ?> sản phẩm sắp hết hàng
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
                                <i class="bi bi-person-plus"></i> 
                                <?php echo $stats['new_customers']; ?> mới trong tuần
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
