<?php
session_start();
include('config/config.php');

// Get order ID from URL parameter
$order_id = isset($_GET['orderId']) ? (int)$_GET['orderId'] : 0;
$payment_success = isset($_SESSION['payment_success']) ? $_SESSION['payment_success'] : false;
$payment_message = isset($_SESSION['payment_message']) ? $_SESSION['payment_message'] : '';

// Clear payment session variables
unset($_SESSION['payment_success']);
unset($_SESSION['payment_message']);
unset($_SESSION['payment_info']);

// Clear buy_now_cart from session if it exists
if (isset($_SESSION['buy_now_cart'])) {
    unset($_SESSION['buy_now_cart']);
}

// Redirect to home page if order ID is not provided
if ($order_id <= 0) {
    // Show success message if it exists
    if (isset($_SESSION['success_message'])) {
        $generic_success = $_SESSION['success_message'];
        unset($_SESSION['success_message']);
    } else {
        header('Location: index.php');
        exit();
    }
}

// Fetch order details from the database with updated schema
if ($order_id > 0) {
    $order_query = $conn->prepare("
        SELECT 
            o.*,
            CONCAT(o.diachi, ', ', o.phuong_xa, ', ', o.quan_huyen, ', ', o.tinh_tp) AS full_address
        FROM donhang o
        WHERE o.id = ?
    ");
    
    $order_query->bind_param("i", $order_id);
    $order_query->execute();
    $order_result = $order_query->get_result();
    
    if ($order_result->num_rows === 0) {
        header('Location: index.php');
        exit();
    }
    
    $order = $order_result->fetch_assoc();
    
    // Fetch order items
    $items_query = $conn->prepare("
        SELECT 
            od.*,
            p.tensanpham,
            p.hinhanh,
            sbt.id_mau,
            sbt.id_size,
            s.gia_tri AS ten_kichthuoc,
            c.gia_tri AS ten_mau
        FROM donhang_chitiet od
        JOIN sanpham p ON od.id_sanpham = p.id
        LEFT JOIN sanpham_bien_the sbt ON od.id_bienthe = sbt.id
        LEFT JOIN thuoc_tinh s ON sbt.id_size = s.id AND s.loai = 'size'
        LEFT JOIN thuoc_tinh c ON sbt.id_mau = c.id AND c.loai = 'color'
        WHERE od.id_donhang = ?
    ");
    
    $items_query->bind_param("i", $order_id);
    $items_query->execute();
    $items_result = $items_query->get_result();
    
    $order_items = [];
    while ($item = $items_result->fetch_assoc()) {
        $order_items[] = $item;
    }
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
    <link rel="stylesheet" href="css/index.css">
    <style>
        .thank-you-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .order-success-icon {
            width: 80px;
            height: 80px;
            background-color: #d1e7dd;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        .order-success-icon i {
            font-size: 40px;
            color: #198754;
        }
        /* Fix the product image styling */
        .product-img {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border-radius: 4px;
            position: relative; /* Change from absolute to relative */
            display: block;
            margin: 0;
            top: auto;
            left: auto;
        }
        .timeline {
            margin-top: 40px;
            position: relative;
        }
        .timeline:before {
            content: '';
            position: absolute;
            height: 100%;
            left: 1rem;
            border-left: 2px solid #dee2e6;
        }
        .timeline-item {
            padding-left: 40px;
            position: relative;
            padding-bottom: 20px;
        }
        .timeline-marker {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            border: 2px solid #0d6efd;
            background: white;
            position: absolute;
            left: 0;
        }
        .timeline-active .timeline-marker {
            background: #0d6efd;
        }
    </style>
</head>
<body>
    <?php include('includes/header.php'); ?>
    
    <div class="container py-5">
        <div class="thank-you-container">
            <?php if (isset($generic_success)): ?>
                <!-- Generic success message when no order details are available -->
                <div class="text-center mb-5">
                    <div class="order-success-icon">
                        <i class="bi bi-check-lg"></i>
                    </div>
                    <h1 class="mb-4">Cảm ơn bạn!</h1>
                    <p class="lead mb-4"><?php echo $generic_success; ?></p>
                    <div class="d-grid gap-2 d-md-block">
                        <a href="index.php" class="btn btn-outline-secondary me-2">
                            <i class="bi bi-house-door"></i> Trang chủ
                        </a>
                        <a href="sanpham.php" class="btn btn-primary">
                            <i class="bi bi-bag"></i> Tiếp tục mua sắm
                        </a>
                    </div>
                </div>
            <?php elseif (isset($order)): ?>
                <!-- Order success with details -->
                <div class="text-center mb-4">
                    <div class="order-success-icon">
                        <i class="bi bi-check-lg"></i>
                    </div>
                    <h1 class="mb-3">Đặt hàng thành công!</h1>
                    <?php if (!empty($payment_message)): ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle-fill me-2"></i> <?php echo $payment_message; ?>
                        </div>
                    <?php endif; ?>
                    <p class="lead">Cảm ơn bạn đã mua sắm tại Bug Shop.</p>
                </div>
                
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h5 class="mb-3">Thông tin đơn hàng</h5>
                                <p><strong>Mã đơn hàng:</strong> #<?php echo $order['ma_donhang']; ?></p>
                                <p><strong>Ngày đặt hàng:</strong> <?php echo date('d/m/Y H:i', strtotime($order['ngay_dat'])); ?></p>
                                <p><strong>Trạng thái:</strong> 
                                    <?php if ($order['trang_thai_don_hang'] == 1): ?>
                                        <span class="badge bg-warning text-dark">Chờ xác nhận</span>
                                    <?php elseif ($order['trang_thai_don_hang'] == 2): ?>
                                        <span class="badge bg-info">Đã xác nhận</span>
                                    <?php elseif ($order['trang_thai_don_hang'] == 3): ?>
                                        <span class="badge bg-primary">Đang giao hàng</span>
                                    <?php elseif ($order['trang_thai_don_hang'] == 4): ?>
                                        <span class="badge bg-success">Đã giao hàng</span>
                                    <?php elseif ($order['trang_thai_don_hang'] == 5): ?>
                                        <span class="badge bg-danger">Đã hủy</span>
                                    <?php endif; ?>
                                </p>
                                <p>
                                    <strong>Phương thức thanh toán:</strong> 
                                    <?php if ($order['phuong_thuc_thanh_toan'] == 'cod'): ?>
                                        <span>COD (Thanh toán khi nhận hàng)</span>
                                    <?php elseif ($order['phuong_thuc_thanh_toan'] == 'vnpay'): ?>
                                        <span>VNPAY</span>
                                    <?php else: ?>
                                        <span><?php echo $order['phuong_thuc_thanh_toan']; ?></span>
                                    <?php endif; ?>
                                </p>
                                <?php if ($order['trang_thai_thanh_toan'] == 1): ?>
                                    <p><strong>Trạng thái thanh toán:</strong> <span class="text-success">Đã thanh toán</span></p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <h5 class="mb-3">Thông tin nhận hàng</h5>
                                <p><strong>Họ tên:</strong> <?php echo $order['ho_ten']; ?></p>
                                <p><strong>Số điện thoại:</strong> <?php echo $order['sodienthoai']; ?></p>
                                <p><strong>Email:</strong> <?php echo $order['email']; ?></p>
                                <p><strong>Địa chỉ:</strong> <?php echo $order['full_address']; ?></p>
                                <?php if (!empty($order['ghi_chu'])): ?>
                                    <p><strong>Ghi chú:</strong> <?php echo $order['ghi_chu']; ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Chi tiết đơn hàng</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Sản phẩm</th>
                                        <th>Đơn giá</th>
                                        <th class="text-center">Số lượng</th>
                                        <th class="text-end">Thành tiền</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($order_items as $item): ?>
                                        <?php
                                        // Process image path for display
                                        $img_path = 'images/no-image.png'; // Default image
                                        if (!empty($item['hinhanh'])) {
                                            // Check if path already contains uploads/ prefix
                                            if (strpos($item['hinhanh'], 'uploads/') === 0) {
                                                $img_path = $item['hinhanh'];
                                            } else if (file_exists('uploads/products/' . $item['hinhanh'])) {
                                                $img_path = 'uploads/products/' . $item['hinhanh'];
                                            } else if (file_exists($item['hinhanh'])) {
                                                $img_path = $item['hinhanh'];
                                            }
                                        }
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="flex-shrink-0 me-3">
                                                        <img src="<?php echo $img_path; ?>" alt="<?php echo $item['tensanpham']; ?>" class="product-img">
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-0"><?php echo $item['tensanpham']; ?></h6>
                                                        <small class="text-muted">
                                                            <?php if (!empty($item['ten_kichthuoc'])): ?>
                                                                Size: <?php echo $item['ten_kichthuoc']; ?>
                                                            <?php endif; ?>
                                                            <?php if (!empty($item['ten_mau'])): ?>
                                                                | Màu: <?php echo $item['ten_mau']; ?>
                                                            <?php endif; ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo number_format($item['gia'], 0, ',', '.'); ?>₫</td>
                                            <td class="text-center"><?php echo $item['soluong']; ?></td>
                                            <td class="text-end"><?php echo number_format($item['thanh_tien'], 0, ',', '.'); ?>₫</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot class="table-light">
                                    <tr>
                                        <td colspan="3" class="text-end"><strong>Tạm tính:</strong></td>
                                        <td class="text-end"><?php echo number_format($order['tong_tien'], 0, ',', '.'); ?>₫</td>
                                    </tr>
                                    <?php if ($order['giam_gia'] > 0): ?>
                                    <tr>
                                        <td colspan="3" class="text-end"><strong>Giảm giá:</strong></td>
                                        <td class="text-end">-<?php echo number_format($order['giam_gia'], 0, ',', '.'); ?>₫</td>
                                    </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <td colspan="3" class="text-end"><strong>Phí vận chuyển:</strong></td>
                                        <td class="text-end"><?php echo number_format($order['phi_vanchuyen'], 0, ',', '.'); ?>₫</td>
                                    </tr>
                                    <tr>
                                        <td colspan="3" class="text-end"><strong>Tổng cộng:</strong></td>
                                        <td class="text-end fw-bold fs-5 text-danger"><?php echo number_format($order['thanh_tien'], 0, ',', '.'); ?>₫</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Order status timeline -->
                <div class="timeline mb-4">
                    <h5 class="mb-3">Trạng thái đơn hàng</h5>
                    
                    <div class="timeline-item <?php echo $order['trang_thai_don_hang'] >= 1 ? 'timeline-active' : ''; ?>">
                        <div class="timeline-marker"></div>
                        <div class="timeline-content">
                            <h6 class="mb-0">Đã đặt hàng</h6>
                            <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($order['ngay_dat'])); ?></small>
                        </div>
                    </div>
                    
                    <div class="timeline-item <?php echo $order['trang_thai_don_hang'] >= 2 ? 'timeline-active' : ''; ?>">
                        <div class="timeline-marker"></div>
                        <div class="timeline-content">
                            <h6 class="mb-0">Đã xác nhận</h6>
                            <?php if ($order['trang_thai_don_hang'] >= 2 && $order['ngay_capnhat']): ?>
                                <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($order['ngay_capnhat'])); ?></small>
                            <?php else: ?>
                                <small class="text-muted">Đang chờ xử lý</small>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="timeline-item <?php echo $order['trang_thai_don_hang'] >= 3 ? 'timeline-active' : ''; ?>">
                        <div class="timeline-marker"></div>
                        <div class="timeline-content">
                            <h6 class="mb-0">Đang giao hàng</h6>
                            <?php if ($order['trang_thai_don_hang'] >= 3): ?>
                                <?php if ($order['ngay_capnhat']): ?>
                                    <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($order['ngay_capnhat'])); ?></small>
                                <?php endif; ?>
                            <?php else: ?>
                                <small class="text-muted">Chưa giao hàng</small>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="timeline-item <?php echo $order['trang_thai_don_hang'] >= 4 ? 'timeline-active' : ''; ?>">
                        <div class="timeline-marker"></div>
                        <div class="timeline-content">
                            <h6 class="mb-0">Đã giao hàng</h6>
                            <?php if ($order['trang_thai_don_hang'] >= 4): ?>
                                <?php if ($order['ngay_capnhat']): ?>
                                    <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($order['ngay_capnhat'])); ?></small>
                                <?php endif; ?>
                            <?php else: ?>
                                <small class="text-muted">Chưa hoàn thành</small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-center mt-5">
                    <a href="index.php" class="btn btn-outline-secondary me-md-2">
                        <i class="bi bi-house-door"></i> Trang chủ
                    </a>
                    <a href="donhang.php" class="btn btn-outline-primary me-md-2">
                        <i class="bi bi-receipt"></i> Xem đơn hàng của tôi
                    </a>
                    <a href="sanpham.php" class="btn btn-primary">
                        <i class="bi bi-bag"></i> Tiếp tục mua sắm
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include('includes/footer.php'); ?>
</body>
</html>