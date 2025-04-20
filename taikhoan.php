<?php
session_start();
require_once('config/config.php');

// Check if user is logged in
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    header('Location: dangnhap.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

$user_id = $_SESSION['user']['id'];

// Initialize variables for form handling
$success_message = '';
$error_message = '';

// Process form submission for profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Get and sanitize data
    $ten = trim($_POST['ten']);
    $email = trim($_POST['email']);
    $sodienthoai = trim($_POST['sodienthoai']);
    $diachi = trim($_POST['diachi']);
    $tinh_tp = trim($_POST['tinh_tp']);
    $quan_huyen = trim($_POST['quan_huyen']);
    $phuong_xa = trim($_POST['phuong_xa']);
    
    // Validation
    if (empty($ten)) {
        $error_message = 'Vui lòng nhập họ tên';
    } else {
        // Update user profile
        try {
            $stmt = $conn->prepare("
                UPDATE users SET 
                ten = ?, 
                email = ?, 
                sodienthoai = ?, 
                diachi = ?,
                tinh_tp = ?,
                quan_huyen = ?,
                phuong_xa = ?
                WHERE id = ?
            ");
            
            $stmt->bind_param("sssssssi", 
                $ten, 
                $email, 
                $sodienthoai, 
                $diachi,
                $tinh_tp,
                $quan_huyen,
                $phuong_xa,
                $user_id
            );
            
            if ($stmt->execute()) {
                $success_message = 'Cập nhật thông tin thành công!';
                
                // Update session data
                $_SESSION['user']['tenuser'] = $ten;
                
            } else {
                $error_message = 'Lỗi khi cập nhật thông tin: ' . $stmt->error;
            }
        } catch (Exception $e) {
            $error_message = 'Đã xảy ra lỗi: ' . $e->getMessage();
        }
    }
}

// Process password change form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate input
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = 'Vui lòng điền đầy đủ thông tin';
    } elseif ($new_password !== $confirm_password) {
        $error_message = 'Mật khẩu mới không khớp';
    } elseif (strlen($new_password) < 6) {
        $error_message = 'Mật khẩu mới phải có ít nhất 6 ký tự';
    } else {
        // Check current password
        $stmt = $conn->prepare("SELECT matkhau FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($current_password, $user['matkhau'])) {
                // Hash new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Update password
                $update_stmt = $conn->prepare("UPDATE users SET matkhau = ? WHERE id = ?");
                $update_stmt->bind_param("si", $hashed_password, $user_id);
                
                if ($update_stmt->execute()) {
                    $success_message = 'Mật khẩu đã được cập nhật thành công!';
                } else {
                    $error_message = 'Lỗi khi cập nhật mật khẩu: ' . $update_stmt->error;
                }
            } else {
                $error_message = 'Mật khẩu hiện tại không chính xác';
            }
        } else {
            $error_message = 'Không tìm thấy thông tin người dùng';
        }
    }
}

// Get user information
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // User not found - log out
    session_unset();
    session_destroy();
    header('Location: dangnhap.php');
    exit();
}

$user = $result->fetch_assoc();

// Get order count
$order_count_query = $conn->prepare("
    SELECT COUNT(*) as count FROM donhang WHERE id_user = ?
");
$order_count_query->bind_param("i", $user_id);
$order_count_query->execute();
$order_count_result = $order_count_query->get_result();
$order_count = $order_count_result->fetch_assoc()['count'];

// Get pending orders count
$pending_order_query = $conn->prepare("
    SELECT COUNT(*) as count FROM donhang 
    WHERE id_user = ? AND trang_thai_don_hang IN (1, 2, 3)
");
$pending_order_query->bind_param("i", $user_id);
$pending_order_query->execute();
$pending_result = $pending_order_query->get_result();
$pending_orders = $pending_result->fetch_assoc()['count'];

// Get recent orders
$recent_orders_query = $conn->prepare("
    SELECT 
        id, 
        ma_donhang, 
        thanh_tien, 
        trang_thai_don_hang, 
        ngay_dat 
    FROM donhang 
    WHERE id_user = ? 
    ORDER BY ngay_dat DESC 
    LIMIT 5
");
$recent_orders_query->bind_param("i", $user_id);
$recent_orders_query->execute();
$recent_orders_result = $recent_orders_query->get_result();
$recent_orders = [];

while ($order = $recent_orders_result->fetch_assoc()) {
    $recent_orders[] = $order;
}

// Get wishlist count - using the new yeu_thich table
$wishlist_query = $conn->prepare("
    SELECT COUNT(*) as count FROM yeu_thich WHERE id_user = ?
");
$wishlist_query->bind_param("i", $user_id);
$wishlist_query->execute();
$wishlist_result = $wishlist_query->get_result();
$wishlist_count = $wishlist_result->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tài khoản của tôi - Bug Shop</title>
    <link rel="stylesheet" href="node_modules/bootstrap/dist/css/bootstrap.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/index.css">
    <style>
        .user-info-table td {
            padding: 0.5rem 0;
            vertical-align: middle;
        }
        .user-info-table td:first-child {
            width: 30%;
            font-weight: 500;
            color: #666;
        }
        .nav-pills .nav-link {
            color: #444;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
        }
        .nav-pills .nav-link.active {
            background-color: #1e3a8a;
            color: white;
        }
        .nav-pills .nav-link:hover:not(.active) {
            background-color: #f1f5f9;
        }
        .nav-pills .nav-icon {
            margin-right: 10px;
        }
        .profile-header {
            background: linear-gradient(to right, #1e3a8a, #3b82f6);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 0.5rem;
        }
        .profile-avatar {
            width: 100px;
            height: 100px;
            background-color: #ffffff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: #1e3a8a;
            margin-right: 1.5rem;
        }
        .account-stats .stat-item {
            text-align: center;
            padding: 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
        }
        .section-title {
            position: relative;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .section-title:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 60px;
            height: 3px;
            background-color: #1e3a8a;
        }
    </style>
</head>

<body>
    <?php 
    include('includes/head.php');
    include('includes/header.php'); ?>
    
    <div class="container py-4">
        <!-- Display messages -->
        <?php if(!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if(!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <!-- Profile Header -->
        <div class="profile-header shadow-sm">
            <div class="container">
                <div class="d-flex align-items-center">
                    <div class="profile-avatar">
                        <?php if (!empty($user['anh_dai_dien'])): ?>
                            <img src="<?php echo $user['anh_dai_dien']; ?>" alt="Avatar" class="img-fluid rounded-circle">
                        <?php else: ?>
                            <i class="bi bi-person-circle"></i>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h2 class="mb-1"><?php echo htmlspecialchars($user['ten']); ?></h2>
                        <p class="mb-0">Thành viên kể từ <?php echo date('d/m/Y', strtotime($user['ngay_tao'])); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Left sidebar with menu items -->
            <div class="col-md-3 mb-4">
                <div class="card shadow-sm">
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <a href="taikhoan.php" class="list-group-item list-group-item-action active">
                                <i class="bi bi-person-circle me-2"></i> Trang cá nhân
                            </a>
                            <a href="donhang.php" class="list-group-item list-group-item-action">
                                <i class="bi bi-bag-check me-2"></i> Đơn hàng của tôi
                                <?php if ($pending_orders > 0): ?>
                                    <span class="badge bg-primary rounded-pill float-end"><?php echo $pending_orders; ?></span>
                                <?php endif; ?>
                            </a>
                            <a href="thong-tin-ca-nhan.php" class="list-group-item list-group-item-action">
                                <i class="bi bi-pencil-square me-2"></i> Cập nhật thông tin
                            </a>
                            <a href="doi-mat-khau.php" class="list-group-item list-group-item-action">
                                <i class="bi bi-shield-lock me-2"></i> Đổi mật khẩu
                            </a>
                            <a href="logout.php" class="list-group-item list-group-item-action text-danger">
                                <i class="bi bi-box-arrow-right me-2"></i> Đăng xuất
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Main content area -->
            <div class="col-md-9">
                <!-- Account Stats -->
                <div class="row account-stats mb-4">
                    <div class="col-md-4 mb-3">
                        <div class="stat-item shadow-sm">
                            <h2 class="mb-0"><?php echo $order_count; ?></h2>
                            <p class="mb-0 text-muted">Đơn hàng</p>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="stat-item shadow-sm">
                            <h2 class="mb-0"><?php echo $pending_orders; ?></h2>
                            <p class="mb-0 text-muted">Đơn đang xử lý</p>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="stat-item shadow-sm">
                            <h2 class="mb-0"><?php echo $wishlist_count; ?></h2>
                            <p class="mb-0 text-muted">Sản phẩm yêu thích</p>
                        </div>
                    </div>
                </div>
                
                <!-- User Information Section -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Thông tin cá nhân</h5>
                            <a href="thong-tin-ca-nhan.php" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil"></i> Cập nhật
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <table class="user-info-table table table-borderless">
                            <tr>
                                <td>Họ và tên:</td>
                                <td><?php echo htmlspecialchars($user['ten']); ?></td>
                            </tr>
                            <tr>
                                <td>Email:</td>
                                <td><?php echo htmlspecialchars($user['email'] ?? 'Chưa cung cấp'); ?></td>
                            </tr>
                            <tr>
                                <td>Số điện thoại:</td>
                                <td><?php echo htmlspecialchars($user['sodienthoai'] ?? 'Chưa cung cấp'); ?></td>
                            </tr>
                            <tr>
                                <td>Địa chỉ:</td>
                                <td>
                                    <?php
                                    $address_parts = [];
                                    if (!empty($user['diachi'])) {
                                        $address_parts[] = $user['diachi'];
                                    }
                                    if (!empty($user['phuong_xa'])) {
                                        $address_parts[] = $user['phuong_xa'];
                                    }
                                    if (!empty($user['quan_huyen'])) {
                                        $address_parts[] = $user['quan_huyen'];
                                    }
                                    if (!empty($user['tinh_tp'])) {
                                        $address_parts[] = $user['tinh_tp'];
                                    }
                                    
                                    echo !empty($address_parts) ? htmlspecialchars(implode(', ', $address_parts)) : 'Chưa cung cấp';
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <td>Ngày tham gia:</td>
                                <td><?php echo date('d/m/Y', strtotime($user['ngay_tao'])); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- Recent Orders Section -->
                <div class="card shadow-sm">
                    <div class="card-header bg-white py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Đơn hàng gần đây</h5>
                            <a href="donhang.php" class="btn btn-sm btn-outline-primary">
                                Xem tất cả <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (count($recent_orders) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Mã đơn hàng</th>
                                            <th>Ngày đặt</th>
                                            <th>Tổng tiền</th>
                                            <th>Trạng thái</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_orders as $order): ?>
                                            <tr>
                                                <td><a href="chitiet-donhang.php?id=<?php echo $order['id']; ?>" class="fw-bold text-decoration-none">#<?php echo $order['ma_donhang']; ?></a></td>
                                                <td><?php echo date('d/m/Y', strtotime($order['ngay_dat'])); ?></td>
                                                <td><?php echo number_format($order['thanh_tien'], 0, ',', '.'); ?>₫</td>
                                                <td>
                                                    <?php if($order['trang_thai_don_hang'] == 1): ?>
                                                        <span class="badge bg-warning text-dark">Chờ xác nhận</span>
                                                    <?php elseif($order['trang_thai_don_hang'] == 2): ?>
                                                        <span class="badge bg-info">Đã xác nhận</span>
                                                    <?php elseif($order['trang_thai_don_hang'] == 3): ?>
                                                        <span class="badge bg-primary">Đang giao hàng</span>
                                                    <?php elseif($order['trang_thai_don_hang'] == 4): ?>
                                                        <span class="badge bg-success">Đã giao hàng</span>
                                                    <?php elseif($order['trang_thai_don_hang'] == 5): ?>
                                                        <span class="badge bg-danger">Đã hủy</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="chitiet-donhang.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="p-4 text-center">
                                <i class="bi bi-bag-x text-muted" style="font-size: 2.5rem;"></i>
                                <p class="mt-3">Bạn chưa có đơn hàng nào.</p>
                                <a href="sanpham.php" class="btn btn-primary">Mua sắm ngay</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include('includes/footer.php'); ?>
</body>
</html>
