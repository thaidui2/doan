<?php
// Thiết lập tiêu đề trang
$page_title = "Chi Tiết Đơn Hàng";

// Include header
include('includes/header.php');

// Lấy ID đơn hàng
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($order_id <= 0) {
    $_SESSION['error_message'] = "ID đơn hàng không hợp lệ";
    header("Location: don-hang.php");
    exit();
}

// Kiểm tra đơn hàng có sản phẩm của người bán này hay không
$order_query = $conn->prepare("
    SELECT 
        dh.*,
        (
            SELECT GROUP_CONCAT(DISTINCT sp.id_sanpham)
            FROM donhang_chitiet dc 
            JOIN sanpham sp ON dc.id_sanpham = sp.id_sanpham
            WHERE dc.id_donhang = dh.id_donhang
            AND sp.id_nguoiban = ?
        ) as seller_product_ids,
        (
            SELECT SUM(dc.thanh_tien)
            FROM donhang_chitiet dc 
            JOIN sanpham sp ON dc.id_sanpham = sp.id_sanpham
            WHERE dc.id_donhang = dh.id_donhang
            AND sp.id_nguoiban = ?
        ) as seller_subtotal
    FROM donhang dh
    WHERE dh.id_donhang = ?
    AND EXISTS (
        SELECT 1 
        FROM donhang_chitiet dc 
        JOIN sanpham sp ON dc.id_sanpham = sp.id_sanpham 
        WHERE dc.id_donhang = dh.id_donhang 
        AND sp.id_nguoiban = ?
    )
");

$order_query->bind_param("iiii", $user_id, $user_id, $order_id, $user_id);
$order_query->execute();
$result = $order_query->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "Bạn không có quyền xem chi tiết đơn hàng này hoặc đơn hàng không tồn tại";
    header("Location: don-hang.php");
    exit();
}

$order = $result->fetch_assoc();

// Lấy danh sách sản phẩm trong đơn hàng (chỉ của người bán này)
$items_query = $conn->prepare("
    SELECT 
        dc.*,
        sp.tensanpham,
        sp.hinhanh,
        sp.id_nguoiban,
        kt.tenkichthuoc,
        ms.tenmau,
        ms.mamau
    FROM donhang_chitiet dc
    JOIN sanpham sp ON dc.id_sanpham = sp.id_sanpham
    LEFT JOIN kichthuoc kt ON dc.id_kichthuoc = kt.id_kichthuoc
    LEFT JOIN mausac ms ON dc.id_mausac = ms.id_mausac
    WHERE dc.id_donhang = ? AND sp.id_nguoiban = ?
");

$items_query->bind_param("ii", $order_id, $user_id);
$items_query->execute();
$order_items = $items_query->get_result();

// Lấy lịch sử đơn hàng
$history_query = $conn->prepare("
    SELECT * FROM donhang_lichsu 
    WHERE id_donhang = ? 
    ORDER BY ngay_thaydoi DESC
");

$history_query->bind_param("i", $order_id);
$history_query->execute();
$order_history = $history_query->get_result();

// Mảng trạng thái đơn hàng
$order_statuses = [
    1 => ['name' => 'Chờ xác nhận', 'badge' => 'warning text-dark'],
    2 => ['name' => 'Đang xử lý', 'badge' => 'info text-dark'],
    3 => ['name' => 'Đang giao hàng', 'badge' => 'primary'],
    4 => ['name' => 'Đã giao', 'badge' => 'success'],
    5 => ['name' => 'Đã hủy', 'badge' => 'danger'],
    6 => ['name' => 'Hoàn trả', 'badge' => 'secondary']
];

// Xác định các trạng thái tiếp theo hợp lệ
$current_status = $order['trangthai'];
$next_statuses = [];

switch ($current_status) {
    case 1: // Chờ xác nhận
        $next_statuses = [2, 5]; // Có thể chuyển sang Đang xử lý hoặc Đã hủy
        break;
    case 2: // Đang xử lý
        $next_statuses = [3, 5]; // Có thể chuyển sang Đang giao hàng hoặc Đã hủy
        break;
    case 3: // Đang giao hàng
        $next_statuses = [4, 5]; // Có thể chuyển sang Đã giao hoặc Đã hủy
        break;
    case 4: // Đã giao
        $next_statuses = [6]; // Chỉ có thể chuyển sang Hoàn trả
        break;
    // Đã hủy hoặc Hoàn trả không thể chuyển sang trạng thái khác
}
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Chi tiết đơn hàng #<?php echo $order_id; ?></h1>
    <div>
        <a href="don-hang.php" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Quay lại danh sách
        </a>
    </div>
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

<div class="row">
    <!-- Thông tin đơn hàng -->
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between">
                <strong>Thông tin đơn hàng</strong>
                <span class="badge bg-<?php echo $order_statuses[$order['trangthai']]['badge']; ?>">
                    <?php echo $order_statuses[$order['trangthai']]['name']; ?>
                </span>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <strong>Mã đơn hàng:</strong> #<?php echo $order['id_donhang']; ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Ngày đặt hàng:</strong> <?php echo date('d/m/Y H:i', strtotime($order['ngaytao'])); ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Phương thức thanh toán:</strong> 
                        <?php 
                        echo $order['phuongthucthanhtoan'] === 'cod' ? 'Thanh toán khi nhận hàng' : 
                            ($order['phuongthucthanhtoan'] === 'bank' ? 'Chuyển khoản ngân hàng' : $order['phuongthucthanhtoan']); 
                        ?>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <h6>Thông tin người nhận</h6>
                        <div class="mb-1"><strong>Họ tên:</strong> <?php echo htmlspecialchars($order['tennguoinhan']); ?></div>
                        <div class="mb-1"><strong>Số điện thoại:</strong> <?php echo htmlspecialchars($order['sodienthoai']); ?></div>
                        <div class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($order['email'] ?? '-'); ?></div>
                        <div class="mb-1">
                            <strong>Địa chỉ:</strong> 
                            <?php 
                                echo htmlspecialchars($order['diachi']) . ", " . 
                                     htmlspecialchars($order['phuong_xa']) . ", " . 
                                     htmlspecialchars($order['quan_huyen']) . ", " . 
                                     htmlspecialchars($order['tinh_tp']); 
                            ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6>Thông tin thanh toán</h6>
                        <div class="mb-1">
                            <strong>Tổng giá trị đơn hàng:</strong> 
                            <span class="fw-bold"><?php echo number_format($order['tongtien'], 0, ',', '.'); ?>₫</span>
                        </div>
                        <div class="mb-1">
                            <strong>Phí vận chuyển:</strong> 
                            <?php echo number_format($order['phivanchuyen'], 0, ',', '.'); ?>₫
                        </div>
                        <div class="mb-1">
                            <strong>Giá trị của bạn:</strong> 
                            <span class="fw-bold text-success"><?php echo number_format($order['seller_subtotal'], 0, ',', '.'); ?>₫</span>
                            <span class="small fst-italic text-muted">(chỉ tính sản phẩm của bạn)</span>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($order['ghichu'])): ?>
                <div class="mt-3">
                    <h6>Ghi chú đơn hàng</h6>
                    <div class="border p-2 rounded">
                        <?php echo nl2br(htmlspecialchars($order['ghichu'])); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Chi tiết sản phẩm -->
        <div class="card mb-4">
            <div class="card-header">
                <strong>Sản phẩm trong đơn hàng (chỉ sản phẩm của bạn)</strong>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 60px;"></th>
                            <th>Sản phẩm</th>
                            <th>Đơn giá</th>
                            <th>Số lượng</th>
                            <th class="text-end">Thành tiền</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($item = $order_items->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($item['hinhanh'])): ?>
                                        <img src="../uploads/products/<?php echo $item['hinhanh']; ?>" 
                                             alt="<?php echo htmlspecialchars($item['tensanpham']); ?>"
                                             class="img-thumbnail" style="width: 50px; height: 50px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="bg-light text-center" style="width: 50px; height: 50px; line-height: 50px;">
                                            <i class="bi bi-image text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($item['tensanpham']); ?></div>
                                    <?php if (!empty($item['tenkichthuoc']) || !empty($item['tenmau'])): ?>
                                        <div class="small text-muted">
                                            <?php 
                                            if (!empty($item['tenkichthuoc'])) echo 'Size: ' . $item['tenkichthuoc']; 
                                            if (!empty($item['tenkichthuoc']) && !empty($item['tenmau'])) echo ' / ';
                                            if (!empty($item['tenmau'])) echo 'Màu: ' . $item['tenmau']; 
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo number_format($item['gia'], 0, ',', '.'); ?>₫</td>
                                <td><?php echo $item['soluong']; ?></td>
                                <td class="text-end"><?php echo number_format($item['thanh_tien'], 0, ',', '.'); ?>₫</td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="4" class="text-end"><strong>Tổng cộng:</strong></td>
                            <td class="text-end fw-bold"><?php echo number_format($order['seller_subtotal'], 0, ',', '.'); ?>₫</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        
        <!-- Lịch sử đơn hàng -->
        <div class="card mb-4">
            <div class="card-header">
                <strong>Lịch sử đơn hàng</strong>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php if ($order_history->num_rows > 0): ?>
                        <?php while ($history = $order_history->fetch_assoc()): ?>
                            <li class="list-group-item">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <strong><?php echo htmlspecialchars($history['hanh_dong']); ?></strong>
                                        <span class="text-muted"> bởi </span>
                                        <strong><?php echo htmlspecialchars($history['nguoi_thuchien']); ?></strong>
                                    </div>
                                    <div class="text-muted"><?php echo date('d/m/Y H:i', strtotime($history['ngay_thaydoi'])); ?></div>
                                </div>
                                <div class="text-muted small mt-1"><?php echo htmlspecialchars($history['ghi_chu']); ?></div>
                            </li>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <li class="list-group-item">Không có lịch sử cập nhật</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
    
    <!-- Cập nhật trạng thái đơn hàng -->
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header">
                <strong>Cập nhật trạng thái đơn hàng</strong>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Trạng thái hiện tại:</strong>
                    <span class="badge bg-<?php echo $order_statuses[$order['trangthai']]['badge']; ?> ms-2">
                        <?php echo $order_statuses[$order['trangthai']]['name']; ?>
                    </span>
                </div>
                
                <?php if (!empty($next_statuses)): ?>
                    <form action="xu-ly-don-hang.php" method="post">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                        
                        <div class="mb-3">
                            <label for="new_status" class="form-label">Đổi trạng thái thành:</label>
                            <select class="form-select" id="new_status" name="new_status" required>
                                <option value="">-- Chọn trạng thái --</option>
                                <?php foreach ($next_statuses as $status_id): ?>
                                    <option value="<?php echo $status_id; ?>">
                                        <?php echo $order_statuses[$status_id]['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="note" class="form-label">Ghi chú (tùy chọn):</label>
                            <textarea class="form-control" id="note" name="note" rows="3" placeholder="Nhập ghi chú về việc thay đổi trạng thái..."></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">Cập nhật trạng thái</button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-info mb-0">
                        <i class="bi bi-info-circle me-2"></i> Đơn hàng này đã hoàn thành hoặc bị hủy, không thể thay đổi trạng thái.
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <strong>Hướng dẫn</strong>
            </div>
            <div class="card-body">
                <p class="small text-muted mb-2">Quy trình xử lý đơn hàng:</p>
                <ol class="small text-muted">
                    <li><strong>Chờ xác nhận:</strong> Đơn hàng mới, chờ người bán xác nhận</li>
                    <li><strong>Đang xử lý:</strong> Người bán đã xác nhận, đang chuẩn bị hàng</li>
                    <li><strong>Đang giao hàng:</strong> Đơn hàng đã được giao cho đơn vị vận chuyển</li>
                    <li><strong>Đã giao:</strong> Khách hàng đã nhận được hàng</li>
                    <li><strong>Đã hủy:</strong> Đơn hàng đã bị hủy</li>
                    <li><strong>Hoàn trả:</strong> Đơn hàng bị trả lại</li>
                </ol>
                <div class="alert alert-warning small py-2 mb-0">
                    <i class="bi bi-exclamation-triangle me-1"></i> Lưu ý: Mỗi khi thay đổi trạng thái, hệ thống sẽ ghi lại lịch sử và thông báo cho khách hàng.
                </div>
            </div>
        </div>
    </div>
</div>

<?php include('includes/footer.php'); ?>