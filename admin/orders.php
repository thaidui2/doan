<?php
// Đặt tiêu đề trang
$page_title = 'Quản lý đơn hàng';

// Include header (sẽ kiểm tra đăng nhập)
include('includes/header.php');

// Các biến lọc và tìm kiếm
$status_filter = isset($_GET['status']) ? (int)$_GET['status'] : 0;
$search_keyword = isset($_GET['search']) ? $_GET['search'] : '';

// Biến lọc thời gian
$time_filter = isset($_GET['time']) ? $_GET['time'] : 'all';

// Thiết lập phân trang
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$orders_per_page = 10; // Số đơn hàng hiển thị trên mỗi trang
$offset = ($current_page - 1) * $orders_per_page;

// Xây dựng truy vấn - Cập nhật tên bảng và các cột
$query = "SELECT * FROM donhang";

// Thêm điều kiện lọc - Cập nhật tên cột
$where_conditions = [];
if ($status_filter > 0) {
    $where_conditions[] = "trang_thai_don_hang = $status_filter";
}

if (!empty($search_keyword)) {
    $search_keyword = $conn->real_escape_string($search_keyword);
    $where_conditions[] = "(ma_donhang LIKE '%$search_keyword%' OR ho_ten LIKE '%$search_keyword%' OR sodienthoai LIKE '%$search_keyword%' OR email LIKE '%$search_keyword%')";
}

// Thêm điều kiện lọc theo thời gian - Cập nhật tên cột
if ($time_filter !== 'all') {
    $today = date('Y-m-d');
    if ($time_filter === 'today') {
        $where_conditions[] = "DATE(ngay_dat) = '$today'";
    } elseif ($time_filter === 'yesterday') {
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $where_conditions[] = "DATE(ngay_dat) = '$yesterday'";
    } elseif ($time_filter === 'week') {
        $week_start = date('Y-m-d', strtotime('-7 days'));
        $where_conditions[] = "DATE(ngay_dat) >= '$week_start'";
    } elseif ($time_filter === 'month') {
        $month_start = date('Y-m-d', strtotime('-30 days'));
        $where_conditions[] = "DATE(ngay_dat) >= '$month_start'";
    }
}

// Kết hợp các điều kiện
if (!empty($where_conditions)) {
    $query .= " WHERE " . implode(" AND ", $where_conditions);
}

// Sắp xếp và phân trang - Cập nhật tên cột
$query .= " ORDER BY id DESC LIMIT $offset, $orders_per_page";

// Thực hiện truy vấn
$result = $conn->query($query);

// Thêm truy vấn đếm tổng số đơn hàng để tính số trang - Cập nhật tên bảng
$count_query = "SELECT COUNT(*) as total FROM donhang";
if (!empty($where_conditions)) {
    $count_query .= " WHERE " . implode(" AND ", $where_conditions);
}
$count_result = $conn->query($count_query);
$total_orders = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_orders / $orders_per_page);

// Mảng trạng thái đơn hàng - Cập nhật mã trạng thái theo schema mới
$order_statuses = [
    1 => ['name' => 'Chờ xác nhận', 'badge' => 'warning'],
    2 => ['name' => 'Đã xác nhận', 'badge' => 'info'],
    3 => ['name' => 'Đang giao hàng', 'badge' => 'primary'],
    4 => ['name' => 'Đã giao', 'badge' => 'success'],
    5 => ['name' => 'Đã hủy', 'badge' => 'danger']
];
?>

<!-- Main content -->
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Quản lý đơn hàng</h1>
    </div>
    
    <!-- Filter and search -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="btn-group" role="group">
                <a href="orders.php" class="btn <?php echo $status_filter === 0 ? 'btn-dark' : 'btn-outline-dark'; ?>">
                    Tất cả
                </a>
                <?php foreach ($order_statuses as $status_id => $status): ?>
                    <a href="orders.php?status=<?php echo $status_id; ?>" 
                       class="btn <?php echo $status_filter === $status_id ? 'btn-dark' : 'btn-outline-dark'; ?>">
                        <?php echo $status['name']; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="col-md-6">
            <form action="orders.php" method="get" class="d-flex">
                <?php if ($status_filter > 0): ?>
                    <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
                <?php endif; ?>
                <input type="text" name="search" class="form-control me-2" placeholder="Tìm theo mã, tên, email, SĐT..." value="<?php echo htmlspecialchars($search_keyword); ?>">
                <button type="submit" class="btn btn-outline-primary">
                    <i class="bi bi-search"></i> Tìm kiếm
                </button>
            </form>
        </div>
    </div>
    
    <div class="row mb-3">
        <div class="col-12">
            <div class="btn-group" role="group">
                <a href="?<?php echo $status_filter > 0 ? 'status='.$status_filter.'&' : ''; ?><?php echo !empty($search_keyword) ? 'search='.urlencode($search_keyword).'&' : ''; ?>time=all" class="btn <?php echo $time_filter === 'all' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                    Tất cả
                </a>
                <a href="?<?php echo $status_filter > 0 ? 'status='.$status_filter.'&' : ''; ?><?php echo !empty($search_keyword) ? 'search='.urlencode($search_keyword).'&' : ''; ?>time=today" class="btn <?php echo $time_filter === 'today' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                    Hôm nay
                </a>
                <a href="?<?php echo $status_filter > 0 ? 'status='.$status_filter.'&' : ''; ?><?php echo !empty($search_keyword) ? 'search='.urlencode($search_keyword).'&' : ''; ?>time=yesterday" class="btn <?php echo $time_filter === 'yesterday' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                    Hôm qua
                </a>
                <a href="?<?php echo $status_filter > 0 ? 'status='.$status_filter.'&' : ''; ?><?php echo !empty($search_keyword) ? 'search='.urlencode($search_keyword).'&' : ''; ?>time=week" class="btn <?php echo $time_filter === 'week' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                    7 ngày qua
                </a>
                <a href="?<?php echo $status_filter > 0 ? 'status='.$status_filter.'&' : ''; ?><?php echo !empty($search_keyword) ? 'search='.urlencode($search_keyword).'&' : ''; ?>time=month" class="btn <?php echo $time_filter === 'month' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                    30 ngày qua
                </a>
            </div>
        </div>
    </div>
    
    <!-- Orders table -->
    <div class="card">
        <div class="card-header bg-white">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Danh sách đơn hàng</h5>
                <span class="badge bg-secondary"><?php echo $result->num_rows; ?> đơn hàng</span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">ID</th>
                            <th scope="col">Mã đơn hàng</th>
                            <th scope="col">Khách hàng</th>
                            <th scope="col">Liên hệ</th>
                            <th scope="col">Tổng tiền</th>
                            <th scope="col">Ngày đặt</th>
                            <th scope="col">Trạng thái</th>
                            <th scope="col">Thanh toán</th>
                            <th scope="col">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($order = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $order['id']; ?></td>
                                    <td><?php echo htmlspecialchars($order['ma_donhang']); ?></td>
                                    <td><?php echo htmlspecialchars($order['ho_ten']); ?></td>
                                    <td>
                                        <div><?php echo htmlspecialchars($order['sodienthoai']); ?></div>
                                        <?php if (!empty($order['email'])): ?>
                                        <div class="small text-muted"><?php echo htmlspecialchars($order['email']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo number_format($order['tong_tien'], 0, ',', '.'); ?> ₫</td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($order['ngay_dat'])); ?></td>
                                    <td>
                                        <?php 
                                        $status_id = $order['trang_thai_don_hang'];
                                        $status = $order_statuses[$status_id] ?? ['name' => 'Không xác định', 'badge' => 'secondary'];
                                        ?>
                                        <span class="badge bg-<?php echo $status['badge']; ?>">
                                            <?php echo $status['name']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $payment_method = $order['phuong_thuc_thanh_toan'];
                                        $payment_status = $order['trang_thai_thanh_toan'] ? 'Đã thanh toán' : 'Chưa thanh toán';
                                        $payment_badge = $order['trang_thai_thanh_toan'] ? 'success' : 'warning';
                                        
                                        echo '<span class="badge bg-info">' . ($payment_method == 'cod' ? 'Tiền mặt' : 
                                             ($payment_method == 'bank_transfer' ? 'Chuyển khoản' : 
                                             ($payment_method == 'momo' ? 'MoMo' : 
                                             ($payment_method == 'vnpay' ? 'VNPay' : 'Khác')))) . '</span><br>';
                                        echo '<span class="badge bg-' . $payment_badge . '">' . $payment_status . '</span>';
                                        ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="order-detail.php?id=<?php echo $order['id']; ?>" class="btn btn-outline-primary">
                                                <i class="bi bi-eye"></i> Xem
                                            </a>
                                            <button type="button" class="btn btn-outline-dark dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                                                <span class="visually-hidden">Toggle Dropdown</span>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li class="dropdown-header">Cập nhật trạng thái</li>
                                                <?php foreach ($order_statuses as $status_id => $status): ?>
                                                    <?php if ($status_id != $order['trang_thai_don_hang']): ?>
                                                        <li>
                                                            <a class="dropdown-item update-status" 
                                                               href="#" 
                                                               data-order-id="<?php echo $order['id']; ?>" 
                                                               data-status="<?php echo $status_id; ?>">
                                                                <?php echo $status['name']; ?>
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <a class="dropdown-item text-primary" href="print-order.php?id=<?php echo $order['id']; ?>" target="_blank">
                                                        <i class="bi bi-printer"></i> In đơn hàng
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center py-4">
                                    <div class="text-muted">Không tìm thấy đơn hàng nào</div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Phân trang - Cập nhật các tham số URL -->
    <div class="d-flex justify-content-center mt-4">
        <nav aria-label="Phân trang đơn hàng">
            <ul class="pagination">
                <?php if ($current_page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=1<?php echo $status_filter > 0 ? '&status='.$status_filter : ''; ?><?php echo !empty($search_keyword) ? '&search='.urlencode($search_keyword) : ''; ?>" aria-label="Trang đầu">
                            <span aria-hidden="true">&laquo;&laquo;</span>
                        </a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $current_page - 1; ?><?php echo $status_filter > 0 ? '&status='.$status_filter : ''; ?><?php echo !empty($search_keyword) ? '&search='.urlencode($search_keyword) : ''; ?>" aria-label="Trang trước">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                <?php endif; ?>
                
                <?php 
                // Hiển thị tối đa 5 trang gần nhất
                $start_page = max(1, $current_page - 2);
                $end_page = min($total_pages, $current_page + 2);
                
                for ($i = $start_page; $i <= $end_page; $i++): 
                ?>
                    <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo $status_filter > 0 ? '&status='.$status_filter : ''; ?><?php echo !empty($search_keyword) ? '&search='.urlencode($search_keyword) : ''; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                
                <?php if ($current_page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $current_page + 1; ?><?php echo $status_filter > 0 ? '&status='.$status_filter : ''; ?><?php echo !empty($search_keyword) ? '&search='.urlencode($search_keyword) : ''; ?>" aria-label="Trang sau">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $total_pages; ?><?php echo $status_filter > 0 ? '&status='.$status_filter : ''; ?><?php echo !empty($search_keyword) ? '&search='.urlencode($search_keyword) : ''; ?>" aria-label="Trang cuối">
                            <span aria-hidden="true">&raquo;&raquo;</span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</main>

<?php 
// JavaScript cụ thể cho trang này
$page_specific_js = '
<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Xử lý cập nhật trạng thái đơn hàng
        document.querySelectorAll(".update-status").forEach(function(button) {
            button.addEventListener("click", function(e) {
                e.preventDefault();
                
                const orderId = this.getAttribute("data-order-id");
                const statusId = this.getAttribute("data-status");
                const statusName = this.textContent.trim();
                
                if (confirm(`Bạn có chắc chắn muốn chuyển trạng thái đơn hàng #${orderId} thành "${statusName}"?`)) {
                    updateOrderStatus(orderId, statusId, statusName);
                }
            });
        });
        
        // Hàm cập nhật trạng thái đơn hàng qua AJAX
        function updateOrderStatus(orderId, statusId, statusName) {
            fetch("ajax/update-order.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                },
                body: `order_id=${orderId}&status=${statusId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast("Cập nhật trạng thái thành công!", "success");
                    // Reload trang sau 1 giây để hiển thị dữ liệu mới
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showToast(`Lỗi: ${data.message}`, "danger");
                }
            })
            .catch(error => {
                console.error("Error:", error);
                showToast("Đã xảy ra lỗi khi cập nhật trạng thái đơn hàng!", "danger");
            });
        }
    });
</script>';

// Include footer
include('includes/footer.php');
?>