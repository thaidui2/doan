<?php
session_start();
require_once('../config/config.php');

// Kiểm tra đăng nhập và phân quyền
if (!isset($_SESSION['user']) || $_SESSION['user']['logged_in'] !== true) {
    // Chưa đăng nhập, chuyển đến trang đăng nhập
    header("Location: ../dangnhap.php?redirect=seller/trang-chu.php");
    exit();
}

// Kiểm tra quyền seller
$user_id = $_SESSION['user']['id'];
$check_seller = $conn->prepare("SELECT loai_user FROM users WHERE id_user = ? AND trang_thai = 1");
$check_seller->bind_param("i", $user_id);
$check_seller->execute();
$result = $check_seller->get_result();

if ($result->num_rows == 0 || $result->fetch_assoc()['loai_user'] != 1) {
    // Không phải seller hoặc tài khoản bị khóa
    $_SESSION['error_message'] = "Bạn không có quyền truy cập trang người bán!";
    header("Location: ../index.php");
    exit();
}

// Lấy thông tin seller
$seller_info = $conn->prepare("SELECT * FROM users WHERE id_user = ?");
$seller_info->bind_param("i", $user_id);
$seller_info->execute();
$seller = $seller_info->get_result()->fetch_assoc();

// Lấy trang hiện tại để đánh dấu menu active
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Kênh Người Bán</title>
    <link rel="stylesheet" href="../node_modules/bootstrap/dist/css/bootstrap.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        
        .seller-sidebar {
            background-color: #343a40;
            min-height: 100vh;
            color: #fff;
        }
        
        .seller-sidebar .nav-link {
            color: rgba(255, 255, 255, 0.75);
            padding: 0.75rem 1.25rem;
            border-radius: 0;
        }
        
        .seller-sidebar .nav-link:hover {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .seller-sidebar .nav-link.active {
            color: #fff;
            background-color: #007bff;
        }
        
        .seller-sidebar .nav-link i {
            margin-right: 0.5rem;
            width: 20px;
            text-align: center;
        }
        
        .content-wrapper {
            padding: 20px;
        }
        
        .navbar-seller {
            background-color: #fff;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .text-muted{
            color:#fff !important;
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light navbar-seller">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="trang-chu.php">
                <i class="bi bi-shop me-2 text-primary" style="font-size: 1.5rem;"></i>
                <span class="fw-bold">Kênh Người Bán</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSeller">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSeller">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php" target="_blank">
                            <i class="bi bi-house-door"></i> Xem Cửa Hàng
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="thong-bao.php">
                            <i class="bi bi-bell"></i>
                            <span class="position-relative">
                                Thông báo
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                    2 <!-- Số thông báo -->
                                    <span class="visually-hidden">thông báo chưa đọc</span>
                                </span>
                            </span>
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i> <?php echo htmlspecialchars($seller['tenuser']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="ho-so.php"><i class="bi bi-person me-2"></i>Hồ sơ</a></li>
                            <li><a class="dropdown-item" href="cai-dat.php"><i class="bi bi-gear me-2"></i>Cài đặt</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i>Đăng xuất</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block seller-sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4 py-3">
                        <?php if (!empty($seller['anh_dai_dien']) && file_exists('../uploads/users/' . $seller['anh_dai_dien'])): ?>
                            <img src="../uploads/users/<?php echo $seller['anh_dai_dien']; ?>" class="rounded-circle mb-2" width="60" height="60" alt="Profile">
                        <?php else: ?>
                            <div class="bg-secondary rounded-circle mb-2 d-flex align-items-center justify-content-center mx-auto" style="width: 60px; height: 60px;">
                                <i class="bi bi-person text-white" style="font-size: 1.5rem;"></i>
                            </div>
                        <?php endif; ?>
                        <h6 class="mb-0"><?php echo htmlspecialchars($seller['ten_shop'] ?? $seller['tenuser']); ?></h6>
                        <small class="text-muted"><?php echo !empty($seller['ngay_tro_thanh_nguoi_ban']) ? 'Bán hàng từ ' . date('d/m/Y', strtotime($seller['ngay_tro_thanh_nguoi_ban'])) : 'Người bán mới'; ?></small>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page == 'trang-chu.php' ? 'active' : ''; ?>" href="trang-chu.php">
                                <i class="bi bi-speedometer2"></i> Tổng quan
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page == 'don-hang.php' ? 'active' : ''; ?>" href="don-hang.php">
                                <i class="bi bi-receipt"></i> Đơn hàng
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo in_array($current_page, ['danh-sach-san-pham.php', 'them-san-pham.php', 'chinh-sua-san-pham.php']) ? 'active' : ''; ?>" href="danh-sach-san-pham.php">
                                <i class="bi bi-box-seam"></i> Sản phẩm
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page == 'doanh-thu.php' ? 'active' : ''; ?>" href="doanh-thu.php">
                                <i class="bi bi-graph-up"></i> Doanh thu
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page == 'danh-gia.php' ? 'active' : ''; ?>" href="danh-gia.php">
                                <i class="bi bi-star"></i> Đánh giá
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page == 'khuyen-mai.php' ? 'active' : ''; ?>" href="khuyen-mai.php">
                                <i class="bi bi-ticket-perforated"></i> Khuyến mãi
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $current_page == 'ho-so.php' ? 'active' : ''; ?>" href="ho-so.php">
                                <i class="bi bi-person-badge"></i> Hồ sơ shop
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 content-wrapper">
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i> <?php echo $_SESSION['success_message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $_SESSION['error_message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>
