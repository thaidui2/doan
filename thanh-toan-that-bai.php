<?php
session_start();
include('config/config.php');

$payment_error = isset($_SESSION['payment_error']) ? $_SESSION['payment_error'] : "Đã có lỗi xảy ra trong quá trình thanh toán!";
$order_id = isset($_SESSION['last_order_id']) ? $_SESSION['last_order_id'] : 0;

// Xóa thông tin thanh toán trong session sau khi hiển thị
unset($_SESSION['payment_error']);
unset($_SESSION['payment_info']);
unset($_SESSION['vnpay_payment']);

// Lấy thông tin chi tiết lỗi thanh toán từ database nếu có
$payment_details = [];
if ($order_id > 0) {
    $query = $conn->prepare("SELECT * FROM thanh_toan WHERE id_donhang = ? AND trang_thai = 0 ORDER BY ngay_tao DESC LIMIT 1");
    $query->bind_param("i", $order_id);
    $query->execute();
    $result = $query->get_result();
    if ($result->num_rows > 0) {
        $payment_details = $result->fetch_assoc();
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanh toán thất bại - Bug Shop</title>
    <link rel="stylesheet" href="node_modules/bootstrap/dist/css/bootstrap.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <style>
        .error-icon {
            font-size: 100px;
            color: #dc3545;
            display: block;
            margin: 0 auto;
            text-align: center;
        }
        
        .error-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 40px 20px;
            text-align: center;
        }
    </style>
</head>
<body>
    <?php include('includes/header.php'); ?>
    
    <div class="container py-5">
        <div class="error-container">
            <i class="bi bi-x-circle-fill error-icon"></i>
            <h1 class="mt-4">Thanh toán không thành công!</h1>
            <p class="lead text-danger"><?php echo $payment_error; ?></p>
            
            <?php if (!empty($payment_details)): ?>
            <div class="alert alert-secondary mt-3">
                <h5 class="mb-3">Chi tiết giao dịch</h5>
                <p class="mb-1"><strong>Mã giao dịch:</strong> <?php echo htmlspecialchars($payment_details['ma_giaodich']); ?></p>
                <p class="mb-1"><strong>Thời gian:</strong> <?php echo date('d/m/Y H:i:s', strtotime($payment_details['ngay_thanhtoan'])); ?></p>
                <p class="mb-1"><strong>Phương thức:</strong> <?php echo htmlspecialchars(strtoupper($payment_details['phuong_thuc'])); ?></p>
                <p class="mb-0"><strong>Ghi chú:</strong> <?php echo htmlspecialchars($payment_details['ghi_chu']); ?></p>
            </div>
            <?php endif; ?>
            
            <div class="mt-4">
                <p class="text-muted">Đơn hàng của bạn chưa được thanh toán. Bạn có thể thử lại hoặc chọn phương thức thanh toán khác.</p>
                <a href="giohang.php" class="btn btn-primary me-2">Quay lại giỏ hàng</a>
                <a href="index.php" class="btn btn-outline-primary">Tiếp tục mua sắm</a>
            </div>
        </div>
    </div>
    
    <?php include('includes/footer.php'); ?>
    <script src="node_modules/bootstrap/dist/js/bootstrap.bundle.js"></script>
</body>
</html>