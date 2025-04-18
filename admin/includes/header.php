<?php
// Start output buffering if not already started
if (ob_get_level() == 0) {
    ob_start();
}

// Check if session is already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Debug admin session data
error_log("ADMIN SESSION: " . json_encode($_SESSION));

// Kiểm tra đăng nhập
if(!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // Lưu URL hiện tại để redirect sau khi đăng nhập
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: login.php?expired=1');
    exit;
}

// Xác định tên trang hiện tại từ URL
$current_page = basename($_SERVER['PHP_SELF']);

// Lấy kết nối database
include_once(__DIR__ . '/../../config/config.php');
// Include functions file
include_once(__DIR__ . '/functions.php');

// Lấy thông tin admin
$admin_id = $_SESSION['admin_id'];

// Make sure admin_level is set in the session
if (!isset($_SESSION['admin_level'])) {
    // Retrieve admin level from database if not in session
    $admin_level_query = $conn->prepare("
        SELECT loai_user FROM users 
        WHERE id = ? AND (loai_user = 1 OR loai_user = 2)
    ");
    $admin_level_query->bind_param("i", $admin_id);
    $admin_level_query->execute();
    $admin_level_result = $admin_level_query->get_result();
    
    if ($admin_level_result->num_rows === 1) {
        $admin_data = $admin_level_result->fetch_assoc();
        $_SESSION['admin_level'] = $admin_data['loai_user'];
    } else {
        // If can't determine admin level, log out
        session_unset();
        session_destroy();
        header('Location: login.php?error=unauthorized');
        exit;
    }
}

// Set admin level from session
$admin_level = $_SESSION['admin_level'];

// Debug admin level
error_log("Admin Level: $admin_level for user ID: $admin_id");

// Sửa lại truy vấn để sử dụng bảng users thay vì admin
$admin_query = $conn->prepare("
    SELECT * FROM users 
    WHERE id = ? AND (loai_user = 1 OR loai_user = 2)
");
$admin_query->bind_param("i", $admin_id);
$admin_query->execute();
$admin_result = $admin_query->get_result();

if ($admin_result->num_rows === 0) {
    // Nếu không tìm thấy admin trong DB, hủy session và chuyển hướng về trang đăng nhập
    session_unset();
    session_destroy();
    header('Location: login.php?expired=1');
    exit;
}

$admin = $admin_result->fetch_assoc();

// Nhận thông tin từ session hoặc từ DB
$admin_name = $_SESSION['admin_name'] ?? $admin['ten'];
$admin_username = $_SESSION['admin_username'] ?? $admin['taikhoan'];

// Kiểm tra xem có thông báo lỗi hoặc thành công không 
$error_message = '';
$success_message = '';

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Make sure to end output buffering and send content after all headers are set
// Only include this if you're confident no more redirects will be needed
// ob_end_flush(); 
// DO NOT add any whitespace after the PHP closing tag
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Trang quản trị'; ?> - Bug Shop Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Load jQuery first, then Bootstrap Bundle -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Fix for Bootstrap dropdowns -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize all dropdowns
        var dropdownElementList = document.querySelectorAll('.dropdown-toggle')
        dropdownElementList.forEach(function(dropdownToggleEl) {
            new bootstrap.Dropdown(dropdownToggleEl);
        });
        
        // Add click handlers to ensure dropdowns work
        document.querySelectorAll('.dropdown-toggle').forEach(item => {
            item.addEventListener('click', function(e) {
                e.stopPropagation();
                bootstrap.Dropdown.getOrCreateInstance(this).toggle();
            });
        });
    });
    </script>
    
    <style>
        /* Existing CSS styles */
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
        
        /* Fix for dropdowns */
        .dropdown-menu {
            margin-top: 0.125rem !important;
            z-index: 1050;
        }
        
        /* Ensure dropdown toggles are clickable */
        .dropdown-toggle {
            cursor: pointer;
        }
    </style>
</head>
<body>

<header class="navbar navbar-dark sticky-top bg-dark flex-md-nowrap p-0 shadow">
    <a class="navbar-brand col-md-3 col-lg-2 me-0 px-3 fs-6" href="index.php">
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
    
    <!-- User dropdown -->
    <div class="dropdown text-end pe-4">
        <a href="#" class="d-block link-light text-decoration-none dropdown-toggle" 
           id="dropdownUser" data-bs-toggle="dropdown" aria-expanded="false">
            <span class="d-none d-md-inline me-2"><?php echo htmlspecialchars($admin_name); ?></span>
            <i class="bi bi-person-circle"></i>
        </a>
        <ul class="dropdown-menu dropdown-menu-end text-small shadow" aria-labelledby="dropdownUser">
            <li><a class="dropdown-item" href="profile.php">Hồ sơ</a></li>
            <li><a class="dropdown-item" href="settings.php">Cài đặt</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="logout.php">Đăng xuất</a></li>
        </ul>
    </div>
</header>

<div class="container-fluid">
    <div class="row">
        <!-- Include sidebar -->
        <?php include('sidebar.php'); ?>
        
        <!-- Thông báo lỗi và thành công -->
        <?php if(!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show mx-3 mt-3" role="alert">
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if(!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show mx-3 mt-3" role="alert">
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
