<?php
session_start();
include('config/config.php');

// Debug information - ghi ra file log hoặc hiển thị
error_log("Truy cập trang dathang_thanhcong.php");
error_log("Session ID: " . session_id());
error_log("Session data: " . print_r($_SESSION, true));
error_log("GET data: " . print_r($_GET, true));

// Lấy ID đơn hàng từ tham số URL hoặc session
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

// Nếu không có order_id trong URL, thử lấy từ session
if ($order_id <= 0 && isset($_SESSION['last_order_id'])) {
    $order_id = (int)$_SESSION['last_order_id'];
    error_log("Lấy order_id từ session: " . $order_id);
}

// // Hiển thị thông tin debug
// echo "<div style='background: #f8f9fa; padding: 10px; margin: 10px; border: 1px solid #ddd;'>";
// echo "<h4>Debug Information</h4>";
// echo "<p>Order ID: " . $order_id . "</p>";
// echo "<p>Session ID: " . session_id() . "</p>";
// echo "<p>Last Order ID in Session: " . (isset($_SESSION['last_order_id']) ? $_SESSION['last_order_id'] : 'Not set') . "</p>";
// echo "</div>";

// Kiểm tra nếu không có ID đơn hàng hợp lệ
if ($order_id <= 0) {
    echo "<div style='background: #f8d7da; padding: 10px; margin: 10px; border: 1px solid #f5c6cb;'>";
    echo "<h4>Error</h4>";
    echo "<p>Không tìm thấy ID đơn hàng. Bạn sẽ được chuyển hướng về trang chủ.</p>";
    echo "</div>";
    // Delay redirect để đọc thông báo lỗi
    echo "<script>setTimeout(function(){ window.location.href = 'index.php'; }, 5000);</script>";
    exit;
}

// Thêm đoạn code này vào dathang_thanhcong.php
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// Nếu đơn hàng thuộc về người dùng đã đăng nhập
if ($user_id) {
    $order_query = $conn->prepare("
        SELECT d.*, COUNT(dc.id_chitiet) AS total_items, SUM(dc.soluong) AS total_quantity
        FROM donhang d
        LEFT JOIN donhang_chitiet dc ON d.id_donhang = dc.id_donhang
        WHERE d.id_donhang = ? AND d.id_nguoidung = ?
        GROUP BY d.id_donhang
    ");
    $order_query->bind_param("ii", $order_id, $user_id);
} else {
    $order_query = $conn->prepare("
        SELECT d.*, COUNT(dc.id_chitiet) AS total_items, SUM(dc.soluong) AS total_quantity
        FROM donhang d
        LEFT JOIN donhang_chitiet dc ON d.id_donhang = dc.id_donhang
        WHERE d.id_donhang = ?
        GROUP BY d.id_donhang
    ");
    $order_query->bind_param("i", $order_id);
}

$order_query->execute();
$order_result = $order_query->get_result();

// Kiểm tra đơn hàng tồn tại
if ($order_result->num_rows === 0) {
    // Đơn hàng không tồn tại hoặc không thuộc về người dùng hiện tại
    header('Location: index.php');
    exit;
}

$order = $order_result->fetch_assoc();

// Lấy chi tiết đơn hàng
$items_query = $conn->prepare("
    SELECT dc.*, sp.tensanpham, sp.hinhanh, kt.tenkichthuoc, ms.tenmau, ms.mamau
    FROM donhang_chitiet dc
    JOIN sanpham sp ON dc.id_sanpham = sp.id_sanpham
    LEFT JOIN kichthuoc kt ON dc.id_kichthuoc = kt.id_kichthuoc
    LEFT JOIN mausac ms ON dc.id_mausac = ms.id_mausac
    WHERE dc.id_donhang = ?
    ORDER BY dc.id_chitiet
");
$items_query->bind_param("i", $order_id);
$items_query->execute();
$items_result = $items_query->get_result();

// Tính lại tổng số lượng sản phẩm và tổng tiền để đảm bảo chính xác
$total_quantity = 0;
$total_amount = 0;
$items = [];

while ($item = $items_result->fetch_assoc()) {
    // Đảm bảo số lượng là số nguyên dương
    $item['soluong'] = max(1, (int)$item['soluong']);
    
    // Tính lại thành tiền để đảm bảo chính xác
    $item['thanh_tien'] = $item['gia'] * $item['soluong'];
    
    // Cập nhật tổng số lượng và tổng tiền
    $total_quantity += $item['soluong'];
    $total_amount += $item['thanh_tien'];
    
    $items[] = $item;
}

// Xóa order_id khỏi session để tránh hiển thị lại đơn hàng nếu người dùng tải lại trang
if (isset($_SESSION['last_order_id'])) {
    unset($_SESSION['last_order_id']);
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đặt hàng thành công - Bug Shop</title>
    <link rel="stylesheet" href="node_modules/bootstrap/dist/css/bootstrap.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .order-success-icon {
            font-size: 5rem;
            color: #28a745;
        }
        .order-item-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
        }
        .status-badge {
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <?php 
    include('includes/head.php');
    include('includes/header.php'); ?>
    
    <div class="container mt-5 mb-5">
        <div class="text-center mb-5">
            <div class="mb-4">
                <i class="bi bi-check-circle-fill order-success-icon"></i>
            </div>
            <h1 class="mb-3">Đặt hàng thành công!</h1>
            <p class="lead">Cảm ơn bạn đã đặt hàng tại Bug Shop. Đơn hàng của bạn đã được xác nhận.</p>
        </div>
        
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Thông tin đơn hàng #<?php echo $order_id; ?></h5>
                            <span class="badge 
                                <?php
                                switch ($order['trangthai']) {
                                    case 1: echo 'bg-info'; break;     // Chờ xác nhận
                                    case 2: echo 'bg-primary'; break;  // Đang xử lý
                                    case 3: echo 'bg-warning'; break;  // Đang giao hàng
                                    case 4: echo 'bg-success'; break;  // Đã giao
                                    case 5: echo 'bg-danger'; break;   // Đã hủy
                                    case 6: echo 'bg-secondary'; break; // Hoàn trả
                                    default: echo 'bg-secondary';
                                }
                                ?>">
                                <?php
                                switch ($order['trangthai']) {
                                    case 1: echo 'Chờ xác nhận'; break;
                                    case 2: echo 'Đang xử lý'; break;
                                    case 3: echo 'Đang giao hàng'; break;
                                    case 4: echo 'Đã giao'; break;
                                    case 5: echo 'Đã hủy'; break;
                                    case 6: echo 'Hoàn trả'; break;
                                    default: echo 'Không xác định';
                                }
                                ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h6 class="fw-bold">Thông tin người nhận</h6>
                                <p class="mb-1">Họ tên: <?php echo htmlspecialchars($order['tennguoinhan']); ?></p>
                                <p class="mb-1">Số điện thoại: <?php echo htmlspecialchars($order['sodienthoai']); ?></p>
                                <?php if(!empty($order['email'])): ?>
                                <p class="mb-1">Email: <?php echo htmlspecialchars($order['email']); ?></p>
                                <?php endif; ?>
                                <p class="mb-1">Địa chỉ: <?php echo htmlspecialchars($order['diachi']); ?></p>
                                <?php if(!empty($order['phuong_xa']) || !empty($order['quan_huyen']) || !empty($order['tinh_tp'])): ?>
                                <p class="mb-1">
                                    <?php echo htmlspecialchars($order['phuong_xa'] . ', ' . $order['quan_huyen'] . ', ' . $order['tinh_tp']); ?>
                                </p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <h6 class="fw-bold">Thông tin đơn hàng</h6>
                                <p class="mb-1">Mã đơn hàng: #<?php echo $order_id; ?></p>
                                <p class="mb-1">Ngày đặt: <?php echo date('d/m/Y H:i', strtotime($order['ngaytao'])); ?></p>
                                <p class="mb-1">Phương thức thanh toán: 
                                    <?php
                                    switch ($order['phuongthucthanhtoan']) {
                                        case 'cod': echo 'Thanh toán khi nhận hàng (COD)'; break;
                                        case 'bank': echo 'Chuyển khoản ngân hàng'; break;
                                        case 'momo': echo 'Ví điện tử MoMo'; break;
                                        case 'vnpay': echo 'VNPAY'; break;
                                        default: echo ucfirst($order['phuongthucthanhtoan']);
                                    }
                                    ?>
                                </p>
                                <?php if(!empty($order['ghichu'])): ?>
                                <p class="mb-1">Ghi chú: <?php echo htmlspecialchars($order['ghichu']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <h6 class="fw-bold mb-3">Chi tiết đơn hàng (<?php echo $total_quantity; ?> sản phẩm)</h6>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th width="10%">Hình ảnh</th>
                                        <th width="40%">Tên sản phẩm</th>
                                        <th width="15%">Giá</th>
                                        <th width="10%">Số lượng</th>
                                        <th width="15%">Thành tiền</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td>
                                            <img src="<?php echo !empty($item['hinhanh']) ? 'uploads/products/' . $item['hinhanh'] : 'images/no-image.png'; ?>" 
                                                 class="order-item-image rounded" 
                                                 alt="<?php echo htmlspecialchars($item['tensanpham']); ?>">
                                        </td>
                                        <td>
                                            <div><?php echo htmlspecialchars($item['tensanpham']); ?></div>
                                            <div class="small text-muted mt-1">
                                                <?php if (!empty($item['tenkichthuoc'])): ?>
                                                    <span class="me-3">Kích thước: <?php echo $item['tenkichthuoc']; ?></span>
                                                <?php endif; ?>
                                                <?php if (!empty($item['tenmau'])): ?>
                                                    <span>Màu: <?php echo $item['tenmau']; ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td><?php echo number_format($item['gia'], 0, ',', '.'); ?>₫</td>
                                        <td><?php echo $item['soluong']; ?></td>
                                        <td><?php echo number_format($item['thanh_tien'], 0, ',', '.'); ?>₫</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <td colspan="3"></td>
                                        <td class="text-end fw-bold">Tạm tính:</td>
                                        <td><?php echo number_format($total_amount, 0, ',', '.'); ?>₫</td>
                                    </tr>
                                    <tr>
                                        <td colspan="3"></td>
                                        <td class="text-end fw-bold">Phí vận chuyển:</td>
                                        <td><?php echo number_format($order['phivanchuyen'], 0, ',', '.'); ?>₫</td>
                                    </tr>
                                    <tr>
                                        <td colspan="3"></td>
                                        <td class="text-end fw-bold">Tổng cộng:</td>
                                        <td class="fw-bold text-danger"><?php echo number_format($order['tongtien'], 0, ',', '.'); ?>₫</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    
                    <div class="card-footer bg-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <a href="index.php" class="btn btn-outline-primary">
                                <i class="bi bi-arrow-left me-2"></i>Quay lại trang chủ
                            </a>
                            <a href="donhang.php" class="btn btn-primary">
                                <i class="bi bi-list-check me-2"></i>xem đơn hàng của tôi
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="text-center">
                    <h5 class="mb-3">Có thể bạn sẽ thích</h5>
                    <a href="sanpham.php" class="btn btn-outline-dark">
                        <i class="bi bi-shop me-2"></i>Tiếp tục mua sắm
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <?php include('includes/footer.php'); ?>
    
</body>
</html>