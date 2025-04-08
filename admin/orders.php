<?php
// Đặt tiêu đề trang
$page_title = 'Quản lý đơn hàng';

// Include header (sẽ kiểm tra đăng nhập)
include('includes/header.php');

// Include kết nối database
include('../config/config.php');

// Các biến lọc và tìm kiếm
$status_filter = isset($_GET['status']) ? (int)$_GET['status'] : 0;
$search_keyword = isset($_GET['search']) ? $_GET['search'] : '';

// Xây dựng truy vấn
$query = "SELECT * FROM donhang";

// Thêm điều kiện lọc
$where_conditions = [];
if ($status_filter > 0) {
    $where_conditions[] = "trangthai = $status_filter";
}

if (!empty($search_keyword)) {
    $search_keyword = $conn->real_escape_string($search_keyword);
    $where_conditions[] = "(id_donhang LIKE '%$search_keyword%' OR tennguoinhan LIKE '%$search_keyword%' OR sodienthoai LIKE '%$search_keyword%' OR email LIKE '%$search_keyword%')";
}

// Kết hợp các điều kiện
if (!empty($where_conditions)) {
    $query .= " WHERE " . implode(" AND ", $where_conditions);
}

// Sắp xếp
$query .= " ORDER BY id_donhang DESC";

// Thực hiện truy vấn
$result = $conn->query($query);

// Mảng trạng thái đơn hàng
$order_statuses = [
    1 => ['name' => 'Chờ xác nhận', 'badge' => 'warning'],
    2 => ['name' => 'Đang xử lý', 'badge' => 'info'],
    3 => ['name' => 'Đang giao hàng', 'badge' => 'primary'],
    4 => ['name' => 'Đã giao', 'badge' => 'success'],
    5 => ['name' => 'Đã hủy', 'badge' => 'danger'],
    6 => ['name' => 'Hoàn trả', 'badge' => 'secondary']
];
?>

<!-- Include sidebar -->
<?php include('includes/sidebar.php'); ?>

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
                <input type="text" name="search" class="form-control me-2" placeholder="Tìm theo ID, tên, email, SĐT..." value="<?php echo htmlspecialchars($search_keyword); ?>">
                <button type="submit" class="btn btn-outline-primary">
                    <i class="bi bi-search"></i> Tìm kiếm
                </button>
            </form>
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
                            <th scope="col">Khách hàng</th>
                            <th scope="col">Liên hệ</th>
                            <th scope="col">Địa chỉ</th>
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
                                    <td><?php echo $order['id_donhang']; ?></td>
                                    <td><?php echo htmlspecialchars($order['tennguoinhan']); ?></td>
                                    <td>
                                        <div><?php echo htmlspecialchars($order['sodienthoai']); ?></div>
                                        <?php if (!empty($order['email'])): ?>
                                        <div class="small text-muted"><?php echo htmlspecialchars($order['email']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $address_parts = [];
                                        if (!empty($order['diachi'])) $address_parts[] = htmlspecialchars($order['diachi']);
                                        if (!empty($order['phuong_xa'])) $address_parts[] = htmlspecialchars($order['phuong_xa']);
                                        if (!empty($order['quan_huyen'])) $address_parts[] = htmlspecialchars($order['quan_huyen']);
                                        if (!empty($order['tinh_tp'])) $address_parts[] = htmlspecialchars($order['tinh_tp']);
                                        
                                        echo implode(", ", $address_parts);
                                        ?>
                                    </td>
                                    <td><?php echo number_format($order['tongtien'], 0, ',', '.'); ?> ₫</td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($order['ngaytao'])); ?></td>
                                    <td>
                                        <?php 
                                        $status_id = $order['trangthai'];
                                        $status = $order_statuses[$status_id] ?? ['name' => 'Không xác định', 'badge' => 'secondary'];
                                        ?>
                                        <span class="badge bg-<?php echo $status['badge']; ?>">
                                            <?php echo $status['name']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $payment_method = $order['phuongthucthanhtoan'];
                                        if ($payment_method === 'cod') {
                                            echo '<span class="badge bg-info">Tiền mặt khi nhận hàng</span>';
                                        } elseif ($payment_method === 'bank_transfer') {
                                            echo '<span class="badge bg-success">Chuyển khoản ngân hàng</span>';
                                        } elseif ($payment_method === 'momo') {
                                            echo '<span class="badge bg-danger">Ví MoMo</span>';
                                        } elseif ($payment_method === 'vnpay') {
                                            echo '<span class="badge bg-primary">VNPay</span>';
                                        } else {
                                            echo '<span class="badge bg-secondary">Khác</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="order-detail.php?id=<?php echo $order['id_donhang']; ?>" class="btn btn-outline-primary">
                                                <i class="bi bi-eye"></i> Xem
                                            </a>
                                            <button type="button" class="btn btn-outline-dark dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                                                <span class="visually-hidden">Toggle Dropdown</span>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li class="dropdown-header">Cập nhật trạng thái</li>
                                                <?php foreach ($order_statuses as $status_id => $status): ?>
                                                    <?php if ($status_id != $order['trangthai']): ?>
                                                        <li>
                                                            <a class="dropdown-item update-status" 
                                                               href="#" 
                                                               data-order-id="<?php echo $order['id_donhang']; ?>" 
                                                               data-status="<?php echo $status_id; ?>">
                                                                <?php echo $status['name']; ?>
                                                            </a>
                                                        </li>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <a class="dropdown-item text-primary" href="print-order.php?id=<?php echo $order['id_donhang']; ?>" target="_blank">
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