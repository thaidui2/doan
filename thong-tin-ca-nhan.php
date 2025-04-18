<?php
session_start();
include('config/config.php');

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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Email không hợp lệ';
    } elseif (empty($sodienthoai) || !preg_match('/^[0-9]{10}$/', $sodienthoai)) {
        $error_message = 'Số điện thoại không hợp lệ (phải có 10 số)';
    } elseif (empty($diachi)) {
        $error_message = 'Vui lòng nhập địa chỉ';
    } elseif (empty($tinh_tp)) {
        $error_message = 'Vui lòng chọn tỉnh/thành phố';
    } elseif (empty($quan_huyen)) {
        $error_message = 'Vui lòng chọn quận/huyện';
    } elseif (empty($phuong_xa)) {
        $error_message = 'Vui lòng chọn phường/xã';
    } else {
        // Update user profile
        try {
            // Check if email already exists for other users
            if (!empty($email)) {
                $email_check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $email_check->bind_param("si", $email, $user_id);
                $email_check->execute();
                $email_result = $email_check->get_result();
                
                if ($email_result->num_rows > 0) {
                    $error_message = 'Email này đã được sử dụng bởi tài khoản khác';
                    // Stop execution if email is already used
                    throw new Exception($error_message);
                }
            }
            
            // Process avatar upload if provided
            $avatar_path = null;
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $max_size = 2 * 1024 * 1024; // 2MB
                
                if (!in_array($_FILES['avatar']['type'], $allowed_types)) {
                    $error_message = 'Chỉ chấp nhận file ảnh (JPEG, PNG, GIF, WEBP)';
                    throw new Exception($error_message);
                }
                
                if ($_FILES['avatar']['size'] > $max_size) {
                    $error_message = 'Kích thước file ảnh không được vượt quá 2MB';
                    throw new Exception($error_message);
                }
                
                $upload_dir = 'uploads/users/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $filename = time() . '_' . uniqid() . '_' . $_FILES['avatar']['name'];
                $upload_path = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_path)) {
                    $avatar_path = $filename;
                } else {
                    $error_message = 'Không thể tải lên ảnh đại diện';
                    throw new Exception($error_message);
                }
            }
            
            // Update user profile in database
            if ($avatar_path) {
                $stmt = $conn->prepare("
                    UPDATE users SET 
                    ten = ?, 
                    email = ?, 
                    sodienthoai = ?, 
                    diachi = ?,
                    tinh_tp = ?,
                    quan_huyen = ?,
                    phuong_xa = ?,
                    anh_dai_dien = ?
                    WHERE id = ?
                ");
                
                $stmt->bind_param("ssssssssi", 
                    $ten, 
                    $email, 
                    $sodienthoai, 
                    $diachi,
                    $tinh_tp,
                    $quan_huyen,
                    $phuong_xa,
                    $avatar_path,
                    $user_id
                );
            } else {
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
            }
            
            if ($stmt->execute()) {
                $success_message = 'Cập nhật thông tin thành công!';
                
                // Update session data
                $_SESSION['user']['tenuser'] = $ten;
                
                // Reload user information to update the form
                $user_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                $user_stmt->bind_param("i", $user_id);
                $user_stmt->execute();
                $user_data = $user_stmt->get_result();
                $user = $user_data->fetch_assoc();
                
            } else {
                $error_message = 'Lỗi khi cập nhật thông tin: ' . $stmt->error;
            }
        } catch (Exception $e) {
            if (empty($error_message)) {
                $error_message = 'Đã xảy ra lỗi: ' . $e->getMessage();
            }
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
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thông tin cá nhân - Bug Shop</title>
    <link rel="stylesheet" href="node_modules/bootstrap/dist/css/bootstrap.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/index.css">
    <style>
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
            overflow: hidden;
        }
        
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .avatar-preview {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            overflow: hidden;
            margin: 1rem auto;
            border: 3px solid #eaeaea;
        }
        
        .avatar-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .form-label.required::after {
            content: "*";
            color: red;
            margin-left: 4px;
        }
    </style>
</head>

<body>
    <?php include('includes/header.php'); ?>
    
    <div class="container py-5">
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
        
        <div class="row">
            <!-- Left sidebar with menu items -->
            <div class="col-md-3 mb-4">
                <div class="card shadow-sm">
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <a href="taikhoan.php" class="list-group-item list-group-item-action">
                                <i class="bi bi-person-circle me-2"></i> Trang cá nhân
                            </a>
                            <a href="donhang.php" class="list-group-item list-group-item-action">
                                <i class="bi bi-bag-check me-2"></i> Đơn hàng của tôi
                            </a>
                            <a href="thong-tin-ca-nhan.php" class="list-group-item list-group-item-action active">
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
                <div class="card shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">Thông tin cá nhân</h5>
                    </div>
                    
                    <div class="card-body">
                        <form action="thong-tin-ca-nhan.php" method="post" enctype="multipart/form-data">
                            <div class="row">
                                <!-- Avatar section -->
                                <div class="col-md-4 text-center border-end">
                                    <div class="avatar-preview">
                                        <?php if (!empty($user['anh_dai_dien'])): ?>
                                            <img src="uploads/users/<?php echo htmlspecialchars($user['anh_dai_dien']); ?>" alt="Avatar" id="avatar-preview-image">
                                        <?php else: ?>
                                            <div class="h-100 d-flex align-items-center justify-content-center bg-light">
                                                <i class="bi bi-person-circle" style="font-size: 5rem; color: #ccc;"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="avatar" class="form-label">Ảnh đại diện</label>
                                        <input type="file" class="form-control" id="avatar" name="avatar" accept="image/*">
                                        <div class="form-text">Tối đa 2MB (JPG, PNG, GIF, WEBP)</div>
                                    </div>
                                </div>
                                
                                <!-- Personal information -->
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label for="ten" class="form-label required">Họ và tên</label>
                                        <input type="text" class="form-control" id="ten" name="ten" value="<?php echo htmlspecialchars($user['ten']); ?>" required>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="email" class="form-label">Email</label>
                                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="sodienthoai" class="form-label required">Số điện thoại</label>
                                            <input type="tel" class="form-control" id="sodienthoai" name="sodienthoai" value="<?php echo htmlspecialchars($user['sodienthoai'] ?? ''); ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="diachi" class="form-label required">Địa chỉ</label>
                                        <input type="text" class="form-control" id="diachi" name="diachi" value="<?php echo htmlspecialchars($user['diachi'] ?? ''); ?>" required placeholder="Số nhà, tên đường">
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <label for="tinh_tp" class="form-label required">Tỉnh/Thành phố</label>
                                            <select class="form-select" id="tinh_tp" name="tinh_tp" required>
                                                <option value="">Chọn tỉnh/thành phố</option>
                                                <!-- Will be populated via JS -->
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="quan_huyen" class="form-label required">Quận/Huyện</label>
                                            <select class="form-select" id="quan_huyen" name="quan_huyen" required disabled>
                                                <option value="">Chọn quận/huyện</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="phuong_xa" class="form-label required">Phường/Xã</label>
                                            <select class="form-select" id="phuong_xa" name="phuong_xa" required disabled>
                                                <option value="">Chọn phường/xã</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Cập nhật thông tin
                                </button>
                                <a href="taikhoan.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left"></i> Quay lại
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include('includes/footer.php'); ?>
    
    <script src="js/address-selector.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Avatar preview
            const avatarInput = document.getElementById('avatar');
            const avatarPreview = document.getElementById('avatar-preview-image');
            
            if (avatarInput && avatarPreview) {
                avatarInput.addEventListener('change', function() {
                    if (this.files && this.files[0]) {
                        const reader = new FileReader();
                        
                        reader.onload = function(e) {
                            if (!avatarPreview) {
                                // If no image element exists yet, create one
                                const imgElement = document.createElement('img');
                                imgElement.id = 'avatar-preview-image';
                                imgElement.alt = 'Avatar Preview';
                                imgElement.src = e.target.result;
                                
                                const previewContainer = document.querySelector('.avatar-preview');
                                previewContainer.innerHTML = '';
                                previewContainer.appendChild(imgElement);
                            } else {
                                // If image element already exists, just update the source
                                avatarPreview.src = e.target.result;
                            }
                        }
                        
                        reader.readAsDataURL(this.files[0]);
                    }
                });
            }
            
            // Initialize address selection
            initializeAddressSelectors({
                provinceSelector: '#tinh_tp',
                districtSelector: '#quan_huyen',
                wardSelector: '#phuong_xa',
                selectedProvince: '<?php echo htmlspecialchars($user['tinh_tp'] ?? ''); ?>',
                selectedDistrict: '<?php echo htmlspecialchars($user['quan_huyen'] ?? ''); ?>',
                selectedWard: '<?php echo htmlspecialchars($user['phuong_xa'] ?? ''); ?>'
            });
            
            // Phone number validation
            const phoneInput = document.getElementById('sodienthoai');
            if (phoneInput) {
                phoneInput.addEventListener('input', function() {
                    // Remove non-numeric characters
                    this.value = this.value.replace(/[^0-9]/g, '');
                    
                    // Limit to 10 digits
                    if (this.value.length > 10) {
                        this.value = this.value.substring(0, 10);
                    }
                });
            }
            
            // Form validation
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(event) {
                    let isValid = true;
                    const requiredInputs = form.querySelectorAll('[required]');
                    
                    requiredInputs.forEach(input => {
                        if (!input.value.trim()) {
                            input.classList.add('is-invalid');
                            isValid = false;
                        } else {
                            input.classList.remove('is-invalid');
                        }
                    });
                    
                    if (!isValid) {
                        event.preventDefault();
                        alert('Vui lòng điền đầy đủ các trường bắt buộc');
                    }
                });
            }
        });
    </script>
</body>
</html>
