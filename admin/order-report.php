<?php
// Set time limit to prevent script timeouts
set_time_limit(60); // 60 seconds max execution time

// Set page title
$page_title = 'Báo cáo doanh thu';

// Include header (với kiểm tra đăng nhập)
include('includes/header.php');

// Include database connection
include('../config/config.php');

// Check database connection
if (!$conn) {
    echo '<div class="alert alert-danger">Không thể kết nối đến cơ sở dữ liệu.</div>';
    exit;
}

// Thiết lập múi giờ cho các tính toán ngày tháng
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Xác định khoảng thời gian báo cáo
$current_date = date('Y-m-d');
$current_month = date('Y-m');
$current_year = date('Y');
$last_month = date('Y-m', strtotime('-1 month'));
$last_month_name = date('m/Y', strtotime('-1 month'));

// Lấy thời gian từ form nếu có
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : $current_date;
$filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : 'custom';

// Thay đổi khoảng thời gian dựa trên filter_type
if ($filter_type == 'today') {
    $start_date = $current_date;
    $end_date = $current_date;
} elseif ($filter_type == 'yesterday') {
    $start_date = date('Y-m-d', strtotime('-1 day'));
    $end_date = date('Y-m-d', strtotime('-1 day'));
} elseif ($filter_type == 'this_week') {
    $start_date = date('Y-m-d', strtotime('monday this week'));
    $end_date = $current_date;
} elseif ($filter_type == 'this_month') {
    $start_date = date('Y-m-01');
    $end_date = $current_date;
} elseif ($filter_type == 'last_month') {
    $start_date = date('Y-m-01', strtotime('-1 month'));
    $end_date = date('Y-m-t', strtotime('-1 month'));
} elseif ($filter_type == 'this_year') {
    $start_date = date('Y-01-01');
    $end_date = $current_date;
}

// Lấy trạng thái đơn hàng từ form nếu có (mặc định chỉ tính đơn đã giao)
$order_status = isset($_GET['order_status']) ? $_GET['order_status'] : 4;

// Xây dựng câu truy vấn tổng doanh thu
$revenue_query = "
    SELECT SUM(tongtien) as total_revenue, COUNT(*) as total_orders
    FROM donhang
    WHERE ngaytao BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'
";

// Chỉ tính đơn hàng có trạng thái được chọn (nếu trạng thái là 0 thì tính tất cả)
if ($order_status > 0) {
    $revenue_query .= " AND trangthai = $order_status";
}

// Thực hiện truy vấn tổng doanh thu
$revenue_result = $conn->query($revenue_query);
$revenue_data = $revenue_result->fetch_assoc();
$total_revenue = $revenue_data['total_revenue'] ?? 0;
$total_orders = $revenue_data['total_orders'] ?? 0;

// Tính doanh thu theo ngày
$daily_query = "
    SELECT DATE(ngaytao) as order_date, 
           SUM(tongtien) as daily_revenue,
           COUNT(*) as order_count
    FROM donhang 
    WHERE ngaytao BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'
";

if ($order_status > 0) {
    $daily_query .= " AND trangthai = $order_status";
}

$daily_query .= " GROUP BY DATE(ngaytao) ORDER BY order_date";
$daily_result = $conn->query($daily_query);

$daily_data = [];
$daily_labels = [];
$daily_values = [];
$daily_counts = [];

while ($row = $daily_result->fetch_assoc()) {
    $daily_data[] = $row;
    $daily_labels[] = date('d/m/Y', strtotime($row['order_date']));
    $daily_values[] = (int)$row['daily_revenue'];
    $daily_counts[] = (int)$row['order_count'];
}

// Lấy top sản phẩm bán chạy
$top_products_query = "
    SELECT sp.id_sanpham, sp.tensanpham, sp.hinhanh, lsp.tenloai,
           SUM(dct.soluong) as total_quantity,
           SUM(dct.thanh_tien) as total_amount
    FROM donhang_chitiet dct
    JOIN donhang dh ON dct.id_donhang = dh.id_donhang
    JOIN sanpham sp ON dct.id_sanpham = sp.id_sanpham
    LEFT JOIN loaisanpham lsp ON sp.id_loai = lsp.id_loai
    WHERE dh.ngaytao BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'
";

if ($order_status > 0) {
    $top_products_query .= " AND dh.trangthai = $order_status";
}

$top_products_query .= " GROUP BY sp.id_sanpham
                       ORDER BY total_quantity DESC
                       LIMIT 10";

$top_products_result = $conn->query($top_products_query);

// Thống kê theo danh mục
$category_query = "
    SELECT lsp.id_loai, lsp.tenloai,
           COUNT(DISTINCT dh.id_donhang) as order_count,
           SUM(dct.thanh_tien) as category_revenue
    FROM donhang_chitiet dct
    JOIN donhang dh ON dct.id_donhang = dh.id_donhang
    JOIN sanpham sp ON dct.id_sanpham = sp.id_sanpham
    JOIN loaisanpham lsp ON sp.id_loai = lsp.id_loai
    WHERE dh.ngaytao BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'
";

if ($order_status > 0) {
    $category_query .= " AND dh.trangthai = $order_status";
}

$category_query .= " GROUP BY lsp.id_loai
                   ORDER BY category_revenue DESC";

$category_result = $conn->query($category_query);

// Mảng trạng thái đơn hàng
$order_statuses = [
    0 => 'Tất cả trạng thái',
    1 => 'Chờ xác nhận',
    2 => 'Đang xử lý',
    3 => 'Đang giao hàng',
    4 => 'Đã giao',
    5 => 'Đã hủy',
    6 => 'Hoàn trả'
];

// Đảm bảo các mảng dữ liệu cho biểu đồ không trống
if (empty($daily_labels)) $daily_labels = [date('d/m/Y')];
if (empty($daily_values)) $daily_values = [0];
if (empty($daily_counts)) $daily_counts = [0];

// Chuẩn bị dữ liệu thống kê danh mục
$category_labels = [];
$category_values = [];
$category_data = [];
$category_total = 0;

if ($category_result && $category_result->num_rows > 0) {
    while ($category = $category_result->fetch_assoc()) {
        $category_total += $category['category_revenue'];
        $category_data[] = $category;
        $category_labels[] = $category['tenloai'];
        $category_values[] = (int)$category['category_revenue'];
    }
}

if (empty($category_labels)) $category_labels = ['Không có dữ liệu'];
if (empty($category_values)) $category_values = [0];

// Giới hạn số lượng dữ liệu đồ thị để tránh quá tải trình duyệt
if (count($daily_labels) > 30) {
    $daily_labels = array_slice($daily_labels, -30);
    $daily_values = array_slice($daily_values, -30);
    $daily_counts = array_slice($daily_counts, -30);
}

if (count($category_labels) > 10) {
    $category_labels = array_slice($category_labels, 0, 10);
    $category_values = array_slice($category_values, 0, 10);
}

// Đảm bảo không có giá trị null trong mảng
$daily_labels = array_map(function($val) { return $val ?? ''; }, $daily_labels);
$daily_values = array_map(function($val) { return $val ?? 0; }, $daily_values);
$daily_counts = array_map(function($val) { return $val ?? 0; }, $daily_counts);
$category_labels = array_map(function($val) { return $val ?? 'Khác'; }, $category_labels);
$category_values = array_map(function($val) { return $val ?? 0; }, $category_values);
?>

<!-- Include sidebar -->
<?php include('includes/sidebar.php'); ?>

<!-- Main content -->
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Báo cáo doanh thu</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="reportExport">
                <i class="bi bi-download"></i> Xuất báo cáo
            </button>
        </div>
    </div>
    
    <!-- Filter Form -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-3">
                    <label for="filter_type" class="form-label">Khoảng thời gian</label>
                    <select class="form-select" id="filter_type" name="filter_type">
                        <option value="custom" <?php echo $filter_type == 'custom' ? 'selected' : ''; ?>>Tùy chỉnh</option>
                        <option value="today" <?php echo $filter_type == 'today' ? 'selected' : ''; ?>>Hôm nay</option>
                        <option value="yesterday" <?php echo $filter_type == 'yesterday' ? 'selected' : ''; ?>>Hôm qua</option>
                        <option value="this_week" <?php echo $filter_type == 'this_week' ? 'selected' : ''; ?>>Tuần này</option>
                        <option value="this_month" <?php echo $filter_type == 'this_month' ? 'selected' : ''; ?>>Tháng này</option>
                        <option value="last_month" <?php echo $filter_type == 'last_month' ? 'selected' : ''; ?>>Tháng trước</option>
                        <option value="this_year" <?php echo $filter_type == 'this_year' ? 'selected' : ''; ?>>Năm nay</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="start_date" class="form-label">Từ ngày</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                </div>
                
                <div class="col-md-3">
                    <label for="end_date" class="form-label">Đến ngày</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                </div>
                
                <div class="col-md-2">
                    <label for="order_status" class="form-label">Trạng thái đơn hàng</label>
                    <select class="form-select" id="order_status" name="order_status">
                        <?php foreach ($order_statuses as $status_id => $status_name): ?>
                            <option value="<?php echo $status_id; ?>" <?php echo $order_status == $status_id ? 'selected' : ''; ?>>
                                <?php echo $status_name; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Lọc</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Tổng doanh thu
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($total_revenue, 0, ',', '.'); ?>₫
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-currency-dollar fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Số đơn hàng
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo $total_orders; ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-cart-check fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Giá trị trung bình
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo $total_orders > 0 ? number_format($total_revenue / $total_orders, 0, ',', '.') : 0; ?>₫
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-calculator fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Khoảng thời gian
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php 
                                if ($start_date == $end_date) {
                                    echo date('d/m/Y', strtotime($start_date));
                                } else {
                                    echo date('d/m/Y', strtotime($start_date)) . ' - ' . date('d/m/Y', strtotime($end_date));
                                }
                                ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-calendar-range fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Charts Row -->
    <div class="row mb-4">
        <!-- Revenue Chart -->
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold">Biểu đồ doanh thu theo ngày</h6>
                </div>
                <div class="card-body">
                    <div style="height: 300px;">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Top Products -->
        <div class="col-md-7 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold">10 sản phẩm bán chạy nhất</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th width="5%">#</th>
                                    <th width="15%">Hình ảnh</th>
                                    <th width="40%">Tên sản phẩm</th>
                                    <th width="15%">Danh mục</th>
                                    <th width="10%">Số lượng</th>
                                    <th width="15%">Doanh thu</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $count = 1;
                                if ($top_products_result->num_rows > 0):
                                    while ($product = $top_products_result->fetch_assoc()):
                                ?>
                                <tr>
                                    <td><?php echo $count++; ?></td>
                                    <td>
                                        <?php if (!empty($product['hinhanh'])): ?>
                                            <img src="../uploads/products/<?php echo $product['hinhanh']; ?>" 
                                                 alt="<?php echo htmlspecialchars($product['tensanpham']); ?>"
                                                 class="img-thumbnail" style="width: 50px; height: 50px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="bg-light text-center p-2">
                                                <i class="bi bi-image text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="../product-detail.php?id=<?php echo $product['id_sanpham']; ?>" target="_blank">
                                            <?php echo htmlspecialchars($product['tensanpham']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo htmlspecialchars($product['tenloai'] ?? 'Không có'); ?></td>
                                    <td><?php echo number_format($product['total_quantity']); ?></td>
                                    <td><?php echo number_format($product['total_amount'], 0, ',', '.'); ?>₫</td>
                                </tr>
                                <?php 
                                    endwhile;
                                else:
                                ?>
                                <tr>
                                    <td colspan="6" class="text-center">Không có dữ liệu sản phẩm</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Categories Chart -->
        <div class="col-md-5 mb-4">
            <div class="card shadow h-100">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold">Doanh thu theo danh mục</h6>
                </div>
                <div class="card-body">
                    <div style="height: 250px;">
                        <canvas id="categoryChart"></canvas>
                    </div>
                    <div class="mt-4">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Danh mục</th>
                                        <th>Đơn hàng</th>
                                        <th>Doanh thu</th>
                                        <th>%</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    if (!empty($category_data)):
                                        foreach ($category_data as $category):
                                            $percentage = $category_total > 0 ? ($category['category_revenue'] / $category_total * 100) : 0;
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($category['tenloai']); ?></td>
                                        <td><?php echo number_format($category['order_count']); ?></td>
                                        <td><?php echo number_format($category['category_revenue'], 0, ',', '.'); ?>₫</td>
                                        <td><?php echo number_format($percentage, 1); ?>%</td>
                                    </tr>
                                    <?php 
                                        endforeach;
                                    else:
                                    ?>
                                    <tr>
                                        <td colspan="4" class="text-center">Không có dữ liệu danh mục</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php
// Prepare chart data for JavaScript
$js_daily_labels = json_encode($daily_labels);
$js_daily_values = json_encode($daily_values);
$js_daily_counts = json_encode($daily_counts);
$js_category_labels = json_encode($category_labels);
$js_category_values = json_encode($category_values);

// Add specific JavaScript for charts
$page_specific_js = <<<EOT
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Filter form behavior
    const filterType = document.getElementById("filter_type");
    const startDate = document.getElementById("start_date");
    const endDate = document.getElementById("end_date");
    
    filterType.addEventListener("change", function() {
        if (this.value !== "custom") {
            startDate.disabled = true;
            endDate.disabled = true;
        } else {
            startDate.disabled = false;
            endDate.disabled = false;
        }
    });
    
    // Initialize with current state
    if (filterType.value !== "custom") {
        startDate.disabled = true;
        endDate.disabled = true;
    }
    
    // Revenue Chart
    const revenueCtx = document.getElementById("revenueChart");
    if (revenueCtx) {
        new Chart(revenueCtx, {
            type: "line",
            data: {
                labels: {$js_daily_labels},
                datasets: [
                    {
                        label: "Doanh thu (VNĐ)",
                        data: {$js_daily_values},
                        backgroundColor: "rgba(78, 115, 223, 0.05)",
                        borderColor: "rgba(78, 115, 223, 1)",
                        pointRadius: 3,
                        pointBackgroundColor: "rgba(78, 115, 223, 1)",
                        pointBorderColor: "rgba(78, 115, 223, 1)",
                        pointHoverRadius: 5,
                        pointHoverBackgroundColor: "rgba(78, 115, 223, 1)",
                        pointHoverBorderColor: "rgba(78, 115, 223, 1)",
                        pointHitRadius: 10,
                        pointBorderWidth: 2,
                        borderWidth: 2,
                        fill: true
                    },
                    {
                        label: "Số đơn hàng",
                        data: {$js_daily_counts},
                        backgroundColor: "rgba(28, 200, 138, 0.05)",
                        borderColor: "rgba(28, 200, 138, 1)",
                        pointRadius: 3,
                        pointBackgroundColor: "rgba(28, 200, 138, 1)",
                        pointBorderColor: "rgba(28, 200, 138, 1)",
                        pointHoverRadius: 5,
                        pointHoverBackgroundColor: "rgba(28, 200, 138, 1)",
                        pointHoverBorderColor: "rgba(28, 200, 138, 1)",
                        pointHitRadius: 10,
                        pointBorderWidth: 2,
                        borderWidth: 2,
                        fill: false,
                        yAxisID: "y2"
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
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
                            callback: function(value) {
                                return new Intl.NumberFormat("vi-VN", {
                                    style: "currency",
                                    currency: "VND",
                                    minimumFractionDigits: 0,
                                    maximumFractionDigits: 0
                                }).format(value);
                            }
                        },
                        grid: {
                            color: "rgb(234, 236, 244)",
                            zeroLineColor: "rgb(234, 236, 244)",
                            drawBorder: false,
                            borderDash: [2],
                            zeroLineBorderDash: [2]
                        }
                    },
                    y2: {
                        position: "right",
                        ticks: {
                            maxTicksLimit: 5,
                            padding: 10,
                            stepSize: 1
                        },
                        grid: {
                            drawBorder: false,
                            display: false
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: "top"
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || "";
                                if (label) {
                                    label += ": ";
                                }
                                if (context.dataset.yAxisID === "y") {
                                    label += new Intl.NumberFormat("vi-VN", {
                                        style: "currency",
                                        currency: "VND",
                                        minimumFractionDigits: 0,
                                        maximumFractionDigits: 0
                                    }).format(context.parsed.y);
                                } else {
                                    label += context.parsed.y;
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Category Chart
    const categoryCtx = document.getElementById("categoryChart");
    if (categoryCtx) {
        new Chart(categoryCtx, {
            type: "doughnut",
            data: {
                labels: {$js_category_labels},
                datasets: [
                    {
                        data: {$js_category_values},
                        backgroundColor: [
                            "#4e73df", "#1cc88a", "#36b9cc", "#f6c23e", "#e74a3b", 
                            "#fd7e14", "#6f42c1", "#20c9a6", "#5a5c69", "#858796"
                        ],
                        hoverBackgroundColor: [
                            "#2e59d9", "#17a673", "#2c9faf", "#dda20a", "#be2617", 
                            "#d56308", "#5a319a", "#169782", "#40414f", "#60616f"
                        ],
                        hoverBorderColor: "rgba(234, 236, 244, 1)"
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: "bottom",
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || "";
                                if (label) {
                                    label += ": ";
                                }
                                
                                const value = context.parsed;
                                label += new Intl.NumberFormat("vi-VN", {
                                    style: "currency",
                                    currency: "VND",
                                    minimumFractionDigits: 0,
                                    maximumFractionDigits: 0
                                }).format(value);
                                
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Export Report button
    document.getElementById("reportExport")?.addEventListener("click", function() {
        window.print();
    });
});
</script>
EOT;

// Include CSS for print version
$page_specific_css = <<<EOT
<style>
@media print {
    .sidebar, 
    .navbar,
    .btn-toolbar,
    .card-header,
    form,
    footer,
    .btn,
    .modal,
    .toast-container {
        display: none !important;
    }
    
    body,
    .container-fluid,
    .row,
    .col-md-9,
    main,
    .card,
    .card-body {
        width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
    }
    
    .card {
        border: none !important;
        box-shadow: none !important;
    }
    
    .card-body {
        page-break-inside: avoid;
    }
    
    h1 {
        font-size: 22px !important;
        text-align: center;
        margin-bottom: 20px !important;
    }
    
    main {
        padding: 0 15px !important;
    }
}

.border-left-primary {
    border-left: .25rem solid #4e73df!important;
}
.border-left-success {
    border-left: .25rem solid #1cc88a!important;
}
.border-left-info {
    border-left: .25rem solid #36b9cc!important;
}
.border-left-warning {
    border-left: .25rem solid #f6c23e!important;
}
</style>
EOT;

// Include footer
include('includes/footer.php');
?>