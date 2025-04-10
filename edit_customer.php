<?php
// Set page title
$page_title = 'Chỉnh sửa khách hàng';

// Include header
include('includes/header.php');

// Include database connection
include('../config/config.php');

// Get customer ID
$customer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Validate customer ID
if ($customer_id <= 0) {
    header('Location: customers.php');
    exit();
}

// Get customer information
$stmt = $conn->prepare("SELECT * FROM users WHERE id_user = ? AND loai_user = 0");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "Không tìm thấy khách hàng với ID: $customer_id";
    header('Location: customers.php');
    exit();
}

$customer = $result->fetch_assoc();
?>

<!-- Include sidebar -->
<?php include('includes/sidebar.php'); ?>

<!-- Main content -->
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Trang chủ</a></li>
            <li class="breadcrumb-item"><a href="customers.php">Quản lý khách hàng</a></li>
            <li class="breadcrumb-item active" aria-current="page">Chỉnh sửa khách hàng</li>
        </ol>
    </nav>
    
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Chỉnh sửa thông tin khách hàng</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="customer-detail.php?id=<?php echo $customer_id; ?>" class="btn btn-sm btn-outline-secondary me-2">
                <i class="bi bi-arrow-left"></i> Quay lại thông tin chi tiết
            </a>
            <a href="customers.php" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-people"></i> Danh sách khách hàng
            </a>
        </div>
    </div>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['error_message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['success_message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header bg-white py-3">
            <h5 class="card-title mb-0">Thông tin tài khoản: <?php echo htmlspecialchars($customer['taikhoan']); ?></h5>
        </div>
        <div class="card-body">
            <form action="process_customer.php" method="post" id="editCustomerForm" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="customer_id" value="<?php echo $customer_id; ?>">
                
                <div class="row mb-4">
                    <div class="col-md-2 text-center">
                        <!-- Profile picture preview -->
                        <div class="mb-3">
                            <div id="profile-image-preview" class="rounded-circle position-relative" 
                                 style="width: 120px; height: 120px; overflow: hidden; margin: 0 auto;">
                                <?php if (!empty($customer['anh_dai_dien']) && file_exists("../uploads/users/" . $customer['anh_dai_dien'])): ?>
                                    <img src="../uploads/users/<?php echo $customer['anh_dai_dien']; ?>" class="img-fluid rounded-circle" 
                                         style="width: 120px; height: 120px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="bg-secondary text-white d-flex align-items-center justify-content-center" 
                                         style="width: 120px; height: 120px; font-size: 3rem;">
                                        <i class="bi bi-person"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Upload profile picture -->
                        <div class="mb-3">
                            <label for="profile_picture" class="form-label">Ảnh đại diện</label>
                            <input type="file" class="form-control form-control-sm" id="profile_picture" name="profile_picture" accept="image/*">
                            <div class="form-text">Kích thước tối đa: 2MB</div>
                        </div>
                        
                        <?php if (!empty($customer['anh_dai_dien'])): ?>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="remove_picture" name="remove_picture" value="1">
                            <label class="form-check-label" for="remove_picture">
                                Xóa ảnh đại diện
                            </label>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-10">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="taikhoan" class="form-label">Tên đăng nhập</label>
                                <input type="text" class="form-control" id="taikhoan" name="taikhoan" value="<?php echo htmlspecialchars($customer['taikhoan']); ?>" readonly>
                                <div class="form-text">Tên đăng nhập không thể thay đổi</div>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($customer['email'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="tenuser" class="form-label required-field">Họ và tên</label>
                                <input type="text" class="form-control" id="tenuser" name="tenuser" value="<?php echo htmlspecialchars($customer['tenuser']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="sdt" class="form-label required-field">Số điện thoại</label>
                                <input type="text" class="form-control" id="sdt" name="sdt" value="<?php echo htmlspecialchars($customer['sdt']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="diachi" class="form-label required-field">Địa chỉ</label>
                            <textarea class="form-control" id="diachi" name="diachi" rows="2" required><?php echo htmlspecialchars($customer['diachi']); ?></textarea>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="trang_thai" name="trang_thai" value="1" <?php echo $customer['trang_thai'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="trang_thai">
                                            Tài khoản đang hoạt động
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="trang_thai_xac_thuc" name="trang_thai_xac_thuc" value="1" <?php echo $customer['trang_thai_xac_thuc'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="trang_thai_xac_thuc">
                                            Tài khoản đã xác thực
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="card-title">Đổi mật khẩu</h6>
                                        <div class="mb-3">
                                            <label for="new_password" class="form-label">Mật khẩu mới</label>
                                            <div class="input-group">
                                                <input type="password" class="form-control" id="new_password" name="new_password">
                                                <button class="btn btn-outline-secondary" type="button" id="togglePasswordBtn">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <button class="btn btn-outline-secondary" type="button" id="generatePasswordBtn">
                                                    <i class="bi bi-magic"></i> Tạo
                                                </button>
                                            </div>
                                            <div class="form-text">Để trống nếu không muốn thay đổi mật khẩu</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <hr>
                
                <div class="d-flex justify-content-between">
                    <a href="customer-detail.php?id=<?php echo $customer_id; ?>" class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i> Hủy thay đổi
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Lưu thay đổi
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<?php 
// JavaScript for the page
$page_specific_js = '
<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Profile image preview
        const profilePictureInput = document.getElementById("profile_picture");
        const profileImagePreview = document.getElementById("profile-image-preview");
        const removeProfilePicture = document.getElementById("remove_picture");
        
        if (profilePictureInput) {
            profilePictureInput.addEventListener("change", function() {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        profileImagePreview.innerHTML = `
                            <img src="${e.target.result}" class="img-fluid rounded-circle" 
                                 style="width: 120px; height: 120px; object-fit: cover;">
                        `;
                    };
                    
                    reader.readAsDataURL(this.files[0]);
                    
                    // Uncheck remove picture if it exists
                    if (removeProfilePicture) {
                        removeProfilePicture.checked = false;
                    }
                }
            });
        }
        
        if (removeProfilePicture) {
            removeProfilePicture.addEventListener("change", function() {
                if (this.checked) {
                    profileImagePreview.innerHTML = `
                        <div class="bg-secondary text-white d-flex align-items-center justify-content-center" 
                             style="width: 120px; height: 120px; font-size: 3rem;">
                            <i class="bi bi-person"></i>
                        </div>
                    `;
                    profilePictureInput.value = "";
                } else {
                    // Restore the original image if possible
                    const originalImage = "' . ((!empty($customer['anh_dai_dien'])) ? "../uploads/users/" . $customer['anh_dai_dien'] : '') . '";
                    if (originalImage) {
                        profileImagePreview.innerHTML = `
                            <img src="${originalImage}" class="img-fluid rounded-circle" 
                                 style="width: 120px; height: 120px; object-fit: cover;">
                        `;
                    }
                }
            });
        }
        
        // Password toggle visibility
        const togglePasswordBtn = document.getElementById("togglePasswordBtn");
        const passwordInput = document.getElementById("new_password");
        
        if (togglePasswordBtn && passwordInput) {
            togglePasswordBtn.addEventListener("click", function() {
                const type = passwordInput.getAttribute("type") === "password" ? "text" : "password";
                passwordInput.setAttribute("type", type);
                
                // Toggle icon
                const icon = this.querySelector("i");
                icon.classList.toggle("bi-eye");
                icon.classList.toggle("bi-eye-slash");
            });
        }
        
        // Password generator
        const generatePasswordBtn = document.getElementById("generatePasswordBtn");
        if (generatePasswordBtn && passwordInput) {
            generatePasswordBtn.addEventListener("click", function() {
                const length = 12;
                const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+";
                let password = "";
                
                for (let i = 0; i < length; i++) {
                    const randomIndex = Math.floor(Math.random() * charset.length);
                    password += charset[randomIndex];
                }
                
                passwordInput.value = password;
                passwordInput.setAttribute("type", "text");
                
                // Update toggle button icon
                const icon = togglePasswordBtn.querySelector("i");
                if (icon.classList.contains("bi-eye")) {
                    icon.classList.replace("bi-eye", "bi-eye-slash");
                }
            });
        }
        
        // Form validation
        const form = document.getElementById("editCustomerForm");
        if (form) {
            form.addEventListener("submit", function(event) {
                const phoneInput = document.getElementById("sdt");
                const emailInput = document.getElementById("email");
                const passwordInput = document.getElementById("new_password");
                
                let isValid = true;
                
                // Validate phone number
                if (phoneInput && phoneInput.value) {
                    const phoneRegex = /^[0-9]{10}$/;
                    if (!phoneRegex.test(phoneInput.value)) {
                        alert("Số điện thoại không hợp lệ. Vui lòng nhập đúng 10 chữ số.");
                        isValid = false;
                    }
                }
                
                // Validate email (if provided)
                if (emailInput && emailInput.value) {
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(emailInput.value)) {
                        alert("Email không hợp lệ.");
                        isValid = false;
                    }
                }
                
                // Validate password (if changing)
                if (passwordInput && passwordInput.value) {
                    if (passwordInput.value.length < 8) {
                        alert("Mật khẩu mới phải có ít nhất 8 ký tự.");
                        isValid = false;
                    }
                }
                
                if (!isValid) {
                    event.preventDefault();
                }
            });
        }
    });
</script>
';

include('includes/footer.php'); 
?>
