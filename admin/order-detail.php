<?php
// Set page title
$page_title = 'Chi tiết đơn hàng';

// Include header (với kiểm tra đăng nhập)
include('includes/header.php');

// Include database connection
include('../config/config.php');

// Lấy ID đơn hàng từ URL
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Kiểm tra ID hợp lệ
if ($order_id <= 0) {
    // Chuyển hướng về trang danh sách nếu không có ID hợp lệ
    header('Location: orders.php');
    exit();
}

// Lấy thông tin đơn hàng
$order_stmt = $conn->prepare("
    SELECT dh.*, 
           IFNULL(u.tenuser, 'Khách vãng lai') as tenkhachhang
    FROM donhang dh
    LEFT JOIN users u ON dh.id_nguoidung = u.id_user
    WHERE dh.id_donhang = ?
");
$order_stmt->bind_param("i", $order_id);
$order_stmt->execute();
$order_result = $order_stmt->get_result();

// Kiểm tra đơn hàng tồn tại
if ($order_result->num_rows === 0) {
    // Chuyển hướng nếu không tìm thấy đơn hàng
    header('Location: orders.php');
    exit();
}

$order = $order_result->fetch_assoc();

// Lấy chi tiết đơn hàng
$items_stmt = $conn->prepare("
    SELECT dct.*, 
           sp.tensanpham, sp.hinhanh,
           kt.tenkichthuoc, 
           ms.tenmau, ms.mamau
    FROM donhang_chitiet dct
    LEFT JOIN sanpham sp ON dct.id_sanpham = sp.id_sanpham
    LEFT JOIN kichthuoc kt ON dct.id_kichthuoc = kt.id_kichthuoc
    LEFT JOIN mausac ms ON dct.id_mausac = ms.id_mausac
    WHERE dct.id_donhang = ?
");
$items_stmt->bind_param("i", $order_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();

// Mảng trạng thái đơn hàng
$order_statuses = [
    1 => ['name' => 'Chờ xác nhận', 'badge' => 'warning text-dark'],
    2 => ['name' => 'Đang xử lý', 'badge' => 'info'],
    3 => ['name' => 'Đang giao hàng', 'badge' => 'primary'],
    4 => ['name' => 'Đã giao', 'badge' => 'success'],
    5 => ['name' => 'Đã hủy', 'badge' => 'danger'],
    6 => ['name' => 'Hoàn trả', 'badge' => 'secondary']
];

// Phương thức thanh toán
$payment_methods = [
    'cod' => 'Tiền mặt khi nhận hàng',
    'bank_transfer' => 'Chuyển khoản ngân hàng',
    'momo' => 'Ví MoMo',
    'vnpay' => 'VNPay'
];

// Xử lý cập nhật trạng thái đơn hàng
$update_message = '';
if (isset($_POST['update_status'])) {
    $new_status = (int)$_POST['status'];
    
    if (array_key_exists($new_status, $order_statuses)) {
        $update_stmt = $conn->prepare("UPDATE donhang SET trangthai = ?, ngaycapnhat = NOW() WHERE id_donhang = ?");
        $update_stmt->bind_param("ii", $new_status, $order_id);
        
        if ($update_stmt->execute()) {
            // Cập nhật thành công
            $update_message = '<div class="alert alert-success">Cập nhật trạng thái đơn hàng thành công!</div>';
            
            // Cập nhật lại dữ liệu đơn hàng
            $order['trangthai'] = $new_status;
            $order['ngaycapnhat'] = date('Y-m-d H:i:s');
            
            // Thêm đoạn code ghi lịch sử thay đổi trạng thái
            $admin_name = $_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? 'Admin';
            $trang_thai_cu = $order_statuses[$order['trangthai']]['name'];
            $trang_thai_moi = $order_statuses[$new_status]['name'];
            $ghi_chu = "Thay đổi trạng thái từ \"$trang_thai_cu\" sang \"$trang_thai_moi\"";
            
            // Kiểm tra bảng lịch sử tồn tại
            $table_check = $conn->query("SHOW TABLES LIKE 'donhang_lichsu'");
            if ($table_check->num_rows > 0) {
                $history_stmt = $conn->prepare("INSERT INTO donhang_lichsu (id_donhang, hanh_dong, nguoi_thuchien, ghi_chu, ngay_thaydoi) VALUES (?, 'Cập nhật trạng thái', ?, ?, NOW())");
                $history_stmt->bind_param("iss", $order_id, $admin_name, $ghi_chu);
                $history_stmt->execute();
            } else {
                // Tạo bảng donhang_lichsu nếu chưa tồn tại
                $create_table = "CREATE TABLE IF NOT EXISTS `donhang_lichsu` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `id_donhang` int(11) NOT NULL,
                    `hanh_dong` varchar(255) NOT NULL,
                    `nguoi_thuchien` varchar(100) NOT NULL,
                    `ghi_chu` text DEFAULT NULL,
                    `ngay_thaydoi` timestamp NOT NULL DEFAULT current_timestamp(),
                    PRIMARY KEY (`id`),
                    KEY `id_donhang` (`id_donhang`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
                
                if ($conn->query($create_table)) {
                    // Sau khi tạo bảng, thêm bản ghi
                    $history_stmt = $conn->prepare("INSERT INTO donhang_lichsu (id_donhang, hanh_dong, nguoi_thuchien, ghi_chu) VALUES (?, 'Cập nhật trạng thái', ?, ?)");
                    $history_stmt->bind_param("iss", $order_id, $admin_name, $ghi_chu);
                    $history_stmt->execute();
                }
            }

            // Gọi hàm cập nhật số lượng sản phẩm khi trạng thái đơn hàng là "Đã giao"
            if ($new_status == 4) {
                updateProductQuantity($order_id);
            }
        } else {
            $update_message = '<div class="alert alert-danger">Có lỗi xảy ra khi cập nhật trạng thái!</div>';
        }
    }
}

// Lấy lịch sử đơn hàng (nếu có)
$history = [];
$history_query = $conn->prepare("
    SELECT * FROM donhang_lichsu 
    WHERE id_donhang = ? 
    ORDER BY ngay_thaydoi DESC
");

// Kiểm tra nếu bảng lịch sử tồn tại
$table_exists = $conn->query("SHOW TABLES LIKE 'donhang_lichsu'")->num_rows > 0;
if ($table_exists) {
    $history_query->bind_param("i", $order_id);
    $history_query->execute();
    $history_result = $history_query->get_result();
    
    while ($row = $history_result->fetch_assoc()) {
        $history[] = $row;
    }
}

/**
 * Cập nhật số lượng sản phẩm sau khi đơn hàng hoàn thành
 * 
 * @param int $order_id ID đơn hàng đã hoàn thành
 */
function updateProductQuantity($order_id) {
    global $conn;
    
    // Lấy tất cả sản phẩm trong đơn hàng
    $order_items_query = $conn->prepare("
        SELECT id_sanpham, id_kichthuoc, id_mausac, soluong 
        FROM donhang_chitiet 
        WHERE id_donhang = ?
    ");
    $order_items_query->bind_param("i", $order_id);
    $order_items_query->execute();
    $result = $order_items_query->get_result();
    
    while($item = $result->fetch_assoc()) {
        // Giảm số lượng trong bảng chi tiết sản phẩm (biến thể)
        if($item['id_kichthuoc'] && $item['id_mausac']) {
            $update_variant = $conn->prepare("
                UPDATE sanpham_chitiet 
                SET soluong = GREATEST(0, soluong - ?) 
                WHERE id_sanpham = ? AND id_kichthuoc = ? AND id_mausac = ?
            ");
            $update_variant->bind_param("iiii", $item['soluong'], $item['id_sanpham'], 
                                              $item['id_kichthuoc'], $item['id_mausac']);
            $update_variant->execute();
        }
        
        // Cập nhật tổng số lượng trong bảng sản phẩm
        $update_total = $conn->prepare("
            UPDATE sanpham SET soluong = (
                SELECT COALESCE(SUM(soluong), 0) 
                FROM sanpham_chitiet 
                WHERE id_sanpham = ?
            ) WHERE id_sanpham = ?
        ");
        $update_total->bind_param("ii", $item['id_sanpham'], $item['id_sanpham']);
        $update_total->execute();
    }
    
    // Ghi log
    $log_file = fopen("../logs/inventory_update.txt", "a");
    fwrite($log_file, date('Y-m-d H:i:s') . " - Cập nhật tồn kho cho đơn hàng #$order_id\n");
    fclose($log_file);
}
?>

<!-- Include sidebar -->
<?php include('includes/sidebar.php'); ?>

<!-- Main content -->
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Trang chủ</a></li>
            <li class="breadcrumb-item"><a href="orders.php">Quản lý đơn hàng</a></li>
            <li class="breadcrumb-item active" aria-current="page">Đơn hàng #<?php echo $order_id; ?></li>
        </ol>
    </nav>
    
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Chi tiết đơn hàng #<?php echo $order_id; ?></h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <a href="print-order.php?id=<?php echo $order_id; ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-printer"></i> In đơn hàng
                </a>
                <a href="orders.php" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-arrow-left"></i> Quay lại
                </a>
            </div>
        </div>
    </div>
    
    <?php echo $update_message; ?>
    
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Thông tin đơn hàng</h5>
                    <span class="badge bg-<?php echo $order_statuses[$order['trangthai']]['badge']; ?>">
                        <?php echo $order_statuses[$order['trangthai']]['name']; ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p><strong>Mã đơn hàng:</strong> #<?php echo $order_id; ?></p>
                            <p><strong>Ngày đặt:</strong> <?php echo date('d/m/Y H:i', strtotime($order['ngaytao'])); ?></p>
                            <p><strong>Cập nhật lần cuối:</strong> <?php echo date('d/m/Y H:i', strtotime($order['ngaycapnhat'])); ?></p>
                            <p>
                                <strong>Phương thức thanh toán:</strong> 
                                <?php 
                                    echo isset($payment_methods[$order['phuongthucthanhtoan']]) ? 
                                         $payment_methods[$order['phuongthucthanhtoan']] : 
                                         $order['phuongthucthanhtoan']; 
                                ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Tổng tiền hàng:</strong> <?php echo number_format($order['tongtien'] - $order['phivanchuyen'], 0, ',', '.'); ?>₫</p>
                            <p><strong>Phí vận chuyển:</strong> <?php echo number_format($order['phivanchuyen'], 0, ',', '.'); ?>₫</p>
                            <p><strong>Tổng thanh toán:</strong> <span class="fw-bold text-danger"><?php echo number_format($order['tongtien'], 0, ',', '.'); ?>₫</span></p>
                        </div>
                    </div>
                    
                    <form method="post" class="mt-4">
                        <div class="mb-3">
                            <label for="orderStatus" class="form-label">Cập nhật trạng thái đơn hàng</label>
                            <div class="d-flex">
                                <select class="form-select" id="orderStatus" name="status">
                                    <?php foreach ($order_statuses as $status_id => $status): ?>
                                        <option value="<?php echo $status_id; ?>" <?php echo ($status_id == $order['trangthai']) ? 'selected' : ''; ?>>
                                            <?php echo $status['name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" name="update_status" class="btn btn-primary ms-2">Cập nhật</button>
                            </div>
                        </div>
                    </form>
                    
                    <?php if (!empty($order['ghichu'])): ?>
                    <div class="alert alert-info mt-3">
                        <h6 class="alert-heading">Ghi chú từ khách hàng:</h6>
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($order['ghichu'])); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0">Thông tin khách hàng</h5>
                </div>
                <div class="card-body">
                    <p><strong>Họ tên:</strong> <?php echo htmlspecialchars($order['tennguoinhan']); ?></p>
                    <p><strong>Điện thoại:</strong> <?php echo htmlspecialchars($order['sodienthoai']); ?></p>
                    <?php if (!empty($order['email'])): ?>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($order['email']); ?></p>
                    <?php endif; ?>
                    <p>
                        <strong>Địa chỉ:</strong><br>
                        <?php
                        $address_parts = [];
                        if (!empty($order['diachi'])) $address_parts[] = htmlspecialchars($order['diachi']);
                        if (!empty($order['phuong_xa'])) $address_parts[] = htmlspecialchars($order['phuong_xa']);
                        if (!empty($order['quan_huyen'])) $address_parts[] = htmlspecialchars($order['quan_huyen']);
                        if (!empty($order['tinh_tp'])) $address_parts[] = htmlspecialchars($order['tinh_tp']);
                        
                        echo implode(', ', $address_parts);
                        ?>
                    </p>
                    
                    <?php if ($order['id_nguoidung']): ?>
                    <hr>
                    <div class="d-flex align-items-center">
                        <img src="<?php echo !empty($order['anh_dai_dien']) ? '../uploads/users/' . $order['anh_dai_dien'] : '../images/avatar-default.png'; ?>" 
                             class="rounded-circle me-2" width="40" height="40">
                        <div>
                            <div class="fw-bold"><?php echo htmlspecialchars($order['tenkhachhang']); ?></div>
                            <a href="customer-detail.php?id=<?php echo $order['id_nguoidung']; ?>" class="btn btn-sm btn-outline-primary mt-1">
                                <i class="bi bi-person"></i> Xem hồ sơ
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Order Items -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="card-title mb-0">Sản phẩm đặt mua</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="5%">#</th>
                            <th width="10%">Hình ảnh</th>
                            <th width="35%">Tên sản phẩm</th>
                            <th width="15%">Kích thước & Màu sắc</th>
                            <th width="10%">Đơn giá</th>
                            <th width="10%">Số lượng</th>
                            <th width="15%">Thành tiền</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $counter = 1;
                        while ($item = $items_result->fetch_assoc()): 
                        ?>
                        <tr>
                            <td><?php echo $counter++; ?></td>
                            <td>
                                <?php if (!empty($item['hinhanh'])): ?>
                                <img src="../uploads/products/<?php echo $item['hinhanh']; ?>" alt="<?php echo htmlspecialchars($item['tensanpham']); ?>" class="product-image">
                                <?php else: ?>
                                <div class="bg-light text-center p-2">
                                    <i class="bi bi-image text-muted"></i>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="../product-detail.php?id=<?php echo $item['id_sanpham']; ?>" target="_blank" class="text-decoration-none">
                                    <?php echo htmlspecialchars($item['tensanpham'] ?: 'Sản phẩm không tồn tại'); ?>
                                </a>
                            </td>
                            <td>
                                <?php if (!empty($item['tenkichthuoc'])): ?>
                                <div><strong>Size:</strong> <?php echo htmlspecialchars($item['tenkichthuoc']); ?></div>
                                <?php endif; ?>
                                
                                <?php if (!empty($item['tenmau'])): ?>
                                <div>
                                    <strong>Màu:</strong>
                                    <?php if (!empty($item['mamau'])): ?>
                                    <span class="color-swatch d-inline-block me-1" style="background-color: <?php echo $item['mamau']; ?>"></span>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($item['tenmau']); ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo number_format($item['gia'], 0, ',', '.'); ?>₫</td>
                            <td><?php echo number_format($item['soluong']); ?></td>
                            <td><?php echo number_format($item['thanh_tien'], 0, ',', '.'); ?>₫</td>
                        </tr>
                        <?php endwhile; ?>
                        
                        <?php if ($items_result->num_rows === 0): ?>
                        <tr>
                            <td colspan="7" class="text-center py-3">Không có sản phẩm nào trong đơn hàng</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="6" class="text-end"><strong>Tổng tiền hàng:</strong></td>
                            <td><?php echo number_format($order['tongtien'] - $order['phivanchuyen'], 0, ',', '.'); ?>₫</td>
                        </tr>
                        <tr>
                            <td colspan="6" class="text-end"><strong>Phí vận chuyển:</strong></td>
                            <td><?php echo number_format($order['phivanchuyen'], 0, ',', '.'); ?>₫</td>
                        </tr>
                        <tr>
                            <td colspan="6" class="text-end"><strong>Tổng thanh toán:</strong></td>
                            <td class="fw-bold text-danger"><?php echo number_format($order['tongtien'], 0, ',', '.'); ?>₫</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Order History -->
    <?php if ($table_exists && !empty($history)): ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="card-title mb-0">Lịch sử đơn hàng</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Thời gian</th>
                            <th>Người thực hiện</th>
                            <th>Hành động</th>
                            <th>Ghi chú</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $entry): ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i:s', strtotime($entry['ngay_thaydoi'])); ?></td>
                            <td><?php echo htmlspecialchars($entry['nguoi_thuchien']); ?></td>
                            <td><?php echo htmlspecialchars($entry['hanh_dong']); ?></td>
                            <td><?php echo htmlspecialchars($entry['ghi_chu']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Add Note & Action Buttons -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="card-title mb-0">Thêm ghi chú</h5>
        </div>
        <div class="card-body">
            <form action="add-order-note.php" method="post">
                <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                <div class="mb-3">
                    <textarea class="form-control" name="note" rows="3" placeholder="Nhập ghi chú nội bộ về đơn hàng..."></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Lưu ghi chú</button>
            </form>
            
            <hr>
            
            <div class="d-flex flex-wrap gap-2">
                <a href="orders.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Quay lại danh sách
                </a>
                <a href="print-order.php?id=<?php echo $order_id; ?>" target="_blank" class="btn btn-outline-primary">
                    <i class="bi bi-printer"></i> In đơn hàng
                </a>
                <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#sendEmailModal">
                    <i class="bi bi-envelope"></i> Gửi email
                </button>
                <?php if ($order['trangthai'] != 5): // Nếu chưa hủy ?>
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelOrderModal">
                    <i class="bi bi-x-circle"></i> Hủy đơn hàng
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<!-- Email Modal -->
<div class="modal fade" id="sendEmailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Gửi email cho khách hàng</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="send-order-email.php" method="post">
                    <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                    <div class="mb-3">
                        <label for="emailSubject" class="form-label">Tiêu đề</label>
                        <input type="text" class="form-control" id="emailSubject" name="subject" value="Thông tin đơn hàng #<?php echo $order_id; ?>">
                    </div>
                    <div class="mb-3">
                        <label for="emailContent" class="form-label">Nội dung</label>
                        <textarea class="form-control" id="emailContent" name="content" rows="5">Kính gửi <?php echo htmlspecialchars($order['tennguoinhan']); ?>,

Cảm ơn bạn đã đặt hàng tại Bug Shop. Đơn hàng #<?php echo $order_id; ?> của bạn đã được cập nhật...

Trân trọng,
Bug Shop</textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                <button type="submit" class="btn btn-primary">Gửi email</button>
            </div>
        </div>
    </div>
</div>

<!-- Cancel Order Modal -->
<div class="modal fade" id="cancelOrderModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Xác nhận hủy đơn hàng</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Bạn có chắc chắn muốn hủy đơn hàng #<?php echo $order_id; ?>?</p>
                <p>Hành động này không thể hoàn tác.</p>
                <form action="cancel-order.php" method="post" id="cancelOrderForm">
                    <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                    <div class="mb-3">
                        <label for="cancelReason" class="form-label">Lý do hủy</label>
                        <textarea class="form-control" id="cancelReason" name="reason" rows="3" required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                <button type="button" class="btn btn-danger" onclick="document.getElementById('cancelOrderForm').submit()">
                    Xác nhận hủy
                </button>
            </div>
        </div>
    </div>
</div>

<?php
// Add page-specific JavaScript
$page_specific_js = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Alert before status change if needed
    document.querySelector("form[name=update-status]")?.addEventListener("submit", function(e) {
        const newStatus = document.getElementById("orderStatus").value;
        const originalStatus = "' . $order['trangthai'] . '";
        
        // Add any specific validation or confirmation here if needed
    });
});
</script>';

// Include footer
include('includes/footer.php');
?>