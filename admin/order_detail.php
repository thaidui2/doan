<?php
// Include database connection
require_once('../config/database.php');

// Include authentication check
require_once('includes/auth_check.php');

// Include functions
require_once('includes/functions.php');

// Set current page for sidebar highlighting
$current_page = 'orders';
$page_title = 'Chi tiết đơn hàng';

// Get order ID from URL
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$order_id) {
    // Redirect to orders list if no ID provided
    header('Location: orders.php');
    exit;
}

// Process order status update
if (isset($_POST['update_status'])) {
    $new_status = intval($_POST['order_status']);
    $note = trim($_POST['admin_note']);
    
    // Get current status
    $current_stmt = $conn->prepare("SELECT trang_thai_don_hang FROM donhang WHERE id = ?");
    $current_stmt->execute([$order_id]);
    $current_status = $current_stmt->fetchColumn();
    
    // Update order status
    $update = $conn->prepare("UPDATE donhang SET trang_thai_don_hang = ?, ngay_capnhat = NOW() WHERE id = ?");
    $update->execute([$new_status, $order_id]);
    
    // Add to order history
    $status_labels = [
        1 => 'Chờ xác nhận',
        2 => 'Đã xác nhận',
        3 => 'Đang giao hàng',
        4 => 'Đã giao',
        5 => 'Đã hủy'
    ];
    
    $history_note = "Thay đổi trạng thái từ \"{$status_labels[$current_status]}\" sang \"{$status_labels[$new_status]}\"";
    if (!empty($note)) {
        $history_note .= ". Ghi chú: $note";
    }
    
    $history = $conn->prepare("INSERT INTO donhang_lichsu (id_donhang, hanh_dong, nguoi_thuchien, ghi_chu) 
                              VALUES (?, 'update_status', ?, ?)");
    $admin_name = $_SESSION['admin_name'] ?? 'Quản trị viên';
    $history->execute([$order_id, $admin_name, $history_note]);
    
    // Log the action
    $detail = "Cập nhật trạng thái đơn hàng #{$order_id} thành: {$status_labels[$new_status]}";
    $log_stmt = $conn->prepare("INSERT INTO nhat_ky (id_user, hanh_dong, doi_tuong_loai, doi_tuong_id, chi_tiet, ip_address) 
                               VALUES (?, 'update_status', 'order', ?, ?, ?)");
    $log_stmt->execute([$_SESSION['admin_id'], $order_id, $detail, $_SERVER['REMOTE_ADDR']]);
    
    // Redirect to avoid form resubmission
    header("Location: order_detail.php?id=$order_id&updated=1");
    exit;
}

// Process order note update
if (isset($_POST['add_note'])) {
    $note = trim($_POST['order_note']);
    
    if (!empty($note)) {
        // Add to order history
        $history = $conn->prepare("INSERT INTO donhang_lichsu (id_donhang, hanh_dong, nguoi_thuchien, ghi_chu) 
                                  VALUES (?, 'add_note', ?, ?)");
        $admin_name = $_SESSION['admin_name'] ?? 'Quản trị viên';
        $history->execute([$order_id, $admin_name, $note]);
        
        // Redirect to avoid form resubmission
        header("Location: order_detail.php?id=$order_id&noted=1");
        exit;
    }
}

// Fetch order details
try {
    // Get basic order information
    $order_stmt = $conn->prepare("
        SELECT d.*, u.ten AS customer_name, u.email AS customer_email, u.sodienthoai AS customer_phone
        FROM donhang d
        LEFT JOIN users u ON d.id_user = u.id
        WHERE d.id = ?
    ");
    $order_stmt->execute([$order_id]);
    $order = $order_stmt->fetch();
    
    if (!$order) {
        // Order not found
        header('Location: orders.php?error=not_found');
        exit;
    }
    
    // Get order items
    $items_stmt = $conn->prepare("
        SELECT dc.*, sp.tensanpham, sp.hinhanh 
        FROM donhang_chitiet dc
        JOIN sanpham sp ON dc.id_sanpham = sp.id
        WHERE dc.id_donhang = ?
    ");
    $items_stmt->execute([$order_id]);
    $order_items = $items_stmt->fetchAll();
    
    // Get order history
    $history_stmt = $conn->prepare("
        SELECT * FROM donhang_lichsu 
        WHERE id_donhang = ? 
        ORDER BY ngay_thaydoi DESC
    ");
    $history_stmt->execute([$order_id]);
    $order_history = $history_stmt->fetchAll();
    
    // Get return/refund requests
    $returns_stmt = $conn->prepare("
        SELECT h.*, sp.tensanpham 
        FROM hoantra h
        JOIN sanpham sp ON h.id_sanpham = sp.id
        WHERE h.id_donhang = ?
        ORDER BY h.ngaytao DESC
    ");
    $returns_stmt->execute([$order_id]);
    $return_requests = $returns_stmt->fetchAll();
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Include header
include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="col-md-10 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Chi tiết đơn hàng #<?php echo $order['ma_donhang']; ?></h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="orders.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Quay lại
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="window.print()">
                            <i class="fas fa-print"></i> In đơn hàng
                        </button>
                    </div>
                </div>
            </div>
            
            <?php if (isset($_GET['updated'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Cập nhật trạng thái đơn hàng thành công!
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['noted'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                Đã thêm ghi chú cho đơn hàng!
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <div class="row mb-4">
                <!-- Order Summary -->
                <div class="col-md-8">
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Thông tin đơn hàng</h5>
                            <span class="badge <?php echo getOrderStatusClass($order['trang_thai_don_hang']); ?>">
                                <?php echo getOrderStatusLabel($order['trang_thai_don_hang']); ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Mã đơn hàng:</strong> <?php echo $order['ma_donhang']; ?></p>
                                    <p><strong>Ngày đặt hàng:</strong> <?php echo date('d/m/Y H:i', strtotime($order['ngay_dat'])); ?></p>
                                    <p>
                                        <strong>Phương thức thanh toán:</strong> 
                                        <?php echo getPaymentMethodLabel($order['phuong_thuc_thanh_toan']); ?>
                                    </p>
                                    <p>
                                        <strong>Trạng thái thanh toán:</strong>
                                        <span class="badge <?php echo $order['trang_thai_thanh_toan'] ? 'bg-success' : 'bg-warning'; ?>">
                                            <?php echo $order['trang_thai_thanh_toan'] ? 'Đã thanh toán' : 'Chưa thanh toán'; ?>
                                        </span>
                                    </p>
                                    <?php if (!empty($order['ma_giam_gia'])): ?>
                                    <p><strong>Mã giảm giá:</strong> <?php echo $order['ma_giam_gia']; ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Tổng giá trị:</strong> <?php echo number_format($order['tong_tien'], 0, ',', '.'); ?>đ</p>
                                    <p><strong>Phí vận chuyển:</strong> <?php echo number_format($order['phi_vanchuyen'], 0, ',', '.'); ?>đ</p>
                                    <p><strong>Giảm giá:</strong> <?php echo number_format($order['giam_gia'], 0, ',', '.'); ?>đ</p>
                                    <p><strong>Thành tiền:</strong> <span class="text-primary fw-bold"><?php echo number_format($order['thanh_tien'], 0, ',', '.'); ?>đ</span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Customer Information -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Thông tin khách hàng</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Họ tên:</strong> <?php echo htmlspecialchars($order['ho_ten']); ?></p>
                                    <p><strong>Email:</strong> <?php echo htmlspecialchars($order['email']); ?></p>
                                    <p><strong>Số điện thoại:</strong> <?php echo htmlspecialchars($order['sodienthoai']); ?></p>
                                    <?php if ($order['id_user']): ?>
                                    <p>
                                        <strong>Tài khoản:</strong> 
                                        <a href="customers.php?edit=<?php echo $order['id_user']; ?>">
                                            <?php echo $order['customer_name']; ?> (ID: <?php echo $order['id_user']; ?>)
                                        </a>
                                    </p>
                                    <?php else: ?>
                                    <p><strong>Tài khoản:</strong> <span class="text-muted">Khách vãng lai</span></p>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Địa chỉ:</strong> <?php echo htmlspecialchars($order['diachi']); ?></p>
                                    <p><strong>Phường/Xã:</strong> <?php echo htmlspecialchars($order['phuong_xa']); ?></p>
                                    <p><strong>Quận/Huyện:</strong> <?php echo htmlspecialchars($order['quan_huyen']); ?></p>
                                    <p><strong>Tỉnh/Thành phố:</strong> <?php echo htmlspecialchars($order['tinh_tp']); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Order Items -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Sản phẩm đã đặt</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th style="width: 60px;">Ảnh</th>
                                            <th>Sản phẩm</th>
                                            <th>Thuộc tính</th>
                                            <th class="text-end">Giá</th>
                                            <th class="text-center" style="width: 80px;">SL</th>
                                            <th class="text-end">Thành tiền</th>
                                            <th class="text-center">Đánh giá</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($order_items as $item): ?>
                                        <tr>
                                            <td>
                                                <?php if ($item['hinhanh']): ?>
                                                <img src="../<?php echo $item['hinhanh']; ?>" alt="<?php echo htmlspecialchars($item['tensp']); ?>" class="img-thumbnail" style="max-width: 50px; max-height: 50px;">
                                                <?php else: ?>
                                                <div class="bg-light text-center" style="width: 50px; height: 50px; line-height: 50px;">
                                                    <i class="fas fa-image text-muted"></i>
                                                </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="../product.php?id=<?php echo $item['id_sanpham']; ?>" target="_blank">
                                                    <?php echo htmlspecialchars($item['tensp']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo htmlspecialchars($item['thuoc_tinh'] ?? ''); ?></td>
                                            <td class="text-end"><?php echo number_format($item['gia'], 0, ',', '.'); ?>đ</td>
                                            <td class="text-center"><?php echo $item['soluong']; ?></td>
                                            <td class="text-end"><?php echo number_format($item['thanh_tien'], 0, ',', '.'); ?>đ</td>
                                            <td class="text-center">
                                                <?php if ($item['da_danh_gia']): ?>
                                                <span class="badge bg-success"><i class="fas fa-check"></i> Đã đánh giá</span>
                                                <?php else: ?>
                                                <span class="badge bg-secondary">Chưa đánh giá</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Return Requests -->
                    <?php if (count($return_requests) > 0): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Yêu cầu hoàn trả</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Sản phẩm</th>
                                            <th>Lý do</th>
                                            <th>Ngày yêu cầu</th>
                                            <th>Trạng thái</th>
                                            <th>Thao tác</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($return_requests as $return): ?>
                                        <tr>
                                            <td><?php echo $return['id_hoantra']; ?></td>
                                            <td><?php echo htmlspecialchars($return['tensanpham']); ?></td>
                                            <td><?php echo htmlspecialchars($return['lydo']); ?></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($return['ngaytao'])); ?></td>
                                            <td>
                                                <span class="badge <?php echo getReturnStatusClass($return['trangthai']); ?>">
                                                    <?php echo getReturnStatusLabel($return['trangthai']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="returns.php?id=<?php echo $return['id_hoantra']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i> Chi tiết
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Order Notes -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Ghi chú đơn hàng</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($order['ghi_chu'])): ?>
                                <div class="alert alert-info">
                                    <strong>Ghi chú từ khách hàng:</strong><br>
                                    <?php echo nl2br(htmlspecialchars($order['ghi_chu'])); ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">Khách hàng không để lại ghi chú.</p>
                            <?php endif; ?>
                            
                            <form method="post" action="">
                                <div class="mb-3">
                                    <label for="order_note" class="form-label">Thêm ghi chú</label>
                                    <textarea class="form-control" id="order_note" name="order_note" rows="3" required></textarea>
                                </div>
                                <button type="submit" name="add_note" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Lưu ghi chú
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Order Management -->
                <div class="col-md-4">
                    <!-- Update Status -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Cập nhật trạng thái</h5>
                        </div>
                        <div class="card-body">
                            <form method="post" action="">
                                <div class="mb-3">
                                    <label for="order_status" class="form-label">Trạng thái đơn hàng</label>
                                    <select class="form-select" id="order_status" name="order_status" required>
                                        <option value="1" <?php echo $order['trang_thai_don_hang'] == 1 ? 'selected' : ''; ?>>Chờ xác nhận</option>
                                        <option value="2" <?php echo $order['trang_thai_don_hang'] == 2 ? 'selected' : ''; ?>>Đã xác nhận</option>
                                        <option value="3" <?php echo $order['trang_thai_don_hang'] == 3 ? 'selected' : ''; ?>>Đang giao hàng</option>
                                        <option value="4" <?php echo $order['trang_thai_don_hang'] == 4 ? 'selected' : ''; ?>>Đã giao</option>
                                        <option value="5" <?php echo $order['trang_thai_don_hang'] == 5 ? 'selected' : ''; ?>>Đã hủy</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="admin_note" class="form-label">Ghi chú</label>
                                    <textarea class="form-control" id="admin_note" name="admin_note" rows="2"></textarea>
                                </div>
                                <button type="submit" name="update_status" class="btn btn-success">
                                    <i class="fas fa-save"></i> Cập nhật trạng thái
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Order Timeline -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Lịch sử đơn hàng</h5>
                        </div>
                        <div class="card-body">
                            <div class="timeline">
                                <?php if (count($order_history) > 0): ?>
                                    <?php foreach ($order_history as $history): ?>
                                        <div class="timeline-item">
                                            <div class="timeline-marker"></div>
                                            <div class="timeline-content">
                                                <h6 class="timeline-title">
                                                    <?php echo getOrderActionLabel($history['hanh_dong']); ?>
                                                    <small class="text-muted">
                                                        <?php echo date('d/m/Y H:i', strtotime($history['ngay_thaydoi'])); ?>
                                                    </small>
                                                </h6>
                                                <p class="mb-0">
                                                    <strong><?php echo htmlspecialchars($history['nguoi_thuchien']); ?></strong>
                                                </p>
                                                <p class="mb-0"><?php echo htmlspecialchars($history['ghi_chu']); ?></p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted">Chưa có lịch sử đơn hàng.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<style>
/* Timeline styling */
.timeline {
    position: relative;
    padding-left: 30px;
}
.timeline-item {
    position: relative;
    padding-bottom: 20px;
    border-left: 2px solid #e9ecef;
    margin-left: 10px;
}
.timeline-marker {
    position: absolute;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #007bff;
    left: -7px;
    top: 5px;
}
.timeline-content {
    padding-left: 15px;
    padding-bottom: 10px;
}
.timeline-title {
    margin-bottom: 5px;
}

/* Print styling */
@media print {
    .sidebar, .btn-toolbar, form, .no-print {
        display: none !important;
    }
    main {
        width: 100% !important;
    }
}
</style>

<?php
// Helper functions
function getOrderStatusLabel($status) {
    $labels = [
        1 => 'Chờ xác nhận',
        2 => 'Đã xác nhận',
        3 => 'Đang giao hàng',
        4 => 'Đã giao',
        5 => 'Đã hủy'
    ];
    return $labels[$status] ?? 'Không xác định';
}

function getOrderStatusClass($status) {
    $classes = [
        1 => 'bg-warning',
        2 => 'bg-info',
        3 => 'bg-primary',
        4 => 'bg-success',
        5 => 'bg-danger'
    ];
    return $classes[$status] ?? 'bg-secondary';
}

function getPaymentMethodLabel($method) {
    $labels = [
        'cod' => 'Thanh toán khi nhận hàng (COD)',
        'vnpay' => 'Thanh toán VNPAY',
        'bank_transfer' => 'Chuyển khoản ngân hàng',
        '0' => 'Thanh toán khi nhận hàng (COD)'
    ];
    return $labels[$method] ?? 'Không xác định';
}

function getReturnStatusLabel($status) {
    $labels = [
        1 => 'Chờ xác nhận',
        2 => 'Đã xác nhận',
        3 => 'Đang xử lý',
        4 => 'Hoàn thành',
        5 => 'Từ chối'
    ];
    return $labels[$status] ?? 'Không xác định';
}

function getReturnStatusClass($status) {
    $classes = [
        1 => 'bg-warning',
        2 => 'bg-info',
        3 => 'bg-primary',
        4 => 'bg-success',
        5 => 'bg-danger'
    ];
    return $classes[$status] ?? 'bg-secondary';
}

function getOrderActionLabel($action) {
    $labels = [
        'create' => 'Tạo đơn hàng',
        'update' => 'Cập nhật đơn hàng',
        'update_status' => 'Cập nhật trạng thái',
        'cancel' => 'Hủy đơn hàng',
        'payment' => 'Thanh toán',
        'add_note' => 'Thêm ghi chú',
        'login' => 'Đăng nhập',
        'logout' => 'Đăng xuất'
    ];
    return $labels[$action] ?? $action;
}
?>

<?php include 'includes/footer.php'; ?>
