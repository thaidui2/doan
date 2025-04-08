<?php
// Thiết lập tiêu đề trang
$page_title = "Trang Chủ Người Bán";

// Include header
include('includes/header.php');

// Lấy thông tin tổng quan
// 1. Tổng doanh thu
$revenue_query = $conn->prepare("
    SELECT COALESCE(SUM(dc.thanh_tien), 0) as total_revenue 
    FROM donhang_chitiet dc
    JOIN donhang dh ON dc.id_donhang = dh.id_donhang
    JOIN sanpham sp ON dc.id_sanpham = sp.id_sanpham
    WHERE sp.id_nguoiban = ? AND dh.trangthai = 4
");
$revenue_query->bind_param("i", $user_id);
$revenue_query->execute();
$total_revenue = $revenue_query->get_result()->fetch_assoc()['total_revenue'];

// 2. Tổng số sản phẩm
$products_query = $conn->prepare("SELECT COUNT(*) as total FROM sanpham WHERE id_nguoiban = ?");
$products_query->bind_param("i", $user_id);
$products_query->execute();
$total_products = $products_query->get_result()->fetch_assoc()['total'];

// 3. Tổng số đơn hàng
$orders_query = $conn->prepare("
    SELECT COUNT(DISTINCT dh.id_donhang) as total_orders
    FROM donhang dh
    JOIN donhang_chitiet dc ON dh.id_donhang = dc.id_donhang
    JOIN sanpham sp ON dc.id_sanpham = sp.id_sanpham
    WHERE sp.id_nguoiban = ?
");
$orders_query->bind_param("i", $user_id);
$orders_query->execute();
$total_orders = $orders_query->get_result()->fetch_assoc()['total_orders'];

// 4. Đơn hàng đang chờ xử lý
$pending_orders_query = $conn->prepare("
    SELECT COUNT(DISTINCT dh.id_donhang) as pending_orders
    FROM donhang dh
    JOIN donhang_chitiet dc ON dh.id_donhang = dc.id_donhang
    JOIN sanpham sp ON dc.id_sanpham = sp.id_sanpham
    WHERE sp.id_nguoiban = ? AND dh.trangthai IN (1, 2)
");
$pending_orders_query->bind_param("i", $user_id);
$pending_orders_query->execute();
$pending_orders = $pending_orders_query->get_result()->fetch_assoc()['pending_orders'];

// Lấy đơn hàng mới nhất
$latest_orders_query = $conn->prepare("
    SELECT DISTINCT dh.id_donhang, dh.ngaytao, dh.tongtien, dh.trangthai,
    (
        SELECT GROUP_CONCAT(DISTINCT sp.tensanpham SEPARATOR ', ')
        FROM donhang_chitiet dc
        JOIN sanpham sp ON dc.id_sanpham = sp.id_sanpham
        WHERE dc.id_donhang = dh.id_donhang AND sp.id_nguoiban = ?
    ) as products_sold
    FROM donhang dh
    JOIN donhang_chitiet dc ON dh.id_donhang = dc.id_donhang
    JOIN sanpham sp ON dc.id_sanpham = sp.id_sanpham
    WHERE sp.id_nguoiban = ?
    ORDER BY dh.ngaytao DESC
    LIMIT 5
");
$latest_orders_query->bind_param("ii", $user_id, $user_id);
$latest_orders_query->execute();
$latest_orders = $latest_orders_query->get_result();

// Lấy sản phẩm bán chạy nhất
$top_products_query = $conn->prepare("
    SELECT sp.id_sanpham, sp.tensanpham, sp.hinhanh, sp.gia,
    SUM(dc.soluong) as total_sold,
    SUM(dc.thanh_tien) as total_revenue
    FROM donhang_chitiet dc
    JOIN sanpham sp ON dc.id_sanpham = sp.id_sanpham
    JOIN donhang dh ON dc.id_donhang = dh.id_donhang
    WHERE sp.id_nguoiban = ? AND dh.trangthai = 4
    GROUP BY sp.id_sanpham
    ORDER BY total_sold DESC
    LIMIT 5
");
$top_products_query->bind_param("i", $user_id);
$top_products_query->execute();
$top_products = $top_products_query->get_result();

// Đánh giá sản phẩm gần đây
$recent_reviews_query = $conn->prepare("
    SELECT dg.*, sp.tensanpham, u.tenuser
    FROM danhgia dg
    JOIN sanpham sp ON dg.id_sanpham = sp.id_sanpham
    JOIN users u ON dg.id_user = u.id_user
    WHERE sp.id_nguoiban = ?
    ORDER BY dg.ngaydanhgia DESC
    LIMIT 5
");
$recent_reviews_query->bind_param("i", $user_id);
$recent_reviews_query->execute();
$recent_reviews = $recent_reviews_query->get_result();

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
    <h1 class="h2">Tổng quan cửa hàng</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="them-san-pham.php" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-plus-lg"></i> Thêm sản phẩm
            </a>
        </div>
    </div>
</div>

<?php if (isset($_SESSION['success_message'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle me-2"></i> <?php echo $_SESSION['success_message']; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php unset($_SESSION['success_message']); ?>
<?php endif; ?>

<!-- Thẻ thống kê -->
<div class="row">
    <div class="col-md-3 mb-4">
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

    <div class="col-md-3 mb-4">
        <div class="card border-left-info h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs fw-bold text-info text-uppercase mb-1">Sản phẩm</div>
                        <div class="h5 mb-0 fw-bold text-gray-800"><?php echo $total_products; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-box-seam fs-2 text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-4">
        <div class="card border-left-warning h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs fw-bold text-warning text-uppercase mb-1">Đơn hàng đã bán</div>
                        <div class="h5 mb-0 fw-bold text-gray-800"><?php echo $total_orders; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-receipt fs-2 text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 mb-4">
        <div class="card border-left-danger h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs fw-bold text-danger text-uppercase mb-1">Đơn hàng chờ xử lý</div>
                        <div class="h5 mb-0 fw-bold text-gray-800"><?php echo $pending_orders; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-hourglass-split fs-2 text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Row cho đơn hàng mới nhất và sản phẩm bán chạy -->
<div class="row">
    <div class="col-lg-8 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold">Đơn hàng gần đây</h6>
                <a href="don-hang.php" class="btn btn-sm btn-primary">Xem tất cả</a>
            </div>
            <div class="card-body p-0">
                <?php if ($latest_orders->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID Đơn</th>
                                <th>Ngày đặt</th>
                                <th>Sản phẩm</th>
                                <th>Tổng tiền</th>
                                <th>Trạng thái</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($order = $latest_orders->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $order['id_donhang']; ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($order['ngaytao'])); ?></td>
                                <td>
                                    <?php 
                                    $products = explode(', ', $order['products_sold']); 
                                    echo count($products) > 1 ? 
                                         htmlspecialchars(substr($products[0], 0, 20) . '... (+' . (count($products) - 1) . ' SP)') : 
                                         htmlspecialchars(substr($products[0], 0, 30)); 
                                    ?>
                                </td>
                                <td><?php echo number_format($order['tongtien'], 0, ',', '.'); ?> VNĐ</td>
                                <td>
                                    <span class="badge bg-<?php echo $order_statuses[$order['trangthai']]['badge']; ?>">
                                        <?php echo $order_statuses[$order['trangthai']]['name']; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="don-hang-chi-tiet.php?id=<?php echo $order['id_donhang']; ?>" class="btn btn-sm btn-outline-primary">
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
    </div>
    
    <div class="col-lg-4 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold">Sản phẩm bán chạy</h6>
                <a href="thong-ke.php" class="btn btn-sm btn-primary">Chi tiết</a>
            </div>
            <div class="card-body p-0">
                <?php if ($top_products->num_rows > 0): ?>
                <ul class="list-group list-group-flush">
                    <?php while ($product = $top_products->fetch_assoc()): ?>
                    <li class="list-group-item">
                        <div class="d-flex align-items-center">
                            <img src="<?php echo !empty($product['hinhanh']) ? '../uploads/products/'.$product['hinhanh'] : '../images/no-image.png'; ?>" 
                                 alt="<?php echo htmlspecialchars($product['tensanpham']); ?>" 
                                 class="rounded me-3" style="width: 50px; height: 50px; object-fit: cover;">
                            <div class="flex-grow-1">
                                <h6 class="mb-0 text-truncate"><?php echo htmlspecialchars($product['tensanpham']); ?></h6>
                                <div class="small text-muted">
                                    <span class="fw-bold"><?php echo $product['total_sold']; ?></span> đã bán | 
                                    <span class="fw-bold"><?php echo number_format($product['gia'], 0, ',', '.'); ?> VNĐ</span>
                                </div>
                            </div>
                            <div class="ms-2 text-end">
                                <div class="text-success fw-bold">
                                    <?php echo number_format($product['total_revenue'], 0, ',', '.'); ?>đ
                                </div>
                                <a href="chinh-sua-san-pham.php?id=<?php echo $product['id_sanpham']; ?>" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-pencil"></i>
                                </a>
                            </div>
                        </div>
                    </li>
                    <?php endwhile; ?>
                </ul>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-bar-chart fs-1 text-muted"></i>
                    <p class="mt-3 text-muted">Chưa có dữ liệu bán hàng</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Reviews gần đây -->
<div class="row">
    <div class="col-12 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold">Đánh giá gần đây</h6>
                <a href="danh-gia.php" class="btn btn-sm btn-primary">Xem tất cả</a>
            </div>
            <div class="card-body p-0">
                <?php if ($recent_reviews->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Sản phẩm</th>
                                <th>Khách hàng</th>
                                <th>Đánh giá</th>
                                <th>Nhận xét</th>
                                <th>Ngày đánh giá</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($review = $recent_reviews->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($review['tensanpham']); ?></td>
                                <td><?php echo htmlspecialchars($review['tenuser']); ?></td>
                                <td>
                                    <?php
                                    for ($i = 1; $i <= 5; $i++) {
                                        echo $i <= $review['diemdanhgia'] 
                                            ? '<i class="bi bi-star-fill text-warning"></i>' 
                                            : '<i class="bi bi-star text-muted"></i>';
                                    }
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars(mb_strimwidth($review['noidung'], 0, 100, "...")); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($review['ngaydanhgia'])); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-star fs-1 text-muted"></i>
                    <p class="mt-3 text-muted">Chưa có đánh giá nào</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Biểu đồ và thống kê -->
<div class="row">
    <div class="col-12 mb-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h6 class="m-0 fw-bold">Thống kê doanh thu 7 ngày qua</h6>
            </div>
            <div class="card-body">
                <canvas id="revenueChart" width="400" height="150"></canvas>
            </div>
        </div>
    </div>
</div>

<?php
// Lấy dữ liệu doanh thu 7 ngày gần đây
$days = 7;
$revenue_data = [];
$date_labels = [];

for ($i = $days - 1; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $date_labels[] = date('d/m', strtotime("-$i days"));
    
    $daily_revenue_query = $conn->prepare("
        SELECT COALESCE(SUM(dc.thanh_tien), 0) as daily_revenue
        FROM donhang_chitiet dc
        JOIN donhang dh ON dc.id_donhang = dh.id_donhang
        JOIN sanpham sp ON dc.id_sanpham = sp.id_sanpham
        WHERE sp.id_nguoiban = ? 
          AND dh.trangthai = 4
          AND DATE(dh.ngaytao) = ?
    ");
    $daily_revenue_query->bind_param("is", $user_id, $date);
    $daily_revenue_query->execute();
    $result = $daily_revenue_query->get_result();
    $revenue_data[] = $result->fetch_assoc()['daily_revenue'];
}

$revenue_json = json_encode($revenue_data);
$date_labels_json = json_encode($date_labels);
?>

<?php
$page_specific_js = "
<script src='https://cdn.jsdelivr.net/npm/chart.js'></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Biểu đồ doanh thu
    const revenueData = $revenue_json;
    const dateLabels = $date_labels_json;
    
    const ctx = document.getElementById('revenueChart').getContext('2d');
    const revenueChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: dateLabels,
            datasets: [{
                label: 'Doanh thu (VNĐ)',
                data: revenueData,
                backgroundColor: 'rgba(78, 115, 223, 0.05)',
                borderColor: 'rgba(78, 115, 223, 1)',
                pointRadius: 3,
                pointBackgroundColor: 'rgba(78, 115, 223, 1)',
                pointBorderColor: 'rgba(78, 115, 223, 1)',
                pointHoverRadius: 5,
                pointHoverBackgroundColor: 'rgba(78, 115, 223, 1)',
                pointHoverBorderColor: 'rgba(78, 115, 223, 1)',
                pointHitRadius: 10,
                pointBorderWidth: 2,
                borderWidth: 2,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return new Intl.NumberFormat('vi-VN').format(value) + ' đ';
                        }
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Doanh thu: ' + new Intl.NumberFormat('vi-VN').format(context.raw) + ' đ';
                        }
                    }
                }
            }
        }
    });
});
</script>
";
include('includes/footer.php');
?>
