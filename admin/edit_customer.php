<?php
// Thêm dòng này vào đầu file, trước tất cả code
ob_start();

// Set page title
$page_title = 'Chỉnh sửa khách hàng';

// Include header (will check for login)
include('includes/header.php');

// Include database connection
include('../config/config.php');

// Get customer ID from URL
$customer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Check for valid ID
if ($customer_id <= 0) {
    $_SESSION['error_message'] = 'ID khách hàng không hợp lệ';
    header('Location: customers.php');
    exit();
}

// Get customer details
$stmt = $conn->prepare("SELECT * FROM users WHERE id_user = ?");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$result = $stmt->get_result();

// Check if customer exists
if ($result->num_rows === 0) {
    $_SESSION['error_message'] = 'Không tìm thấy thông tin khách hàng';
    header('Location: customers.php');
    exit();
}

$customer = $result->fetch_assoc();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $username = trim($_POST['username']);
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $status = isset($_POST['status']) ? 1 : 0;
    
    // Validate form data
    $errors = [];
    
    if (empty($username)) {
        $errors[] = 'Tên đăng nhập không được để trống';
    }
    
    if (empty($fullname)) {
        $errors[] = 'Họ tên không được để trống';
    }
    
    if (empty($email)) {
        $errors[] = 'Email không được để trống';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email không hợp lệ';
    }
    
    if (empty($phone)) {
        $errors[] = 'Số điện thoại không được để trống';
    }
    
    // Check if username already exists (for different user)
    if ($username !== $customer['taikhoan']) {
        $check_stmt = $conn->prepare("SELECT id_user FROM users WHERE taikhoan = ? AND id_user != ?");
        $check_stmt->bind_param("si", $username, $customer_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $errors[] = 'Tên đăng nhập đã tồn tại';
        }
    }
    
    // Check if email already exists (for different user)
    if ($email !== $customer['email']) {
        $check_stmt = $conn->prepare("SELECT id_user FROM users WHERE email = ? AND id_user != ?");
        $check_stmt->bind_param("si", $email, $customer_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $errors[] = 'Email đã tồn tại';
        }
    }
    
    // If no errors, update customer
    if (empty($errors)) {
        // Update basic info
        $update_stmt = $conn->prepare("
            UPDATE users SET 
                taikhoan = ?,
                tenuser = ?,
                email = ?,
                sdt = ?,
                diachi = ?,
                trang_thai = ?
            WHERE id_user = ?
        ");
        $update_stmt->bind_param(
            "sssssii",
            $username, $fullname, $email, $phone, $address, $status, $customer_id
        );
        
        if ($update_stmt->execute()) {
            // If user is a seller, update shop information if provided
            if ($customer['loai_user'] == 1 && isset($_POST['shop_name'])) {
                $shop_name = trim($_POST['shop_name']);
                $shop_description = trim($_POST['shop_description']);
                
                // Check if shop info exists
                $shop_check = $conn->prepare("SELECT id FROM thongtin_shop WHERE id_nguoiban = ?");
                $shop_check->bind_param("i", $customer_id);
                $shop_check->execute();
                $shop_result = $shop_check->get_result();
                
                if ($shop_result->num_rows > 0) {
                    // Update existing shop info
                    $shop_update = $conn->prepare("
                        UPDATE thongtin_shop SET 
                            ten_shop = ?,
                            mo_ta = ?
                        WHERE id_nguoiban = ?
                    ");
                    $shop_update->bind_param("ssi", $shop_name, $shop_description, $customer_id);
                    $shop_update->execute();
                } else {
                    // Insert new shop info
                    $shop_insert = $conn->prepare("
                        INSERT INTO thongtin_shop (id_nguoiban, ten_shop, mo_ta)
                        VALUES (?, ?, ?)
                    ");
                    $shop_insert->bind_param("iss", $customer_id, $shop_name, $shop_description);
                    $shop_insert->execute();
                }
            }
            
            $_SESSION['success_message'] = 'Cập nhật thông tin khách hàng thành công';
            header('Location: customer-detail.php?id=' . $customer_id);
            exit();
        } else {
            $errors[] = 'Đã xảy ra lỗi khi cập nhật thông tin: ' . $conn->error;
        }
    }
}
?>

<!-- Include sidebar -->
<?php include('includes/sidebar.php'); ?>

<!-- Main content -->
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Trang chủ</a></li>
            <li class="breadcrumb-item"><a href="customers.php">Quản lý khách hàng</a></li>
            <li class="breadcrumb-item"><a href="customer-detail.php?id=<?php echo $customer_id; ?>"><?php echo htmlspecialchars($customer['tenuser']); ?></a></li>
            <li class="breadcrumb-item active" aria-current="page">Chỉnh sửa</li>
        </ol>
    </nav>
    
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Chỉnh sửa khách hàng</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="customer-detail.php?id=<?php echo $customer_id; ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Quay lại
            </a>
        </div>
    </div>
    
    <?php if (isset($errors) && !empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>Lỗi:</strong>
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-8 mb-4">
            <div class="card">
                <div class="card-body">
                    <form action="" method="post">
                        <h5 class="card-title mb-4">Thông tin cơ bản</h5>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="username" class="form-label">Tên đăng nhập</label>
                                <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($customer['taikhoan']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="fullname" class="form-label">Họ tên</label>
                                <input type="text" class="form-control" id="fullname" name="fullname" value="<?php echo htmlspecialchars($customer['tenuser']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($customer['email']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Số điện thoại</label>
                                <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($customer['sdt']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="address" class="form-label">Địa chỉ</label>
                            <textarea class="form-control" id="address" name="address" rows="2"><?php echo htmlspecialchars($customer['diachi'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="status" name="status" <?php echo $customer['trang_thai'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="status">Tài khoản hoạt động</label>
                        </div>
                        
                        <?php if ($customer['loai_user'] == 1): ?>
                            <hr class="my-4">
                            <h5 class="card-title mb-4">Thông tin cửa hàng</h5>
                            
                            <?php
                            // Get shop info
                            $shop_stmt = $conn->prepare("SELECT * FROM thongtin_shop WHERE id_nguoiban = ?");
                            $shop_stmt->bind_param("i", $customer_id);
                            $shop_stmt->execute();
                            $shop_result = $shop_stmt->get_result();
                            $shop_info = $shop_result->num_rows > 0 ? $shop_result->fetch_assoc() : null;
                            ?>
                            
                            <div class="mb-3">
                                <label for="shop_name" class="form-label">Tên cửa hàng</label>
                                <input type="text" class="form-control" id="shop_name" name="shop_name" value="<?php echo $shop_info ? htmlspecialchars($shop_info['ten_shop']) : ''; ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="shop_description" class="form-label">Mô tả cửa hàng</label>
                                <textarea class="form-control" id="shop_description" name="shop_description" rows="3"><?php echo $shop_info ? htmlspecialchars($shop_info['mo_ta']) : ''; ?></textarea>
                            </div>
                        <?php endif; ?>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="customer-detail.php?id=<?php echo $customer_id; ?>" class="btn btn-outline-secondary me-md-2">Hủy</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Lưu thay đổi
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-4">
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0">Thông tin tài khoản</h5>
                </div>
                <div class="card-body">
                    <p><strong>ID:</strong> <?php echo $customer_id; ?></p>
                    <p><strong>Loại tài khoản:</strong> 
                        <?php if ($customer['loai_user'] == 0): ?>
                            <span class="badge bg-info">Người mua</span>
                        <?php else: ?>
                            <span class="badge bg-primary">Người bán</span>
                        <?php endif; ?>
                    </p>
                    <p><strong>Ngày đăng ký:</strong> <?php echo date('d/m/Y', strtotime($customer['ngay_tao'])); ?></p>
                    
                    <?php if ($customer['loai_user'] == 1 && isset($customer['ngay_tro_thanh_nguoi_ban'])): ?>
                        <p><strong>Ngày trở thành người bán:</strong> <?php echo date('d/m/Y', strtotime($customer['ngay_tro_thanh_nguoi_ban'])); ?></p>
                    <?php endif; ?>
                    
                    <p><strong>Trạng thái xác thực:</strong> 
                        <?php if (isset($customer['trang_thai_xac_thuc']) && $customer['trang_thai_xac_thuc']): ?>
                            <span class="badge bg-success">Đã xác thực</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">Chưa xác thực</span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0">Tác vụ khác</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#resetPasswordModal">
                            <i class="bi bi-key"></i> Đặt lại mật khẩu
                        </button>
                        
                        <button type="button" class="btn <?php echo $customer['trang_thai'] ? 'btn-outline-danger' : 'btn-outline-success'; ?>"
                                data-bs-toggle="modal" data-bs-target="#toggleStatusModal">
                            <?php if ($customer['trang_thai']): ?>
                                <i class="bi bi-lock"></i> Khóa tài khoản
                            <?php else: ?>
                                <i class="bi bi-unlock"></i> Mở khóa tài khoản
                            <?php endif; ?>
                        </button>
                        
                        <button type="button" class="btn btn-outline-primary"
                                data-bs-toggle="modal" data-bs-target="#changeUserTypeModal">
                            <?php if ($customer['loai_user'] == 0): ?>
                                <i class="bi bi-shop"></i> Nâng cấp thành người bán
                            <?php else: ?>
                                <i class="bi bi-person"></i> Chuyển thành người mua
                            <?php endif; ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Đặt lại mật khẩu</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="process_reset_password.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="customer_id" value="<?php echo $customer_id; ?>">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> 
                        Hành động này sẽ tạo một mật khẩu ngẫu nhiên mới cho người dùng.
                    </div>
                    <div class="mb-3">
                        <label for="newPassword" class="form-label">Mật khẩu mới</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="newPassword" name="new_password" required>
                            <button class="btn btn-outline-secondary" type="button" id="generatePasswordBtn">
                                <i class="bi bi-magic"></i> Tạo
                            </button>
                        </div>
                        <div class="form-text">Mật khẩu phải có ít nhất 8 ký tự</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> Xác nhận
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Toggle Status Modal -->
<div class="modal fade" id="toggleStatusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="toggleStatusTitle">Thay đổi trạng thái tài khoản</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="process_toggle_status.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="customer_id" value="<?php echo $customer_id; ?>">
                    <input type="hidden" name="new_status" id="newStatusInput" value="<?php echo $customer['trang_thai'] ? '0' : '1'; ?>">
                    
                    <div id="lockAccountContent" class="<?php echo $customer['trang_thai'] ? '' : 'd-none'; ?>">
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i> 
                            Bạn sắp khóa tài khoản của người dùng này.
                        </div>
                        <div class="mb-3">
                            <label for="lockReason" class="form-label">Lý do khóa</label>
                            <textarea class="form-control" id="lockReason" name="reason" rows="3"></textarea>
                        </div>
                    </div>
                    
                    <div id="unlockAccountContent" class="<?php echo $customer['trang_thai'] ? 'd-none' : ''; ?>">
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle-fill me-2"></i> 
                            Bạn sắp mở khóa tài khoản của người dùng này.
                        </div>
                        <p>Người dùng sẽ có thể đăng nhập và sử dụng tài khoản bình thường.</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary" id="toggleStatusSubmitBtn">
                        <?php if ($customer['trang_thai']): ?>
                            <i class="bi bi-lock-fill"></i> Khóa tài khoản
                        <?php else: ?>
                            <i class="bi bi-unlock-fill"></i> Mở khóa tài khoản
                        <?php endif; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Change User Type Modal -->
<div class="modal fade" id="changeUserTypeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="changeTypeTitle">Thay đổi loại người dùng</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="process_change_user_type.php" method="post">
                <div class="modal-body" id="changeTypeBody">
                    <input type="hidden" name="customer_id" value="<?php echo $customer_id; ?>">
                    <p>Bạn có chắc chắn muốn thay đổi loại tài khoản của người dùng này?</p>
                    
                    <div id="toSellerInfo" class="<?php echo $customer['loai_user'] == 0 ? '' : 'd-none'; ?>">
                        <input type="hidden" name="new_type" value="1">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i> 
                            Chuyển tài khoản này thành người bán sẽ cho phép họ đăng bán sản phẩm trên hệ thống.
                        </div>
                        <div class="mb-3">
                            <label for="modal_shop_name" class="form-label">Tên cửa hàng</label>
                            <input type="text" class="form-control" id="modal_shop_name" name="shop_name">
                        </div>
                        <div class="mb-3">
                            <label for="modal_shop_description" class="form-label">Mô tả cửa hàng</label>
                            <textarea class="form-control" id="modal_shop_description" name="shop_description" rows="3"></textarea>
                        </div>
                    </div>
                    
                    <div id="toBuyerInfo" class="<?php echo $customer['loai_user'] == 1 ? '' : 'd-none'; ?>">
                        <input type="hidden" name="new_type" value="0">
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i> 
                            Chuyển tài khoản này thành người mua sẽ xóa quyền bán hàng của họ. Tất cả sản phẩm hiện có sẽ bị ẩn.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> Xác nhận
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php 
// JavaScript for the page
$page_specific_js = '
<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Password generator
        document.getElementById("generatePasswordBtn").addEventListener("click", function() {
            const length = 12;
            const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+";
            let password = "";
            
            for (let i = 0; i < length; i++) {
                const randomIndex = Math.floor(Math.random() * charset.length);
                password += charset[randomIndex];
            }
            
            document.getElementById("newPassword").value = password;
        });
        
        // Toggle status modal content
        const toggleStatusBtn = document.querySelector("[data-bs-target=\'#toggleStatusModal\']");
        if (toggleStatusBtn) {
            toggleStatusBtn.addEventListener("click", function() {
                const currentStatus = ' . $customer['trang_thai'] . ';
                const newStatus = currentStatus ? 0 : 1;
                
                // Update form content based on action
                document.getElementById("newStatusInput").value = newStatus;
                
                if (newStatus === 1) {
                    // Unlocking
                    document.getElementById("toggleStatusTitle").textContent = "Mở khóa tài khoản";
                    document.getElementById("unlockAccountContent").classList.remove("d-none");
                    document.getElementById("lockAccountContent").classList.add("d-none");
                    document.getElementById("toggleStatusSubmitBtn").innerHTML = \'<i class="bi bi-unlock-fill"></i> Mở khóa tài khoản\';
                    document.getElementById("toggleStatusSubmitBtn").className = "btn btn-success";
                } else {
                    // Locking
                    document.getElementById("toggleStatusTitle").textContent = "Khóa tài khoản";
                    document.getElementById("lockAccountContent").classList.remove("d-none");
                    document.getElementById("unlockAccountContent").classList.add("d-none");
                    document.getElementById("toggleStatusSubmitBtn").innerHTML = \'<i class="bi bi-lock-fill"></i> Khóa tài khoản\';
                    document.getElementById("toggleStatusSubmitBtn").className = "btn btn-danger";
                }
            });
        }
    });
</script>
';

// Include footer
include('includes/footer.php');
?>