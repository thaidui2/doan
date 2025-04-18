<?php
// Set page title
$page_title = 'Hồ sơ cá nhân';

// Include header (will check for login)
include('includes/header.php');

// Include database connection
include('../config/config.php');

// Get admin information - Updated to use users table instead of admin table
$admin_id = $_SESSION['admin_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND (loai_user = 1 OR loai_user = 2)");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Admin not found, redirect to dashboard
    header('Location: index.php');
    exit();
}

$admin = $result->fetch_assoc();

// Message variables
$success_message = '';
$error_message = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // Updated column names to match users table
    $ten = trim($_POST['ten']);
    $email = trim($_POST['email']);

    if (empty($ten)) {
        $error_message = 'Tên không được để trống';
    } else {
        // Updated table and column names
        $update_stmt = $conn->prepare("UPDATE users SET ten = ?, email = ? WHERE id = ?");
        $update_stmt->bind_param("ssi", $ten, $email, $admin_id);
        
        if ($update_stmt->execute()) {
            $success_message = 'Thông tin đã được cập nhật thành công';
            
            // Log action using nhat_ky table instead of admin_actions
            $log_query = $conn->prepare("
                INSERT INTO nhat_ky (id_user, hanh_dong, doi_tuong_loai, doi_tuong_id, chi_tiet, ip_address) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $action_type = 'edit_profile';
            $target_type = 'admin';
            $target_id = $admin_id;
            $details = "Cập nhật thông tin cá nhân";
            $ip = $_SERVER['REMOTE_ADDR'];
            $log_query->bind_param("ississ", $admin_id, $action_type, $target_type, $target_id, $details, $ip);
            $log_query->execute();
            
            // Refresh admin data
            $stmt->execute();
            $admin = $stmt->get_result()->fetch_assoc();
        } else {
            $error_message = 'Có lỗi xảy ra khi cập nhật thông tin: ' . $conn->error;
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = 'Vui lòng điền đầy đủ thông tin';
    } elseif ($new_password !== $confirm_password) {
        $error_message = 'Mật khẩu mới và xác nhận mật khẩu không khớp';
    } elseif (strlen($new_password) < 8) {
        $error_message = 'Mật khẩu mới phải có ít nhất 8 ký tự';
    } else {
        // Verify current password
        if (password_verify($current_password, $admin['matkhau'])) {
            // Hash new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update password in users table
            $pwd_stmt = $conn->prepare("UPDATE users SET matkhau = ? WHERE id = ?");
            $pwd_stmt->bind_param("si", $hashed_password, $admin_id);
            
            if ($pwd_stmt->execute()) {
                $success_message = 'Mật khẩu đã được thay đổi thành công';
                
                // Log password change action
                $log_query = $conn->prepare("
                    INSERT INTO nhat_ky (id_user, hanh_dong, doi_tuong_loai, doi_tuong_id, chi_tiet, ip_address) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $action_type = 'change_password';
                $target_type = 'admin';
                $target_id = $admin_id;
                $details = "Thay đổi mật khẩu";
                $ip = $_SERVER['REMOTE_ADDR'];
                $log_query->bind_param("ississ", $admin_id, $action_type, $target_type, $target_id, $details, $ip);
                $log_query->execute();
            } else {
                $error_message = 'Có lỗi xảy ra khi đổi mật khẩu: ' . $conn->error;
            }
        } else {
            $error_message = 'Mật khẩu hiện tại không chính xác';
        }
    }
}

// Handle avatar upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_avatar'])) {
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = $_FILES['avatar']['type'];
        $file_size = $_FILES['avatar']['size'];
        $file_tmp = $_FILES['avatar']['tmp_name'];
        
        // Check file type
        if (!in_array($file_type, $allowed_types)) {
            $error_message = 'Chỉ cho phép tải lên các tệp hình ảnh (JPEG, PNG, GIF)';
        } 
        // Check file size (2MB max)
        elseif ($file_size > 2 * 1024 * 1024) {
            $error_message = 'Kích thước tệp không được vượt quá 2MB';
        } 
        else {
            $upload_dir = '../uploads/admin/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate unique filename
            $file_ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
            $new_filename = uniqid('avatar_') . '.' . $file_ext;
            $upload_path = $upload_dir . $new_filename;
            
            // Delete old avatar if exists
            if (!empty($admin['anh_dai_dien']) && file_exists($upload_dir . $admin['anh_dai_dien'])) {
                unlink($upload_dir . $admin['anh_dai_dien']);
            }
            
            // Upload new avatar
            if (move_uploaded_file($file_tmp, $upload_path)) {
                // Update avatar in database - now using users table
                $avatar_stmt = $conn->prepare("UPDATE users SET anh_dai_dien = ? WHERE id = ?");
                $avatar_stmt->bind_param("si", $new_filename, $admin_id);
                
                if ($avatar_stmt->execute()) {
                    $success_message = 'Ảnh đại diện đã được cập nhật thành công';
                    
                    // Log avatar update
                    $log_query = $conn->prepare("
                        INSERT INTO nhat_ky (id_user, hanh_dong, doi_tuong_loai, doi_tuong_id, chi_tiet, ip_address) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $action_type = 'update_avatar';
                    $target_type = 'admin';
                    $target_id = $admin_id;
                    $details = "Cập nhật ảnh đại diện";
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $log_query->bind_param("ississ", $admin_id, $action_type, $target_type, $target_id, $details, $ip);
                    $log_query->execute();
                    
                    // Refresh admin data
                    $stmt->execute();
                    $admin = $stmt->get_result()->fetch_assoc();
                } else {
                    $error_message = 'Có lỗi xảy ra khi cập nhật ảnh đại diện: ' . $conn->error;
                }
            } else {
                $error_message = 'Không thể tải lên ảnh đại diện. Vui lòng thử lại sau.';
            }
        }
    } else {
        $error_message = 'Vui lòng chọn một tệp hình ảnh để tải lên';
    }
}

// Replace login history logic with nhat_ky table queries
$login_history = false;
?>

<!-- Include sidebar -->


<!-- Main content -->
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Trang chủ</a></li>
            <li class="breadcrumb-item active" aria-current="page">Hồ sơ cá nhân</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Hồ sơ cá nhân</h1>
    </div>

    <!-- Display Messages -->
    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Profile Info Card -->
        <div class="col-md-4 mb-4">
            <div class="card">
                <div class="card-body text-center py-4">
                    <!-- Avatar -->
                    <div class="position-relative d-inline-block mb-4">
                        <?php if (!empty($admin['anh_dai_dien']) && file_exists('../uploads/admin/' . $admin['anh_dai_dien'])): ?>
                            <img src="../uploads/admin/<?php echo $admin['anh_dai_dien']; ?>" alt="Avatar" 
                                class="rounded-circle img-thumbnail" style="width:150px;height:150px;object-fit:cover;">
                        <?php else: ?>
                            <div class="rounded-circle bg-light d-flex align-items-center justify-content-center mx-auto" 
                                style="width:150px;height:150px;font-size:4rem;color:#6c757d;">
                                <i class="bi bi-person-circle"></i>
                            </div>
                        <?php endif; ?>
                        <button type="button" class="btn btn-sm btn-primary position-absolute bottom-0 end-0" 
                                data-bs-toggle="modal" data-bs-target="#avatarModal">
                            <i class="bi bi-camera"></i>
                        </button>
                    </div>
                    
                    <h5 class="mb-1"><?php echo htmlspecialchars($admin['ten']); ?></h5>
                    <p class="text-muted"><?php echo htmlspecialchars($admin['taikhoan']); ?></p>
                    
                    <!-- Role badge - Updated to use loai_user column -->
                    <?php if ($admin['loai_user'] == 2): ?>
                        <span class="badge bg-danger">Super Admin</span>
                    <?php elseif ($admin['loai_user'] == 1): ?>
                        <span class="badge bg-primary">Admin</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Nhân viên</span>
                    <?php endif; ?>
                </div>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Email:</span>
                        <span><?php echo htmlspecialchars($admin['email'] ?? 'Chưa cung cấp'); ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Ngày tạo tài khoản:</span>
                        <span><?php echo date('d/m/Y', strtotime($admin['ngay_tao'])); ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>Đăng nhập gần đây:</span>
                        <span><?php echo date('d/m/Y H:i', strtotime($admin['lan_dang_nhap_cuoi'] ?? $admin['ngay_tao'])); ?></span>
                    </li>
                </ul>
            </div>
        </div>
        
        <!-- Profile Tabs -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-white py-3">
                    <ul class="nav nav-tabs card-header-tabs" id="profileTabs">
                        <li class="nav-item">
                            <a class="nav-link active" id="edit-tab" data-bs-toggle="tab" href="#edit">Cập nhật thông tin</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="password-tab" data-bs-toggle="tab" href="#password">Đổi mật khẩu</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="activity-tab" data-bs-toggle="tab" href="#activity">Lịch sử hoạt động</a>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content">
                        <!-- Edit Profile Tab - Updated field names -->
                        <div class="tab-pane fade show active" id="edit">
                            <form method="post" action="" class="mb-3 mt-3">
                                <div class="mb-3">
                                    <label for="ten" class="form-label">Tên hiển thị</label>
                                    <input type="text" class="form-control" id="ten" name="ten" 
                                           value="<?php echo htmlspecialchars($admin['ten']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($admin['email'] ?? ''); ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="taikhoan" class="form-label">Tên đăng nhập</label>
                                    <input type="text" class="form-control" id="taikhoan" 
                                           value="<?php echo htmlspecialchars($admin['taikhoan']); ?>" readonly disabled>
                                    <div class="form-text">Tên đăng nhập không thể thay đổi.</div>
                                </div>
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <button type="submit" name="update_profile" class="btn btn-primary">
                                        <i class="bi bi-save me-1"></i> Lưu thay đổi
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Password Change Tab -->
                        <div class="tab-pane fade" id="password">
                            <form method="post" action="" class="mb-3 mt-3">
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Mật khẩu hiện tại</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                </div>
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">Mật khẩu mới</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" 
                                           pattern=".{8,}" title="Mật khẩu phải có ít nhất 8 ký tự" required>
                                    <div class="form-text">Mật khẩu phải có ít nhất 8 ký tự.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Xác nhận mật khẩu mới</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <button type="submit" name="change_password" class="btn btn-primary">
                                        <i class="bi bi-check-lg me-1"></i> Đổi mật khẩu
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Activity Tab - Updated to use nhat_ky table -->
                        <div class="tab-pane fade" id="activity">
                            <h5 class="mt-3 mb-4">Lịch sử hoạt động</h5>
                            <?php
                            // Lấy lịch sử hoạt động từ bảng nhat_ky
                            $activities_stmt = $conn->prepare("
                                SELECT * FROM nhat_ky 
                                WHERE id_user = ? 
                                ORDER BY ngay_tao DESC 
                                LIMIT 15
                            ");
                            $activities_stmt->bind_param("i", $admin_id);
                            $activities_stmt->execute();
                            $activities = $activities_stmt->get_result();

                            if ($activities && $activities->num_rows > 0):
                            ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead>
                                        <tr>
                                            <th>Thời gian</th>
                                            <th>Hành động</th>
                                            <th>Đối tượng</th>
                                            <th>Chi tiết</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($activity = $activities->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y H:i:s', strtotime($activity['ngay_tao'])); ?></td>
                                            <td><?php echo htmlspecialchars($activity['hanh_dong']); ?></td>
                                            <td><?php echo htmlspecialchars($activity['doi_tuong_loai']); ?></td>
                                            <td><?php echo htmlspecialchars($activity['chi_tiet']); ?></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                Chưa có hoạt động nào được ghi lại.
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Avatar Upload Modal -->
<div class="modal fade" id="avatarModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cập nhật ảnh đại diện</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="avatarForm" method="post" action="" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="avatar" class="form-label">Chọn ảnh đại diện mới</label>
                        <input class="form-control" type="file" id="avatar" name="avatar" accept="image/*" required>
                        <div class="form-text">Chỉ chấp nhận các tệp JPG, PNG hoặc GIF (tối đa 2MB)</div>
                    </div>
                    
                    <div id="preview-container" class="text-center my-3 d-none">
                        <img id="avatar-preview" class="img-thumbnail" style="max-height:200px">
                    </div>
                    
                    <div class="text-end">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" name="upload_avatar" class="btn btn-primary">Cập nhật</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
// Add custom script for password validation and image preview
$page_specific_js = <<<EOT
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Password validation
    const passwordForm = document.querySelector('#password form');
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');

    if (passwordForm) {
        passwordForm.addEventListener('submit', function(e) {
            if (newPassword.value !== confirmPassword.value) {
                e.preventDefault();
                alert('Mật khẩu mới và xác nhận mật khẩu không khớp');
                confirmPassword.focus();
            }
        });
    }

    // Image preview
    const avatarInput = document.getElementById('avatar');
    const previewContainer = document.getElementById('preview-container');
    const avatarPreview = document.getElementById('avatar-preview');

    if (avatarInput) {
        avatarInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    avatarPreview.src = e.target.result;
                    previewContainer.classList.remove('d-none');
                }
                reader.readAsDataURL(this.files[0]);
            } else {
                previewContainer.classList.add('d-none');
            }
        });
    }
});
</script>
EOT;

// Include footer
include('includes/footer.php');
?>