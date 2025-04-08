<?php
session_start();
include('config/config.php');

// Kiểm tra đăng nhập
if (!isset($_SESSION['user']) || $_SESSION['user']['logged_in'] !== true) {
    header('Location: dangnhap.php?redirect=tro-thanh-nguoi-ban.php');
    exit();
}

// Lấy thông tin người dùng hiện tại
$user_id = $_SESSION['user']['id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id_user = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Kiểm tra nếu đã là người bán
if ($user['loai_user'] == 1) {
    header('Location: seller/trang-chu.php');
    exit();
}

$success_message = $error_message = '';

// Xử lý form đăng ký người bán
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lấy dữ liệu từ form
    $ten_shop = trim($_POST['ten_shop']);
    $mo_ta_shop = trim($_POST['mo_ta_shop']);
    $dia_chi_shop = trim($_POST['dia_chi_shop']);
    $so_dien_thoai_shop = trim($_POST['so_dien_thoai_shop']);
    $email_shop = trim($_POST['email_shop']);
    
    // Validate dữ liệu đầu vào
    $errors = [];
    
    if (empty($ten_shop)) {
        $errors[] = "Tên shop không được để trống";
    }
    
    if (empty($dia_chi_shop)) {
        $errors[] = "Địa chỉ shop không được để trống";
    }
    
    if (empty($so_dien_thoai_shop)) {
        $errors[] = "Số điện thoại shop không được để trống";
    } elseif (!preg_match('/^[0-9]{10}$/', $so_dien_thoai_shop)) {
        $errors[] = "Số điện thoại shop phải gồm 10 chữ số";
    }
    
    if (!empty($email_shop) && !filter_var($email_shop, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Email shop không hợp lệ";
    }
    
    // Xử lý upload logo shop
    $logo_shop = null;
    if (isset($_FILES['logo_shop']) && $_FILES['logo_shop']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024; // 2MB
        
        if (!in_array($_FILES['logo_shop']['type'], $allowed_types)) {
            $errors[] = "Logo shop phải là file hình ảnh (JPEG, PNG, GIF)";
        } elseif ($_FILES['logo_shop']['size'] > $max_size) {
            $errors[] = "Logo shop không được vượt quá 2MB";
        } else {
            // Tạo thư mục lưu trữ nếu chưa tồn tại
            $upload_dir = "uploads/shops/";
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Tạo tên file mới
            $file_extension = pathinfo($_FILES['logo_shop']['name'], PATHINFO_EXTENSION);
            $new_filename = 'shop_' . $user_id . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            // Upload file
            if (!move_uploaded_file($_FILES['logo_shop']['tmp_name'], $upload_path)) {
                $errors[] = "Có lỗi xảy ra khi tải lên logo shop";
            } else {
                $logo_shop = $new_filename;
            }
        }
    }
    
    // Nếu không có lỗi, cập nhật thành người bán
    if (empty($errors)) {
        // Bắt đầu transaction để đảm bảo tính toàn vẹn dữ liệu
        $conn->begin_transaction();
        
        try {
            // Cập nhật thông tin người dùng thành người bán
            $update_stmt = $conn->prepare("
                UPDATE users SET 
                    loai_user = 1,
                    ten_shop = ?,
                    mo_ta_shop = ?,
                    logo_shop = ?,
                    ngay_tro_thanh_nguoi_ban = NOW()
                WHERE id_user = ?
            ");
            
            $update_stmt->bind_param("sssi", 
                $ten_shop,
                $mo_ta_shop,
                $logo_shop,
                $user_id
            );
            
            if (!$update_stmt->execute()) {
                throw new Exception("Lỗi khi cập nhật thông tin: " . $conn->error);
            }
            
            // Commit transaction
            $conn->commit();
            
            // Cập nhật session
            $_SESSION['user']['is_seller'] = true;
            
            // Thông báo thành công và chuyển hướng
            $_SESSION['success_message'] = "Chúc mừng! Bạn đã trở thành người bán trên Bug Shop. Hãy bắt đầu đăng sản phẩm ngay!";
            header('Location: seller/trang-chu.php');
            exit();
            
        } catch (Exception $e) {
            // Rollback transaction nếu có lỗi
            $conn->rollback();
            
            // Xóa file ảnh đã upload nếu có
            if ($logo_shop && file_exists($upload_dir . $logo_shop)) {
                @unlink($upload_dir . $logo_shop);
            }
            
            $error_message = $e->getMessage();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

$page_title = "Trở thành người bán";
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Bug Shop</title>
    <link rel="stylesheet" href="node_modules/bootstrap/dist/css/bootstrap.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .seller-banner {
            background-color: #f8f9fa;
            padding: 3rem 0;
            margin-bottom: 2rem;
            border-radius: 0.5rem;
        }
        .benefit-card {
            height: 100%;
            transition: all 0.3s ease;
        }
        .benefit-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .benefit-icon {
            font-size: 2.5rem;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <?php include('includes/header.php'); ?>
    
    <div class="container py-5">
        <div class="seller-banner text-center">
            <h1 class="display-4 fw-bold mb-3">Trở thành người bán trên Bug Shop</h1>
            <p class="lead mb-4">Mở rộng kinh doanh của bạn và tiếp cận hàng ngàn khách hàng tiềm năng</p>
            <a href="#register-form" class="btn btn-primary btn-lg">Bắt đầu ngay</a>
        </div>
        
        <!-- Hiển thị thông báo lỗi hoặc thành công -->
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
        
        <!-- Lợi ích khi trở thành người bán -->
        <h2 class="text-center mb-4">Lợi ích khi trở thành người bán</h2>
        <div class="row g-4 mb-5">
            <div class="col-md-4">
                <div class="card benefit-card text-center p-4">
                    <div class="card-body">
                        <div class="benefit-icon text-primary">
                            <i class="bi bi-shop"></i>
                        </div>
                        <h3 class="card-title h4">Mở rộng kinh doanh</h3>
                        <p class="card-text">Tiếp cận hàng ngàn khách hàng tiềm năng trên nền tảng của chúng tôi.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card benefit-card text-center p-4">
                    <div class="card-body">
                        <div class="benefit-icon text-success">
                            <i class="bi bi-graph-up-arrow"></i>
                        </div>
                        <h3 class="card-title h4">Tăng doanh thu</h3>
                        <p class="card-text">Bán hàng 24/7 và tiếp cận khách hàng mọi lúc, mọi nơi.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card benefit-card text-center p-4">
                    <div class="card-body">
                        <div class="benefit-icon text-warning">
                            <i class="bi bi-tools"></i>
                        </div>
                        <h3 class="card-title h4">Công cụ quản lý</h3>
                        <p class="card-text">Hệ thống quản lý đơn hàng, kho hàng và thống kê doanh thu hiệu quả.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Form đăng ký -->
        <div class="card shadow-sm mb-5" id="register-form">
            <div class="card-header bg-white py-3">
                <h2 class="card-title h4 mb-0">Đăng ký trở thành người bán</h2>
            </div>
            <div class="card-body">
                <form method="post" action="" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-4 text-center mb-4">
                            <div class="mb-3">
                                <div class="shop-logo-wrapper mb-3">
                                    <div class="bg-light d-flex align-items-center justify-content-center mx-auto" style="width: 150px; height: 150px; border-radius: 5px;" id="logo-placeholder">
                                        <i class="bi bi-shop text-muted" style="font-size: 3rem;"></i>
                                    </div>
                                    <img src="" class="img-thumbnail d-none" style="width: 150px; height: 150px; object-fit: cover;" id="logo-preview">
                                </div>
                                
                                <div>
                                    <label for="logo_shop" class="form-label">Logo Shop</label>
                                    <input class="form-control" type="file" id="logo_shop" name="logo_shop" accept="image/*">
                                    <div class="form-text">Chọn hình ảnh có tỷ lệ vuông, kích thước khuyến nghị 500x500px</div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="ten_shop" class="form-label">Tên Shop <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="ten_shop" name="ten_shop" value="<?php echo isset($_POST['ten_shop']) ? htmlspecialchars($_POST['ten_shop']) : ''; ?>" required>
                            </div>
                        </div>
                        
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="mo_ta_shop" class="form-label">Mô tả Shop</label>
                                <textarea class="form-control" id="mo_ta_shop" name="mo_ta_shop" rows="4"><?php echo isset($_POST['mo_ta_shop']) ? htmlspecialchars($_POST['mo_ta_shop']) : ''; ?></textarea>
                                <div class="form-text">Mô tả ngắn gọn về shop của bạn, sản phẩm bạn bán và những điều khác...</div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="dia_chi_shop" class="form-label">Địa chỉ Shop <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="dia_chi_shop" name="dia_chi_shop" value="<?php echo isset($_POST['dia_chi_shop']) ? htmlspecialchars($_POST['dia_chi_shop']) : htmlspecialchars($user['diachi']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="so_dien_thoai_shop" class="form-label">Số điện thoại Shop <span class="text-danger">*</span></label>
                                        <input type="tel" class="form-control" id="so_dien_thoai_shop" name="so_dien_thoai_shop" value="<?php echo isset($_POST['so_dien_thoai_shop']) ? htmlspecialchars($_POST['so_dien_thoai_shop']) : htmlspecialchars($user['sdt']); ?>" pattern="[0-9]{10}" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email_shop" class="form-label">Email Shop</label>
                                        <input type="email" class="form-control" id="email_shop" name="email_shop" value="<?php echo isset($_POST['email_shop']) ? htmlspecialchars($_POST['email_shop']) : htmlspecialchars($user['email']); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle-fill me-2"></i> Sau khi đăng ký thành công, bạn sẽ được chuyển đến trang quản lý dành cho người bán.
                            </div>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="agree_terms" required>
                                <label class="form-check-label" for="agree_terms">
                                    Tôi đồng ý với <a href="#">điều khoản dành cho người bán</a> và <a href="#">chính sách hoạt động</a> của Bug Shop
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-center mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-shop-window me-2"></i> Đăng ký trở thành người bán
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php include('includes/footer.php'); ?>
    <script src="node_modules/bootstrap/dist/js/bootstrap.bundle.js"></script>
    
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        // Preview logo image before upload
        const logoInput = document.getElementById("logo_shop");
        const logoPreview = document.getElementById("logo-preview");
        const logoPlaceholder = document.getElementById("logo-placeholder");
        
        if (logoInput) {
            logoInput.addEventListener("change", function() {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        if (logoPreview) {
                            logoPreview.src = e.target.result;
                            logoPreview.classList.remove("d-none");
                        }
                        
                        if (logoPlaceholder) {
                            logoPlaceholder.classList.add("d-none");
                        }
                    }
                    
                    reader.readAsDataURL(this.files[0]);
                }
            });
        }
    });
    </script>
</body>
</html>
