<?php
session_start();
include('config/config.php');

// Kiểm tra đăng nhập
if (!isset($_SESSION['user']) || $_SESSION['user']['logged_in'] !== true) {
    // Chuyển hướng đến trang đăng nhập nếu chưa đăng nhập
    header('Location: dangnhap.php?redirect=taikhoan.php');
    exit();
}

// Lấy thông tin người dùng từ database
$user_id = $_SESSION['user']['id'];
$sql = "SELECT * FROM users WHERE id_user = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Nếu không tìm thấy người dùng, đăng xuất và chuyển về trang đăng nhập
    session_destroy();
    header('Location: dangnhap.php');
    exit();
}

$user = $result->fetch_assoc();

// Lấy danh sách đơn hàng của người dùng
$orders_sql = "SELECT d.*, COUNT(dc.id_chitiet) as so_san_pham 
               FROM donhang d 
               LEFT JOIN donhang_chitiet dc ON d.id_donhang = dc.id_donhang 
               WHERE d.id_nguoidung = ? 
               GROUP BY d.id_donhang 
               ORDER BY d.ngaytao DESC";
$orders_stmt = $conn->prepare($orders_sql);
$orders_stmt->bind_param("i", $user_id);
$orders_stmt->execute();
$orders_result = $orders_stmt->get_result();

// Xử lý cập nhật thông tin cá nhân
$success_message = $error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $tenuser = trim($_POST['tenuser']);
    $email = trim($_POST['email']);
    $sdt = trim($_POST['sdt']);
    $diachi = trim($_POST['diachi']);
    
    // Validate dữ liệu
    if (empty($tenuser) || empty($sdt) || empty($diachi)) {
        $error_message = 'Vui lòng điền đầy đủ thông tin bắt buộc.';
    } else {
        // Kiểm tra email đã tồn tại chưa (nếu có thay đổi)
        if ($email !== $user['email'] && !empty($email)) {
            $check_email = $conn->prepare("SELECT id_user FROM users WHERE email = ? AND id_user != ?");
            $check_email->bind_param("si", $email, $user_id);
            $check_email->execute();
            if ($check_email->get_result()->num_rows > 0) {
                $error_message = 'Email này đã được sử dụng bởi tài khoản khác.';
            }
        }
        
        // Kiểm tra số điện thoại đã tồn tại chưa (nếu có thay đổi)
        if ($sdt !== $user['sdt']) {
            $check_sdt = $conn->prepare("SELECT id_user FROM users WHERE sdt = ? AND id_user != ?");
            $check_sdt->bind_param("si", $sdt, $user_id);
            $check_sdt->execute();
            if ($check_sdt->get_result()->num_rows > 0) {
                $error_message = 'Số điện thoại này đã được sử dụng bởi tài khoản khác.';
            }
        }
        
        // Nếu không có lỗi, thực hiện cập nhật
        if (empty($error_message)) {
            $update_sql = "UPDATE users SET tenuser = ?, email = ?, sdt = ?, diachi = ? WHERE id_user = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ssssi", $tenuser, $email, $sdt, $diachi, $user_id);
            
            if ($update_stmt->execute()) {
                $success_message = 'Cập nhật thông tin thành công!';
                // Cập nhật lại thông tin người dùng từ database
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
            } else {
                $error_message = 'Có lỗi xảy ra khi cập nhật thông tin: ' . $conn->error;
            }
        }
    }
}

// Xử lý đổi mật khẩu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate dữ liệu
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = 'Vui lòng điền đầy đủ thông tin mật khẩu.';
    } elseif ($new_password !== $confirm_password) {
        $error_message = 'Mật khẩu mới và xác nhận mật khẩu không khớp.';
    } elseif (strlen($new_password) < 8) {
        $error_message = 'Mật khẩu mới phải có ít nhất 8 ký tự.';
    } else {
        // Kiểm tra mật khẩu hiện tại
        if (password_verify($current_password, $user['matkhau'])) {
            // Mã hóa mật khẩu mới
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Cập nhật mật khẩu mới
            $update_pwd = $conn->prepare("UPDATE users SET matkhau = ? WHERE id_user = ?");
            $update_pwd->bind_param("si", $hashed_password, $user_id);
            
            if ($update_pwd->execute()) {
                $success_message = 'Đổi mật khẩu thành công!';
            } else {
                $error_message = 'Có lỗi xảy ra khi cập nhật mật khẩu: ' . $conn->error;
            }
        } else {
            $error_message = 'Mật khẩu hiện tại không chính xác.';
        }
    }
}

// Xử lý tải lên ảnh đại diện
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_avatar'])) {
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['avatar']['tmp_name'];
        $file_name = $_FILES['avatar']['name'];
        $file_size = $_FILES['avatar']['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Validate file
        $allowed_exts = array('jpg', 'jpeg', 'png', 'gif');
        $max_file_size = 2 * 1024 * 1024; // 2MB
        
        if (!in_array($file_ext, $allowed_exts)) {
            $error_message = 'Chỉ cho phép tải lên file hình ảnh (JPG, JPEG, PNG, GIF).';
        } elseif ($file_size > $max_file_size) {
            $error_message = 'Kích thước file không được vượt quá 2MB.';
        } else {
            // Tạo thư mục lưu trữ nếu chưa tồn tại
            $upload_dir = 'uploads/users/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Tạo tên file mới để tránh trùng lặp
            $new_file_name = uniqid() . '_' . time() . '.' . $file_ext;
            $upload_path = $upload_dir . $new_file_name;
            
            // Di chuyển file tải lên vào thư mục lưu trữ
            if (move_uploaded_file($file_tmp, $upload_path)) {
                // Xóa ảnh cũ nếu có
                if (!empty($user['anh_dai_dien']) && file_exists('uploads/users/' . $user['anh_dai_dien'])) {
                    unlink('uploads/users/' . $user['anh_dai_dien']);
                }
                
                // Cập nhật thông tin ảnh đại diện trong database
                $update_avatar = $conn->prepare("UPDATE users SET anh_dai_dien = ? WHERE id_user = ?");
                $update_avatar->bind_param("si", $new_file_name, $user_id);
                
                if ($update_avatar->execute()) {
                    $success_message = 'Cập nhật ảnh đại diện thành công!';
                    // Cập nhật lại thông tin người dùng
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $user = $result->fetch_assoc();
                } else {
                    $error_message = 'Có lỗi xảy ra khi cập nhật ảnh đại diện: ' . $conn->error;
                }
            } else {
                $error_message = 'Có lỗi xảy ra khi tải lên file.';
            }
        }
    } else {
        $error_message = 'Vui lòng chọn file hình ảnh để tải lên.';
    }
}

// Hàm định dạng trạng thái đơn hàng
function formatOrderStatus($status) {
    switch ($status) {
        case 1:
            return '<span class="badge bg-warning">Chờ xác nhận</span>';
        case 2:
            return '<span class="badge bg-info">Đang xử lý</span>';
        case 3:
            return '<span class="badge bg-primary">Đang giao hàng</span>';
        case 4:
            return '<span class="badge bg-success">Đã giao</span>';
        case 5:
            return '<span class="badge bg-danger">Đã hủy</span>';
        case 6:
            return '<span class="badge bg-secondary">Hoàn trả</span>';
        default:
            return '<span class="badge bg-dark">Không xác định</span>';
    }
}

$page_title = "Tài khoản của tôi";
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Bug Shop</title>
    <link rel="stylesheet" href="node_modules/bootstrap/dist/css/bootstrap.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/index.css">
    <style>
        .account-sidebar .nav-link {
            color: #333;
            border-radius: 0;
            padding: 0.75rem 1rem;
            border-left: 3px solid transparent;
        }
        .account-sidebar .nav-link:hover {
            background-color: #f8f9fa;
        }
        .account-sidebar .nav-link.active {
            color: #0d6efd;
            background-color: rgba(13, 110, 253, 0.1);
            border-left-color: #0d6efd;
            font-weight: 500;
        }
        .avatar-wrapper {
            position: relative;
            width: 120px;
            height: 120px;
            margin: 0 auto;
        }
        .avatar {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #eee;
        }
        .avatar-edit {
            position: absolute;
            right: 0;
            bottom: 0;
            width: 36px;
            height: 36px;
            background-color: #0d6efd;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: white;
        }
        .tab-pane {
            padding-top: 1.5rem;
        }
        .order-card {
            transition: transform 0.2s;
        }
        .order-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
    </style>
</head>
<body>
    <?php include('includes/header.php'); ?>
    
    <main class="container py-5">
        <div class="row">
            <!-- Tiêu đề trang -->
            <div class="col-12 mb-4">
                <h1 class="h3"><?php echo $page_title; ?></h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Trang chủ</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Tài khoản</li>
                    </ol>
                </nav>
            </div>
            
            <!-- Thông báo -->
            <?php if (!empty($success_message)): ?>
                <div class="col-12">
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i> <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="col-12">
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Sidebar -->
            <div class="col-lg-3 mb-4">
                <div class="card">
                    <div class="card-body text-center py-4">
                        <div class="avatar-wrapper mb-3">
                            <?php if (!empty($user['anh_dai_dien']) && file_exists('uploads/users/' . $user['anh_dai_dien'])): ?>
                                <img src="uploads/users/<?php echo $user['anh_dai_dien']; ?>" alt="Avatar" class="avatar">
                            <?php else: ?>
                                <div class="avatar d-flex align-items-center justify-content-center bg-light text-secondary">
                                    <i class="bi bi-person" style="font-size: 3rem;"></i>
                                </div>
                            <?php endif; ?>
                            <div class="avatar-edit" data-bs-toggle="modal" data-bs-target="#avatarModal">
                                <i class="bi bi-camera"></i>
                            </div>
                        </div>
                        <h5 class="mb-1"><?php echo htmlspecialchars($user['tenuser']); ?></h5>
                        <p class="text-muted mb-0"><?php echo htmlspecialchars($user['email'] ?? 'Chưa cập nhật email'); ?></p>
                    </div>
                    <div class="list-group list-group-flush account-sidebar">
                        <button class="list-group-item list-group-item-action active" data-bs-toggle="pill" data-bs-target="#dashboard">
                            <i class="bi bi-speedometer2 me-2"></i> Tổng quan
                        </button>
                        <button class="list-group-item list-group-item-action" data-bs-toggle="pill" data-bs-target="#orders">
                            <i class="bi bi-bag me-2"></i> Đơn hàng của tôi
                        </button>
                        <button class="list-group-item list-group-item-action" data-bs-toggle="pill" data-bs-target="#profile">
                            <i class="bi bi-person-circle me-2"></i> Thông tin tài khoản
                        </button>
                        <button class="list-group-item list-group-item-action" data-bs-toggle="pill" data-bs-target="#password">
                            <i class="bi bi-shield-lock me-2"></i> Đổi mật khẩu
                        </button>
                        <a href="logout.php" class="list-group-item list-group-item-action text-danger">
                            <i class="bi bi-box-arrow-right me-2"></i> Đăng xuất
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Nội dung chính -->
            <div class="col-lg-9">
                <div class="card">
                    <div class="card-body">
                        <div class="tab-content">
                            <!-- Dashboard Tab -->
                            <div class="tab-pane fade show active" id="dashboard">
                                <h4 class="card-title mb-4">Tổng quan tài khoản</h4>
                                
                                <div class="row g-4 mb-4">
                                    <div class="col-md-4">
                                        <div class="card bg-light">
                                            <div class="card-body text-center p-4">
                                                <div class="mb-3">
                                                    <i class="bi bi-bag-check text-primary" style="font-size: 2.5rem;"></i>
                                                </div>
                                                <h5 class="card-title"><?php echo $orders_result->num_rows; ?></h5>
                                                <p class="card-text text-muted">Tổng đơn hàng</p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <div class="card bg-light">
                                            <div class="card-body text-center p-4">
                                                <div class="mb-3">
                                                    <i class="bi bi-box2-heart text-success" style="font-size: 2.5rem;"></i>
                                                </div>
                                                <?php
                                                // Đếm số đơn hàng đã giao thành công
                                                $delivered_sql = "SELECT COUNT(*) as count FROM donhang WHERE id_nguoidung = ? AND trangthai = 4";
                                                $delivered_stmt = $conn->prepare($delivered_sql);
                                                $delivered_stmt->bind_param("i", $user_id);
                                                $delivered_stmt->execute();
                                                $delivered_result = $delivered_stmt->get_result()->fetch_assoc();
                                                ?>
                                                <h5 class="card-title"><?php echo $delivered_result['count']; ?></h5>
                                                <p class="card-text text-muted">Đơn hàng thành công</p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-4">
                                        <div class="card bg-light">
                                            <div class="card-body text-center p-4">
                                                <div class="mb-3">
                                                    <i class="bi bi-calendar-check text-warning" style="font-size: 2.5rem;"></i>
                                                </div>
                                                <h5 class="card-title"><?php echo date('d/m/Y', strtotime($user['ngay_tao'])); ?></h5>
                                                <p class="card-text text-muted">Ngày tham gia</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($orders_result->num_rows > 0): ?>
                                    <h5 class="mb-3">Đơn hàng gần đây</h5>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Mã đơn hàng</th>
                                                    <th>Ngày đặt</th>
                                                    <th>Tổng tiền</th>
                                                    <th>Trạng thái</th>
                                                    <th>Thao tác</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $counter = 0;
                                                while ($order = $orders_result->fetch_assoc()): 
                                                    if ($counter >= 5) break; // Chỉ hiển thị 5 đơn hàng mới nhất
                                                    $counter++;
                                                ?>
                                                <tr>
                                                    <td><strong>#<?php echo $order['id_donhang']; ?></strong></td>
                                                    <td><?php echo date('d/m/Y H:i', strtotime($order['ngaytao'])); ?></td>
                                                    <td><?php echo number_format($order['tongtien'], 0, ',', '.'); ?>₫</td>
                                                    <td><?php echo formatOrderStatus($order['trangthai']); ?></td>
                                                    <td>
                                                        <a href="chitietdonhang.php?id=<?php echo $order['id_donhang']; ?>" class="btn btn-sm btn-outline-primary">
                                                            Chi tiết
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php endwhile; 
                                                // Reset con trỏ kết quả để sử dụng lại cho tab đơn hàng
                                                $orders_result->data_seek(0);
                                                ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="text-center mt-3">
                                        <button class="btn btn-outline-primary" data-bs-toggle="pill" data-bs-target="#orders">
                                            Xem tất cả đơn hàng <i class="bi bi-arrow-right"></i>
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-5">
                                        <img src="images/empty-order.svg" alt="No orders" style="max-width: 150px; opacity: 0.6;" class="mb-3">
                                        <h5>Bạn chưa có đơn hàng nào</h5>
                                        <p class="text-muted">Hãy khám phá các sản phẩm và đặt hàng ngay!</p>
                                        <a href="products.php" class="btn btn-primary">Mua sắm ngay</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Orders Tab -->
                            <div class="tab-pane fade" id="orders">
                                <h4 class="card-title mb-4">Đơn hàng của tôi</h4>
                                
                                <?php if ($orders_result->num_rows > 0): ?>
                                    <div class="row g-3">
                                        <?php while ($order = $orders_result->fetch_assoc()): ?>
                                            <div class="col-md-6">
                                                <div class="card order-card h-100">
                                                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                                        <span>Đơn hàng #<?php echo $order['id_donhang']; ?></span>
                                                        <?php echo formatOrderStatus($order['trangthai']); ?>
                                                    </div>
                                                    <div class="card-body">
                                                        <div class="d-flex justify-content-between mb-3">
                                                            <div>
                                                                <div class="text-muted small">Ngày đặt hàng</div>
                                                                <div><?php echo date('d/m/Y H:i', strtotime($order['ngaytao'])); ?></div>
                                                            </div>
                                                            <div>
                                                                <div class="text-muted small">Tổng tiền</div>
                                                                <div class="fw-bold"><?php echo number_format($order['tongtien'], 0, ',', '.'); ?>₫</div>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <div class="text-muted small">Người nhận</div>
                                                            <div><?php echo htmlspecialchars($order['tennguoinhan']); ?></div>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <div class="text-muted small">Số sản phẩm</div>
                                                            <div><?php echo $order['so_san_pham']; ?> sản phẩm</div>
                                                        </div>
                                                    </div>
                                                    <div class="card-footer bg-white">
                                                        <a href="order-detail.php?id=<?php echo $order['id_donhang']; ?>" class="btn btn-outline-primary btn-sm w-100">
                                                            Xem chi tiết
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endwhile; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center py-5">
                                        <img src="images/empty-order.svg" alt="No orders" style="max-width: 150px; opacity: 0.6;" class="mb-3">
                                        <h5>Bạn chưa có đơn hàng nào</h5>
                                        <p class="text-muted">Hãy khám phá các sản phẩm và đặt hàng ngay!</p>
                                        <a href="products.php" class="btn btn-primary">Mua sắm ngay</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Profile Tab -->
                            <div class="tab-pane fade" id="profile">
                                <h4 class="card-title mb-4">Thông tin tài khoản</h4>
                                
                                <form method="post" action="">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="username" class="form-label">Tên đăng nhập</label>
                                            <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($user['taikhoan']); ?>" readonly disabled>
                                            <small class="text-muted">Tên đăng nhập không thể thay đổi</small>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="tenuser" class="form-label">Họ và tên <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="tenuser" name="tenuser" value="<?php echo htmlspecialchars($user['tenuser']); ?>" required>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="email" class="form-label">Email</label>
                                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="sdt" class="form-label">Số điện thoại <span class="text-danger">*</span></label>
                                            <input type="tel" class="form-control" id="sdt" name="sdt" value="<?php echo htmlspecialchars($user['sdt']); ?>" pattern="[0-9]{10}" title="Số điện thoại phải có 10 chữ số" required>
                                        </div>
                                        
                                        <div class="col-12 mb-3">
                                            <label for="diachi" class="form-label">Địa chỉ <span class="text-danger">*</span></label>
                                            <textarea class="form-control" id="diachi" name="diachi" rows="3" required><?php echo htmlspecialchars($user['diachi']); ?></textarea>
                                        </div>
                                        
                                        <div class="col-12">
                                            <input type="hidden" name="update_profile" value="1">
                                            <button type="submit" class="btn btn-primary">Cập nhật thông tin</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            
                            <!-- Password Change Tab -->
                            <div class="tab-pane fade" id="password">
                                <h4 class="card-title mb-4">Đổi mật khẩu</h4>
                                
                                <form method="post" action="" class="row">
                                    <div class="col-md-6 offset-md-3">
                                        <div class="mb-3">
                                            <label for="current_password" class="form-label">Mật khẩu hiện tại <span class="text-danger">*</span></label>
                                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="new_password" class="form-label">Mật khẩu mới <span class="text-danger">*</span></label>
                                            <input type="password" class="form-control" id="new_password" name="new_password" minlength="8" required>
                                            <small class="text-muted">Mật khẩu mới phải có ít nhất 8 ký tự</small>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <label for="confirm_password" class="form-label">Xác nhận mật khẩu mới <span class="text-danger">*</span></label>
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                        </div>
                                        
                                        <div class="d-grid">
                                            <input type="hidden" name="change_password" value="1">
                                            <button type="submit" class="btn btn-primary">Đổi mật khẩu</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Avatar Upload Modal -->
    <div class="modal fade" id="avatarModal" tabindex="-1" aria-labelledby="avatarModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="avatarModalLabel">Cập nhật ảnh đại diện</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post" action="" enctype="multipart/form-data" id="avatar-form">
                        <div class="mb-3">
                            <label for="avatar" class="form-label">Chọn ảnh</label>
                            <input class="form-control" type="file" id="avatar" name="avatar" accept="image/*" required>
                            <div class="form-text">Chỉ chấp nhận file hình ảnh (JPG, PNG, GIF) và kích thước tối đa 2MB</div>
                        </div>
                        <div class="text-center mt-4 mb-3">
                            <div id="avatar-preview" class="d-none mb-3">
                                <img src="" alt="Preview" class="img-thumbnail" style="max-height: 200px;">
                            </div>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Hủy</button>
                            <input type="hidden" name="upload_avatar" value="1">
                            <button type="submit" class="btn btn-primary">Cập nhật</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <?php include('includes/footer.php'); ?>
    
    <script src="node_modules/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tab navigation from URL hash
            let hash = window.location.hash;
            if (hash) {
                const tabId = hash.substring(1);
                const tab = document.querySelector(`[data-bs-target="#${tabId}"]`);
                if (tab) {
                    new bootstrap.Tab(tab).show();
                }
            }
            
            // Update URL hash when tab changes
            const tabs = document.querySelectorAll('[data-bs-toggle="pill"]');
            tabs.forEach(tab => {
                tab.addEventListener('shown.bs.tab', (event) => {
                    const targetId = event.target.getAttribute('data-bs-target').substring(1);
                    window.location.hash = targetId;
                    
                    // Update active class on sidebar
                    document.querySelectorAll('.account-sidebar .list-group-item').forEach(item => {
                        item.classList.remove('active');
                    });
                    event.target.classList.add('active');
                });
            });
            
            // Preview avatar before upload
            const avatarInput = document.getElementById('avatar');
            const avatarPreview = document.getElementById('avatar-preview');
            
            avatarInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        avatarPreview.classList.remove('d-none');
                        avatarPreview.querySelector('img').src = e.target.result;
                    }
                    
                    reader.readAsDataURL(this.files[0]);
                }
            });
            
            // Thay đổi từ
            const vnpayPayment = document.getElementById('vnpay');
        });
    </script>
</body>
</html>
