<?php
session_start();
include('config/config.php');

// Kiểm tra nếu bạn đang ở trang thanh toán
$is_logged_in = isset($_SESSION['user']);
$buy_now = isset($_GET['buy_now']) && $_GET['buy_now'] == '1';

// Biến để kiểm soát hiển thị COD
$allow_cod = $is_logged_in;

// Kiểm tra thông tin sản phẩm (không yêu cầu đăng nhập)
if ($buy_now && !isset($_SESSION['buy_now_cart'])) {
    $_SESSION['error_message'] = 'Không tìm thấy thông tin sản phẩm để mua ngay';
    header('Location: sanpham.php');
    exit;
}

// Xử lý mua ngay
if ($buy_now) {
    $cart_items = [];
    $cart_item = $_SESSION['buy_now_cart'];
    
    // Lấy thêm thông tin kích thước và màu sắc nếu có
    if ($cart_item['size_id']) {
        $size_query = $conn->prepare("SELECT tenkichthuoc FROM kichthuoc WHERE id_kichthuoc = ?");
        $size_query->bind_param("i", $cart_item['size_id']);
        $size_query->execute();
        $size_result = $size_query->get_result();
        if ($size_result->num_rows > 0) {
            $cart_item['tenkichthuoc'] = $size_result->fetch_assoc()['tenkichthuoc'];
        }
    }
    
    if ($cart_item['color_id']) {
        $color_query = $conn->prepare("SELECT tenmau, mamau FROM mausac WHERE id_mausac = ?");
        $color_query->bind_param("i", $cart_item['color_id']);
        $color_query->execute();
        $color_result = $color_query->get_result();
        if ($color_result->num_rows > 0) {
            $color = $color_result->fetch_assoc();
            $cart_item['tenmau'] = $color['tenmau'];
            $cart_item['mamau'] = $color['mamau'];
        }
    }
    
    $cart_item['thanh_tien'] = $cart_item['price'] * $cart_item['quantity'];
    $cart_items[] = $cart_item;
    $total_amount = $cart_item['thanh_tien'];
    $checkout_items = $cart_items;
} else {
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
<?php 
    require_once('includes/head.php');
    require_once('includes/header.php');
    
    ?>
    
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
                            
                            <form id="checkout-form" method="post" action="process_order.php<?php echo $buy_now ? '?buy_now=1' : ''; ?>">
                                <!-- Thêm hidden field để đánh dấu là mua ngay -->
                                <?php if ($buy_now): ?>
                                <input type="hidden" name="buy_now" value="1">
                                <?php endif; ?>
                                
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
                                        <!-- Phương thức thanh toán COD -->
                                        <?php if ($allow_cod): ?>
                                        <div class="col-md-4">
                                            <div class="payment-method-item">
                                                <input type="radio" class="payment-method-radio" name="payment_method" id="cod" value="cod" checked>
                                                <label for="cod" class="payment-method-label">
                                                    <!-- Nội dung COD -->
                                                </label>
                                            </div>
                                        </div>
                                        <?php else: ?>
                                        <div class="col-12 mb-3">
                                            <div class="alert alert-info">
                                                <i class="bi bi-info-circle me-2"></i> 
                                                Để sử dụng phương thức thanh toán COD, vui lòng 
                                                <a href="dangnhap.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>">đăng nhập</a>.
                                            </div>
                                        </div>
                                        <?php endif; ?>
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
                                
                                <div class="promo-code-section mb-3">
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="bi bi-ticket-perforated me-2 text-primary"></i>
                                        <h6 class="mb-0">Mã giảm giá</h6>
                                    </div>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="promo-code" placeholder="Nhập mã giảm giá">
                                        <button class="btn btn-outline-primary" type="button" id="apply-promo">Áp dụng</button>
                                    </div>
                                    <div id="promo-message" class="mt-2 small"></div>
                                </div>
                                
                                <!-- Thông tin mã giảm giá -->
                                <input type="hidden" name="promo_code" id="promo-code-input" value="">
                                <input type="hidden" name="discount_amount" id="discount-amount-input" value="0">
                                <input type="hidden" name="discount_id" id="discount-id-input" value="0">
                                
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
                                                    <img src="uploads/products/<?php echo isset($item['hinhanh']) && !empty($item['hinhanh']) 
                                                        ? $item['hinhanh'] 
                                                        : (isset($item['image']) && !empty($item['image']) ? $item['image'] : 'no-image.png'); ?>" 
                                                         class="img-thumbnail" width="60" 
                                                         alt="<?php echo isset($item['tensanpham']) ? htmlspecialchars($item['tensanpham']) : 'Sản phẩm'; ?>">
                                                    <span class="product-quantity badge bg-primary position-absolute">
                                                        <?php echo isset($item['soluong']) ? $item['soluong'] : ($item['quantity'] ?? 1); ?>
                                                    </span>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-0">
                                                        <?php echo htmlspecialchars($item['tensanpham'] ?? $item['name'] ?? 'Sản phẩm không xác định'); ?>
                                                    </h6>
                                                    <small class="text-muted d-block mb-1">
                                                        <?php if (isset($item['tenkichthuoc']) && !empty($item['tenkichthuoc'])): ?>
                                                            <span>Size: <?php echo htmlspecialchars($item['tenkichthuoc']); ?></span>
                                                        <?php endif; ?>
                                                        <?php if (isset($item['tenmau']) && !empty($item['tenmau'])): ?>
                                                            <span class="mx-1">|</span>
                                                            <span>Màu: <?php echo htmlspecialchars($item['tenmau']); ?></span>
                                                        <?php endif; ?>
                                                    </small>
                                                    <div class="fw-bold"><?php echo isset($item['thanh_tien']) ? number_format($item['thanh_tien'], 0, ',', '.') : 0; ?>₫</div>
                                                    <div class="text-muted small">
                                                        <?php echo isset($item['soluong']) ? $item['soluong'] : ($item['quantity'] ?? 1); ?> x 
                                                        <?php echo number_format($item['gia'] ?? ($item['price'] ?? 0), 0, ',', '.'); ?>₫
                                                    </div>
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
                                    <div class="total-line discount-line" id="discount-row" style="display: none;">
                                        <span>Giảm giá:</span>
                                        <span class="text-danger" id="discount-amount">0₫</span>
                                    </div>
                                    <div class="total-line">
                                        <span>Phí vận chuyển:</span>
                                        <span><?php echo number_format(30000, 0, ',', '.'); ?>₫</span>
                                    </div>
                                    <div class="total-line grand-total">
                                        <span>Tổng cộng:</span>
                                        <span class="grand-total-price" id="grand-total"><?php echo number_format($total_amount + 30000, 0, ',', '.'); ?>₫</span>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($buy_now): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i> Bạn đang thanh toán trực tiếp cho sản phẩm đã chọn.
                            </div>
                            <?php endif; ?>
                            
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
            
            // Kiểm tra phương thức thanh toán cho người dùng chưa đăng nhập
            const isLoggedIn = <?php echo $is_logged_in ? 'true' : 'false'; ?>;
            const codRadio = document.getElementById('cod');
            
            // Ngăn chặn người dùng chưa đăng nhập chọn COD
            if (!isLoggedIn && codRadio) {
                codRadio.disabled = true;
                codRadio.parentElement.classList.add('disabled');
                const label = document.querySelector('label[for="cod"]');
                if (label) {
                    label.title = "Vui lòng đăng nhập để sử dụng COD";
                }
            }
            
            // Kiểm tra khi submit form
            document.getElementById('checkout-form').addEventListener('submit', function(e) {
                const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
                if (!isLoggedIn && paymentMethod && paymentMethod.value === 'cod') {
                    e.preventDefault();
                    alert('Bạn cần đăng nhập để sử dụng phương thức thanh toán COD');
                }
            });

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

            // Xử lý mã giảm giá
            const promoCodeInput = document.getElementById('promo-code');
            const applyPromoButton = document.getElementById('apply-promo');
            const promoMessageElement = document.getElementById('promo-message');
            const discountRow = document.getElementById('discount-row');
            const discountAmountElement = document.getElementById('discount-amount');
            const grandTotalElement = document.getElementById('grand-total');
            const promoCodeInputHidden = document.getElementById('promo-code-input');
            const discountAmountInputHidden = document.getElementById('discount-amount-input');
            const discountIdInputHidden = document.getElementById('discount-id-input');

            // Tổng tiền sản phẩm không bao gồm phí vận chuyển
            const subtotal = <?php echo $total_amount; ?>;
            // Phí vận chuyển
            const shippingFee = 30000;
            // Tổng tiền hiện tại (chưa có giảm giá)
            let currentTotal = subtotal + shippingFee;
            // Số tiền giảm giá
            let discountAmount = 0;

            applyPromoButton.addEventListener('click', function() {
                const code = promoCodeInput.value.trim();
                
                if (!code) {
                    promoMessageElement.innerHTML = '<span class="text-danger">Vui lòng nhập mã giảm giá</span>';
                    return;
                }
                
                // Hiển thị thông báo đang xử lý
                promoMessageElement.innerHTML = '<span class="text-muted"><i class="bi bi-hourglass-split me-2"></i>Đang kiểm tra...</span>';
                
                // Lấy thông tin sản phẩm trong giỏ hàng
                const cartItems = <?php echo json_encode($checkout_items); ?>;
                
                // Gửi request kiểm tra mã giảm giá
                fetch('apply_promo_code.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        code: code,
                        total: subtotal,
                        cart_items: JSON.stringify(cartItems)
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Cập nhật UI khi thành công
                        promoMessageElement.innerHTML = `<span class="text-success"><i class="bi bi-check-circle me-1"></i>${data.message}</span>`;
                        
                        // Hiển thị dòng giảm giá
                        discountRow.style.display = 'flex';
                        discountAmount = data.discount_amount;
                        
                        // Cập nhật số tiền giảm giá và tổng tiền
                        discountAmountElement.textContent = `-${data.formatted_discount}₫`;
                        grandTotalElement.textContent = `${data.formatted_total}₫`;
                        
                        // Cập nhật các input hidden
                        promoCodeInputHidden.value = code;
                        discountAmountInputHidden.value = discountAmount;
                        discountIdInputHidden.value = data.discount_id;
                        
                        // Vô hiệu hóa input và nút áp dụng
                        promoCodeInput.disabled = true;
                        applyPromoButton.disabled = true;
                        
                        // Thêm nút hủy mã giảm giá
                        const cancelButton = document.createElement('button');
                        cancelButton.className = 'btn btn-sm btn-outline-danger ms-2';
                        cancelButton.innerHTML = '<i class="bi bi-x-circle"></i> Hủy mã';
                        cancelButton.onclick = function(e) {
                            e.preventDefault();
                            // Reset lại tất cả
                            discountRow.style.display = 'none';
                            grandTotalElement.textContent = `${new Intl.NumberFormat('vi-VN').format(currentTotal)}₫`;
                            promoMessageElement.innerHTML = '';
                            promoCodeInput.value = '';
                            promoCodeInput.disabled = false;
                            applyPromoButton.disabled = false;
                            
                            // Reset các input hidden
                            promoCodeInputHidden.value = '';
                            discountAmountInputHidden.value = '0';
                            discountIdInputHidden.value = '';
                            
                            // Xóa nút hủy
                            this.remove();
                        };
                        promoMessageElement.appendChild(cancelButton);
                    } else {
                        // Hiển thị thông báo lỗi
                        promoMessageElement.innerHTML = `<span class="text-danger"><i class="bi bi-exclamation-circle me-1"></i>${data.message}</span>`;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    promoMessageElement.innerHTML = '<span class="text-danger">Có lỗi xảy ra khi kiểm tra mã giảm giá</span>';
                });
            });
        });
    </script>
</body>
</html>