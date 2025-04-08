<?php
// Thiết lập tiêu đề trang
$page_title = "Hồ Sơ Shop";

// Include header
include('includes/header.php');

// Xử lý cập nhật thông tin shop
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_shop'])) {
    $ten_shop = trim($_POST['ten_shop']);
    $mo_ta_shop = trim($_POST['mo_ta_shop']);
    $dia_chi_shop = trim($_POST['dia_chi_shop']);
    $so_dien_thoai_shop = trim($_POST['so_dien_thoai_shop']);
    $email_shop = trim($_POST['email_shop']);
    
    // Validate dữ liệu
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
    
    // Nếu không có lỗi, cập nhật thông tin
    if (empty($errors)) {
        // Xử lý upload logo shop nếu có
        $logo_shop = $seller['logo_shop']; // Giữ nguyên logo cũ nếu không upload mới
        
        if (isset($_FILES['logo_shop']) && $_FILES['logo_shop']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 2 * 1024 * 1024; // 2MB
            
            if (!in_array($_FILES['logo_shop']['type'], $allowed_types)) {
                $errors[] = "Logo shop phải là file hình ảnh (JPEG, PNG, GIF)";
            } elseif ($_FILES['logo_shop']['size'] > $max_size) {
                $errors[] = "Logo shop không được vượt quá 2MB";
            } else {
                // Tạo thư mục lưu trữ nếu chưa tồn tại
                $upload_dir = "../uploads/shops/";
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Tạo tên file mới
                $file_extension = pathinfo($_FILES['logo_shop']['name'], PATHINFO_EXTENSION);
                $new_filename = 'shop_' . $user_id . '_' . time() . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                // Upload file
                if (move_uploaded_file($_FILES['logo_shop']['tmp_name'], $upload_path)) {
                    // Xóa logo cũ nếu có
                    if (!empty($seller['logo_shop']) && file_exists("../uploads/shops/" . $seller['logo_shop'])) {
                        @unlink("../uploads/shops/" . $seller['logo_shop']);
                    }
                    
                    $logo_shop = $new_filename;
                } else {
                    $errors[] = "Có lỗi xảy ra khi tải lên logo shop";
                }
            }
        }
        
        // Nếu không có lỗi khi upload, cập nhật thông tin shop
        if (empty($errors)) {
            $update_shop = $conn->prepare("
                UPDATE users 
                SET ten_shop = ?, mo_ta_shop = ?, logo_shop = ?,
                    dia_chi_shop = ?, so_dien_thoai_shop = ?, email_shop = ?,
                    ngay_tro_thanh_nguoi_ban = COALESCE(ngay_tro_thanh_nguoi_ban, NOW())
                WHERE id_user = ?
            ");
            
            $update_shop->bind_param("ssssssi", 
                $ten_shop, $mo_ta_shop, $logo_shop,
                $dia_chi_shop, $so_dien_thoai_shop, $email_shop,
                $user_id
            );
            
            if ($update_shop->execute()) {
                $_SESSION['success_message'] = "Cập nhật thông tin shop thành công!";
                
                // Cập nhật lại thông tin seller
                $seller_info->execute();
                $seller = $seller_info->get_result()->fetch_assoc();
                
                // Redirect để tránh gửi lại form khi refresh
                header("Location: ho-so.php");
                exit();
            } else {
                $errors[] = "Có lỗi xảy ra khi cập nhật thông tin shop: " . $conn->error;
            }
        }
    }
}

// Xử lý cập nhật thông tin ngân hàng
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_bank'])) {
    $ten_chu_tai_khoan = trim($_POST['ten_chu_tai_khoan']);
    $so_tai_khoan = trim($_POST['so_tai_khoan']);
    $ten_ngan_hang = trim($_POST['ten_ngan_hang']);
    $chi_nhanh = trim($_POST['chi_nhanh']);
    
    // Validate dữ liệu
    $errors = [];
    
    if (empty($ten_chu_tai_khoan)) {
        $errors[] = "Tên chủ tài khoản không được để trống";
    }
    
    if (empty($so_tai_khoan)) {
        $errors[] = "Số tài khoản không được để trống";
    }
    
    if (empty($ten_ngan_hang)) {
        $errors[] = "Tên ngân hàng không được để trống";
    }
    
    // Nếu không có lỗi, cập nhật thông tin
    if (empty($errors)) {
        $update_bank = $conn->prepare("
            UPDATE users 
            SET ten_chu_tai_khoan = ?, so_tai_khoan = ?,
                ten_ngan_hang = ?, chi_nhanh = ?
            WHERE id_user = ?
        ");
        
        $update_bank->bind_param("ssssi", 
            $ten_chu_tai_khoan, $so_tai_khoan,
            $ten_ngan_hang, $chi_nhanh,
            $user_id
        );
        
        if ($update_bank->execute()) {
            $_SESSION['success_message'] = "Cập nhật thông tin ngân hàng thành công!";
            
            // Cập nhật lại thông tin seller
            $seller_info->execute();
            $seller = $seller_info->get_result()->fetch_assoc();
            
            // Redirect để tránh gửi lại form khi refresh
            header("Location: ho-so.php?tab=banking");
            exit();
        } else {
            $errors[] = "Có lỗi xảy ra khi cập nhật thông tin ngân hàng: " . $conn->error;
        }
    }
}

// Xác định tab hiện tại
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'profile';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Hồ Sơ Shop</h1>
</div>

<?php if (isset($errors) && !empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['info_message'])): ?>
    <div class="alert alert-info alert-dismissible fade show" role="alert">
        <i class="bi bi-info-circle me-2"></i> <?php echo $_SESSION['info_message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['info_message']); ?>
<?php endif; ?>

<div class="card">
    <div class="card-header bg-white">
        <ul class="nav nav-tabs card-header-tabs" id="shop-profile-tabs">
            <li class="nav-item">
                <a class="nav-link <?php echo $active_tab == 'profile' ? 'active' : ''; ?>" href="#profile" data-bs-toggle="tab">
                    <i class="bi bi-shop me-1"></i> Thông tin shop
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $active_tab == 'banking' ? 'active' : ''; ?>" href="#banking" data-bs-toggle="tab">
                    <i class="bi bi-bank me-1"></i> Thông tin thanh toán
                </a>
            </li>
        </ul>
    </div>
    
    <div class="card-body">
        <div class="tab-content">
            <!-- Profile Tab -->
            <div class="tab-pane fade <?php echo $active_tab == 'profile' ? 'show active' : ''; ?>" id="profile">
                <form method="post" action="" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-4 text-center">
                            <div class="mb-3">
                                <div class="shop-logo-wrapper mb-3">
                                    <?php if (!empty($seller['logo_shop']) && file_exists("../uploads/shops/{$seller['logo_shop']}")): ?>
                                        <img src="../uploads/shops/<?php echo $seller['logo_shop']; ?>" class="img-thumbnail" style="width: 150px; height: 150px; object-fit: cover;" id="logo-preview">
                                    <?php else: ?>
                                        <div class="bg-light d-flex align-items-center justify-content-center mx-auto" style="width: 150px; height: 150px;" id="logo-placeholder">
                                            <i class="bi bi-shop text-muted" style="font-size: 3rem;"></i>
                                        </div>
                                        <img src="" class="img-thumbnail d-none" style="width: 150px; height: 150px; object-fit: cover;" id="logo-preview">
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="logo_shop" class="form-label">Logo Shop</label>
                                    <input class="form-control" type="file" id="logo_shop" name="logo_shop" accept="image/*">
                                    <div class="form-text">Chọn hình ảnh có tỷ lệ vuông, kích thước khuyến nghị 500x500px</div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="ten_shop" class="form-label">Tên Shop <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="ten_shop" name="ten_shop" value="<?php echo htmlspecialchars($seller['ten_shop'] ?? ''); ?>" required>
                            </div>
                        </div>
                        
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="mo_ta_shop" class="form-label">Mô tả Shop</label>
                                <textarea class="form-control" id="mo_ta_shop" name="mo_ta_shop" rows="4"><?php echo htmlspecialchars($seller['mo_ta_shop'] ?? ''); ?></textarea>
                                <div class="form-text">Mô tả ngắn gọn về shop của bạn, sản phẩm bạn bán và những điều khác...</div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="dia_chi_shop" class="form-label">Địa chỉ Shop <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="dia_chi_shop" name="dia_chi_shop" value="<?php echo htmlspecialchars($seller['dia_chi_shop'] ?? $seller['diachi']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="so_dien_thoai_shop" class="form-label">Số điện thoại Shop <span class="text-danger">*</span></label>
                                        <input type="tel" class="form-control" id="so_dien_thoai_shop" name="so_dien_thoai_shop" value="<?php echo htmlspecialchars($seller['so_dien_thoai_shop'] ?? $seller['sdt']); ?>" pattern="[0-9]{10}" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email_shop" class="form-label">Email Shop</label>
                                        <input type="email" class="form-control" id="email_shop" name="email_shop" value="<?php echo htmlspecialchars($seller['email_shop'] ?? $seller['email']); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end">
                        <input type="hidden" name="update_shop" value="1">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i> Lưu thông tin
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Banking Tab -->
            <div class="tab-pane fade <?php echo $active_tab == 'banking' ? 'show active' : ''; ?>" id="banking">
                <form method="post" action="">
                    <div class="row">
                        <div class="col-md-6 offset-md-3">
                            <div class="mb-3">
                                <label for="ten_chu_tai_khoan" class="form-label">Tên chủ tài khoản <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="ten_chu_tai_khoan" name="ten_chu_tai_khoan" value="<?php echo htmlspecialchars($seller['ten_chu_tai_khoan'] ?? ''); ?>" required>
                                <div class="form-text">Tên chủ tài khoản phải trùng với tên trên CMND/CCCD</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="so_tai_khoan" class="form-label">Số tài khoản <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="so_tai_khoan" name="so_tai_khoan" value="<?php echo htmlspecialchars($seller['so_tai_khoan'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="ten_ngan_hang" class="form-label">Tên ngân hàng <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="ten_ngan_hang" name="ten_ngan_hang" value="<?php echo htmlspecialchars($seller['ten_ngan_hang'] ?? ''); ?>" required>
                                <div class="form-text">Ví dụ: Vietcombank, Techcombank, MB Bank...</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="chi_nhanh" class="form-label">Chi nhánh</label>
                                <input type="text" class="form-control" id="chi_nhanh" name="chi_nhanh" value="<?php echo htmlspecialchars($seller['chi_nhanh'] ?? ''); ?>">
                            </div>
                            
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle-fill me-2"></i> Thông tin tài khoản ngân hàng được sử dụng để nhận tiền thanh toán từ các đơn hàng.
                            </div>
                            
                            <div class="d-flex justify-content-center mt-4">
                                <input type="hidden" name="update_bank" value="1">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save me-1"></i> Lưu thông tin ngân hàng
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$page_specific_js = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Tab navigation from URL hash
    let hash = window.location.hash;
    if (hash) {
        const tabId = hash.substring(1);
        const tab = document.querySelector(`[data-bs-target="#${tabId}"]`);
        if (tab) {
            new bootstrap.Tab(tab).show();
        }
    }
    
    // Update URL hash when tab changes
    const tabs = document.querySelectorAll(\'[data-bs-toggle="tab"]\');
    tabs.forEach(tab => {
        tab.addEventListener("shown.bs.tab", function(event) {
            const targetId = event.target.getAttribute("data-bs-target").substring(1);
            window.location.hash = targetId;
        });
    });
    
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
';

include('includes/footer.php');
?>
