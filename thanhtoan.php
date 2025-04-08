<?php
session_start();
include('config/config.php');

// Hiển thị thông báo lỗi nếu có
$error_message = '';
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Kiểm tra có dữ liệu POST hay không
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Nếu là checkout cho các mặt hàng đã chọn
    if (isset($_POST['checkout_selected']) && isset($_POST['selected_items'])) {
        $selected_items = $_POST['selected_items'];
        // Lưu danh sách sản phẩm đã chọn vào session
        $_SESSION['checkout_items'] = $selected_items;
        $_SESSION['checkout_type'] = 'selected';
    } else {
        // Nếu là checkout toàn bộ giỏ hàng
        $_SESSION['checkout_type'] = 'all';
        unset($_SESSION['checkout_items']);
    }
}

// Lấy thông tin giỏ hàng
$session_id = session_id();
$user_id = isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : null;

// Lấy ID giỏ hàng
if ($user_id) {
    $stmt = $conn->prepare("SELECT id_giohang FROM giohang WHERE id_nguoidung = ?");
    $stmt->bind_param("i", $user_id);
} else {
    $stmt = $conn->prepare("SELECT id_giohang FROM giohang WHERE session_id = ? AND id_nguoidung IS NULL");
    $stmt->bind_param("s", $session_id);
}
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Không có giỏ hàng, chuyển hướng về trang giỏ hàng
    header('Location: giohang.php');
    exit();
}

$cart = $result->fetch_assoc();
$cart_id = $cart['id_giohang'];

// Lấy danh sách sản phẩm cần thanh toán
if (isset($_SESSION['checkout_type']) && $_SESSION['checkout_type'] == 'selected' && !empty($_SESSION['checkout_items'])) {
    // Nếu thanh toán các sản phẩm đã chọn
    $selected_items = $_SESSION['checkout_items'];
    $placeholders = str_repeat('?,', count($selected_items) - 1) . '?';
    
    $query = "
        SELECT gct.*, 
               sp.tensanpham, 
               sp.hinhanh, 
               kt.tenkichthuoc, 
               ms.tenmau, 
               ms.mamau
        FROM giohang_chitiet gct
        JOIN sanpham sp ON gct.id_sanpham = sp.id_sanpham
        LEFT JOIN kichthuoc kt ON gct.id_kichthuoc = kt.id_kichthuoc
        LEFT JOIN mausac ms ON gct.id_mausac = ms.id_mausac
        WHERE gct.id_giohang = ? AND gct.id_chitiet IN ($placeholders)
    ";
    
    $types = "i" . str_repeat("i", count($selected_items));
    $params = array_merge([$cart_id], $selected_items);
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
} else {
    // Nếu thanh toán tất cả sản phẩm trong giỏ hàng
    $stmt = $conn->prepare("
        SELECT gct.*, 
               sp.tensanpham, 
               sp.hinhanh, 
               kt.tenkichthuoc, 
               ms.tenmau, 
               ms.mamau
        FROM giohang_chitiet gct
        JOIN sanpham sp ON gct.id_sanpham = sp.id_sanpham
        LEFT JOIN kichthuoc kt ON gct.id_kichthuoc = kt.id_kichthuoc
        LEFT JOIN mausac ms ON gct.id_mausac = ms.id_mausac
        WHERE gct.id_giohang = ?
    ");
    $stmt->bind_param("i", $cart_id);
}

$stmt->execute();
$result = $stmt->get_result();

// Tính tổng tiền các sản phẩm được chọn
$total_amount = 0;
$checkout_items = [];

while ($item = $result->fetch_assoc()) {
    $checkout_items[] = $item;
    $total_amount += $item['thanh_tien'];
}

// Nếu không có sản phẩm nào để thanh toán
if (empty($checkout_items)) {
    header('Location: giohang.php');
    exit();
}

// Lấy thông tin người dùng nếu đã đăng nhập
$user_info = [];
if ($user_id) {
    $user_stmt = $conn->prepare("SELECT * FROM users WHERE id_user = ?");
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user_info = $user_stmt->get_result()->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanh toán - Bug Shop</title>
    <link rel="stylesheet" href="node_modules/bootstrap/dist/css/bootstrap.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/thanhtoan.css">
    <style>
        /* Cải thiện giao diện chung */
        
    </style>
</head>
<body>
    <?php include('includes/header.php'); ?>
    
    <div class="container-fluid py-5 bg-light">
        <div class="checkout-container py-3">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Trang chủ</a></li>
                    <li class="breadcrumb-item"><a href="giohang.php">Giỏ hàng</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Thanh toán</li>
                </ol>
            </nav>
            
            <!-- Checkout Steps -->
            <div class="checkout-steps mb-5">
                <div class="checkout-step step-complete">
                    <div class="step-icon">
                        <i class="bi bi-cart-check"></i>
                    </div>
                    <div class="step-text">Giỏ hàng</div>
                </div>
                <div class="checkout-step step-active">
                    <div class="step-icon">
                        <i class="bi bi-credit-card"></i>
                    </div>
                    <div class="step-text">Thanh toán</div>
                </div>
                <div class="checkout-step">
                    <div class="step-icon">
                        <i class="bi bi-check-lg"></i>
                    </div>
                    <div class="step-text">Hoàn tất</div>
                </div>
            </div>
            
            <div class="row g-4">
                <!-- Thông tin thanh toán -->
                <div class="col-lg-8">
                    <div class="card shadow-sm mb-4">
                        <div class="card-body p-4">
                            <h3 class="section-title">Thông tin thanh toán</h3>
                            
                            <?php if (!empty($error_message)): ?>
                                <div class="alert alert-danger mb-4">
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                    <?php echo htmlspecialchars($error_message); ?>
                                </div>
                            <?php endif; ?>
                            
                            <form id="checkout-form" method="post" action="process_order.php">
                                <div class="mb-4 mt-4">
                                    <h5 class="fw-bold mb-3">
                                        <i class="bi bi-person-circle me-2 text-primary"></i>
                                        Thông tin cá nhân
                                    </h5>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="fullname" class="form-label">Họ và tên <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="fullname" name="fullname" required
                                                   value="<?php echo isset($user_info['tenuser']) ? $user_info['tenuser'] : ''; ?>">
                                            <div class="form-text">Tên người nhận hàng</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="phone" class="form-label">Số điện thoại <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                                                <input type="tel" class="form-control" id="phone" name="phone" required
                                                       value="<?php echo isset($user_info['sdt']) ? $user_info['sdt'] : ''; ?>"
                                                       pattern="[0-9]{10}" title="Vui lòng nhập số điện thoại hợp lệ (10 số)">
                                            </div>
                                            <div class="form-text">Số điện thoại nhận hàng</div>
                                        </div>
                                        <div class="col-12">
                                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                                <input type="email" class="form-control" id="email" name="email" required
                                                       value="<?php echo isset($user_info['email']) ? $user_info['email'] : ''; ?>">
                                            </div>
                                            <div class="form-text">Để nhận thông báo đơn hàng</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <h5 class="fw-bold mb-3">
                                        <i class="bi bi-geo-alt-fill me-2 text-primary"></i>
                                        Địa chỉ giao hàng
                                    </h5>
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label for="address" class="form-label">Địa chỉ <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="bi bi-house"></i></span>
                                                <input type="text" class="form-control" id="address" name="address" required
                                                       placeholder="Số nhà, tên đường"
                                                       value="<?php echo isset($user_info['diachi']) ? $user_info['diachi'] : ''; ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="province" class="form-label">Tỉnh/Thành phố <span class="text-danger">*</span></label>
                                            <select class="form-select" id="province" name="province" required>
                                                <option value="">Chọn tỉnh/thành phố</option>
                                                <!-- Các tùy chọn sẽ được thêm bằng JavaScript -->
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="district" class="form-label">Quận/Huyện <span class="text-danger">*</span></label>
                                            <select class="form-select" id="district" name="district" required disabled>
                                                <option value="">Chọn quận/huyện</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="ward" class="form-label">Phường/Xã <span class="text-danger">*</span></label>
                                            <select class="form-select" id="ward" name="ward" required disabled>
                                                <option value="">Chọn phường/xã</option>
                                            </select>
                                        </div>
                                        <div class="col-12 mt-3">
                                            <div class="address-preview d-none" id="full-address-preview">
                                                <i class="bi bi-geo-alt-fill me-2"></i>
                                                <strong>Địa chỉ giao hàng đầy đủ:</strong>
                                                <span id="full-address-text"></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <h5 class="fw-bold mb-3">
                                        <i class="bi bi-credit-card-2-front me-2 text-primary"></i>
                                        Phương thức thanh toán
                                    </h5>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <input type="radio" class="payment-method-radio d-none" name="payment_method" id="cod" value="cod" checked>
                                            <label class="payment-method-label d-flex align-items-center" for="cod">
                                                <i class="payment-method-icon bi bi-cash text-success"></i>
                                                <div>
                                                    <strong>Tiền mặt (COD)</strong>
                                                    <div class="small text-muted">Thanh toán khi nhận hàng</div>
                                                </div>
                                            </label>
                                        </div>
                                        <div class="col-md-4">
                                            <input type="radio" class="payment-method-radio d-none" name="payment_method" id="bank_transfer" value="bank_transfer">
                                            <label class="payment-method-label d-flex align-items-center" for="bank_transfer">
                                                <i class="payment-method-icon bi bi-bank text-primary"></i>
                                                <div>
                                                    <strong>Ngân hàng</strong>
                                                    <div class="small text-muted">Chuyển khoản ngân hàng</div>
                                                </div>
                                            </label>
                                        </div>
                                        <div class="col-md-4">
                                            <input type="radio" class="payment-method-radio d-none" name="payment_method" id="vnpay" value="vnpay">
                                            <label class="payment-method-label d-flex align-items-center" for="vnpay">
                                                <i class="payment-method-icon bi bi-qr-code-scan text-danger"></i>
                                                <div>
                                                    <strong>VNPAY</strong>
                                                    <div class="small text-muted">Thanh toán qua VNPAY</div>
                                                </div>
                                            </label>
                                        </div>
                                        <div class="col-md-4">
                                            <input type="radio" class="payment-method-radio d-none" name="payment_method" id="vnpay" value="vnpay">
                                            <label class="payment-method-label d-flex align-items-center" for="vnpay">
                                                <i class="payment-method-icon bi bi-qr-code-scan text-danger"></i>
                                                <div>
                                                    <strong>QR VNPAY</strong>
                                                    <div class="small text-muted">Thanh toán qua QR VNPAY</div>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <h5 class="fw-bold mb-3">
                                        <i class="bi bi-pencil-square me-2 text-primary"></i>
                                        Ghi chú
                                    </h5>
                                    <textarea class="form-control" id="note" name="note" rows="3" placeholder="Ghi chú về đơn hàng, ví dụ: thời gian hay chỉ dẫn địa điểm giao hàng chi tiết hơn."></textarea>
                                </div>
                                
                                <!-- Truyền thông tin sản phẩm được chọn -->
                                <?php if (isset($_SESSION['checkout_type']) && $_SESSION['checkout_type'] == 'selected'): ?>
                                    <?php foreach ($selected_items as $item_id): ?>
                                        <input type="hidden" name="selected_items[]" value="<?php echo $item_id; ?>">
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                
                                <div class="d-flex justify-content-between mt-5">
                                    <a href="giohang.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-left"></i> Quay lại giỏ hàng
                                    </a>
                                    <button type="submit" class="btn btn-primary btn-place-order">
                                        Đặt hàng <i class="bi bi-arrow-right ms-2"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Thông tin đơn hàng -->
                <div class="col-lg-4">
                    <div class="card shadow-sm mb-4 sticky-lg-top" style="top:20px; z-index:1;">
                        <div class="card-body p-4">
                            <h3 class="section-title">Thông tin đơn hàng</h3>
                            
                            <div class="mb-3 mt-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="fw-bold mb-0">
                                        <i class="bi bi-bag me-2 text-primary"></i>
                                        Sản phẩm (<?php echo count($checkout_items); ?>)
                                    </h5>
                                    <span class="badge bg-primary rounded-pill"><?php echo count($checkout_items); ?></span>
                                </div>
                                
                                <div class="list-group mb-3">
                                    <?php foreach ($checkout_items as $item): ?>
                                        <div class="list-group-item border-0 px-0">
                                            <div class="d-flex">
                                                <div class="position-relative me-3">
                                                    <img src="<?php echo !empty($item['hinhanh']) ? 'uploads/products/' . $item['hinhanh'] : 'images/no-image.png'; ?>" 
                                                         alt="<?php echo $item['tensanpham']; ?>" 
                                                         class="product-img">
                                                    <span class="product-quantity badge bg-primary position-absolute">
                                                        <?php echo $item['soluong']; ?>
                                                    </span>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="my-0"><?php echo $item['tensanpham']; ?></h6>
                                                    <small class="text-muted d-block mb-1">
                                                        <?php
                                                            $specs = [];
                                                            if (!empty($item['tenkichthuoc'])) {
                                                                $specs[] = 'Size: ' . $item['tenkichthuoc'];
                                                            }
                                                            if (!empty($item['tenmau'])) {
                                                                $specs[] = 'Màu: ' . $item['tenmau'];
                                                            }
                                                            echo implode(' | ', $specs);
                                                        ?>
                                                    </small>
                                                    <span class="text-primary fw-bold"><?php echo number_format($item['thanh_tien'], 0, ',', '.'); ?>₫</span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="order-summary mt-4">
                                    <div class="total-line">
                                        <span>Tạm tính:</span>
                                        <span><?php echo number_format($total_amount, 0, ',', '.'); ?>₫</span>
                                    </div>
                                    <div class="total-line">
                                        <span>Phí vận chuyển:</span>
                                        <span><?php echo number_format(30000, 0, ',', '.'); ?>₫</span>
                                    </div>
                                    <div class="total-line grand-total">
                                        <span>Tổng cộng:</span>
                                        <span class="grand-total-price"><?php echo number_format($total_amount + 30000, 0, ',', '.'); ?>₫</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="alert alert-info" role="alert">
                                <i class="bi bi-info-circle me-2"></i>
                                <small>Vui lòng kiểm tra kỹ thông tin trước khi đặt hàng. Đơn hàng sẽ được xử lý trong vòng 24 giờ.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include('includes/footer.php'); ?>
    
    
    <script src="js/address-selector.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log("Trang thanh toán đã tải xong");
            
            // Xử lý khi submit form
            const form = document.getElementById('checkout-form');
            form.addEventListener('submit', function(e) {
                // Kiểm tra các trường bắt buộc
                let isValid = true;
                const requiredFields = form.querySelectorAll('[required]');
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        isValid = false;
                        field.classList.add('is-invalid');
                    } else {
                        field.classList.remove('is-invalid');
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    alert('Vui lòng điền đầy đủ các trường bắt buộc');
                    return;
                }
                
                // Thêm các trường hidden chứa thông tin địa chỉ
                const addressData = window.getSelectedAddressData();
                
                if (addressData.provinceName) {
                    const provinceNameInput = document.createElement('input');
                    provinceNameInput.type = 'hidden';
                    provinceNameInput.name = 'province_name';
                    provinceNameInput.value = addressData.provinceName;
                    this.appendChild(provinceNameInput);
                }
                
                if (addressData.districtName) {
                    const districtNameInput = document.createElement('input');
                    districtNameInput.type = 'hidden';
                    districtNameInput.name = 'district_name';
                    districtNameInput.value = addressData.districtName;
                    this.appendChild(districtNameInput);
                }
                
                if (addressData.wardName) {
                    const wardNameInput = document.createElement('input');
                    wardNameInput.type = 'hidden';
                    wardNameInput.name = 'ward_name';
                    wardNameInput.value = addressData.wardName;
                    this.appendChild(wardNameInput);
                }
                
                // Thêm trường hidden chứa địa chỉ đầy đủ
                const fullAddressInput = document.createElement('input');
                fullAddressInput.type = 'hidden';
                fullAddressInput.name = 'full_address';
                fullAddressInput.value = addressData.fullAddress;
                this.appendChild(fullAddressInput);
            });

            // Validation cho số điện thoại
            const phoneInput = document.getElementById('phone');
            if (phoneInput) {
                phoneInput.addEventListener('input', function() {
                    // Chỉ cho phép nhập số
                    this.value = this.value.replace(/[^0-9]/g, '');
                    
                    // Giới hạn độ dài
                    if (this.value.length > 10) {
                        this.value = this.value.slice(0, 10);
                    }
                });
            }
            
            // Hiển thị phương thức thanh toán đã chọn
            const paymentMethods = document.querySelectorAll('.payment-method-radio');
            paymentMethods.forEach(method => {
                method.addEventListener('change', function() {
                    const selectedMethod = document.querySelector('.payment-method-selected');
                    if (selectedMethod) {
                        selectedMethod.classList.remove('payment-method-selected');
                    }
                    
                    if (this.checked) {
                        this.parentNode.querySelector('.payment-method-label').classList.add('payment-method-selected');
                    }
                });
            });
            
            // Kích hoạt phương thức thanh toán mặc định
            const defaultPaymentMethod = document.getElementById('cod');
            if (defaultPaymentMethod) {
                defaultPaymentMethod.checked = true;
                defaultPaymentMethod.parentNode.querySelector('.payment-method-label').classList.add('payment-method-selected');
            }

            // Thay đổi từ
            const vnpayPayment = document.getElementById('vnpay');
        });
    </script>
</body>
</html>