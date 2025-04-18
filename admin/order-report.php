<?php
// Set page title
$page_title = 'Báo cáo đơn hàng';

// Include header (which includes authentication checks)
include('includes/header.php');

// Include database connection
include('../config/config.php');

// Check if user has permission
if ($admin_level < 1) {
    $_SESSION['error_message'] = "Bạn không có quyền truy cập trang này.";
    header('Location: index.php');
    exit;
}

// Initialize date filters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); // First day of current month
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d'); // Today

// Initialize status filter
$status_filter = isset($_GET['status']) ? (int)$_GET['status'] : -1; // -1 = all statuses

// Format dates for display
$formatted_start = date('d/m/Y', strtotime($start_date));
$formatted_end = date('d/m/Y', strtotime($end_date));

// Base query with JOIN to get user name
$base_query = "
    SELECT dh.*, u.ten as ten_khach_hang
    FROM donhang dh
    LEFT JOIN users u ON dh.id_user = u.id
    WHERE dh.ngay_dat BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'
";

// Apply status filter if selected
if ($status_filter !== -1) {
    $base_query .= " AND dh.trang_thai_don_hang = $status_filter";
}

// Query for overall statistics
$stats_query = $base_query;
$stats_result = $conn->query($stats_query);

// Count orders by status
$status_counts = [
    1 => 0, // Chờ xác nhận
    2 => 0, // Đã xác nhận
    3 => 0, // Đang giao hàng
    4 => 0, // Đã giao
    5 => 0, // Đã hủy
];

$total_revenue = 0;
$total_shipping = 0;
$total_discount = 0;
$order_count = 0;

if ($stats_result && $stats_result->num_rows > 0) {
    while ($order = $stats_result->fetch_assoc()) {
        $status = (int)$order['trang_thai_don_hang'];
        if (isset($status_counts[$status])) {
            $status_counts[$status]++;
        }
        
        $total_revenue += (float)$order['thanh_tien']; // Total after all deductions
        $total_shipping += (float)$order['phi_vanchuyen'];
        $total_discount += (float)$order['giam_gia'];
        $order_count++;
    }
}

// Calculate average order value
$average_order = $order_count > 0 ? $total_revenue / $order_count : 0;

// Query for top products
$top_products_query = "
    SELECT 
        sp.id, 
        sp.tensanpham, 
        SUM(dhct.soluong) as total_quantity,
        SUM(dhct.soluong * dhct.gia) as total_revenue
    FROM donhang_chitiet dhct
    JOIN sanpham sp ON dhct.id_sanpham = sp.id
    JOIN donhang dh ON dhct.id_donhang = dh.id
    WHERE dh.ngay_dat BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'
    AND dh.trang_thai_don_hang = 4  -- Only count completed orders
    GROUP BY sp.id
    ORDER BY total_quantity DESC
    LIMIT 10
";

$top_products_result = $conn->query($top_products_query);

// Query for sales by day for chart
$daily_sales_query = "
    SELECT 
        DATE(dh.ngay_dat) as sale_date,
        COUNT(dh.id) as order_count,
        SUM(dh.thanh_tien) as daily_revenue
    FROM donhang dh
    WHERE dh.ngay_dat BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'
    AND dh.trang_thai_don_hang != 5  -- Exclude cancelled orders
    GROUP BY DATE(dh.ngay_dat)
    ORDER BY sale_date
";

$daily_sales_result = $conn->query($daily_sales_query);

$chart_labels = [];
$chart_data = [];

if ($daily_sales_result && $daily_sales_result->num_rows > 0) {
    while ($day = $daily_sales_result->fetch_assoc()) {
        $chart_labels[] = date('d/m', strtotime($day['sale_date']));
        $chart_data[] = $day['daily_revenue'];
    }
}

// JSON encode for JavaScript
$chart_labels_json = json_encode($chart_labels);
$chart_data_json = json_encode($chart_data);

?>

<!-- Main content -->
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Báo cáo đơn hàng</h1>
    </div>

    <!-- Filter Form -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-4">
                    <label for="start_date" class="form-label">Từ ngày</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                </div>
                <div class="col-md-4">
                    <label for="end_date" class="form-label">Đến ngày</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Trạng thái</label>
                    <select class="form-select" id="status" name="status">
                        <option value="-1" <?php echo $status_filter === -1 ? 'selected' : ''; ?>>Tất cả trạng thái</option>
                        <option value="1" <?php echo $status_filter === 1 ? 'selected' : ''; ?>>Chờ xác nhận</option>
                        <option value="2" <?php echo $status_filter === 2 ? 'selected' : ''; ?>>Đã xác nhận</option>
                        <option value="3" <?php echo $status_filter === 3 ? 'selected' : ''; ?>>Đang giao hàng</option>
                        <option value="4" <?php echo $status_filter === 4 ? 'selected' : ''; ?>>Đã giao hàng</option>
                        <option value="5" <?php echo $status_filter === 5 ? 'selected' : ''; ?>>Đã hủy</option>
                    </select>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Lọc</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Tổng doanh thu</h5>
                    <h2 class="text-primary"><?php echo number_format($total_revenue, 0, ',', '.'); ?> ₫</h2>
                    <p class="text-muted">Từ <?php echo $formatted_start; ?> đến <?php echo $formatted_end; ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Tổng đơn hàng</h5>
                    <h2 class="text-success"><?php echo number_format($order_count); ?></h2>
                    <p class="text-muted">Giá trị TB: <?php echo number_format($average_order, 0, ',', '.'); ?> ₫</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Đơn hàng hoàn thành</h5>
                    <h2 class="text-info"><?php echo number_format($status_counts[4]); ?></h2>
                    <p class="text-muted">Tỉ lệ: <?php echo $order_count > 0 ? round(($status_counts[4] / $order_count) * 100, 1) : 0; ?>%</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title">Đơn hàng hủy</h5>
                    <h2 class="text-danger"><?php echo number_format($status_counts[5]); ?></h2>
                    <p class="text-muted">Tỉ lệ: <?php echo $order_count > 0 ? round(($status_counts[5] / $order_count) * 100, 1) : 0; ?>%</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Doanh thu theo ngày</h5>
                </div>
                <div class="card-body">
                    <canvas id="salesChart" height="300"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Trạng thái đơn hàng</h5>
                </div>
                <div class="card-body">
                    <canvas id="statusChart" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Products Table -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white">
            <h5 class="card-title mb-0">Top sản phẩm bán chạy</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Sản phẩm</th>
                            <th class="text-end">Số lượng bán</th>
                            <th class="text-end">Doanh thu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($top_products_result && $top_products_result->num_rows > 0): ?>
                            <?php while ($product = $top_products_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $product['id']; ?></td>
                                    <td>
                                        <a href="edit_product.php?id=<?php echo $product['id']; ?>">
                                            <?php echo htmlspecialchars($product['tensanpham']); ?>
                                        </a>
                                    </td>
                                    <td class="text-end"><?php echo number_format($product['total_quantity']); ?></td>
                                    <td class="text-end"><?php echo number_format($product['total_revenue'], 0, ',', '.'); ?> ₫</td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center py-3">Không có dữ liệu sản phẩm nào</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sales chart
    const salesChart = new Chart(document.getElementById('salesChart'), {
        type: 'line',
        data: {
            labels: <?php echo $chart_labels_json; ?>,
            datasets: [{
                label: 'Doanh thu',
                data: <?php echo $chart_data_json; ?>,
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                borderColor: 'rgba(13, 110, 253, 1)',
                borderWidth: 2,
                tension: 0.1,
                fill: true
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let value = context.raw;
                            return 'Doanh thu: ' + new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            if (value >= 1000000) {
                                return (value / 1000000) + 'M';
                            }
                            if (value >= 1000) {
                                return (value / 1000) + 'K';
                            }
                            return value;
                        }
                    }
                }
            }
        }
    });

    // Status chart
    const statusChart = new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: [
                'Chờ xác nhận',
                'Đã xác nhận',
                'Đang giao hàng',
                'Đã giao',
                'Đã hủy'
            ],
            datasets: [{
                data: [
                    <?php echo $status_counts[1]; ?>,
                    <?php echo $status_counts[2]; ?>,
                    <?php echo $status_counts[3]; ?>,
                    <?php echo $status_counts[4]; ?>,
                    <?php echo $status_counts[5]; ?>
                ],
                backgroundColor: [
                    '#ffc107',
                    '#0dcaf0',
                    '#0d6efd',
                    '#198754',
                    '#dc3545'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            },
            cutout: '60%'
        }
    });
});
</script>

<?php include('includes/footer.php'); ?>