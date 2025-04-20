<?php
// Set page title
$page_title = 'Chi tiết khách hàng';

// Include header
include('includes/header.php');

// Include database connection
include('../config/config.php');

// Get customer ID from URL parameter
$customer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($customer_id <= 0) {
    $_SESSION['error_message'] = 'ID khách hàng không hợp lệ';
    header('Location: customers.php');
    exit;
}

// Get customer information - updated column names
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = 'Không tìm thấy khách hàng với ID này';
    header('Location: customers.php');
    exit;
}

$customer = $result->fetch_assoc();

// Get orders for this customer - updated column names
$orders_query = "SELECT id, ma_donhang, tong_tien, trang_thai_thanh_toan, trang_thai_don_hang, ngay_dat 
                 FROM donhang 
                 WHERE id_user = ? 
                 ORDER BY ngay_dat DESC";
$orders_stmt = $conn->prepare($orders_query);
$orders_stmt->bind_param("i", $customer_id);
$orders_stmt->execute();
$orders_result = $orders_stmt->get_result();

// Get reviews for this customer - updated column names
$reviews_query = "SELECT dg.*, sp.tensanpham 
                  FROM danhgia dg
                  JOIN sanpham sp ON dg.id_sanpham = sp.id
                  WHERE dg.id_user = ? 
                  ORDER BY dg.ngay_danhgia DESC";
$reviews_stmt = $conn->prepare($reviews_query);
$reviews_stmt->bind_param("i", $customer_id);
$reviews_stmt->execute();
$reviews_result = $reviews_stmt->get_result();

// Order statuses - with the correct status codes
$order_statuses = [
    1 => ['text' => 'Chờ xác nhận', 'class' => 'warning'],
    2 => ['text' => 'Đã xác nhận', 'class' => 'info'],
    3 => ['text' => 'Đang giao hàng', 'class' => 'primary'],
    4 => ['text' => 'Đã giao hàng', 'class' => 'success'],
    5 => ['text' => 'Đã hủy', 'class' => 'danger']
];

// Handle status toggle if requested
if (isset($_POST['toggle_status'])) {
    $new_status = ($customer['trang_thai'] == 1) ? 0 : 1;
    
    $update_stmt = $conn->prepare("UPDATE users SET trang_thai = ? WHERE id = ?");
    $update_stmt->bind_param("ii", $new_status, $customer_id);
    
    if ($update_stmt->execute()) {
        $status_text = ($new_status == 1) ? 'mở khóa' : 'khóa';
        $_SESSION['success_message'] = "Đã $status_text tài khoản thành công";
        
        // Log the activity
        $admin_id = $_SESSION['admin_id'];
        $action = ($new_status == 1) ? 'unlock' : 'lock';
        $details = "Đã $status_text tài khoản khách hàng: " . $customer['taikhoan'];
        logAdminActivity($conn, $admin_id, $action, 'customer', $customer_id, $details);
        
        // Refresh customer data
        $customer['trang_thai'] = $new_status;
    } else {
        $_SESSION['error_message'] = "Lỗi khi cập nhật trạng thái: " . $conn->error;
    }
}

// Handle delete if requested
if (isset($_POST['delete_customer'])) {
    // First check if this customer has orders
    $check_orders = $conn->prepare("SELECT COUNT(*) as count FROM donhang WHERE id_user = ?");
    $check_orders->bind_param("i", $customer_id);
    $check_orders->execute();
    $orders_count = $check_orders->get_result()->fetch_assoc()['count'];
    
    if ($orders_count > 0) {
        $_SESSION['error_message'] = "Không thể xóa khách hàng này vì đã có $orders_count đơn hàng liên quan";
    } else {
        // Delete the customer
        $delete_stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $delete_stmt->bind_param("i", $customer_id);
        
        if ($delete_stmt->execute()) {
            // Log the activity
            $admin_id = $_SESSION['admin_id'];
            $details = "Đã xóa tài khoản khách hàng: " . $customer['taikhoan'];
            logAdminActivity($conn, $admin_id, 'delete', 'customer', $customer_id, $details);
            
            $_SESSION['success_message'] = "Đã xóa tài khoản khách hàng thành công";
            header('Location: customers.php');
            exit;
        } else {
            $_SESSION['error_message'] = "Lỗi khi xóa tài khoản: " . $conn->error;
        }
    }
}

// Handle password reset if requested
if (isset($_POST['reset_password'])) {
    // Generate a random password
    $new_password = generateRandomPassword();
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    $update_stmt = $conn->prepare("UPDATE users SET matkhau = ? WHERE id = ?");
    $update_stmt->bind_param("si", $hashed_password, $customer_id);
    
    if ($update_stmt->execute()) {
        $_SESSION['success_message'] = "Đã đặt lại mật khẩu thành công";
        $_SESSION['new_password'] = $new_password; // Store temporarily to display to admin
        
        // Log the activity
        $admin_id = $_SESSION['admin_id'];
        $details = "Đã đặt lại mật khẩu cho khách hàng: " . $customer['taikhoan'];
        logAdminActivity($conn, $admin_id, 'reset_password', 'customer', $customer_id, $details);
    } else {
        $_SESSION['error_message'] = "Lỗi khi đặt lại mật khẩu: " . $conn->error;
    }
}

// Function to generate random password
function generateRandomPassword($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $password = '';
    $chars_length = strlen($chars) - 1;
    
    for ($i = 0; $i < $length; $password .= $chars[rand(0, $chars_length)], $i++);
    
    return $password;
}
?>

<!-- Main content -->
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="customers.php">Quản lý khách hàng</a></li>
            <li class="breadcrumb-item active" aria-current="page">Chi tiết khách hàng</li>
        </ol>
    </nav>
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2"><?php echo htmlspecialchars($customer['ten']); ?></h1>
        <div class="btn-toolbar">
            <a href="customers.php" class="btn btn-sm btn-outline-secondary me-2">
                <i class="bi bi-arrow-left"></i> Quay lại
            </a>
            
            <form method="post" class="d-inline me-2">
                <button type="submit" name="reset_password" class="btn btn-sm btn-info me-2"
                        onclick="return confirm('Bạn có chắc chắn muốn đặt lại mật khẩu cho tài khoản này?');">
                    <i class="bi bi-key"></i> Đặt lại mật khẩu
                </button>
            </form>
            
            <form method="post" class="d-inline me-2">
                <?php if ($customer['trang_thai'] == 1): ?>
                    <button type="submit" name="toggle_status" class="btn btn-sm btn-warning" 
                            onclick="return confirm('Bạn có chắc chắn muốn khóa tài khoản này?');">
                        <i class="bi bi-lock"></i> Khóa tài khoản
                    </button>
                <?php else: ?>
                    <button type="submit" name="toggle_status" class="btn btn-sm btn-success">
                        <i class="bi bi-unlock"></i> Mở khóa tài khoản
                    </button>
                <?php endif; ?>
            </form>
            
            <form method="post" class="d-inline">
                <button type="submit" name="delete_customer" class="btn btn-sm btn-danger"
                        onclick="return confirm('Bạn có chắc chắn muốn xóa tài khoản này? Hành động này không thể hoàn tác.');">
                    <i class="bi bi-trash"></i> Xóa tài khoản
                </button>
            </form>
        </div>
    </div>
    
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['new_password'])): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <strong>Mật khẩu mới:</strong> <?php echo $_SESSION['new_password']; ?>
            <br><small class="text-muted">Vui lòng ghi nhớ hoặc sao chép mật khẩu này và cung cấp cho khách hàng. Mật khẩu sẽ biến mất khi bạn rời khỏi trang.</small>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['new_password']); ?>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Thông tin khách hàng</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3 text-center">
                        <div class="avatar-placeholder rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center bg-light" style="width: 100px; height: 100px;">
                            <?php if (!empty($customer['anh_dai_dien'])): ?>
                                <img src="../uploads/avatars/<?php echo htmlspecialchars($customer['anh_dai_dien']); ?>" class="rounded-circle" width="100" height="100" alt="Avatar">
                            <?php else: ?>
                                <i class="bi bi-person-circle display-4 text-secondary"></i>
                            <?php endif; ?>
                        </div>
                        <h5><?php echo htmlspecialchars($customer['ten']); ?></h5>
                        <p class="text-muted mb-0"><?php echo htmlspecialchars($customer['taikhoan']); ?></p>
                    </div>
                    
                    <hr>
                    
                    <div class="customer-info">
                        <div class="mb-2 d-flex justify-content-between">
                            <span class="text-muted">Trạng thái:</span>
                            <?php if ($customer['trang_thai'] == 1): ?>
                                <span class="badge bg-success">Đang hoạt động</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Đã khóa</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-2 d-flex justify-content-between">
                            <span class="text-muted">Ngày đăng ký:</span>
                            <span><?php echo date('d/m/Y', strtotime($customer['ngay_tao'])); ?></span>
                        </div>
                        
                        <div class="mb-2 d-flex justify-content-between">
                            <span class="text-muted">Lần đăng nhập cuối:</span>
                            <span>
                                <?php 
                                    echo !empty($customer['lan_dang_nhap_cuoi']) 
                                        ? date('d/m/Y H:i', strtotime($customer['lan_dang_nhap_cuoi']))
                                        : 'Chưa có';
                                ?>
                            </span>
                        </div>
                        
                        <div class="mb-2 d-flex justify-content-between">
                            <span class="text-muted">Loại tài khoản:</span>
                            <?php if ($customer['loai_user'] == 0): ?>
                                <span class="badge bg-info">Khách hàng</span>
                            <?php elseif ($customer['loai_user'] == 1): ?>
                                <span class="badge bg-primary">Quản lý</span>
                            <?php else: ?>
                                <span class="badge bg-dark">Admin</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Thông tin liên hệ</h5>
                </div>
                <div class="card-body">
                    <div class="customer-contact">
                        <div class="mb-2">
                            <span class="text-muted">Email:</span>
                            <div><?php echo htmlspecialchars($customer['email'] ?? 'Chưa cung cấp'); ?></div>
                        </div>
                        
                        <div class="mb-2">
                            <span class="text-muted">Số điện thoại:</span>
                            <div><?php echo htmlspecialchars($customer['sodienthoai'] ?? 'Chưa cung cấp'); ?></div>
                        </div>
                        
                        <div class="mb-2">
                            <span class="text-muted">Địa chỉ:</span>
                            <div><?php echo htmlspecialchars($customer['diachi'] ?? 'Chưa cung cấp'); ?></div>
                        </div>
                        
                        <?php if (!empty($customer['tinh_tp']) || !empty($customer['quan_huyen'])): ?>
                            <div class="mb-0">
                                <span class="text-muted">Khu vực:</span>
                                <div>
                                    <?php 
                                        $location = [];
                                        if (!empty($customer['quan_huyen'])) $location[] = htmlspecialchars($customer['quan_huyen']);
                                        if (!empty($customer['tinh_tp'])) $location[] = htmlspecialchars($customer['tinh_tp']);
                                        echo !empty($location) ? implode(', ', $location) : 'Chưa cung cấp';
                                    ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <!-- Orders tab -->
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Lịch sử đơn hàng</h5>
                        <span class="badge bg-primary"><?php echo $orders_result->num_rows; ?> đơn hàng</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if ($orders_result->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Mã đơn hàng</th>
                                        <th>Ngày đặt</th>
                                        <th>Tổng tiền</th>
                                        <th>Trạng thái</th>
                                        <th>Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($order = $orders_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($order['ma_donhang']); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($order['ngay_dat'])); ?></td>
                                            <td><?php echo number_format($order['tong_tien'], 0, ',', '.'); ?>₫</td>
                                            <td>
                                                <?php 
                                                    $status = $order_statuses[$order['trang_thai_don_hang']] ?? ['text' => 'Không xác định', 'class' => 'secondary'];
                                                    echo '<span class="badge bg-' . $status['class'] . '">' . $status['text'] . '</span>';
                                                    
                                                    if ($order['trang_thai_thanh_toan'] == 1) {
                                                        echo ' <span class="badge bg-success">Đã thanh toán</span>';
                                                    }
                                                ?>
                                            </td>
                                            <td>
                                                <a href="order-detail.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-eye"></i> Xem
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="p-4 text-center text-muted">
                            <i class="bi bi-bag-x display-4"></i>
                            <p class="mt-3">Khách hàng chưa có đơn hàng nào</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Reviews tab -->
            <div class="card">
                <div class="card-header bg-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Đánh giá sản phẩm</h5>
                        <span class="badge bg-primary"><?php echo $reviews_result->num_rows; ?> đánh giá</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if ($reviews_result->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Sản phẩm</th>
                                        <th>Đánh giá</th>
                                        <th>Nội dung</th>
                                        <th>Ngày đánh giá</th>
                                        <th>Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($review = $reviews_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($review['tensanpham']); ?></td>
                                            <td>
                                                <div class="rating">
                                                    <?php 
                                                        for ($i = 1; $i <= 5; $i++) {
                                                            if ($i <= $review['diem']) {
                                                                echo '<i class="bi bi-star-fill text-warning"></i> ';
                                                            } else {
                                                                echo '<i class="bi bi-star text-muted"></i> ';
                                                            }
                                                        }
                                                    ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="text-truncate" style="max-width: 200px;" title="<?php echo htmlspecialchars($review['noi_dung']); ?>">
                                                    <?php echo htmlspecialchars($review['noi_dung']); ?>
                                                </div>
                                            </td>
                                            <td><?php echo date('d/m/Y', strtotime($review['ngay_danhgia'])); ?></td>
                                            <td>
                                                <a href="review-detail.php?id=<?php echo $review['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-eye"></i> Xem
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="p-4 text-center text-muted">
                            <i class="bi bi-chat-square-text display-4"></i>
                            <p class="mt-3">Khách hàng chưa có đánh giá nào</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php
// Include footer
include('includes/footer.php');
?>