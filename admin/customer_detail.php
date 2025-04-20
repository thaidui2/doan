<?php
// Kiểm tra đăng nhập và quyền admin
session_start();
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_loai']) || $_SESSION['admin_loai'] < 1) {
    header('Location: login.php');
    exit();
}

// Kết nối database
require_once '../config/config.php';
include 'includes/header.php';
include 'includes/sidebar.php';

// Kiểm tra ID khách hàng
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: customers.php?error=ID khách hàng không hợp lệ');
    exit();
}

$customer_id = intval($_GET['id']);

// Lấy thông tin khách hàng
$customer_sql = "SELECT * FROM users WHERE id = ? AND loai_user = 0";
$customer_stmt = $conn->prepare($customer_sql);
$customer_stmt->bind_param('i', $customer_id);
$customer_stmt->execute();
$customer_result = $customer_stmt->get_result();

if ($customer_result->num_rows === 0) {
    header('Location: customers.php?error=Không tìm thấy khách hàng');
    exit();
}

$customer = $customer_result->fetch_assoc();

// Lấy số liệu thống kê
$stats_sql = "SELECT 
              COUNT(d.id) as total_orders,
              SUM(CASE WHEN d.trang_thai_don_hang = 4 THEN 1 ELSE 0 END) as completed_orders,
              SUM(CASE WHEN d.trang_thai_don_hang = 5 THEN 1 ELSE 0 END) as canceled_orders,
              SUM(d.thanh_tien) as total_spent,
              MIN(d.ngay_dat) as first_order_date,
              MAX(d.ngay_dat) as last_order_date
              FROM donhang d
              WHERE d.id_user = ?";
              
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param('i', $customer_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

// Lấy danh sách đơn hàng
$orders_sql = "SELECT d.*, 
              (SELECT COUNT(*) FROM donhang_chitiet WHERE id_donhang = d.id) as item_count
              FROM donhang d
              WHERE d.id_user = ?
              ORDER BY d.ngay_dat DESC
              LIMIT 10";
              
$orders_stmt = $conn->prepare($orders_sql);
$orders_stmt->bind_param('i', $customer_id);
$orders_stmt->execute();
$orders = $orders_stmt->get_result();

// Lấy danh sách đánh giá
$reviews_sql = "SELECT r.*, s.tensanpham, s.hinhanh
               FROM danhgia r
               JOIN sanpham s ON r.id_sanpham = s.id
               WHERE r.id_user = ?
               ORDER BY r.ngay_danhgia DESC
               LIMIT 5";
               
$reviews_stmt = $conn->prepare($reviews_sql);
$reviews_stmt->bind_param('i', $customer_id);
$reviews_stmt->execute();
$reviews = $reviews_stmt->get_result();

// Xử lý reset password nếu được yêu cầu
if (isset($_POST['action']) && $_POST['action'] === 'reset_password') {
    $username = $customer['taikhoan'];
    $new_password = $username . '@' . rand(1000, 9999);
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    $update_sql = "UPDATE users SET matkhau = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param('si', $hashed_password, $customer_id);
    
    if ($update_stmt->execute()) {
        // Ghi log
        $log_sql = "INSERT INTO nhat_ky (id_user, hanh_dong, doi_tuong_loai, doi_tuong_id, chi_tiet, ip_address) 
                   VALUES (?, 'reset_password', 'customer', ?, ?, ?)";
        $log_stmt = $conn->prepare($log_sql);
        $admin_id = $_SESSION['admin_id'];
        $detail = "Đã đặt lại mật khẩu cho khách hàng: " . $customer['ten'];
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $log_stmt->bind_param('iiss', $admin_id, $customer_id, $detail, $ip);
        $log_stmt->execute();
        
        $reset_success = "Mật khẩu mới: " . $new_password;
    } else {
        $reset_error = "Không thể đặt lại mật khẩu";
    }
}

// Xử lý toggle trạng thái tài khoản
if (isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    $new_status = $customer['trang_thai'] ? 0 : 1;
    $action = $new_status ? 'unlock' : 'lock';
    
    $update_sql = "UPDATE users SET trang_thai = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param('ii', $new_status, $customer_id);
    
    if ($update_stmt->execute()) {
        // Ghi log
        $log_sql = "INSERT INTO nhat_ky (id_user, hanh_dong, doi_tuong_loai, doi_tuong_id, chi_tiet, ip_address) 
                   VALUES (?, ?, 'customer', ?, ?, ?)";
        $log_stmt = $conn->prepare($log_sql);
        $admin_id = $_SESSION['admin_id'];
        $detail = "Đã " . ($new_status ? 'mở khóa' : 'khóa') . " tài khoản khách hàng: " . $customer['ten'];
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $log_stmt->bind_param('isiss', $admin_id, $action, $customer_id, $detail, $ip);
        $log_stmt->execute();
        
        // Cập nhật lại thông tin khách hàng
        $customer['trang_thai'] = $new_status;
        $status_success = "Đã " . ($new_status ? "mở khóa" : "khóa") . " tài khoản thành công";
    } else {
        $status_error = "Không thể thay đổi trạng thái tài khoản";
    }
}

// Format tiền VNĐ
function formatVND($amount) {
    if ($amount === null) return '0 ₫';
    return number_format($amount, 0, ',', '.') . ' ₫';
}

// Hiển thị trạng thái đơn hàng
function getOrderStatus($status) {
    switch ($status) {
        case 1:
            return '<span class="badge bg-info">Chờ xác nhận</span>';
        case 2:
            return '<span class="badge bg-primary">Đã xác nhận</span>';
        case 3:
            return '<span class="badge bg-warning text-dark">Đang giao</span>';
        case 4:
            return '<span class="badge bg-success">Đã giao</span>';
        case 5:
            return '<span class="badge bg-danger">Đã hủy</span>';
        default:
            return '<span class="badge bg-secondary">Không xác định</span>';
    }
}

// Hiển thị phương thức thanh toán
function getPaymentMethod($method) {
    switch ($method) {
        case 'cod':
            return 'Tiền mặt khi nhận hàng';
        case 'vnpay':
            return 'VNPay';
        case 'bank_transfer':
            return 'Chuyển khoản ngân hàng';
        default:
            return 'Không xác định';
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi tiết khách hàng - Bug Shop Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/customer_detail.css">
</head>
<body>
    
    <!-- Main Content -->
    <div class="col-md-10 col-lg-10 ms-auto">
        <div class="container-fluid px-4 py-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0 text-gray-800">
                        Chi tiết khách hàng
                    </h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="customers.php">Danh sách khách hàng</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Chi tiết khách hàng</li>
                        </ol>
                    </nav>
                </div>
                <a href="customers.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Quay lại danh sách
                </a>
            </div>
            
            <!-- Thông báo -->
            <?php if (isset($reset_success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($reset_success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($reset_error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($reset_error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($status_success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($status_success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($status_error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($status_error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <!-- Thông tin khách hàng -->
                <div class="col-md-4">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary">Thông tin khách hàng</h6>
                            <span class="badge <?php echo $customer['trang_thai'] ? 'bg-success' : 'bg-danger'; ?>">
                                <?php echo $customer['trang_thai'] ? 'Hoạt động' : 'Bị khóa'; ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-3">
                                <?php if (!empty($customer['anh_dai_dien'])): ?>
                                    <img src="../<?php echo htmlspecialchars($customer['anh_dai_dien']); ?>" alt="Avatar" class="avatar rounded-circle img-thumbnail">
                                <?php else: ?>
                                    <div class="avatar-placeholder rounded-circle mx-auto">
                                        <?php echo strtoupper(substr($customer['ten'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                <h5 class="mt-3 mb-0"><?php echo htmlspecialchars($customer['ten']); ?></h5>
                                <p class="text-muted mb-0">ID: <?php echo $customer['id']; ?></p>
                                <p class="text-muted">Thành viên từ: <?php echo date('d/m/Y', strtotime($customer['ngay_tao'])); ?></p>
                            </div>
                            
                            <hr>
                            
                            <div class="info-item">
                                <div class="fw-bold text-muted small">Tên đăng nhập</div>
                                <div><?php echo htmlspecialchars($customer['taikhoan']); ?></div>
                            </div>
                            
                            <div class="info-item">
                                <div class="fw-bold text-muted small">Email</div>
                                <div><?php echo !empty($customer['email']) ? htmlspecialchars($customer['email']) : '<span class="text-muted fst-italic">Chưa cung cấp</span>'; ?></div>
                            </div>
                            
                            <div class="info-item">
                                <div class="fw-bold text-muted small">Số điện thoại</div>
                                <div><?php echo !empty($customer['sodienthoai']) ? htmlspecialchars($customer['sodienthoai']) : '<span class="text-muted fst-italic">Chưa cung cấp</span>'; ?></div>
                            </div>
                            
                            <div class="info-item">
                                <div class="fw-bold text-muted small">Địa chỉ</div>
                                <div>
                                    <?php if (!empty($customer['diachi'])): ?>
                                        <?php echo htmlspecialchars($customer['diachi']); ?>
                                        <?php if (!empty($customer['phuong_xa']) || !empty($customer['quan_huyen']) || !empty($customer['tinh_tp'])): ?>
                                            <br>
                                            <?php 
                                                $address_parts = [];
                                                if(!empty($customer['phuong_xa'])) $address_parts[] = htmlspecialchars($customer['phuong_xa']);
                                                if(!empty($customer['quan_huyen'])) $address_parts[] = htmlspecialchars($customer['quan_huyen']);
                                                if(!empty($customer['tinh_tp'])) $address_parts[] = htmlspecialchars($customer['tinh_tp']);
                                                echo implode(", ", $address_parts);
                                            ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted fst-italic">Chưa cung cấp</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="info-item">
                                <div class="fw-bold text-muted small">Lần đăng nhập cuối</div>
                                <div>
                                    <?php echo $customer['lan_dang_nhap_cuoi'] ? date('d/m/Y H:i', strtotime($customer['lan_dang_nhap_cuoi'])) : '<span class="text-muted fst-italic">Chưa đăng nhập</span>'; ?>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="d-grid gap-2">
                                <form method="POST" action="" id="formToggleStatus">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <button type="submit" class="btn btn-block w-100 <?php echo $customer['trang_thai'] ? 'btn-warning' : 'btn-success'; ?>" 
                                           onclick="return confirm('Bạn có chắc muốn <?php echo $customer['trang_thai'] ? 'khóa' : 'mở khóa'; ?> tài khoản này?')">
                                        <i class="fas <?php echo $customer['trang_thai'] ? 'fa-lock' : 'fa-unlock'; ?>"></i> 
                                        <?php echo $customer['trang_thai'] ? 'Khóa tài khoản' : 'Mở khóa tài khoản'; ?>
                                    </button>
                                </form>
                                
                                <form method="POST" action="" id="formResetPassword">
                                    <input type="hidden" name="action" value="reset_password">
                                    <button type="submit" class="btn btn-info btn-block w-100" 
                                           onclick="return confirm('Bạn có chắc muốn đặt lại mật khẩu cho tài khoản này?')">
                                        <i class="fas fa-key"></i> Đặt lại mật khẩu
                                    </button>
                                </form>
                                
                                <a href="orders.php?customer=<?php echo $customer_id; ?>" class="btn btn-primary btn-block">
                                    <i class="fas fa-shopping-bag"></i> Xem tất cả đơn hàng
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Thống kê và hoạt động gần đây -->
                <div class="col-md-8">
                    <!-- Thống kê khách hàng -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Thống kê mua hàng</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
                                    <div class="stats-item text-center">
                                        <div class="stats-value"><?php echo $stats['total_orders'] ?? 0; ?></div>
                                        <div class="stats-label">Đơn hàng</div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
                                    <div class="stats-item text-center">
                                        <div class="stats-value"><?php echo $stats['completed_orders'] ?? 0; ?></div>
                                        <div class="stats-label">Đã hoàn thành</div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6 mb-3 mb-md-0">
                                    <div class="stats-item text-center">
                                        <div class="stats-value"><?php echo $stats['canceled_orders'] ?? 0; ?></div>
                                        <div class="stats-label">Đã hủy</div>
                                    </div>
                                </div>
                                <div class="col-md-3 col-sm-6">
                                    <div class="stats-item text-center">
                                        <div class="stats-value"><?php echo formatVND($stats['total_spent'] ?? 0); ?></div>
                                        <div class="stats-label">Tổng chi tiêu</div>
                                    </div>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <div class="info-item">
                                        <div class="fw-bold text-muted small">Đơn hàng đầu tiên</div>
                                        <div>
                                            <?php echo $stats['first_order_date'] ? date('d/m/Y H:i', strtotime($stats['first_order_date'])) : 'Chưa có đơn hàng'; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-item">
                                        <div class="fw-bold text-muted small">Đơn hàng gần nhất</div>
                                        <div>
                                            <?php echo $stats['last_order_date'] ? date('d/m/Y H:i', strtotime($stats['last_order_date'])) : 'Chưa có đơn hàng'; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Đơn hàng gần đây -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary">Đơn hàng gần đây</h6>
                            <a href="orders.php?customer=<?php echo $customer_id; ?>" class="btn btn-sm btn-primary">
                                Xem tất cả
                            </a>
                        </div>
                        <div class="card-body">
                            <?php if ($orders && $orders->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Mã đơn hàng</th>
                                                <th>Ngày đặt</th>
                                                <th>Sản phẩm</th>
                                                <th>Tổng tiền</th>
                                                <th>Trạng thái</th>
                                                <th>Thao tác</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($order = $orders->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($order['ma_donhang']); ?></td>
                                                    <td><?php echo date('d/m/Y', strtotime($order['ngay_dat'])); ?></td>
                                                    <td>
                                                        <span class="badge bg-secondary"><?php echo $order['item_count']; ?> sản phẩm</span>
                                                    </td>
                                                    <td><?php echo formatVND($order['thanh_tien']); ?></td>
                                                    <td><?php echo getOrderStatus($order['trang_thai_don_hang']); ?></td>
                                                    <td>
                                                        <a href="order_detail.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-primary">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <div class="text-muted mb-3">
                                        <i class="fas fa-shopping-cart fa-3x"></i>
                                    </div>
                                    <h5>Không có đơn hàng nào</h5>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Đánh giá sản phẩm gần đây -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Đánh giá sản phẩm</h6>
                        </div>
                        <div class="card-body">
                            <?php if ($reviews && $reviews->num_rows > 0): ?>
                                <?php while ($review = $reviews->fetch_assoc()): ?>
                                    <div class="review-item">
                                        <div class="d-flex">
                                            <div class="me-3">
                                                <?php if (!empty($review['hinhanh'])): ?>
                                                    <img src="../<?php echo htmlspecialchars($review['hinhanh']); ?>" 
                                                         alt="<?php echo htmlspecialchars($review['tensanpham']); ?>" 
                                                         class="review-product-img">
                                                <?php else: ?>
                                                    <div class="review-product-placeholder">
                                                        <i class="fas fa-image"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <h6 class="mb-1">
                                                    <a href="../product.php?id=<?php echo $review['id_sanpham']; ?>" target="_blank">
                                                        <?php echo htmlspecialchars($review['tensanpham']); ?>
                                                    </a>
                                                </h6>
                                                <div class="review-rating mb-1">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <?php if ($i <= $review['diem']): ?>
                                                            <i class="fas fa-star text-warning"></i>
                                                        <?php else: ?>
                                                            <i class="far fa-star text-warning"></i>
                                                        <?php endif; ?>
                                                    <?php endfor; ?>
                                                    <span class="text-muted ms-2">
                                                        <?php echo date('d/m/Y', strtotime($review['ngay_danhgia'])); ?>
                                                    </span>
                                                </div>
                                                <p class="mb-0"><?php echo htmlspecialchars($review['noi_dung']); ?></p>
                                                
                                                <?php if (!empty($review['hinh_anh'])): ?>
                                                    <div class="mt-2">
                                                        <a href="../<?php echo htmlspecialchars($review['hinh_anh']); ?>" target="_blank">
                                                            <img src="../<?php echo htmlspecialchars($review['hinh_anh']); ?>" 
                                                                 alt="Review image" class="review-image">
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="text-end mt-1">
                                            <span class="badge <?php echo $review['trang_thai'] ? 'bg-success' : 'bg-secondary'; ?>">
                                                <?php echo $review['trang_thai'] ? 'Hiển thị' : 'Đã ẩn'; ?>
                                            </span>
                                        </div>
                                    </div>
                                    <hr>
                                <?php endwhile; ?>
                                <div class="text-center">
                                    <a href="reviews.php?customer=<?php echo $customer_id; ?>" class="btn btn-outline-primary btn-sm">
                                        Xem tất cả đánh giá
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <div class="text-muted mb-3">
                                        <i class="fas fa-star fa-3x"></i>
                                    </div>
                                    <h5>Không có đánh giá nào</h5>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/customer_detail.js"></script>
</body>
</html>
