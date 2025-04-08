<?php
session_start();
include('config/config.php');

// Kiểm tra có thông tin order không
$order_id = isset($_GET['orderId']) ? (int)$_GET['orderId'] : 0;
$payment_success = isset($_SESSION['payment_success']) ? $_SESSION['payment_success'] : false;
$payment_message = isset($_SESSION['payment_message']) ? $_SESSION['payment_message'] : "Đặt hàng thành công!";

// Xóa thông tin thanh toán trong session sau khi hiển thị
unset($_SESSION['payment_success']);
unset($_SESSION['payment_message']);
unset($_SESSION['payment_info']);
unset($_SESSION['vnpay_payment']);
unset($_SESSION['checkout_items']);
unset($_SESSION['checkout_type']);

// Nếu có order_id, lấy thông tin đơn hàng
$order_info = [];
if ($order_id > 0) {
    $order_query = $conn->prepare("
        SELECT dh.*, COUNT(dc.id_chitiet) as total_items 
        FROM donhang dh 
        LEFT JOIN donhang_chitiet dc ON dh.id_donhang = dc.id_donhang 
        WHERE dh.id_donhang = ?
        GROUP BY dh.id_donhang
    ");
    $order_query->bind_param("i", $order_id);
    $order_query->execute();
    $result = $order_query->get_result();
    
    if ($result->num_rows > 0) {
        $order_info = $result->fetch_assoc();
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
    <link rel="stylesheet" href="css/styles.css">
    <style>
        .success-icon {
            font-size: 100px;
            color: #28a745;
            display: block;
            margin: 0 auto;
            text-align: center;
        }
        
        .success-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 40px 20px;
            text-align: center;
        }
        
        .order-details {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            text-align: left;
        }
    </style>
</head>
<body>
    <?php include('includes/header.php'); ?>
    
    <div class="container py-5">
        <div class="success-container">
            <i class="bi bi-check-circle-fill success-icon"></i>
            <h1 class="mt-4">Đặt hàng thành công!</h1>
            <p class="lead text-success"><?php echo $payment_message; ?></p>
            
            <?php if (!empty($order_info)): ?>
            <div class="order-details">
                <h4>Thông tin đơn hàng #<?php echo $order_info['id_donhang']; ?></h4>
                <hr>
                <div class="row mb-2">
                    <div class="col-6 text-muted">Ngày đặt hàng:</div>
                    <div class="col-6 text-end"><?php echo date('d/m/Y H:i', strtotime($order_info['ngaytao'])); ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-6 text-muted">Sản phẩm:</div>
                    <div class="col-6 text-end"><?php echo $order_info['total_items']; ?> sản phẩm</div>
                </div>
                <div class="row mb-2">
                    <div class="col-6 text-muted">Tổng tiền:</div>
                    <div class="col-6 text-end fw-bold"><?php echo number_format($order_info['tongtien'], 0, ',', '.'); ?> ₫</div>
                </div>
                <div class="row mb-2">
                    <div class="col-6 text-muted">Phương thức thanh toán:</div>
                    <div class="col-6 text-end">
                        <?php
                        switch($order_info['phuongthucthanhtoan']) {
                            case 'cod':
                                echo 'Thanh toán khi nhận hàng (COD)';
                                break;
                            case 'bank_transfer':
                                echo 'Chuyển khoản ngân hàng';
                                break;
                            case 'vnpay':
                                echo 'VNPAY';
                                break;
                            default:
                                echo ucfirst($order_info['phuongthucthanhtoan']);
                        }
                        ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="mt-4">
                <a href="index.php" class="btn btn-primary me-2">Tiếp tục mua sắm</a>
                <?php if (!empty($order_info)): ?>
                <a href="don-hang.php?id=<?php echo $order_info['id_donhang']; ?>" class="btn btn-outline-primary">Xem chi tiết đơn hàng</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php include('includes/footer.php'); ?>
    
</body>
</html>