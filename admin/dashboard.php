<?php
// Kiểm tra đăng nhập và quyền admin
session_start();
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_loai']) || $_SESSION['admin_loai'] < 1) {
    header('Location: login.php');
    exit();
}

// Kết nối database
require_once '../config/config.php';

// Lấy thông tin tổng quan
// 1. Tổng số đơn hàng
$sql_total_orders = "SELECT COUNT(*) as total FROM donhang";
$result_total_orders = $conn->query($sql_total_orders);
$total_orders = $result_total_orders->fetch_assoc()['total'];

// 2. Đơn hàng theo trạng thái
$sql_orders_by_status = "SELECT 
                            SUM(CASE WHEN trang_thai_don_hang = 1 THEN 1 ELSE 0 END) as pending,
                            SUM(CASE WHEN trang_thai_don_hang = 2 THEN 1 ELSE 0 END) as confirmed,
                            SUM(CASE WHEN trang_thai_don_hang = 3 THEN 1 ELSE 0 END) as shipping,
                            SUM(CASE WHEN trang_thai_don_hang = 4 THEN 1 ELSE 0 END) as completed,
                            SUM(CASE WHEN trang_thai_don_hang = 5 THEN 1 ELSE 0 END) as canceled
                          FROM donhang";
$result_orders_by_status = $conn->query($sql_orders_by_status);
$orders_by_status = $result_orders_by_status->fetch_assoc();

// 3. Tổng doanh thu (từ đơn hàng đã hoàn thành)
$sql_total_revenue = "SELECT SUM(thanh_tien) as total_revenue FROM donhang WHERE trang_thai_don_hang = 4";
$result_total_revenue = $conn->query($sql_total_revenue);
$total_revenue = $result_total_revenue->fetch_assoc()['total_revenue'];

// 4. Tổng sản phẩm
$sql_total_products = "SELECT COUNT(*) as total FROM sanpham";
$result_total_products = $conn->query($sql_total_products);
$total_products = $result_total_products->fetch_assoc()['total'];

// 5. Tổng khách hàng
$sql_total_customers = "SELECT COUNT(*) as total FROM users WHERE loai_user = 0";
$result_total_customers = $conn->query($sql_total_customers);
$total_customers = $result_total_customers->fetch_assoc()['total'];

// 6. Đánh giá chưa duyệt
$sql_pending_reviews = "SELECT COUNT(*) as total FROM danhgia WHERE trang_thai = 0";
$result_pending_reviews = $conn->query($sql_pending_reviews);
$pending_reviews = $result_pending_reviews->fetch_assoc()['total'];

// 7. Yêu cầu hoàn trả chưa xử lý
$sql_pending_returns = "SELECT COUNT(*) as total FROM hoantra WHERE trangthai = 1";
$result_pending_returns = $conn->query($sql_pending_returns);
$pending_returns = $result_pending_returns->fetch_assoc()['total'];

// 8. Doanh thu theo tháng (6 tháng gần nhất)
$current_month = date('m');
$current_year = date('Y');

$revenue_by_month = [];
for ($i = 5; $i >= 0; $i--) {
    $month = $current_month - $i;
    $year = $current_year;

    if ($month <= 0) {
        $month += 12;
        $year -= 1;
    }

    $sql_revenue_month = "SELECT SUM(thanh_tien) as revenue 
                          FROM donhang 
                          WHERE MONTH(ngay_dat) = $month 
                          AND YEAR(ngay_dat) = $year 
                          AND trang_thai_don_hang = 4";
    $result_revenue_month = $conn->query($sql_revenue_month);
    $revenue = $result_revenue_month->fetch_assoc()['revenue'];

    $revenue_by_month[] = [
        'month' => date('M', mktime(0, 0, 0, $month, 1, $year)),
        'revenue' => $revenue ? $revenue : 0
    ];
}

// 8.1 Doanh thu theo ngày (7 ngày gần nhất)
$revenue_by_day = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));

    $sql_revenue_day = "SELECT SUM(thanh_tien) as revenue, 
                               COUNT(*) as order_count
                        FROM donhang 
                        WHERE DATE(ngay_dat) = '$date' 
                        AND trang_thai_don_hang IN (2, 3, 4)"; // Include confirmed, shipping, and completed orders
    $result_revenue_day = $conn->query($sql_revenue_day);
    $row = $result_revenue_day->fetch_assoc();

    $revenue_by_day[] = [
        'date' => date('d/m', strtotime($date)),
        'full_date' => date('d/m/Y', strtotime($date)),
        'revenue' => $row['revenue'] ? $row['revenue'] : 0,
        'order_count' => $row['order_count']
    ];
}

// 9. Đơn hàng gần đây
$sql_recent_orders = "SELECT dh.id, dh.ma_donhang, dh.ho_ten, dh.thanh_tien, dh.trang_thai_don_hang, dh.ngay_dat
                     FROM donhang dh
                     ORDER BY dh.ngay_dat DESC
                     LIMIT 10";
$result_recent_orders = $conn->query($sql_recent_orders);

// 10. Sản phẩm bán chạy
$sql_best_sellers = "SELECT sp.id, sp.tensanpham, COUNT(dct.id) as total_sold
                    FROM sanpham sp 
                    JOIN donhang_chitiet dct ON sp.id = dct.id_sanpham
                    JOIN donhang dh ON dct.id_donhang = dh.id
                    WHERE dh.trang_thai_don_hang = 4
                    GROUP BY sp.id
                    ORDER BY total_sold DESC
                    LIMIT 5";
$result_best_sellers = $conn->query($sql_best_sellers);

// Hàm trạng thái đơn hàng
function getOrderStatusLabel($status)
{
    switch ($status) {
        case 1:
            return '<span class="badge bg-warning">Chờ xác nhận</span>';
        case 2:
            return '<span class="badge bg-info">Đã xác nhận</span>';
        case 3:
            return '<span class="badge bg-primary">Đang giao hàng</span>';
        case 4:
            return '<span class="badge bg-success">Đã giao</span>';
        case 5:
            return '<span class="badge bg-danger">Đã hủy</span>';
        default:
            return '<span class="badge bg-secondary">Không xác định</span>';
    }
}

// Function to format VND
function formatVND($amount)
{
    return number_format($amount, 0, ',', '.') . ' ₫';
}

// Set timezone to Vietnam
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Set page title and current page for active menu
$page_title = 'Dashboard';
$current_page = 'dashboard';

// CSS riêng cho trang này
$page_css = ['css/dashboard.css'];

// Javascript riêng cho trang này
$page_js = ['js/dashboard.js'];

// Dữ liệu JavaScript tùy chỉnh cần truyền từ PHP sang JS
$head_custom = '
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
// Truyền dữ liệu từ PHP sang JS - sử dụng window để đảm bảo biến global
window.revenueChartData = {
    labels: ' . json_encode(array_column($revenue_by_month, 'month')) . ',
    data: ' . json_encode(array_column($revenue_by_month, 'revenue')) . '
};

window.dailyRevenueData = {
    labels: ' . json_encode(array_column($revenue_by_day, 'date')) . ',
    revenue: ' . json_encode(array_column($revenue_by_day, 'revenue')) . ',
    orders: ' . json_encode(array_column($revenue_by_day, 'order_count')) . ',
    fullDates: ' . json_encode(array_column($revenue_by_day, 'full_date')) . '
};

// Debugging data
console.log("Chart data loaded:", window.revenueChartData);
console.log("Daily data loaded:", window.dailyRevenueData);
</script>';

// Include header and sidebar
include 'includes/header.php';
include 'includes/sidebar.php';
?>
<!-- Main Content -->
<div class="col-md-10 col-lg-10 ms-auto">
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center mt-4 mb-4">
            <h1 class="h3 mb-0 text-gray-800">Dashboard</h1>
            <div class="d-none d-md-inline-block">
                <div class="btn-group">
                    <a href="#" class="btn btn-sm btn-primary btn-export-report">
                        <i class="fas fa-download fa-sm"></i> Xuất báo cáo
                    </a>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row">
            <!-- Total Revenue Card -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card shadow h-100 py-2 stat-card card-primary">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Doanh thu
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php echo formatVND($total_revenue); ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-calendar fa-2x text-gray-300 stat-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total Orders Card -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card shadow h-100 py-2 stat-card card-success">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    Đơn hàng
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_orders; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-shopping-cart fa-2x text-gray-300 stat-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Products Card -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card shadow h-100 py-2 stat-card card-info">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                    Sản phẩm
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_products; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-box fa-2x text-gray-300 stat-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Customers Card -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card shadow h-100 py-2 stat-card card-warning">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                    Khách hàng
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_customers; ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-users fa-2x text-gray-300 stat-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Order Status Row -->
        <div class="row">
            <!-- Pending Orders -->
            <div class="col-md-4 col-lg mb-4">
                <div class="card shadow h-100">
                    <div class="card-body text-center">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Chờ xác nhận</div>
                        <div class="h5 mb-0 font-weight-bold"><?php echo $orders_by_status['pending']; ?></div>
                    </div>
                </div>
            </div>

            <!-- Confirmed Orders -->
            <div class="col-md-4 col-lg mb-4">
                <div class="card shadow h-100">
                    <div class="card-body text-center">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Đã xác nhận</div>
                        <div class="h5 mb-0 font-weight-bold"><?php echo $orders_by_status['confirmed']; ?></div>
                    </div>
                </div>
            </div>

            <!-- Shipping Orders -->
            <div class="col-md-4 col-lg mb-4">
                <div class="card shadow h-100">
                    <div class="card-body text-center">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Đang giao</div>
                        <div class="h5 mb-0 font-weight-bold"><?php echo $orders_by_status['shipping']; ?></div>
                    </div>
                </div>
            </div>

            <!-- Completed Orders -->
            <div class="col-md-6 col-lg mb-4">
                <div class="card shadow h-100">
                    <div class="card-body text-center">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Đã giao</div>
                        <div class="h5 mb-0 font-weight-bold"><?php echo $orders_by_status['completed']; ?></div>
                    </div>
                </div>
            </div>

            <!-- Canceled Orders -->
            <div class="col-md-6 col-lg mb-4">
                <div class="card shadow h-100">
                    <div class="card-body text-center">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Đã hủy</div>
                        <div class="h5 mb-0 font-weight-bold"><?php echo $orders_by_status['canceled']; ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Sales Chart -->
            <div class="col-xl-8 col-lg-7">
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary">Thống kê doanh thu</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notification Cards -->
            <div class="col-xl-4 col-lg-5">
                <!-- Pending Returns -->
                <div class="card shadow mb-4">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-bell me-1"></i>Hoạt động cần xử lý
                        </h6>
                        <button class="btn btn-sm p-0 text-muted" data-bs-toggle="tooltip" title="Làm mới">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <!-- Compact notification items -->
                        <div class="list-group list-group-flush border-bottom">
                            <!-- Return requests -->
                            <div class="list-group-item px-3 py-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <span
                                            class="badge rounded-pill bg-danger me-2"><?php echo $pending_returns; ?></span>
                                        <span>Yêu cầu hoàn trả mới</span>
                                    </div>
                                    <a href="returns.php" class="btn btn-sm btn-link text-danger p-0">
                                        <i class="fas fa-undo me-1"></i>Xem
                                    </a>
                                </div>
                            </div>

                            <!-- Reviews -->
                            <div class="list-group-item px-3 py-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <span
                                            class="badge rounded-pill bg-warning me-2"><?php echo $pending_reviews; ?></span>
                                        <span>Đánh giá chưa duyệt</span>
                                    </div>
                                    <a href="reviews.php" class="btn btn-sm btn-link text-warning p-0">
                                        <i class="fas fa-star me-1"></i>Xem
                                    </a>
                                </div>
                            </div>

                            <!-- Best Sellers -->
                            <div class="list-group-item px-3 py-2 bg-light">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0 fw-bold">
                                        <i class="fas fa-fire-alt text-danger me-1"></i>Sản phẩm bán chạy
                                    </h6>
                                    <a href="products.php?sort=bestselling"
                                        class="btn btn-sm btn-link p-0 text-primary">
                                        <i class="fas fa-external-link-alt"></i>
                                    </a>
                                </div>
                            </div>
                            <?php if ($result_best_sellers && $result_best_sellers->num_rows > 0): ?>
                                <?php $count = 1;
                                while ($row = $result_best_sellers->fetch_assoc()): ?>
                                    <div class="list-group-item px-3 py-1 border-0 border-bottom">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="text-truncate" style="max-width: 80%;">
                                                <small class="text-muted">#<?php echo $count++; ?></small>
                                                <span class="ms-1"><?php echo $row['tensanpham']; ?></span>
                                            </div>
                                            <span class="badge bg-primary rounded-pill"><?php echo $row['total_sold']; ?></span>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="list-group-item px-3 py-2 text-center text-muted">
                                    <small>Chưa có dữ liệu</small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Daily Revenue Stats -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Thống kê doanh thu theo ngày (7 ngày gần nhất)</h6>
                    <div class="dropdown no-arrow">
                        <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow animated--fade-in"
                            aria-labelledby="dropdownMenuLink">
                            <li><a class="dropdown-item" href="#" id="refreshDailyStats">Làm mới dữ liệu</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="#" id="exportDailyStats">Xuất báo cáo</a></li>
                        </ul>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="chart-container" style="height: 250px">
                                <canvas id="dailyRevenueChart"></canvas>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead>
                                        <tr class="bg-light">
                                            <th>Ngày</th>
                                            <th>Đơn hàng</th>
                                            <th>Doanh thu</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $total_daily_revenue = 0;
                                        $total_daily_orders = 0;

                                        foreach ($revenue_by_day as $day):
                                            $total_daily_revenue += $day['revenue'];
                                            $total_daily_orders += $day['order_count'];
                                            ?>
                                            <tr>
                                                <td><?php echo $day['full_date']; ?></td>
                                                <td class="text-center"><?php echo $day['order_count']; ?></td>
                                                <td class="text-end"><?php echo formatVND($day['revenue']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr class="fw-bold bg-light">
                                            <td>Tổng</td>
                                            <td class="text-center"><?php echo $total_daily_orders; ?></td>
                                            <td class="text-end"><?php echo formatVND($total_daily_revenue); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Orders Table -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Đơn hàng gần đây</h6>
                    <a href="orders.php" class="btn btn-sm btn-primary">Xem tất cả</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Mã đơn</th>
                                    <th>Khách hàng</th>
                                    <th>Giá trị</th>
                                    <th>Trạng thái</th>
                                    <th>Ngày đặt</th>
                                    <th>Thao tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result_recent_orders && $result_recent_orders->num_rows > 0): ?>
                                    <?php while ($row = $result_recent_orders->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $row['ma_donhang']; ?></td>
                                            <td><?php echo $row['ho_ten']; ?></td>
                                            <td><?php echo formatVND($row['thanh_tien']); ?></td>
                                            <td><?php echo getOrderStatusLabel($row['trang_thai_don_hang']); ?></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($row['ngay_dat'])); ?></td>
                                            <td>
                                                <a href="order_detail.php?id=<?php echo $row['id']; ?>"
                                                    class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center">Chưa có đơn hàng nào</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php
    // Include footer
    include 'includes/footer.php';
    ?>