<?php
// Set page title
$page_title = 'Cài đặt hệ thống';

// Include header (will check for login)
include('includes/header.php');

// Include database connection
include('../config/config.php');

// Check if user has permission to access settings
if (function_exists('checkPermissionRedirect')) {
    checkPermissionRedirect('settings_view');
}

// Initialize messages
$success_message = $error_message = '';

// Function to get setting value from database
function getSetting($key, $default = '') {
    global $conn;
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc()['setting_value'];
    }
    
    return $default;
}

// Check if settings table exists
$tableExists = false;
$tableCheck = $conn->query("SHOW TABLES LIKE 'settings'");
if ($tableCheck->num_rows > 0) {
    $tableExists = true;
} else {
    // Create settings table if it doesn't exist
    $createTable = "CREATE TABLE settings (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT,
        setting_group VARCHAR(50) NOT NULL DEFAULT 'general',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    if ($conn->query($createTable)) {
        $tableExists = true;
        
        // Insert default settings
        $default_settings = [
            // General settings
            ['site_name', 'Bug Shop', 'general'],
            ['site_description', 'Cửa hàng giày dép chất lượng cao', 'general'],
            ['contact_email', 'contact@bugshop.com', 'general'],
            ['contact_phone', '0123456789', 'general'],
            ['address', 'Số 123, Đường ABC, Quận XYZ, TP. HCM', 'general'],
            ['logo', '', 'general'],
            ['favicon', '', 'general'],
            
            // Email settings
            ['smtp_host', '', 'email'],
            ['smtp_port', '587', 'email'],
            ['smtp_username', '', 'email'],
            ['smtp_password', '', 'email'],
            ['smtp_encryption', 'tls', 'email'],
            ['email_sender', 'no-reply@bugshop.com', 'email'],
            ['email_sender_name', 'Bug Shop', 'email'],
            
            // Order settings
            ['default_shipping_fee', '30000', 'order'],
            ['free_shipping_threshold', '500000', 'order'],
            ['order_prefix', 'BUG-', 'order'],
            ['enable_cod', '1', 'order'],
            ['enable_bank_transfer', '1', 'order'],
            
            // Social media
            ['facebook_url', '', 'social'],
            ['instagram_url', '', 'social'],
            ['twitter_url', '', 'social'],
            ['youtube_url', '', 'social']
        ];
        
        $insertStmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value, setting_group) VALUES (?, ?, ?)");
        foreach ($default_settings as $setting) {
            $insertStmt->bind_param("sss", $setting[0], $setting[1], $setting[2]);
            $insertStmt->execute();
        }
    } else {
        $error_message = "Không thể tạo bảng cài đặt: " . $conn->error;
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    // Get the settings group being updated
    $group = trim($_POST['settings_group']);
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Remove settings_group and update_settings from $_POST
        unset($_POST['settings_group'], $_POST['update_settings']);
        
        // Prepare statement for updating settings
        $updateStmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value, setting_group) 
                                    VALUES (?, ?, ?) 
                                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        
        // Process file uploads first
        if (isset($_FILES) && !empty($_FILES)) {
            foreach ($_FILES as $key => $file) {
                if ($file['error'] === UPLOAD_ERR_OK) {
                    // Handle file upload
                    $upload_dir = "../uploads/settings/";
                    
                    // Create directory if it doesn't exist
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $new_filename = $key . '_' . uniqid() . '.' . $file_ext;
                    $upload_path = $upload_dir . $new_filename;
                    
                    // Move the uploaded file
                    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                        // Update the setting
                        $updateStmt->bind_param("sss", $key, $new_filename, $group);
                        $updateStmt->execute();
                    }
                }
            }
        }
        
        // Process other form fields
        foreach ($_POST as $key => $value) {
            // Exclude non-setting fields
            if ($key !== 'settings_group' && $key !== 'update_settings') {
                $updateStmt->bind_param("sss", $key, $value, $group);
                $updateStmt->execute();
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        $success_message = "Cài đặt đã được cập nhật thành công!";
        
        // Log admin action
        if ($conn->query("SHOW TABLES LIKE 'admin_actions'")->num_rows > 0) {
            $admin_id = $_SESSION['admin_id'] ?? 0;
            $action = 'update';
            $details = "Cập nhật cài đặt: " . ucfirst($group);
            $target_type = 'settings';
            $target_id = 0;
            $ip = $_SERVER['REMOTE_ADDR'];
            
            $log_stmt = $conn->prepare("
                INSERT INTO admin_actions (admin_id, action_type, target_type, target_id, details, ip_address) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $log_stmt->bind_param("ississ", $admin_id, $action, $target_type, $target_id, $details, $ip);
            $log_stmt->execute();
        }
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $error_message = "Lỗi khi lưu cài đặt: " . $e->getMessage();
    }
}
?>

<!-- Include sidebar -->
<?php include('includes/sidebar.php'); ?>

<!-- Main content -->
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Cài đặt hệ thống</h1>
    </div>

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
    
    <?php if (!$tableExists): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> Không thể khởi tạo bảng cài đặt. Vui lòng liên hệ quản trị viên hệ thống.
        </div>
    <?php endif; ?>

    <!-- Settings Tabs -->
    <div class="card">
        <div class="card-body">
            <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab" aria-controls="general" aria-selected="true">
                        <i class="bi bi-gear me-1"></i> Cài đặt chung
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="order-tab" data-bs-toggle="tab" data-bs-target="#order" type="button" role="tab" aria-controls="order" aria-selected="false">
                        <i class="bi bi-cart me-1"></i> Đơn hàng
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="email-tab" data-bs-toggle="tab" data-bs-target="#email" type="button" role="tab" aria-controls="email" aria-selected="false">
                        <i class="bi bi-envelope me-1"></i> Email
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="social-tab" data-bs-toggle="tab" data-bs-target="#social" type="button" role="tab" aria-controls="social" aria-selected="false">
                        <i class="bi bi-share me-1"></i> Mạng xã hội
                    </button>
                </li>
            </ul>

            <div class="tab-content pt-4" id="settingsTabsContent">
                <!-- General Settings Tab -->
                <div class="tab-pane fade show active" id="general" role="tabpanel" aria-labelledby="general-tab">
                    <form method="post" action="" enctype="multipart/form-data">
                        <input type="hidden" name="settings_group" value="general">
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h5 class="border-bottom pb-2 mb-3">Thông tin trang web</h5>
                                
                                <div class="mb-3">
                                    <label for="site_name" class="form-label">Tên cửa hàng</label>
                                    <input type="text" class="form-control" id="site_name" name="site_name" value="<?php echo htmlspecialchars(getSetting('site_name', 'Bug Shop')); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="site_description" class="form-label">Mô tả cửa hàng</label>
                                    <textarea class="form-control" id="site_description" name="site_description" rows="3"><?php echo htmlspecialchars(getSetting('site_description')); ?></textarea>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <h5 class="border-bottom pb-2 mb-3">Logo & Favicon</h5>
                                
                                <div class="mb-3">
                                    <label for="logo" class="form-label">Logo</label>
                                    <input type="file" class="form-control" id="logo" name="logo" accept="image/*">
                                    <?php
                                    $logo = getSetting('logo');
                                    if (!empty($logo) && file_exists("../uploads/settings/" . $logo)): ?>
                                    <div class="mt-2">
                                        <img src="../uploads/settings/<?php echo $logo; ?>" alt="Logo" class="img-thumbnail" style="max-height: 100px;">
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="favicon" class="form-label">Favicon</label>
                                    <input type="file" class="form-control" id="favicon" name="favicon" accept="image/x-icon,image/png">
                                    <?php
                                    $favicon = getSetting('favicon');
                                    if (!empty($favicon) && file_exists("../uploads/settings/" . $favicon)): ?>
                                    <div class="mt-2">
                                        <img src="../uploads/settings/<?php echo $favicon; ?>" alt="Favicon" class="img-thumbnail" style="max-height: 32px;">
                                    </div>
                                    <?php endif; ?>
                                    <div class="form-text">Khuyến nghị sử dụng hình ảnh có kích thước 32x32 hoặc 16x16 pixels</div>
                                </div>
                            </div>
                        </div>
                        
                        <h5 class="border-bottom pb-2 mb-3">Thông tin liên hệ</h5>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="contact_email" class="form-label">Email liên hệ</label>
                                    <input type="email" class="form-control" id="contact_email" name="contact_email" value="<?php echo htmlspecialchars(getSetting('contact_email')); ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="contact_phone" class="form-label">Số điện thoại liên hệ</label>
                                    <input type="text" class="form-control" id="contact_phone" name="contact_phone" value="<?php echo htmlspecialchars(getSetting('contact_phone')); ?>">
                                </div>
                            </div>
                            
                            <div class="col-12">
                                <div class="mb-3">
                                    <label for="address" class="form-label">Địa chỉ</label>
                                    <textarea class="form-control" id="address" name="address" rows="2"><?php echo htmlspecialchars(getSetting('address')); ?></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" name="update_settings" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i> Lưu cài đặt
                        </button>
                    </form>
                </div>
                
                <!-- Order Settings Tab -->
                <div class="tab-pane fade" id="order" role="tabpanel" aria-labelledby="order-tab">
                    <form method="post" action="">
                        <input type="hidden" name="settings_group" value="order">
                        
                        <h5 class="border-bottom pb-2 mb-3">Cài đặt đơn hàng</h5>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="default_shipping_fee" class="form-label">Phí vận chuyển mặc định (VNĐ)</label>
                                    <input type="number" class="form-control" id="default_shipping_fee" name="default_shipping_fee" value="<?php echo htmlspecialchars(getSetting('default_shipping_fee', '30000')); ?>" min="0">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="free_shipping_threshold" class="form-label">Giá trị đơn hàng để miễn phí vận chuyển (VNĐ)</label>
                                    <input type="number" class="form-control" id="free_shipping_threshold" name="free_shipping_threshold" value="<?php echo htmlspecialchars(getSetting('free_shipping_threshold', '500000')); ?>" min="0">
                                    <div class="form-text">Đặt 0 để tắt tính năng miễn phí vận chuyển</div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="order_prefix" class="form-label">Tiền tố mã đơn hàng</label>
                                    <input type="text" class="form-control" id="order_prefix" name="order_prefix" value="<?php echo htmlspecialchars(getSetting('order_prefix', 'BUG-')); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label d-block">Phương thức thanh toán</label>
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" id="enable_cod" name="enable_cod" value="1" <?php echo getSetting('enable_cod', '1') == '1' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="enable_cod">Thanh toán khi nhận hàng (COD)</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="enable_bank_transfer" name="enable_bank_transfer" value="1" <?php echo getSetting('enable_bank_transfer', '1') == '1' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="enable_bank_transfer">Chuyển khoản ngân hàng</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="bank_account_info" class="form-label">Thông tin tài khoản ngân hàng</label>
                                    <textarea class="form-control" id="bank_account_info" name="bank_account_info" rows="4"><?php echo htmlspecialchars(getSetting('bank_account_info')); ?></textarea>
                                    <div class="form-text">Thông tin này sẽ hiển thị cho khách hàng khi chọn phương thức thanh toán chuyển khoản</div>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" name="update_settings" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i> Lưu cài đặt
                        </button>
                    </form>
                </div>
                
                <!-- Email Settings Tab -->
                <div class="tab-pane fade" id="email" role="tabpanel" aria-labelledby="email-tab">
                    <form method="post" action="">
                        <input type="hidden" name="settings_group" value="email">
                        
                        <h5 class="border-bottom pb-2 mb-3">Cấu hình SMTP</h5>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle-fill me-2"></i> Cài đặt này được sử dụng để gửi email thông báo đơn hàng, email xác thực và các email khác từ hệ thống.
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="smtp_host" class="form-label">SMTP Host</label>
                                    <input type="text" class="form-control" id="smtp_host" name="smtp_host" value="<?php echo htmlspecialchars(getSetting('smtp_host')); ?>">
                                    <div class="form-text">Ví dụ: smtp.gmail.com</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="smtp_port" class="form-label">SMTP Port</label>
                                    <input type="text" class="form-control" id="smtp_port" name="smtp_port" value="<?php echo htmlspecialchars(getSetting('smtp_port', '587')); ?>">
                                    <div class="form-text">Các cổng phổ biến: 25, 465, 587, 2525</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="smtp_encryption" class="form-label">Mã hóa</label>
                                    <select class="form-select" id="smtp_encryption" name="smtp_encryption">
                                        <option value="tls" <?php echo getSetting('smtp_encryption', 'tls') == 'tls' ? 'selected' : ''; ?>>TLS</option>
                                        <option value="ssl" <?php echo getSetting('smtp_encryption') == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                        <option value="" <?php echo getSetting('smtp_encryption') === '' ? 'selected' : ''; ?>>Không mã hóa</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="smtp_username" class="form-label">SMTP Username</label>
                                    <input type="text" class="form-control" id="smtp_username" name="smtp_username" value="<?php echo htmlspecialchars(getSetting('smtp_username')); ?>">
                                    <div class="form-text">Thường là địa chỉ email của bạn</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="smtp_password" class="form-label">SMTP Password</label>
                                    <input type="password" class="form-control" id="smtp_password" name="smtp_password" value="<?php echo htmlspecialchars(getSetting('smtp_password')); ?>">
                                    <div class="form-text">Với Gmail, bạn cần tạo mật khẩu ứng dụng</div>
                                </div>
                            </div>
                        </div>
                        
                        <h5 class="border-bottom pb-2 mb-3 mt-4">Cài đặt email</h5>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email_sender" class="form-label">Email người gửi</label>
                                    <input type="email" class="form-control" id="email_sender" name="email_sender" value="<?php echo htmlspecialchars(getSetting('email_sender', 'no-reply@bugshop.com')); ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email_sender_name" class="form-label">Tên người gửi</label>
                                    <input type="text" class="form-control" id="email_sender_name" name="email_sender_name" value="<?php echo htmlspecialchars(getSetting('email_sender_name', 'Bug Shop')); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <button type="submit" name="test_email" class="btn btn-outline-primary">
                                        <i class="bi bi-send me-1"></i> Kiểm tra gửi email
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" name="update_settings" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i> Lưu cài đặt
                        </button>
                    </form>
                </div>
                
                <!-- Social Media Tab -->
                <div class="tab-pane fade" id="social" role="tabpanel" aria-labelledby="social-tab">
                    <form method="post" action="">
                        <input type="hidden" name="settings_group" value="social">
                        
                        <h5 class="border-bottom pb-2 mb-3">Liên kết mạng xã hội</h5>
                        
                        <div class="mb-3">
                            <label for="facebook_url" class="form-label">
                                <i class="bi bi-facebook me-1 text-primary"></i> Facebook
                            </label>
                            <input type="url" class="form-control" id="facebook_url" name="facebook_url" value="<?php echo htmlspecialchars(getSetting('facebook_url')); ?>" placeholder="https://facebook.com/yourbrand">
                        </div>
                        
                        <div class="mb-3">
                            <label for="instagram_url" class="form-label">
                                <i class="bi bi-instagram me-1 text-danger"></i> Instagram
                            </label>
                            <input type="url" class="form-control" id="instagram_url" name="instagram_url" value="<?php echo htmlspecialchars(getSetting('instagram_url')); ?>" placeholder="https://instagram.com/yourbrand">
                        </div>
                        
                        <div class="mb-3">
                            <label for="twitter_url" class="form-label">
                                <i class="bi bi-twitter me-1 text-info"></i> Twitter
                            </label>
                            <input type="url" class="form-control" id="twitter_url" name="twitter_url" value="<?php echo htmlspecialchars(getSetting('twitter_url')); ?>" placeholder="https://twitter.com/yourbrand">
                        </div>
                        
                        <div class="mb-3">
                            <label for="youtube_url" class="form-label">
                                <i class="bi bi-youtube me-1 text-danger"></i> YouTube
                            </label>
                            <input type="url" class="form-control" id="youtube_url" name="youtube_url" value="<?php echo htmlspecialchars(getSetting('youtube_url')); ?>" placeholder="https://youtube.com/channel/yourbrand">
                        </div>
                        
                        <button type="submit" name="update_settings" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i> Lưu cài đặt
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<?php 
// JavaScript for the page
$page_specific_js = '
<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Handle tab switching from URL hash
        let hash = window.location.hash;
        if (hash) {
            let tabId = hash.substring(1);
            let tabEl = document.querySelector(\'#settingsTabs button[data-bs-target="#\' + tabId + \'"]\');
            if (tabEl) {
                let tab = new bootstrap.Tab(tabEl);
                tab.show();
            }
        }
        
        // Update URL hash when tab changes
        let tabs = document.querySelectorAll(\'#settingsTabs button\');
        tabs.forEach(tab => {
            tab.addEventListener(\'shown.bs.tab\', function(event) {
                let id = event.target.getAttribute(\'data-bs-target\').substring(1);
                window.location.hash = id;
            });
        });
        
        // Preview image before upload
        [\'logo\', \'favicon\'].forEach(function(field) {
            const input = document.getElementById(field);
            if (input) {
                input.addEventListener(\'change\', function() {
                    const imgContainer = input.closest(\'div\').querySelector(\'img\');
                    const noImgText = input.closest(\'div\').querySelector(\'.no-image-text\');
                    
                    if (this.files && this.files[0]) {
                        const reader = new FileReader();
                        
                        reader.onload = function(e) {
                            if (imgContainer) {
                                imgContainer.src = e.target.result;
                                imgContainer.style.display = \'\';
                            } else {
                                const newImg = document.createElement(\'img\');
                                newImg.src = e.target.result;
                                newImg.alt = field.charAt(0).toUpperCase() + field.slice(1);
                                newImg.className = \'img-thumbnail mt-2\';
                                newImg.style.maxHeight = field === \'favicon\' ? \'32px\' : \'100px\';
                                input.parentNode.appendChild(newImg);
                            }
                            
                            if (noImgText) {
                                noImgText.style.display = \'none\';
                            }
                        }
                        
                        reader.readAsDataURL(this.files[0]);
                    }
                });
            }
        });
    });
</script>
';

// Include footer
include('includes/footer.php');
?>
