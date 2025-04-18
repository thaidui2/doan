<?php
// Set page title
$page_title = 'Trang chủ Admin';

// Include the header (which includes session check and common elements)
include('includes/header.php');

// Include database connection
include('../config/config.php');

// Get current date/time info for statistics
$current_month_start = date('Y-m-01 00:00:00');
$current_month_end = date('Y-m-t 23:59:59');
$last_month_start = date('Y-m-01 00:00:00', strtotime('-1 month'));
$last_month_end = date('Y-m-t 23:59:59', strtotime('-1 month'));
$week_ago = date('Y-m-d H:i:s', strtotime('-7 days'));

// Prepare stats array
$stats = [
    'orders' => 0,
    'orders_change' => 0,
    'revenue' => 0,
    'revenue_change' => 0,
    'products' => 0,
    'customers' => 0,
    'low_stock_count' => 0,
    'new_customers' => 0
];

// Total orders
$orders_query = $conn->query("SELECT COUNT(*) as total FROM donhang");
if ($orders_query) {
    $stats['orders'] = $orders_query->fetch_assoc()['total'];
}

// Orders this month vs last month
$orders_this_month = $conn->query("SELECT COUNT(*) as count FROM donhang WHERE ngay_dat BETWEEN '$current_month_start' AND '$current_month_end'");
$orders_last_month = $conn->query("SELECT COUNT(*) as count FROM donhang WHERE ngay_dat BETWEEN '$last_month_start' AND '$last_month_end'");

$this_month_orders = $orders_this_month->fetch_assoc()['count'];
$last_month_orders = $orders_last_month->fetch_assoc()['count'];

if ($last_month_orders > 0) {
    $stats['orders_change'] = round(($this_month_orders - $last_month_orders) / $last_month_orders * 100);
} else {
    $stats['orders_change'] = $this_month_orders > 0 ? 100 : 0;
}

// Total revenue from completed orders - Trang thái 4: Đã giao
$revenue_query = $conn->query("SELECT SUM(thanh_tien) as total FROM donhang WHERE trang_thai_don_hang = 4");
if ($revenue_query) {
    $result = $revenue_query->fetch_assoc();
    $stats['revenue'] = $result['total'] ?? 0;
}

// Revenue this month vs last month
$revenue_this_month = $conn->query("SELECT SUM(thanh_tien) as total FROM donhang WHERE trang_thai_don_hang = 4 AND ngay_dat BETWEEN '$current_month_start' AND '$current_month_end'");
$revenue_last_month = $conn->query("SELECT SUM(thanh_tien) as total FROM donhang WHERE trang_thai_don_hang = 4 AND ngay_dat BETWEEN '$last_month_start' AND '$last_month_end'");

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
    FROM sanpham_bien_the 
    WHERE so_luong > 0 AND so_luong <= 5
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
    SELECT dh.*, u.ten as tenkhachhang
    FROM donhang dh
    LEFT JOIN users u ON dh.id_user = u.id
    ORDER BY dh.ngay_dat DESC
    LIMIT 5
");
?>

<!-- Main content -->
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    

    <!-- Dashboard Stats -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100 card-dashboard border-left-primary">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Đơn hàng</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['orders']); ?></div>
                            <?php if ($stats['orders_change'] != 0): ?>
                                <div class="text-xs mt-1">
                                    <span class="<?php echo $stats['orders_change'] > 0 ? 'text-success' : 'text-danger'; ?>">
                                        <i class="bi <?php echo $stats['orders_change'] > 0 ? 'bi-arrow-up' : 'bi-arrow-down'; ?>"></i>
                                        <?php echo abs($stats['orders_change']); ?>%
                                    </span>
                                    so với tháng trước
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-cart text-gray-300" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100 card-dashboard border-left-success">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Doanh thu</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['revenue']); ?>₫</div>
                            <?php if ($stats['revenue_change'] != 0): ?>
                                <div class="text-xs mt-1">
                                    <span class="<?php echo $stats['revenue_change'] > 0 ? 'text-success' : 'text-danger'; ?>">
                                        <i class="bi <?php echo $stats['revenue_change'] > 0 ? 'bi-arrow-up' : 'bi-arrow-down'; ?>"></i>
                                        <?php echo abs($stats['revenue_change']); ?>%
                                    </span>
                                    so với tháng trước
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-currency-dollar text-gray-300" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100 card-dashboard border-left-info">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Sản phẩm</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['products']); ?></div>
                            <?php if ($stats['low_stock_count'] > 0): ?>
                                <div class="text-xs mt-1 text-warning">
                                    <i class="bi bi-exclamation-triangle"></i>
                                    <?php echo $stats['low_stock_count']; ?> sản phẩm sắp hết hàng
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-box-seam text-gray-300" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100 card-dashboard border-left-warning">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Khách hàng</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['customers']); ?></div>
                            <div class="text-xs mt-1">
                                <span class="text-success">
                                    <i class="bi bi-person-plus"></i>
                                    <?php echo $stats['new_customers']; ?>
                                </span>
                                khách hàng mới trong 7 ngày
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

    <!-- Content Row -->
    <div class="row">
        <!-- Area Chart -->
        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Doanh thu theo tháng</h6>
                </div>
                <div class="card-body">
                    <div class="chart-area">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pie Chart -->
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Đơn hàng theo trạng thái</h6>
                </div>
                <div class="card-body">
                    <div class="chart-pie pt-4 pb-2">
                        <canvas id="orderStatusChart"></canvas>
                    </div>
                    <div class="mt-4 text-center small">
                        <span class="mr-2">
                            <i class="fas fa-circle text-primary"></i> Chờ xác nhận
                        </span>
                        <span class="mr-2">
                            <i class="fas fa-circle text-success"></i> Đã xác nhận
                        </span>
                        <span class="mr-2">
                            <i class="fas fa-circle text-info"></i> Đang giao
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Orders Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">Đơn hàng gần đây</h6>
            <a href="orders.php" class="btn btn-sm btn-primary">
                Xem tất cả <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover border-bottom">
                    <thead class="table-light">
                        <tr>
                            <th>#Mã</th>
                            <th>Khách hàng</th>
                            <th>Tổng tiền</th>
                            <th>Trạng thái</th>
                            <th>Thời gian</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($recent_orders_query && $recent_orders_query->num_rows > 0): ?>
                            <?php while ($order = $recent_orders_query->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <a href="order-detail.php?id=<?php echo $order['id']; ?>" class="fw-bold text-decoration-none">
                                            <?php echo $order['ma_donhang']; ?>
                                        </a>
                                    </td>
                                    <td>
                                        <?php if ($order['id_user']): ?>
                                            <a href="customer-detail.php?id=<?php echo $order['id_user']; ?>">
                                                <?php echo htmlspecialchars($order['tenkhachhang']); ?>
                                            </a>
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($order['ho_ten']); ?> (Khách)
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo number_format($order['thanh_tien']); ?>₫</td>
                                    <td>
                                        <?php
                                        switch ($order['trang_thai_don_hang']) {
                                            case 1:
                                                echo '<span class="badge bg-warning">Chờ xác nhận</span>';
                                                break;
                                            case 2:
                                                echo '<span class="badge bg-info">Đã xác nhận</span>';
                                                break;
                                            case 3:
                                                echo '<span class="badge bg-primary">Đang giao hàng</span>';
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
                                    <td><?php echo date('d/m/Y H:i', strtotime($order['ngay_dat'])); ?></td>
                                    <td>
                                        <a href="order-detail.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-3">Chưa có đơn hàng nào</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- Chart.js Scripts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
<script>
    // Set new default font family and font color to mimic Bootstrap's default styling
    Chart.defaults.font.family = '-apple-system,system-ui,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif';
    Chart.defaults.color = '#858796';

    // Get monthly revenue data from database
    <?php
    $monthly_data = [];
    for ($i = 5; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $month_start = $month.'-01 00:00:00';
        $month_end = date('Y-m-t 23:59:59', strtotime($month_start));
        
        $month_query = $conn->query("SELECT SUM(thanh_tien) as total FROM donhang WHERE trang_thai_don_hang = 4 AND ngay_dat BETWEEN '$month_start' AND '$month_end'");
        $result = $month_query->fetch_assoc();
        $monthly_data[] = [
            'month' => date('M Y', strtotime($month)),
            'revenue' => (float)$result['total'] ?? 0
        ];
    }
    ?>

    // Revenue Chart
    let ctx = document.getElementById('revenueChart');
    let revenueChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: [<?php echo "'" . implode("','", array_column($monthly_data, 'month')) . "'"; ?>],
            datasets: [{
                label: "Doanh thu",
                lineTension: 0.3,
                backgroundColor: "rgba(78, 115, 223, 0.05)",
                borderColor: "rgba(78, 115, 223, 1)",
                pointRadius: 3,
                pointBackgroundColor: "rgba(78, 115, 223, 1)",
                pointBorderColor: "rgba(78, 115, 223, 1)",
                pointHoverRadius: 3,
                pointHoverBackgroundColor: "rgba(78, 115, 223, 1)",
                pointHoverBorderColor: "rgba(78, 115, 223, 1)",
                pointHitRadius: 10,
                pointBorderWidth: 2,
                data: [<?php echo implode(",", array_column($monthly_data, 'revenue')); ?>],
            }],
        },
        options: {
            maintainAspectRatio: false,
            layout: {
                padding: {
                    left: 10,
                    right: 25,
                    top: 25,
                    bottom: 0
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false,
                        drawBorder: false
                    },
                    ticks: {
                        maxTicksLimit: 7
                    }
                },
                y: {
                    ticks: {
                        maxTicksLimit: 5,
                        padding: 10,
                        callback: function(value, index, values) {
                            return value.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",") + '₫';
                        }
                    },
                    grid: {
                        color: "rgb(234, 236, 244)",
                        zeroLineColor: "rgb(234, 236, 244)",
                        drawBorder: false
                    }
                },
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            label += new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(context.parsed.y);
                            return label;
                        }
                    }
                }
            }
        }
    });

    // Order Status Chart
    <?php
    $order_status_query = $conn->query("
        SELECT trang_thai_don_hang, COUNT(*) as count
        FROM donhang
        GROUP BY trang_thai_don_hang
    ");
    
    $status_labels = [1 => 'Chờ xác nhận', 2 => 'Đã xác nhận', 3 => 'Đang giao', 4 => 'Đã giao', 5 => 'Đã hủy'];
    $status_colors = [1 => '#ffc107', 2 => '#0dcaf0', 3 => '#0d6efd', 4 => '#198754', 5 => '#dc3545'];
    
    $labels = [];
    $data = [];
    $backgroundColor = [];
    
    while ($status = $order_status_query->fetch_assoc()) {
        $status_id = $status['trang_thai_don_hang'];
        $labels[] = $status_labels[$status_id] ?? "Trạng thái $status_id";
        $data[] = $status['count'];
        $backgroundColor[] = $status_colors[$status_id] ?? '#6c757d';
    }
    ?>

    let statusCtx = document.getElementById('orderStatusChart');
    let orderStatusChart = new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: [<?php echo "'" . implode("','", $labels) . "'"; ?>],
            datasets: [{
                data: [<?php echo implode(",", $data); ?>],
                backgroundColor: [<?php echo "'" . implode("','", $backgroundColor) . "'"; ?>],
                hoverBackgroundColor: [<?php echo "'" . implode("','", $backgroundColor) . "'"; ?>],
                hoverBorderColor: "rgba(234, 236, 244, 1)",
            }],
        },
        options: {
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    display: true
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.label || '';
                            if (label) {
                                label += ': ';
                            }
                            label += context.raw + ' đơn';
                            return label;
                        }
                    }
                }
            },
            cutout: '70%'
        }
    });
</script>

<?php include('includes/footer.php'); ?>
