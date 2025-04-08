<?php
// Thiết lập tiêu đề trang
$page_title = "Thống kê doanh thu";

// Include header
include('includes/header.php');

// Khởi tạo các biến lọc
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); // Mặc định là ngày đầu tháng hiện tại
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d'); // Mặc định là ngày hiện tại
$category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$period = isset($_GET['period']) ? $_GET['period'] : 'monthly';

// Lấy danh sách danh mục sản phẩm
$categories_query = $conn->query("SELECT * FROM loaisanpham WHERE trangthai = 1 ORDER BY tenloai");
$categories = [];
while ($cat = $categories_query->fetch_assoc()) {
    $categories[] = $cat;
}

// Tổng doanh thu
$total_revenue_query = $conn->prepare("
    SELECT COALESCE(SUM(dc.thanh_tien), 0) as total_revenue
    FROM donhang_chitiet dc
    JOIN donhang dh ON dc.id_donhang = dh.id_donhang
    JOIN sanpham sp ON dc.id_sanpham = sp.id_sanpham
    WHERE sp.id_nguoiban = ? AND dh.trangthai = 4
    AND (dh.ngaytao BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY))
");
$total_revenue_query->bind_param("iss", $user_id, $start_date, $end_date);
$total_revenue_query->execute();
$total_revenue = $total_revenue_query->get_result()->fetch_assoc()['total_revenue'];

// Tổng số đơn hàng hoàn thành
$total_orders_query = $conn->prepare("
    SELECT COUNT(DISTINCT dh.id_donhang) as total_orders
    FROM donhang dh
    JOIN donhang_chitiet dc ON dh.id_donhang = dc.id_donhang
    JOIN sanpham sp ON dc.id_sanpham = sp.id_sanpham
    WHERE sp.id_nguoiban = ? AND dh.trangthai = 4
    AND (dh.ngaytao BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY))
");
$total_orders_query->bind_param("iss", $user_id, $start_date, $end_date);
$total_orders_query->execute();
$total_orders = $total_orders_query->get_result()->fetch_assoc()['total_orders'];

// Tổng số sản phẩm đã bán
$total_products_sold_query = $conn->prepare("
    SELECT COALESCE(SUM(dc.soluong), 0) as total_sold
    FROM donhang_chitiet dc
    JOIN donhang dh ON dc.id_donhang = dh.id_donhang
    JOIN sanpham sp ON dc.id_sanpham = sp.id_sanpham
    WHERE sp.id_nguoiban = ? AND dh.trangthai = 4
    AND (dh.ngaytao BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY))
");
$total_products_sold_query->bind_param("iss", $user_id, $start_date, $end_date);
$total_products_sold_query->execute();
$total_products_sold = $total_products_sold_query->get_result()->fetch_assoc()['total_sold'];

// Giá trị đơn hàng trung bình
$avg_order_value = $total_orders > 0 ? $total_revenue / $total_orders : 0;

// Doanh thu theo danh mục
$category_revenue_query = $conn->prepare("
    SELECT 
        lsp.id_loai, 
        lsp.tenloai, 
        COALESCE(SUM(dc.thanh_tien), 0) as category_revenue,
        COUNT(DISTINCT dh.id_donhang) as order_count,
        COALESCE(SUM(dc.soluong), 0) as quantity_sold
    FROM loaisanpham lsp
    LEFT JOIN sanpham sp ON lsp.id_loai = sp.id_loai AND sp.id_nguoiban = ?
    LEFT JOIN donhang_chitiet dc ON sp.id_sanpham = dc.id_sanpham
    LEFT JOIN donhang dh ON dc.id_donhang = dh.id_donhang AND dh.trangthai = 4 
        AND (dh.ngaytao BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY))
    GROUP BY lsp.id_loai
    ORDER BY category_revenue DESC
");
$category_revenue_query->bind_param("iss", $user_id, $start_date, $end_date);
$category_revenue_query->execute();
$category_revenue_result = $category_revenue_query->get_result();

// Top sản phẩm bán chạy nhất
$top_products_query = $conn->prepare("
    SELECT 
        sp.id_sanpham,
        sp.tensanpham,
        sp.hinhanh,
        sp.gia,
        COALESCE(SUM(dc.soluong), 0) as quantity_sold,
        COALESCE(SUM(dc.thanh_tien), 0) as total_revenue
    FROM sanpham sp
    JOIN donhang_chitiet dc ON sp.id_sanpham = dc.id_sanpham
    JOIN donhang dh ON dc.id_donhang = dh.id_donhang
    WHERE sp.id_nguoiban = ? AND dh.trangthai = 4
    AND (dh.ngaytao BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY))
    GROUP BY sp.id_sanpham
    ORDER BY quantity_sold DESC
    LIMIT 5
");
$top_products_query->bind_param("iss", $user_id, $start_date, $end_date);
$top_products_query->execute();
$top_products_result = $top_products_query->get_result();

// Dữ liệu doanh thu theo thời gian cho biểu đồ
$chart_data = [];
$chart_labels = [];

switch ($period) {
    case 'daily':
        // Doanh thu theo ngày trong khoảng thời gian
        $period_query = $conn->prepare("
            SELECT 
                DATE(dh.ngaytao) as date,
                COALESCE(SUM(dc.thanh_tien), 0) as revenue
            FROM donhang dh
            JOIN donhang_chitiet dc ON dh.id_donhang = dc.id_donhang
            JOIN sanpham sp ON dc.id_sanpham = sp.id_sanpham
            WHERE sp.id_nguoiban = ? AND dh.trangthai = 4
            AND (dh.ngaytao BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY))
            GROUP BY DATE(dh.ngaytao)
            ORDER BY date
        ");
        $period_query->bind_param("iss", $user_id, $start_date, $end_date);
        break;

    case 'weekly':
        // Doanh thu theo tuần
        $period_query = $conn->prepare("
            SELECT 
                YEAR(dh.ngaytao) as year,
                WEEK(dh.ngaytao, 1) as week,
                CONCAT('Tuần ', WEEK(dh.ngaytao, 1), ' (', YEAR(dh.ngaytao), ')') as date_label,
                COALESCE(SUM(dc.thanh_tien), 0) as revenue
            FROM donhang dh
            JOIN donhang_chitiet dc ON dh.id_donhang = dc.id_donhang
            JOIN sanpham sp ON dc.id_sanpham = sp.id_sanpham
            WHERE sp.id_nguoiban = ? AND dh.trangthai = 4
            AND (dh.ngaytao BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY))
            GROUP BY year, week
            ORDER BY year, week
        ");
        $period_query->bind_param("iss", $user_id, $start_date, $end_date);
        break;

    case 'yearly':
        // Doanh thu theo năm
        $period_query = $conn->prepare("
            SELECT 
                YEAR(dh.ngaytao) as date,
                COALESCE(SUM(dc.thanh_tien), 0) as revenue
            FROM donhang dh
            JOIN donhang_chitiet dc ON dh.id_donhang = dc.id_donhang
            JOIN sanpham sp ON dc.id_sanpham = sp.id_sanpham
            WHERE sp.id_nguoiban = ? AND dh.trangthai = 4
            AND (dh.ngaytao BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY))
            GROUP BY YEAR(dh.ngaytao)
            ORDER BY date
        ");
        $period_query->bind_param("iss", $user_id, $start_date, $end_date);
        break;

    default: // monthly
        // Doanh thu theo tháng
        $period_query = $conn->prepare("
            SELECT 
                DATE_FORMAT(dh.ngaytao, '%Y-%m') as month_key,
                DATE_FORMAT(dh.ngaytao, '%m/%Y') as date,
                COALESCE(SUM(dc.thanh_tien), 0) as revenue
            FROM donhang dh
            JOIN donhang_chitiet dc ON dh.id_donhang = dc.id_donhang
            JOIN sanpham sp ON dc.id_sanpham = sp.id_sanpham
            WHERE sp.id_nguoiban = ? AND dh.trangthai = 4
            AND (dh.ngaytao BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY))
            GROUP BY month_key
            ORDER BY month_key
        ");
        $period_query->bind_param("iss", $user_id, $start_date, $end_date);
}

$period_query->execute();
$period_result = $period_query->get_result();

while ($row = $period_result->fetch_assoc()) {
    // Xử lý khóa date theo từng loại period
    if ($period === 'weekly') {
        $chart_labels[] = $row['date_label'];
    } else if ($period === 'daily') {
        $chart_labels[] = date('d/m', strtotime($row['date']));
    } else {
        $chart_labels[] = $row['date'];
    }
    $chart_data[] = $row['revenue'];
}

// Chuyển đổi dữ liệu sang JSON để sử dụng trong JavaScript
$chart_labels_json = json_encode($chart_labels);
$chart_data_json = json_encode($chart_data);

// Danh sách đơn hàng gần đây đã hoàn thành
$recent_orders_query = $conn->prepare("
    SELECT 
        dh.id_donhang,
        dh.ngaytao,
        dh.tennguoinhan,
        COUNT(DISTINCT dc.id_sanpham) as product_count,
        SUM(dc.thanh_tien) as total_amount
    FROM donhang dh
    JOIN donhang_chitiet dc ON dh.id_donhang = dc.id_donhang
    JOIN sanpham sp ON dc.id_sanpham = sp.id_sanpham
    WHERE sp.id_nguoiban = ? AND dh.trangthai = 4
    GROUP BY dh.id_donhang
    ORDER BY dh.ngaytao DESC
    LIMIT 10
");
$recent_orders_query->bind_param("i", $user_id);
$recent_orders_query->execute();
$recent_orders_result = $recent_orders_query->get_result();

// Doanh thu theo tháng so với tháng trước
$current_month = date('Y-m');
$prev_month = date('Y-m', strtotime('-1 month'));

// Doanh thu tháng hiện tại
$current_month_query = $conn->prepare("
    SELECT COALESCE(SUM(dc.thanh_tien), 0) as month_revenue
    FROM donhang_chitiet dc
    JOIN donhang dh ON dc.id_donhang = dh.id_donhang
    JOIN sanpham sp ON dc.id_sanpham = sp.id_sanpham
    WHERE sp.id_nguoiban = ? AND dh.trangthai = 4
    AND DATE_FORMAT(dh.ngaytao, '%Y-%m') = ?
");
$current_month_query->bind_param("is", $user_id, $current_month);
$current_month_query->execute();
$current_month_revenue = $current_month_query->get_result()->fetch_assoc()['month_revenue'];

// Doanh thu tháng trước
$prev_month_query = $conn->prepare("
    SELECT COALESCE(SUM(dc.thanh_tien), 0) as month_revenue
    FROM donhang_chitiet dc
    JOIN donhang dh ON dc.id_donhang = dh.id_donhang
    JOIN sanpham sp ON dc.id_sanpham = sp.id_sanpham
    WHERE sp.id_nguoiban = ? AND dh.trangthai = 4
    AND DATE_FORMAT(dh.ngaytao, '%Y-%m') = ?
");
$prev_month_query->bind_param("is", $user_id, $prev_month);
$prev_month_query->execute();
$prev_month_revenue = $prev_month_query->get_result()->fetch_assoc()['month_revenue'];

// Tính phần trăm tăng/giảm
$revenue_change = 0;
$revenue_change_percent = 0;
if ($prev_month_revenue > 0) {
    $revenue_change = $current_month_revenue - $prev_month_revenue;
    $revenue_change_percent = ($revenue_change / $prev_month_revenue) * 100;
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Thống kê doanh thu</h1>
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

<!-- Bộ lọc -->
<div class="card mb-4">
    <div class="card-header bg-white">
        <h5 class="mb-0">Bộ lọc thống kê</h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="start_date" class="form-label">Từ ngày</label>
                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
            </div>
            <div class="col-md-3">
                <label for="end_date" class="form-label">Đến ngày</label>
                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
            </div>
            <div class="col-md-3">
                <label for="category" class="form-label">Danh mục</label>
                <select class="form-select" id="category" name="category">
                    <option value="0">Tất cả danh mục</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat['id_loai']; ?>" <?php echo $category == $cat['id_loai'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat['tenloai']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="period" class="form-label">Hiển thị dữ liệu theo</label>
                <select class="form-select" id="period" name="period">
                    <option value="daily" <?php echo $period == 'daily' ? 'selected' : ''; ?>>Ngày</option>
                    <option value="weekly" <?php echo $period == 'weekly' ? 'selected' : ''; ?>>Tuần</option>
                    <option value="monthly" <?php echo $period == 'monthly' ? 'selected' : ''; ?>>Tháng</option>
                    <option value="yearly" <?php echo $period == 'yearly' ? 'selected' : ''; ?>>Năm</option>
                </select>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-filter me-1"></i> Lọc dữ liệu
                </button>
                <a href="doanh-thu.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-repeat me-1"></i> Đặt lại
                </a>
                <button type="button" class="btn btn-success export-excel float-end">
                    <i class="bi bi-file-excel me-1"></i> Xuất Excel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Thẻ thông tin tổng quan -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs fw-bold text-primary text-uppercase mb-1">Doanh thu</div>
                        <div class="h5 mb-0 fw-bold text-gray-800"><?php echo number_format($total_revenue, 0, ',', '.'); ?> VNĐ</div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-currency-dollar fs-2 text-gray-300"></i>
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
                        <div class="text-xs fw-bold text-success text-uppercase mb-1">Đơn hàng</div>
                        <div class="h5 mb-0 fw-bold text-gray-800"><?php echo number_format($total_orders); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-receipt fs-2 text-gray-300"></i>
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
                        <div class="text-xs fw-bold text-info text-uppercase mb-1">Sản phẩm đã bán</div>
                        <div class="h5 mb-0 fw-bold text-gray-800"><?php echo number_format($total_products_sold); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-box-seam fs-2 text-gray-300"></i>
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
                        <div class="text-xs fw-bold text-warning text-uppercase mb-1">Giá trị đơn TB</div>
                        <div class="h5 mb-0 fw-bold text-gray-800"><?php echo number_format($avg_order_value, 0, ',', '.'); ?> VNĐ</div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-graph-up fs-2 text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- So sánh với tháng trước -->
<div class="card mb-4">
    <div class="card-header bg-white">
        <h5 class="mb-0">Doanh thu tháng <?php echo date('m/Y'); ?> so với tháng trước</h5>
    </div>
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-md-6">
                <div class="d-flex align-items-center">
                    <div>
                        <h4 class="mb-0"><?php echo number_format($current_month_revenue, 0, ',', '.'); ?> VNĐ</h4>
                        <p class="text-muted">Tháng <?php echo date('m/Y'); ?></p>
                    </div>
                    <?php if ($revenue_change != 0): ?>
                    <div class="ms-3">
                        <span class="badge <?php echo $revenue_change > 0 ? 'bg-success' : 'bg-danger'; ?> fs-6">
                            <?php echo $revenue_change > 0 ? '+' : ''; ?><?php echo number_format($revenue_change_percent, 1); ?>%
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="mt-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span>Tháng trước (<?php echo date('m/Y', strtotime('-1 month')); ?>):</span>
                        <span><?php echo number_format($prev_month_revenue, 0, ',', '.'); ?> VNĐ</span>
                    </div>
                    <?php if ($revenue_change != 0): ?>
                    <div class="d-flex justify-content-between">
                        <span>Chênh lệch:</span>
                        <span class="<?php echo $revenue_change > 0 ? 'text-success' : 'text-danger'; ?>">
                            <?php echo $revenue_change > 0 ? '+' : ''; ?><?php echo number_format($revenue_change, 0, ',', '.'); ?> VNĐ
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-6">
                <div class="progress" style="height: 30px;">
                    <?php if ($prev_month_revenue > 0): ?>
                        <?php if ($current_month_revenue > $prev_month_revenue): ?>
                            <div class="progress-bar bg-success" role="progressbar" style="width: 100%" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100">
                                <?php echo number_format($revenue_change_percent, 1); ?>% ↑
                            </div>
                        <?php else: ?>
                            <div class="progress-bar bg-danger" role="progressbar" 
                                style="width: <?php echo max(($current_month_revenue / $prev_month_revenue) * 100, 10); ?>%" 
                                aria-valuenow="<?php echo ($current_month_revenue / $prev_month_revenue) * 100; ?>" 
                                aria-valuemin="0" aria-valuemax="100">
                                <?php echo number_format($revenue_change_percent, 1); ?>% ↓
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="progress-bar bg-info" role="progressbar" style="width: 100%" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100">
                            Không có dữ liệu tháng trước
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Biểu đồ doanh thu -->
<div class="row">
    <div class="col-lg-8 mb-4">
        <div class="card h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0">Biểu đồ doanh thu</h5>
            </div>
            <div class="card-body">
                <canvas id="revenueChart" height="300"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0">Doanh thu theo danh mục</h5>
            </div>
            <div class="card-body">
                <?php if ($category_revenue_result->num_rows > 0): ?>
                    <canvas id="categoryChart" height="300"></canvas>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-pie-chart fs-1 text-muted"></i>
                        <p class="mt-3 text-muted">Chưa có dữ liệu doanh thu theo danh mục</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Top sản phẩm bán chạy -->
<div class="card mb-4">
    <div class="card-header bg-white">
        <h5 class="mb-0">Top sản phẩm bán chạy</h5>
    </div>
    <div class="card-body">
        <?php if ($top_products_result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th style="width: 60px;"></th>
                            <th>Sản phẩm</th>
                            <th>Giá</th>
                            <th>Số lượng đã bán</th>
                            <th>Doanh thu</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($product = $top_products_result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <img src="../uploads/products/<?php echo $product['hinhanh']; ?>" 
                                         alt="<?php echo htmlspecialchars($product['tensanpham']); ?>"
                                         class="img-thumbnail" style="width: 50px; height: 50px; object-fit: cover;">
                                </td>
                                <td>
                                    <a href="../product-detail.php?id=<?php echo $product['id_sanpham']; ?>" target="_blank" class="text-decoration-none">
                                        <?php echo htmlspecialchars($product['tensanpham']); ?>
                                    </a>
                                </td>
                                <td><?php echo number_format($product['gia'], 0, ',', '.'); ?> VNĐ</td>
                                <td><?php echo number_format($product['quantity_sold']); ?></td>
                                <td><?php echo number_format($product['total_revenue'], 0, ',', '.'); ?> VNĐ</td>
                                <td>
                                    <a href="chinh-sua-san-pham.php?id=<?php echo $product['id_sanpham']; ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-bag fs-1 text-muted"></i>
                <p class="mt-3 text-muted">Chưa có dữ liệu sản phẩm bán chạy</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Doanh thu theo danh mục chi tiết -->
<div class="card mb-4">
    <div class="card-header bg-white">
        <h5 class="mb-0">Doanh thu theo danh mục chi tiết</h5>
    </div>
    <div class="card-body">
        <?php if ($category_revenue_result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Danh mục</th>
                            <th>Số đơn hàng</th>
                            <th>Số sản phẩm đã bán</th>
                            <th>Doanh thu</th>
                            <th>% Tổng doanh thu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $category_revenue_result->data_seek(0);
                        while ($category = $category_revenue_result->fetch_assoc()): 
                            // Tính phần trăm đóng góp vào tổng doanh thu
                            $percentage = $total_revenue > 0 ? ($category['category_revenue'] / $total_revenue) * 100 : 0;
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($category['tenloai']); ?></td>
                                <td><?php echo number_format($category['order_count']); ?></td>
                                <td><?php echo number_format($category['quantity_sold']); ?></td>
                                <td><?php echo number_format($category['category_revenue'], 0, ',', '.'); ?> VNĐ</td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="progress flex-grow-1 me-2" style="height: 8px;">
                                            <div class="progress-bar" role="progressbar" style="width: <?php echo number_format($percentage, 1); ?>%;" 
                                                aria-valuenow="<?php echo number_format($percentage, 1); ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                        <span class="text-muted small"><?php echo number_format($percentage, 1); ?>%</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-folder fs-1 text-muted"></i>
                <p class="mt-3 text-muted">Chưa có dữ liệu doanh thu theo danh mục</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Đơn hàng gần đây -->
<div class="card mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Đơn hàng gần đây</h5>
        <a href="don-hang.php" class="btn btn-sm btn-primary">
            <i class="bi bi-list-ul me-1"></i> Xem tất cả
        </a>
    </div>
    <div class="card-body">
        <?php if ($recent_orders_result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Mã đơn</th>
                            <th>Người nhận</th>
                            <th>Ngày đặt</th>
                            <th>Số SP</th>
                            <th>Tổng tiền</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($order = $recent_orders_result->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $order['id_donhang']; ?></td>
                                <td><?php echo htmlspecialchars($order['tennguoinhan']); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($order['ngaytao'])); ?></td>
                                <td><?php echo $order['product_count']; ?></td>
                                <td><?php echo number_format($order['total_amount'], 0, ',', '.'); ?> VNĐ</td>
                                <td>
                                    <a href="don-hang-chi-tiet.php?id=<?php echo $order['id_donhang']; ?>" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-center py-5">
                <i class="bi bi-receipt fs-1 text-muted"></i>
                <p class="mt-3 text-muted">Chưa có đơn hàng nào</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$category_data = [];
$category_labels = [];

$category_revenue_result->data_seek(0);
while ($category = $category_revenue_result->fetch_assoc()) {
    if ($category['category_revenue'] > 0) {
        $category_labels[] = $category['tenloai'];
        $category_data[] = $category['category_revenue'];
    }
}

$category_labels_json = json_encode($category_labels);
$category_data_json = json_encode($category_data);

// Custom JS for this page
$page_specific_js = '
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Biểu đồ doanh thu theo thời gian
    const ctx = document.getElementById("revenueChart").getContext("2d");
    const revenueChart = new Chart(ctx, {
        type: "line",
        data: {
            labels: ' . $chart_labels_json . ',
            datasets: [{
                label: "Doanh thu (VNĐ)",
                data: ' . $chart_data_json . ',
                borderColor: "rgba(78, 115, 223, 1)",
                backgroundColor: "rgba(78, 115, 223, 0.05)",
                pointBackgroundColor: "rgba(78, 115, 223, 1)",
                pointBorderColor: "#fff",
                pointHoverBackgroundColor: "#fff",
                pointHoverBorderColor: "rgba(78, 115, 223, 1)",
                borderWidth: 2,
                fill: true,
                tension: 0.1
            }]
        },
        options: {
            maintainAspectRatio: false,
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ": " + new Intl.NumberFormat("vi-VN").format(context.raw) + " VNĐ";
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return new Intl.NumberFormat("vi-VN", {
                                style: "currency",
                                currency: "VND",
                                maximumFractionDigits: 0
                            }).format(value);
                        }
                    }
                }
            }
        }
    });

    // Biểu đồ doanh thu theo danh mục
    const categoryLabels = ' . $category_labels_json . ';
    const categoryData = ' . $category_data_json . ';
    
    if (categoryLabels.length > 0) {
        const ctxCategory = document.getElementById("categoryChart").getContext("2d");
        const categoryChart = new Chart(ctxCategory, {
            type: "pie",
            data: {
                labels: categoryLabels,
                datasets: [{
                    data: categoryData,
                    backgroundColor: [
                        "#4e73df", "#1cc88a", "#36b9cc", "#f6c23e", "#e74a3b",
                        "#6f42c1", "#5a5c69", "#e83e8c", "#20c9a6", "#fd7e14"
                    ],
                    hoverOffset: 5,
                    borderWidth: 1
                }]
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ": " + new Intl.NumberFormat("vi-VN").format(context.raw) + " VNĐ";
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Xuất Excel
    document.querySelector(".export-excel").addEventListener("click", function() {
        window.location.href = "export/export-revenue.php?start_date=' . $start_date . '&end_date=' . $end_date . '&category=' . $category . '";
    });
});
</script>
';

include('includes/footer.php');
?>