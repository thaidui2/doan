<?php
session_start();

// Kiểm tra đăng nhập
if(!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hướng dẫn quản lý ảnh màu sắc - Bug Shop Admin</title>
    <link rel="stylesheet" href="../node_modules/bootstrap/dist/css/bootstrap.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            font-size: 0.875rem;
        }
        
        .sidebar {
            min-height: 100vh;
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
            background-color: #212529;
        }
        
        .sidebar .nav-link {
            color: #adb5bd;
            font-weight: 500;
            padding: 0.75rem 1rem;
        }
        
        .sidebar .nav-link:hover {
            color: #fff;
        }
        
        .sidebar .nav-link.active {
            color: #fff;
        }
        
        .sidebar .nav-link i {
            margin-right: 10px;
        }
        
        main {
            padding-top: 20px;
        }
        
        .guide-img {
            max-width: 100%;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        
        .step {
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav id="sidebar" class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <img src="../images/logo.png" alt="Bug Shop Logo" height="40">
                        <h5 class="text-white mt-2">Admin Panel</h5>
                    </div>
                    <hr class="bg-light">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="index.php">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="products.php">
                                <i class="bi bi-box"></i> Sản phẩm
                            </a>
                        </li>
                        <!-- ... other nav links ... -->
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-2 mb-3 border-bottom">
                    <h1 class="h2">Hướng dẫn quản lý ảnh màu sắc</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="products.php" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Quay lại danh sách sản phẩm
                        </a>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h4>Tính năng ảnh theo màu sắc</h4>
                        <p>Tính năng này cho phép bạn tải lên hình ảnh riêng cho từng màu sắc của sản phẩm, giúp khách hàng thấy được sản phẩm thực tế trong từng màu khi họ chọn.</p>
                        
                        <div class="step">
                            <h5>Bước 1: Thêm màu sắc cho sản phẩm</h5>
                            <p>Trước tiên, bạn cần chọn các màu sắc có sẵn cho sản phẩm trong phần "Màu sắc sản phẩm"</p>
                            <img src="../images/admin-guide/color_selection.jpg" alt="Chọn màu sắc" class="guide-img">
                        </div>
                        
                        <div class="step">
                            <h5>Bước 2: Tải lên hình ảnh cho từng màu</h5>
                            <p>Sau khi chọn màu sắc, bạn sẽ thấy phần "Hình ảnh cho từng màu sắc" hiển thị các màu đã chọn. Tại đây, bạn có thể tải lên hình ảnh riêng cho từng màu.</p>
                            <img src="../images/admin-guide/color_image_upload.jpg" alt="Tải hình ảnh màu" class="guide-img">
                        </div>
                        
                        <div class="step">
                            <h5>Bước 3: Lưu sản phẩm</h5>
                            <p>Nhấn "Lưu thay đổi" để lưu lại tất cả thông tin sản phẩm, bao gồm các ảnh màu đã tải lên.</p>
                        </div>
                        
                        <div class="step">
                            <h5>Cách hiển thị trên trang người dùng</h5>
                            <p>Trên trang chi tiết sản phẩm, khách hàng sẽ thấy các hình ảnh theo từng màu sắc. Khi họ chọn một màu, hình ảnh tương ứng sẽ được hiển thị.</p>
                            <img src="../images/admin-guide/color_display.jpg" alt="Hiển thị ảnh màu" class="guide-img">
                        </div>
                        
                        <div class="alert alert-info">
                            <h5><i class="bi bi-info-circle"></i> Lưu ý</h5>
                            <ul>
                                <li>Hình ảnh nên có kích thước đồng đều và chất lượng tốt</li>
                                <li>Nên chụp sản phẩm trong điều kiện ánh sáng tương tự nhau để đảm bảo màu sắc hiển thị chính xác</li>
                                <li>Bạn có thể cập nhật hình ảnh màu bất cứ lúc nào bằng cách tải lên hình ảnh mới</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="text-center">
                    <a href="check_structure.php" class="btn btn-primary">Kiểm tra cấu trúc database</a>
                </div>
            </main>
        </div>
    </div>
    
    <script src="../node_modules/bootstrap/dist/js/bootstrap.bundle.js"></script>
</body>
</html>
