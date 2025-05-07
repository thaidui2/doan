<?php
session_start();
include('config/config.php');

// Kiểm tra xem người dùng đã đăng nhập chưa
if (!isset($_SESSION['user']) || $_SESSION['user']['logged_in'] !== true) {
    // Lưu URL hiện tại để chuyển hướng sau khi đăng nhập
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: dangnhap.php");
    exit;
}

// Lấy thông tin người dùng
$user_id = $_SESSION['user']['id'];
$success_message = '';
$error_message = '';

// Xử lý đổi mật khẩu
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Xác thực đầu vào
    if (empty($current_password)) {
        $error_message = 'Vui lòng nhập mật khẩu hiện tại.';
    } elseif (empty($new_password)) {
        $error_message = 'Vui lòng nhập mật khẩu mới.';
    } elseif (strlen($new_password) < 8) {
        $error_message = 'Mật khẩu mới phải có ít nhất 8 ký tự.';
    } elseif ($new_password !== $confirm_password) {
        $error_message = 'Mật khẩu mới và xác nhận mật khẩu không khớp.';
    } else {
        // Kiểm tra mật khẩu hiện tại
        $stmt = $conn->prepare("SELECT matkhau FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($current_password, $user['matkhau'])) {
                // Mật khẩu hiện tại đúng, cập nhật mật khẩu mới
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                $update_stmt = $conn->prepare("UPDATE users SET matkhau = ? WHERE id = ?");
                $update_stmt->bind_param("si", $hashed_password, $user_id);
                
                if ($update_stmt->execute()) {
                    $success_message = 'Đổi mật khẩu thành công!';
                    
                    // Ghi log thay đổi mật khẩu
                    $log_stmt = $conn->prepare("
                        INSERT INTO nhat_ky (id_user, hanh_dong, doi_tuong_loai, doi_tuong_id, chi_tiet, ip_address) 
                        VALUES (?, 'change_password', 'user', ?, 'Người dùng đã thay đổi mật khẩu', ?)
                    ");
                    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                    $log_stmt->bind_param("iis", $user_id, $user_id, $ip);
                    $log_stmt->execute();
                } else {
                    $error_message = 'Có lỗi xảy ra khi cập nhật mật khẩu. Vui lòng thử lại sau.';
                }
            } else {
                $error_message = 'Mật khẩu hiện tại không đúng.';
            }
        } else {
            $error_message = 'Không tìm thấy thông tin người dùng.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đổi mật khẩu - Bug Shop</title>
    <link rel="stylesheet" href="node_modules/bootstrap/dist/css/bootstrap.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/index.css">
    <style>
        .password-strength {
            height: 5px;
            transition: all 0.3s;
            margin-top: 5px;
        }
        
        .password-strength-weak { background-color: #dc3545; width: 30%; }
        .password-strength-medium { background-color: #ffc107; width: 60%; }
        .password-strength-strong { background-color: #28a745; width: 100%; }
        
        .password-requirements {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .requirement-met {
            color: #28a745;
        }
        
        .password-toggle {
            cursor: pointer;
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
        }
    </style>
</head>
<body>
    <?php 
    require_once('includes/head.php');
    require_once('includes/header.php');
    ?>
    
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <nav aria-label="breadcrumb" class="mb-4">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Trang chủ</a></li>
                        <li class="breadcrumb-item"><a href="taikhoan.php">Tài khoản</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Đổi mật khẩu</li>
                    </ol>
                </nav>
                
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="bi bi-key-fill me-2"></i>Đổi mật khẩu</h4>
                    </div>
                    
                    <div class="card-body">
                        <?php if(!empty($success_message)): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle-fill me-2"></i><?php echo $success_message; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if(!empty($error_message)): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error_message; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form id="password-change-form" method="post" action="doimatkhau.php" class="needs-validation" novalidate>
                            <div class="mb-4">
                                <label for="current_password" class="form-label">Mật khẩu hiện tại <span class="text-danger">*</span></label>
                                <div class="position-relative">
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    <span class="password-toggle" data-target="current_password">
                                        <i class="bi bi-eye-slash"></i>
                                    </span>
                                </div>
                                <div class="invalid-feedback">Vui lòng nhập mật khẩu hiện tại.</div>
                            </div>
                            
                            <hr>
                            
                            <div class="mb-3">
                                <label for="new_password" class="form-label">Mật khẩu mới <span class="text-danger">*</span></label>
                                <div class="position-relative">
                                    <input type="password" class="form-control" id="new_password" name="new_password" 
                                           pattern=".{8,}" required>
                                    <span class="password-toggle" data-target="new_password">
                                        <i class="bi bi-eye-slash"></i>
                                    </span>
                                </div>
                                <div class="password-strength"></div>
                                <div class="invalid-feedback">Mật khẩu phải có ít nhất 8 ký tự.</div>
                                
                                <div class="password-requirements mt-2">
                                    <p class="mb-1"><small>Mật khẩu của bạn cần phải:</small></p>
                                    <ul class="ps-3 mb-0">
                                        <li id="length-check"><small>Có ít nhất 8 ký tự</small></li>
                                        <li id="lowercase-check"><small>Có ít nhất một chữ cái thường (a-z)</small></li>
                                        <li id="uppercase-check"><small>Có ít nhất một chữ cái hoa (A-Z)</small></li>
                                        <li id="number-check"><small>Có ít nhất một chữ số (0-9)</small></li>
                                    </ul>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="confirm_password" class="form-label">Nhập lại mật khẩu mới <span class="text-danger">*</span></label>
                                <div class="position-relative">
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    <span class="password-toggle" data-target="confirm_password">
                                        <i class="bi bi-eye-slash"></i>
                                    </span>
                                </div>
                                <div class="invalid-feedback">Mật khẩu xác nhận không khớp.</div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-lg me-2"></i>Cập nhật mật khẩu
                                </button>
                                <a href="taikhoan.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left me-2"></i>Quay lại
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="card mt-4 border-warning">
                    <div class="card-body">
                        <div class="d-flex">
                            <i class="bi bi-info-circle-fill text-warning me-2 fs-4"></i>
                            <div>
                                <h5>Lưu ý về bảo mật</h5>
                                <p class="mb-0">Để bảo vệ tài khoản của bạn, hãy chọn mật khẩu mạnh và không sử dụng lại mật khẩu đã dùng cho các dịch vụ khác. Sau khi đổi mật khẩu, hệ thống sẽ yêu cầu bạn đăng nhập lại.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include('includes/footer.php'); ?>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Xử lý kiểm tra độ mạnh mật khẩu
            const newPasswordInput = document.getElementById('new_password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const passwordStrength = document.querySelector('.password-strength');
            const lengthCheck = document.getElementById('length-check');
            const lowercaseCheck = document.getElementById('lowercase-check');
            const uppercaseCheck = document.getElementById('uppercase-check');
            const numberCheck = document.getElementById('number-check');
            
            // Hiển thị/ẩn mật khẩu
            document.querySelectorAll('.password-toggle').forEach(toggle => {
                toggle.addEventListener('click', function() {
                    const targetId = this.dataset.target;
                    const passwordInput = document.getElementById(targetId);
                    const icon = this.querySelector('i');
                    
                    if (passwordInput.type === 'password') {
                        passwordInput.type = 'text';
                        icon.classList.remove('bi-eye-slash');
                        icon.classList.add('bi-eye');
                    } else {
                        passwordInput.type = 'password';
                        icon.classList.remove('bi-eye');
                        icon.classList.add('bi-eye-slash');
                    }
                });
            });
            
            // Kiểm tra độ mạnh mật khẩu
            newPasswordInput.addEventListener('input', function() {
                const password = this.value;
                
                // Kiểm tra các yêu cầu về mật khẩu
                const hasLength = password.length >= 8;
                const hasLowerCase = /[a-z]/.test(password);
                const hasUpperCase = /[A-Z]/.test(password);
                const hasNumber = /[0-9]/.test(password);
                
                // Cập nhật giao diện yêu cầu
                updateRequirement(lengthCheck, hasLength);
                updateRequirement(lowercaseCheck, hasLowerCase);
                updateRequirement(uppercaseCheck, hasUpperCase);
                updateRequirement(numberCheck, hasNumber);
                
                // Tính độ mạnh mật khẩu
                let strength = 0;
                if (hasLength) strength += 1;
                if (hasLowerCase) strength += 1;
                if (hasUpperCase) strength += 1;
                if (hasNumber) strength += 1;
                
                // Hiển thị độ mạnh mật khẩu
                passwordStrength.className = 'password-strength';
                if (password.length === 0) {
                    passwordStrength.style.width = '0';
                } else if (strength <= 2) {
                    passwordStrength.classList.add('password-strength-weak');
                } else if (strength === 3) {
                    passwordStrength.classList.add('password-strength-medium');
                } else {
                    passwordStrength.classList.add('password-strength-strong');
                }
            });
            
            // Kiểm tra xác nhận mật khẩu
            confirmPasswordInput.addEventListener('input', function() {
                if (this.value !== newPasswordInput.value) {
                    this.setCustomValidity('Mật khẩu xác nhận không khớp');
                } else {
                    this.setCustomValidity('');
                }
            });
            
            // Cập nhật kiểu hiển thị các yêu cầu mật khẩu
            function updateRequirement(element, isValid) {
                if (isValid) {
                    element.classList.add('requirement-met');
                    element.innerHTML = '<small><i class="bi bi-check-circle-fill me-1"></i>' + element.innerText.trim() + '</small>';
                } else {
                    element.classList.remove('requirement-met');
                    if (element.innerHTML.includes('bi-check-circle-fill')) {
                        element.innerHTML = '<small>' + element.innerText.trim() + '</small>';
                    }
                }
            }
            
            // Bootstrap validation
            const form = document.getElementById('password-change-form');
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                
                form.classList.add('was-validated');
            });
        });
    </script>
</body>
</html>
