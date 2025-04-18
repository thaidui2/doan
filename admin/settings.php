<?php
// Start output buffering to capture any unwanted output before headers are sent
ob_start();

// Set page title
$page_title = 'Cài đặt hệ thống';

// Process form submission BEFORE including header
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    // Include database connection if not already included
    if (!isset($conn)) {
        include('../config/config.php');
    }
    
    // Include admin authentication check
    include('includes/auth_check.php');
    
    // Restrict access to admin only
    if ($admin_level < 2) {
        $_SESSION['error_message'] = "Bạn cần quyền quản trị để truy cập trang này.";
        header('Location: index.php');
        exit();
    }
    
    // Include functions file for logAdminActivity
    include_once('includes/functions.php');
    
    // Include the SettingsManager class definition
    require_once('includes/settings_manager.php');
    
    // Initialize settings manager
    $settingsManager = new SettingsManager($conn);
    
    try {
        if ($settingsManager->saveSettings($_POST, $_FILES)) {
            // Log admin activity
            $admin_id = $_SESSION['admin_id'];
            $details = "Cập nhật cài đặt hệ thống";
            logAdminActivity($conn, $admin_id, 'update', 'settings', 0, $details);
            
            $_SESSION['success_message'] = "Cài đặt đã được cập nhật thành công.";
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Lỗi khi cập nhật cài đặt: " . $e->getMessage();
    }
    
    // Redirect to avoid form resubmission - this should now work correctly
    header('Location: settings.php');
    exit();
}

// Now it's safe to include header
include('includes/header.php');

// Restrict access to admin only
if ($admin_level < 2) {
    $_SESSION['error_message'] = "Bạn cần quyền quản trị để truy cập trang này.";
    echo "<script>window.location.href = 'index.php';</script>";
    exit();
}

// Move the SettingsManager class to a separate file: includes/settings_manager.php
// If you don't want to create a new file, you can include the class definition here
if (!class_exists('SettingsManager')) {
    // Define the class only if it's not already defined
    class SettingsManager {
        private $conn;
        private $settings = [];
        private $existingKeys = [];
        
        /**
         * Constructor
         */
        public function __construct($conn) {
            $this->conn = $conn;
            $this->loadExistingKeys();
        }
        
        /**
         * Load all existing setting keys to avoid collation issues
         */
        private function loadExistingKeys() {
            $query = "SELECT khoa FROM cai_dat";
            $result = $this->conn->query($query);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $this->existingKeys[] = $row['khoa'];
                }
            }
        }
        
        /**
         * Load all settings from both tables
         */
        public function loadAllSettings() {
            // Get settings from cai_dat table (preferred)
            $settings = [];
            $query = "SELECT nhom as group_name, khoa as key_name, gia_tri as value, 
                     kieu_du_lieu as type, mo_ta as description 
                     FROM cai_dat ORDER BY nhom, id";
            
            $result = $this->conn->query($query);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $group = $row['group_name'];
                    $key = $row['key_name'];
                    $settings[$group][$key] = $row;
                }
            }
            
            // Get settings from legacy settings table without collation issues
            $query2 = "SELECT setting_group as group_name, setting_key as key_name, 
                      setting_value as value, 'string' as type, '' as description 
                      FROM settings ORDER BY setting_group, id";
            
            $result2 = $this->conn->query($query2);
            if ($result2) {
                while ($row = $result2->fetch_assoc()) {
                    $group = $row['group_name'];
                    $key = $row['key_name'];
                    // Skip if this key already exists in cai_dat
                    if (!in_array($key, $this->existingKeys) && !isset($settings[$group][$key])) {
                        $settings[$group][$key] = $row;
                    }
                }
            }
            
            $this->settings = $settings;
            return $settings;
        }
        
        /**
         * Update a setting value
         */
        public function updateSetting($key, $value) {
            // First try updating in cai_dat table
            $stmt = $this->conn->prepare("UPDATE cai_dat SET gia_tri = ? WHERE khoa = ?");
            $stmt->bind_param("ss", $value, $key);
            $stmt->execute();
            
            // If no rows affected, check if we need to insert or update settings table
            if ($stmt->affected_rows === 0) {
                // Check if key exists in cai_dat
                if (in_array($key, $this->existingKeys)) {
                    // The key exists but didn't update (value unchanged?)
                    return true;
                }
                
                // Try to update in settings table
                $stmt2 = $this->conn->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
                $stmt2->bind_param("ss", $value, $key);
                $stmt2->execute();
                
                return $stmt2->affected_rows > 0;
            }
            
            return true;
        }
        
        /**
         * Handle file upload for settings
         */
        public function uploadFile($file, $target_dir) {
            // Create directory if it doesn't exist
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            
            $filename = time() . '_' . basename($file['name']);
            $target_file = $target_dir . $filename;
            
            // Check file format
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed_exts = array('jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico');
            
            if (!in_array($file_ext, $allowed_exts)) {
                return [false, "Chỉ chấp nhận file ảnh: " . implode(', ', $allowed_exts)];
            }
            
            // Check file size (2MB limit)
            if ($file['size'] > 2 * 1024 * 1024) {
                return [false, "File không được vượt quá 2MB"];
            }
            
            if (move_uploaded_file($file['tmp_name'], $target_file)) {
                return [true, $filename];
            } else {
                return [false, "Không thể upload file"];
            }
        }
        
        /**
         * Render form field based on setting type
         */
        public function renderField($key, $setting) {
            $value = htmlspecialchars($setting['value']);
            $type = $setting['type'] ?? 'string';
            $id = "setting-" . $key;
            
            // Determine field type based on key name, value or type
            if (in_array($key, ['logo', 'favicon'])) {
                // Image upload fields
                $output = '<div class="input-group mb-2">
                            <input type="file" class="form-control" id="'.$id.'" name="'.$key.'" accept="image/*">
                          </div>';
                
                if (!empty($value)) {
                    $output .= '<div class="mb-2">
                                 <img src="../'.$value.'" alt="'.$key.'" class="img-thumbnail" 
                                      style="max-height: 100px; max-width: 200px;">
                               </div>
                               <input type="hidden" name="setting['.$key.']" value="'.$value.'">';
                }
                
                return $output;
            }
            
            if ($type === 'boolean' || strpos($key, 'enable_') === 0 || in_array($key, ['cod_enabled', 'bank_transfer_enabled'])) {
                return '<div class="form-check form-switch">
                          <input class="form-check-input" type="checkbox" id="'.$id.'" 
                                 name="setting['.$key.']" value="1" '.($value == '1' ? 'checked' : '').'>
                          <label class="form-check-label" for="'.$id.'">Bật</label>
                        </div>';
            }
            
            if (strpos($key, 'password') !== false || $key == 'smtp_password') {
                return '<div class="input-group">
                         <input type="password" class="form-control" id="'.$id.'" 
                                name="setting['.$key.']" value="'.$value.'">
                         <button class="btn btn-outline-secondary toggle-password" type="button" data-target="#'.$id.'">
                            <i class="bi bi-eye"></i>
                         </button>
                       </div>';
            }
            
            if ($key == 'address' || $key == 'site_description' || $type === 'textarea') {
                return '<textarea class="form-control" id="'.$id.'" name="setting['.$key.']" 
                                 rows="3">'.$value.'</textarea>';
            }
            
            if ($type === 'number') {
                return '<input type="number" class="form-control" id="'.$id.'" 
                               name="setting['.$key.']" value="'.$value.'">';
            }
            
            if ($key === 'email_sender' || $type === 'email') {
                return '<input type="email" class="form-control" id="'.$id.'" 
                              name="setting['.$key.']" value="'.$value.'">';
            }
            
            if ($key === 'color' || $type === 'color') {
                return '<input type="color" class="form-control form-control-color" id="'.$id.'" 
                              name="setting['.$key.']" value="'.$value.'">';
            }
            
            // Default to text input
            return '<input type="text" class="form-control" id="'.$id.'" 
                           name="setting['.$key.']" value="'.$value.'">';
        }
        
        /**
         * Save all settings from form submission
         */
        public function saveSettings($post_data, $files) {
            $this->conn->begin_transaction();
            
            try {
                // Update text settings
                foreach ($post_data['setting'] as $key => $value) {
                    $key = trim($key);
                    $value = is_array($value) ? implode(',', $value) : trim($value);
                    $this->updateSetting($key, $value);
                }
                
                // Handle file uploads
                if (!empty($files['logo']['name'])) {
                    $logo_info = $this->uploadFile($files['logo'], '../uploads/general/');
                    if ($logo_info[0]) {
                        $logo_path = 'uploads/general/' . $logo_info[1];
                        $this->updateSetting('logo', $logo_path);
                    }
                }
                
                if (!empty($files['favicon']['name'])) {
                    $favicon_info = $this->uploadFile($files['favicon'], '../uploads/general/');
                    if ($favicon_info[0]) {
                        $favicon_path = 'uploads/general/' . $favicon_info[1];
                        $this->updateSetting('favicon', $favicon_path);
                    }
                }
                
                $this->conn->commit();
                return true;
                
            } catch (Exception $e) {
                $this->conn->rollback();
                throw $e;
            }
        }
        
        /**
         * Generate human-friendly label from setting key
         */
        public function generateLabel($key) {
            $label = str_replace(['_', '-'], ' ', $key);
            return ucwords($label);
        }
    }
}

// Initialize settings manager
$settingsManager = new SettingsManager($conn);

// Define setting groups and their labels in display order
$group_labels = [
    'general' => ['title' => 'Thông tin chung', 'icon' => 'bi-gear'],
    'contact' => ['title' => 'Thông tin liên hệ', 'icon' => 'bi-person-lines-fill'],
    'social' => ['title' => 'Mạng xã hội', 'icon' => 'bi-share'],
    'payment' => ['title' => 'Thanh toán', 'icon' => 'bi-credit-card'],
    'shipping' => ['title' => 'Vận chuyển', 'icon' => 'bi-truck'],
    'email' => ['title' => 'Cấu hình email', 'icon' => 'bi-envelope'],
    'order' => ['title' => 'Đơn hàng', 'icon' => 'bi-basket']
];

// Get all settings
$all_settings = $settingsManager->loadAllSettings();
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
            <li class="breadcrumb-item active" aria-current="page">Cài đặt hệ thống</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="bi bi-sliders me-2"></i>Cài đặt hệ thống</h1>
        <div>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#settingsHelp">
                <i class="bi bi-question-circle me-1"></i> Trợ giúp
            </button>
        </div>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i><?php echo $_SESSION['success_message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i><?php echo $_SESSION['error_message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>
    
    <div class="card mb-4">
        <div class="card-body bg-light">
            <div class="d-flex align-items-center">
                <div class="flex-shrink-0 me-3">
                    <i class="bi bi-info-circle text-primary fs-3"></i>
                </div>
                <div>
                    <h5 class="card-title mb-1">Cấu hình hệ thống</h5>
                    <p class="card-text mb-0">Quản lý các thiết lập chung cho toàn bộ trang web</p>
                </div>
            </div>
        </div>
    </div>

    <form method="post" enctype="multipart/form-data" id="settingsForm" class="needs-validation" novalidate>
        <div class="row">
            <div class="col-md-3 mb-4">
                <div class="list-group sticky-top settings-nav" style="top: 6rem;">
                    <?php 
                    $first_group = true;
                    foreach ($group_labels as $group_key => $group_data): 
                        if (!isset($all_settings[$group_key])) continue;
                    ?>
                        <a class="list-group-item list-group-item-action d-flex align-items-center <?php echo $first_group ? 'active' : ''; ?>"
                           href="#group-<?php echo $group_key; ?>" data-group="<?php echo $group_key; ?>">
                            <i class="bi <?php echo $group_data['icon']; ?> me-2"></i>
                            <?php echo $group_data['title']; ?>
                        </a>
                    <?php 
                        $first_group = false;
                    endforeach; 
                    ?>
                </div>
                
                <div class="card mt-4">
                    <div class="card-body">
                        <h5 class="card-title">Hành động</h5>
                        <div class="d-grid gap-2">
                            <button type="submit" name="save_settings" class="btn btn-primary">
                                <i class="bi bi-save me-1"></i> Lưu thay đổi
                            </button>
                            <button type="reset" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-counterclockwise me-1"></i> Khôi phục
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-9">
                <?php 
                $first_group = true;
                foreach ($group_labels as $group_key => $group_data): 
                    if (!isset($all_settings[$group_key])) continue;
                ?>
                    <div class="card mb-4 shadow-sm settings-section" id="group-<?php echo $group_key; ?>" 
                         data-group="<?php echo $group_key; ?>">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0">
                                <i class="bi <?php echo $group_data['icon']; ?> me-2"></i>
                                <?php echo $group_data['title']; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php foreach ($all_settings[$group_key] as $setting_key => $setting): ?>
                                <div class="mb-4">
                                    <label for="setting-<?php echo htmlspecialchars($setting_key); ?>" class="form-label">
                                        <?php echo $settingsManager->generateLabel($setting_key); ?>
                                    </label>
                                    
                                    <?php echo $settingsManager->renderField($setting_key, $setting); ?>
                                    
                                    <?php if (!empty($setting['description'])): ?>
                                        <div class="form-text"><?php echo htmlspecialchars($setting['description']); ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php 
                    $first_group = false;
                endforeach; 
                ?>

                <div class="d-grid gap-2 d-md-flex justify-content-md-end mb-4">
                    <button type="reset" class="btn btn-outline-secondary me-2">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Khôi phục
                    </button>
                    <button type="submit" name="save_settings" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i> Lưu thay đổi
                    </button>
                </div>
            </div>
        </div>
    </form>
</main>

<!-- Help Modal -->
<div class="modal fade" id="settingsHelp" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Trợ giúp cài đặt</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <h6>Hướng dẫn sử dụng</h6>
                <p>Trang cài đặt cho phép bạn quản lý các thông số quan trọng của hệ thống. Các cài đặt được chia thành nhiều nhóm để dễ quản lý.</p>
                
                <div class="accordion" id="helpAccordion">
                    <?php foreach ($group_labels as $group_key => $group_data): ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#help-<?php echo $group_key; ?>">
                                <i class="bi <?php echo $group_data['icon']; ?> me-2"></i> <?php echo $group_data['title']; ?>
                            </button>
                        </h2>
                        <div id="help-<?php echo $group_key; ?>" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                            <div class="accordion-body">
                                <p>Giải thích về các thiết lập trong nhóm <?php echo $group_data['title']; ?>.</p>
                                <?php if ($group_key === 'general'): ?>
                                    <ul>
                                        <li><strong>Site Name</strong> - Tên hiển thị của website</li>
                                        <li><strong>Site Description</strong> - Mô tả ngắn về website</li>
                                        <li><strong>Logo</strong> - Logo hiển thị trên trang web</li>
                                        <li><strong>Favicon</strong> - Biểu tượng hiển thị trên trình duyệt</li>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Navigation functionality
    const navItems = document.querySelectorAll('.settings-nav .list-group-item');
    const sections = document.querySelectorAll('.settings-section');
    
    // Smooth scroll to settings groups with active state management
    navItems.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Update active state
            navItems.forEach(item => item.classList.remove('active'));
            this.classList.add('active');
            
            // Smooth scroll
            const targetId = this.getAttribute('href');
            const targetElement = document.querySelector(targetId);
            
            window.scrollTo({
                top: targetElement.offsetTop - 100,
                behavior: 'smooth'
            });
        });
    });

    // Track scroll position to update active navigation item
    window.addEventListener('scroll', function() {
        const scrollPosition = window.scrollY + 120;
        
        // Find the current section in view
        let currentSection = null;
        sections.forEach(section => {
            if (
                section.offsetTop <= scrollPosition &&
                section.offsetTop + section.offsetHeight > scrollPosition
            ) {
                currentSection = section;
            }
        });
        
        if (currentSection) {
            const groupId = currentSection.getAttribute('data-group');
            
            // Update active state in navigation
            navItems.forEach(item => {
                item.classList.remove('active');
                if (item.getAttribute('data-group') === groupId) {
                    item.classList.add('active');
                }
            });
        }
    });
    
    // Form validation
    const form = document.getElementById('settingsForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            if (!this.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            this.classList.add('was-validated');
        });
    }
    
    // Toggle password visibility
    document.querySelectorAll('.toggle-password').forEach(button => {
        button.addEventListener('click', function() {
            const target = document.querySelector(this.dataset.target);
            const icon = this.querySelector('i');
            
            if (target.type === 'password') {
                target.type = 'text';
                icon.classList.replace('bi-eye', 'bi-eye-slash');
            } else {
                target.type = 'password';
                icon.classList.replace('bi-eye-slash', 'bi-eye');
            }
        });
    });
    
    // Preview image uploads
    const imageInputs = document.querySelectorAll('input[type="file"]');
    imageInputs.forEach(input => {
        input.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                const preview = this.closest('.mb-4').querySelector('img');
                
                reader.onload = function(e) {
                    if (preview) {
                        preview.src = e.target.result;
                        preview.classList.add('preview-highlight');
                        setTimeout(() => {
                            preview.classList.remove('preview-highlight');
                        }, 1000);
                    }
                };
                
                reader.readAsDataURL(this.files[0]);
            }
        });
    });
    
    // Auto-save warning
    let formChanged = false;
    document.querySelectorAll('#settingsForm input, #settingsForm select, #settingsForm textarea').forEach(input => {
        input.addEventListener('change', function() {
            formChanged = true;
        });
    });
    
    // Warn user before leaving page with unsaved changes
    window.addEventListener('beforeunload', function(e) {
        if (formChanged) {
            const message = 'Bạn có thay đổi chưa lưu. Bạn có chắc chắn muốn rời khỏi trang này?';
            e.returnValue = message;
            return message;
        }
    });
});
</script>

<style>
/* Additional custom styles */
.preview-highlight {
    animation: highlight-pulse 1s ease;
    box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.5);
}

@keyframes highlight-pulse {
    0% { box-shadow: 0 0 0 0 rgba(13, 110, 253, 0.7); }
    70% { box-shadow: 0 0 0 5px rgba(13, 110, 253, 0); }
    100% { box-shadow: 0 0 0 0 rgba(13, 110, 253, 0); }
}

/* Improve focus styles */
.form-control:focus, .form-check-input:focus {
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
}

/* Sticky navigation enhancements */
.settings-nav {
    transition: top 0.2s ease;
}

.settings-nav .list-group-item.active {
    background-color: #0d6efd;
    border-color: #0d6efd;
    color: white;
}

/* Add hover effect */
.settings-nav .list-group-item:not(.active):hover {
    background-color: #f8f9fa;
}
</style>

<?php include('includes/footer.php'); ?>
