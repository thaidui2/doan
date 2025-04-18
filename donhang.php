<?php
session_start();
include('config/config.php');



// Kiểm tra đăng nhập
if (!isset($_SESSION['user'])) {
    $_SESSION['redirect_after_login'] = 'donhang.php';
    header('Location: dangnhap.php?required=1');
    exit;
}

$user_id = $_SESSION['user']['id'];

// Lấy thông tin đơn hàng của người dùng - Cập nhật theo schema mới
$orders_query = $conn->prepare("
    SELECT d.*, COUNT(dc.id) as total_items 
    FROM donhang d 
    LEFT JOIN donhang_chitiet dc ON d.id = dc.id_donhang 
    WHERE d.id_user = ? 
    GROUP BY d.id 
    ORDER BY d.ngay_dat DESC
");
$orders_query->bind_param("i", $user_id);
$orders_query->execute();
$orders = $orders_query->get_result();

// Mảng trạng thái đơn hàng - Cập nhật các trạng thái theo schema mới
$order_statuses = [
    1 => ['name' => 'Chờ xác nhận', 'color' => 'warning'],
    2 => ['name' => 'Đã xác nhận', 'color' => 'info'],
    3 => ['name' => 'Đang giao hàng', 'color' => 'primary'],
    4 => ['name' => 'Đã giao', 'color' => 'success'],
    5 => ['name' => 'Đã hủy', 'color' => 'danger']
];

// Mảng phương thức thanh toán
$payment_methods = [
    'cod' => 'Thanh toán khi nhận hàng',
    'bank_transfer' => 'Chuyển khoản ngân hàng',
    'momo' => 'Ví MoMo',
    'vnpay' => 'VNPay'
];

// Tiêu đề trang
$page_title = "Đơn hàng của tôi";
?>

<?php include('includes/head.php'); ?>
<?php include('includes/header.php'); ?>

<div class="container py-5">
    <div class="row">
        <!-- Sidebar menu -->
        <div class="col-md-3 mb-4">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Tài khoản của tôi</h5>
                </div>
                <div class="list-group list-group-flush">
                    <a href="taikhoan.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-person me-2"></i> Thông tin tài khoản
                    </a>
                    <a href="donhang.php" class="list-group-item list-group-item-action active">
                        <i class="bi bi-box-seam me-2"></i> Đơn hàng của tôi
                    </a>
                    <a href="doimatkhau.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-shield-lock me-2"></i> Đổi mật khẩu
                    </a>
                    <a href="yeuthich.php" class="list-group-item list-group-item-action">
                        <i class="bi bi-heart me-2"></i> Sản phẩm yêu thích
                    </a>
                    <a href="dangxuat.php" class="list-group-item list-group-item-action text-danger">
                        <i class="bi bi-box-arrow-right me-2"></i> Đăng xuất
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Main content -->
        <div class="col-md-9">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Đơn hàng của tôi</h5>
                </div>
                <div class="card-body p-0">
                    <?php if ($orders->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Mã đơn hàng</th>
                                        <th>Ngày đặt</th>
                                        <th>Số lượng</th>
                                        <th>Tổng tiền</th>
                                        <th>Trạng thái</th>
                                        <th>Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($order = $orders->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $order['ma_donhang']; ?></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($order['ngay_dat'])); ?></td>
                                            <td><?php echo $order['total_items']; ?> sản phẩm</td>
                                            <td><?php echo number_format($order['thanh_tien'], 0, ',', '.'); ?>₫</td>
                                            <td>
                                                <?php
                                                $status = $order_statuses[$order['trang_thai_don_hang']] ?? ['name' => 'Không xác định', 'color' => 'secondary']; 
                                                ?>
                                                <span class="badge bg-<?php echo $status['color']; ?>"><?php echo $status['name']; ?></span>
                                                <?php if ($order['phuong_thuc_thanh_toan'] !== 'cod' && $order['trang_thai_thanh_toan']): ?>
                                                    <span class="badge bg-success">Đã thanh toán</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="chitiet-donhang.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-primary">Chi tiết</a>
                                                <?php if ($order['trang_thai_don_hang'] == 1): // Chỉ cho phép hủy đơn khi đơn hàng ở trạng thái "Chờ xác nhận" ?>
                                                    <button type="button" class="btn btn-sm btn-outline-danger cancel-order-btn" data-id="<?php echo $order['id']; ?>" data-bs-toggle="modal" data-bs-target="#cancelOrderModal">Hủy đơn</button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <div class="mb-4">
                                <i class="bi bi-box2 fs-1 text-muted"></i>
                            </div>
                            <h5>Bạn chưa có đơn hàng nào</h5>
                            <p class="text-muted">Hãy mua sắm và quay lại đây để kiểm tra đơn hàng của bạn.</p>
                            <a href="sanpham.php" class="btn btn-primary mt-3">
                                <i class="bi bi-bag me-2"></i> Mua sắm ngay
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal xác nhận hủy đơn hàng -->
<div class="modal fade" id="cancelOrderModal" tabindex="-1" aria-labelledby="cancelOrderModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cancelOrderModalLabel">Xác nhận hủy đơn hàng</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Bạn có chắc chắn muốn hủy đơn hàng này không?</p>
                <p class="text-muted small">Lưu ý: Hành động này không thể hoàn tác.</p>
                <form id="cancelOrderForm" method="post" action="huydonhang.php">
                    <input type="hidden" name="order_id" id="cancelOrderId" value="">
                    <div class="mb-3">
                        <label for="cancelReason" class="form-label">Lý do hủy đơn:</label>
                        <select class="form-select" id="cancelReason" name="cancel_reason" required>
                            <option value="">-- Chọn lý do --</option>
                            <option value="Muốn thay đổi địa chỉ giao hàng">Muốn thay đổi địa chỉ giao hàng</option>
                            <option value="Muốn thay đổi phương thức thanh toán">Muốn thay đổi phương thức thanh toán</option>
                            <option value="Đổi ý, không muốn mua nữa">Đổi ý, không muốn mua nữa</option>
                            <option value="Thời gian giao hàng quá lâu">Thời gian giao hàng quá lâu</option>
                            <option value="Tìm thấy sản phẩm tốt hơn/giá rẻ hơn">Tìm thấy sản phẩm tốt hơn/giá rẻ hơn</option>
                            <option value="other">Lý do khác</option>
                        </select>
                    </div>
                    <div class="mb-3 d-none" id="otherReasonContainer">
                        <label for="otherReason" class="form-label">Lý do khác:</label>
                        <textarea class="form-control" id="otherReason" name="other_reason" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                <button type="button" class="btn btn-danger" id="confirmCancelBtn">Hủy đơn hàng</button>
            </div>
        </div>
    </div>
</div>

<?php include('includes/footer.php'); ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Xử lý form hủy đơn hàng
        const cancelButtons = document.querySelectorAll('.cancel-order-btn');
        const cancelOrderIdField = document.getElementById('cancelOrderId');
        const cancelReasonSelect = document.getElementById('cancelReason');
        const otherReasonContainer = document.getElementById('otherReasonContainer');
        const otherReasonField = document.getElementById('otherReason');
        const confirmCancelBtn = document.getElementById('confirmCancelBtn');
        const cancelOrderForm = document.getElementById('cancelOrderForm');
        
        cancelButtons.forEach(button => {
            button.addEventListener('click', function() {
                cancelOrderIdField.value = this.getAttribute('data-id');
            });
        });
        
        cancelReasonSelect.addEventListener('change', function() {
            if (this.value === 'other') {
                otherReasonContainer.classList.remove('d-none');
                otherReasonField.setAttribute('required', 'required');
            } else {
                otherReasonContainer.classList.add('d-none');
                otherReasonField.removeAttribute('required');
            }
        });
        
        confirmCancelBtn.addEventListener('click', function() {
            if (cancelOrderForm.checkValidity()) {
                cancelOrderForm.submit();
            } else {
                cancelOrderForm.reportValidity();
            }
        });
    });
</script>
</body>
</html>