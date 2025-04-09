<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include configuration file
include_once('../config/config.php');

// Include helper functions first
include_once('includes/functions.php');       // Đầu tiên
include_once('includes/admin_helpers.php');   // Thứ hai
include_once('includes/permissions.php');  

// Kiểm tra đăng nhập
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// Get current page for highlighting in sidebar
$current_page = basename($_SERVER['PHP_SELF']);

// Thiết lập cấp bậc admin để phân quyền nếu chưa có
if (!isset($_SESSION['admin_level']) && isset($_SESSION['admin_id'])) {
    $admin_id = $_SESSION['admin_id'];
    $query = "SELECT cap_bac FROM admin WHERE id_admin = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $admin = $result->fetch_assoc();
        $_SESSION['admin_level'] = $admin['cap_bac'];
    }
}

// Lấy thông tin admin đang đăng nhập
$admin_info = [];
if (isset($_SESSION['admin_id'])) {
    $query = "SELECT * FROM admin WHERE id_admin = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $_SESSION['admin_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $admin_info = $result->fetch_assoc();
    }
}

// Lấy số thông báo chưa đọc
$unread_notifications = 0;
$latest_notifications = [];
if (isset($_SESSION['admin_id'])) {
    // Kiểm tra bảng thông báo tồn tại không
    $table_check = $conn->query("SHOW TABLES LIKE 'admin_notifications'");
    if ($table_check->num_rows > 0) {
        $query = "SELECT COUNT(*) as count FROM admin_notifications WHERE id_admin = ? AND read_status = 0";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $_SESSION['admin_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $unread_notifications = $result->fetch_assoc()['count'];
        }
        
        // Lấy 5 thông báo mới nhất
        $query = "SELECT * FROM admin_notifications WHERE id_admin = ? ORDER BY created_at DESC LIMIT 5";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $_SESSION['admin_id']);
        $stmt->execute();
        $latest_notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Admin Dashboard'; ?> - Bug Shop</title>
    <link rel="stylesheet" href="../node_modules/bootstrap/dist/css/bootstrap.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/admin.css">
    <style>
        :root {
            --bs-primary: #007bff;
            --header-bg: #1e2a38;
            --header-border: rgba(255,255,255,0.1);
            --sidebar-bg: #f8f9fa;
            --sidebar-active: #e9ecef;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }
        
        .brand-header {
            background-color: var(--header-bg);
            border-bottom: 1px solid var(--header-border);
        }
        
        .brand-logo {
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        
        .navbar-dark .navbar-nav .nav-link {
            color: rgba(255,255,255,.85);
            padding: 0.65rem 0.85rem;
            border-radius: 0.25rem;
            transition: all 0.2s ease;
        }
        
        .navbar-dark .navbar-nav .nav-link:hover {
            color: rgba(255,255,255,1);
            background: rgba(255,255,255,0.1);
        }
        
        .search-input {
            border: none;
            background: rgba(255,255,255,0.1);
            color: white;
            transition: all 0.2s ease;
            border-radius: 6px!important;
        }
        
        .search-input:focus {
            background: rgba(255,255,255,0.15);
            box-shadow: 0 0 0 0.25rem rgba(255,255,255,0.1);
            color: white;
        }
        
        .search-input::placeholder {
            color: rgba(255,255,255,0.5);
        }
        
        .notification-indicator {
            top: 3px!important;
            right: 3px!important;
            transform: none!important;
        }
        
        .nav-item.dropdown {
            position: relative;
        }
        
        .profile-dropdown {
            min-width: 260px;
        }
        
        .dropdown-menu-dark {
            background-color: var(--header-bg);
            border: 1px solid var(--header-border);
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }
        
        .dropdown-item {
            padding: 0.6rem 1rem;
            transition: all 0.2s;
        }
        
        .dropdown-item:hover {
            background: rgba(255,255,255,0.1);
        }
        
        .profile-header {
            padding: 1rem;
            display: flex;
            align-items: center;
            border-bottom: 1px solid var(--header-border);
        }
        
        .profile-avatar {
            width: 42px;
            height: 42px;
            object-fit: cover;
            border-radius: 50%;
            background: rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.75rem;
        }
    </style>
    <?php if (isset($page_specific_css)): ?>
    <?php echo $page_specific_css; ?>
    <?php endif; ?>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Top Navigation Bar -->
            <header class="navbar navbar-dark sticky-top brand-header shadow-sm p-0">
                <div class="container-fluid px-0">
                    <a class="navbar-brand col-md-3 col-lg-2 py-3 px-4 brand-logo m-0 d-flex align-items-center" href="index.php">
                        <i class="bi bi-bug-fill fs-4 me-2"></i>
                        <span>Bug Shop Admin</span>
                    </a>
                    
                    <button class="navbar-toggler m-2 border-0" type="button" 
                            data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" 
                            aria-expanded="false" aria-label="Toggle navigation">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                    
                    <!-- Search Form -->
                    <div class="flex-grow-1 d-none d-md-flex px-4">
                        <form class="w-100 me-3 d-flex" action="search.php" method="get">
                            <div class="input-group">
                                <span class="input-group-text bg-transparent border-0 text-white">
                                    <i class="bi bi-search opacity-75"></i>
                                </span>
                                <input type="text" class="form-control search-input" name="q" 
                                    placeholder="Tìm kiếm sản phẩm, đơn hàng, khách hàng..." aria-label="Search">
                            </div>
                        </form>
                    </div>
                    
                    <!-- Admin Controls -->
                    <ul class="navbar-nav flex-row align-items-center ms-auto me-3">
                        <!-- Notifications Dropdown -->
                        <li class="nav-item dropdown mx-1">
                            <a class="nav-link position-relative px-3 py-2" href="#" id="notificationsDropdown" 
                               role="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                                <i class="bi bi-bell fs-5"></i>
                                <?php if ($unread_notifications > 0): ?>
                                <span class="position-absolute notification-indicator badge rounded-pill bg-danger text-white" style="font-size: 0.65rem;">
                                    <?php echo $unread_notifications <= 99 ? $unread_notifications : '99+'; ?>
                                </span>
                                <?php endif; ?>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end dropdown-menu-dark p-0 border-0 shadow-lg" 
                                 aria-labelledby="notificationsDropdown" style="width: 320px; max-height: 480px; overflow-y: auto;">
                                <div class="p-3 border-bottom border-secondary d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0">Thông báo</h6>
                                    <div>
                                        <button class="btn btn-sm btn-outline-light opacity-75" title="Đánh dấu tất cả đã đọc">
                                            <i class="bi bi-check2-all"></i>
                                        </button>
                                        <a href="notifications.php" class="btn btn-sm btn-outline-primary ms-1">Xem tất cả</a>
                                    </div>
                                </div>
                                
                                <div>
                                    <?php if (!empty($latest_notifications)): ?>
                                        <?php foreach($latest_notifications as $notification): ?>
                                        <a href="<?php echo $notification['link'] ?? 'notifications.php'; ?>" 
                                           class="dropdown-item d-flex p-3 border-bottom border-secondary <?php echo ($notification['read_status'] == 0) ? 'bg-dark' : ''; ?>">
                                            <div class="me-3 align-self-center">
                                                <div class="notification-icon bg-<?php echo $notification['type'] ?? 'secondary'; ?> bg-opacity-25 rounded-circle p-2 text-<?php echo $notification['type'] ?? 'secondary'; ?>">
                                                    <i class="bi <?php echo $notification['icon'] ?? 'bi-bell'; ?>"></i>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="text-wrap mb-1"><?php echo $notification['message']; ?></div>
                                                <div class="small text-muted">
                                                    <i class="bi bi-clock me-1"></i>
                                                    <?php echo date('d/m/Y H:i', strtotime($notification['created_at'])); ?>
                                                </div>
                                            </div>
                                        </a>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                    <div class="p-4 text-center text-muted">
                                        <div class="mb-2">
                                            <i class="bi bi-inbox display-6 opacity-50"></i>
                                        </div>
                                        <p class="mb-0">Không có thông báo mới</p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </li>
                        
                        <!-- Visit Site Link -->
                        <li class="nav-item mx-1">
                            <a class="nav-link px-3 py-2" href="../index.php" target="_blank" title="Xem trang chủ">
                                <i class="bi bi-box-arrow-up-right fs-5"></i>
                            </a>
                        </li>
                        
                        <!-- Admin Profile Dropdown -->
                        <li class="nav-item dropdown mx-1">
                            <a class="nav-link dropdown-toggle px-3 py-2 d-flex align-items-center" href="#" id="userDropdown" 
                               role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <?php if (!empty($admin_info['anh_dai_dien']) && file_exists('../uploads/admin/' . $admin_info['anh_dai_dien'])): ?>
                                    <img src="../uploads/admin/<?php echo $admin_info['anh_dai_dien']; ?>" alt="Profile" 
                                        class="rounded-circle me-2" width="28" height="28" style="object-fit: cover;">
                                <?php else: ?>
                                    <div class="d-flex align-items-center justify-content-center rounded-circle bg-white bg-opacity-25 me-2" style="width: 28px; height: 28px;">
                                        <i class="bi bi-person-fill text-white" style="font-size: 16px;"></i>
                                    </div>
                                <?php endif; ?>
                                <span class="d-none d-sm-inline"><?php echo htmlspecialchars($admin_info['ten_admin'] ?? $_SESSION['admin_username']); ?></span>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end dropdown-menu-dark profile-dropdown shadow-lg border-0" aria-labelledby="userDropdown">
                                <div class="profile-header">
                                    <div class="profile-avatar">
                                        <?php if (!empty($admin_info['anh_dai_dien']) && file_exists('../uploads/admin/' . $admin_info['anh_dai_dien'])): ?>
                                            <img src="../uploads/admin/<?php echo $admin_info['anh_dai_dien']; ?>" alt="Profile" 
                                                class="w-100 h-100 rounded-circle">
                                        <?php else: ?>
                                            <i class="bi bi-person-fill text-white fs-4"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($admin_info['ten_admin'] ?? $_SESSION['admin_username']); ?></div>
                                        <div class="text-muted small">
                                            <?php if ($admin_info['cap_bac'] >= 2): ?>
                                                <span class="badge bg-danger">Super Admin</span>
                                            <?php elseif ($admin_info['cap_bac'] == 1): ?>
                                                <span class="badge bg-primary">Admin</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Nhân viên</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="p-1">
                                    <a class="dropdown-item d-flex align-items-center" href="profile.php">
                                        <i class="bi bi-person me-2"></i> Hồ sơ cá nhân
                                    </a>
                                    <a class="dropdown-item d-flex align-items-center" href="settings.php">
                                        <i class="bi bi-gear me-2"></i> Cài đặt hệ thống
                                    </a>
                                    <?php if ($_SESSION['admin_level'] >= 2): ?>
                                    <a class="dropdown-item d-flex align-items-center" href="activity_log.php">
                                        <i class="bi bi-journal-text me-2"></i> Nhật ký hoạt động
                                    </a>
                                    <?php endif; ?>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item d-flex align-items-center text-danger" href="logout.php">
                                        <i class="bi bi-box-arrow-right me-2"></i> Đăng xuất
                                    </a>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
            </header>
            
            <!-- Toast container -->
            <div class="toast-container position-fixed bottom-0 end-0 p-3"></div>
