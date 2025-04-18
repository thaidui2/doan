<?php
session_start();
include('config/config.php');
include('config/vnpay_config.php');

// For debugging - log all received data
error_log("VNPAY Return Data: " . print_r($_GET, true));

// Initialize variables
$success = false;
$message = '';
$order_id = '';
$vnp_TransactionNo = '';
$vnp_ResponseCode = '';
$transaction_status = '';

// Get user ID if logged in, otherwise null (for guest checkout)
$user_id = isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : null;

if (isset($_GET['vnp_ResponseCode'])) {
    // Get response data
    $vnp_ResponseCode = $_GET['vnp_ResponseCode'];
    $vnp_TxnRef = $_GET['vnp_TxnRef']; // Mã đơn hàng
    $vnp_Amount = $_GET['vnp_Amount'] / 100; // Số tiền thanh toán
    $vnp_TransactionNo = $_GET['vnp_TransactionNo'] ?? ''; // Mã giao dịch tại VNPAY
    $vnp_BankCode = $_GET['vnp_BankCode'] ?? ''; // Mã ngân hàng
    $vnp_PayDate = $_GET['vnp_PayDate'] ?? ''; // Thời gian thanh toán
    $vnp_OrderInfo = $_GET['vnp_OrderInfo'] ?? ''; // Thông tin thanh toán
    
    // Verify signature
    $inputData = [];
    foreach ($_GET as $key => $value) {
        if (substr($key, 0, 4) == "vnp_") {
            $inputData[$key] = $value;
        }
    }
    
    unset($inputData['vnp_SecureHash']);
    unset($inputData['vnp_SecureHashType']);
    
    ksort($inputData);
    $i = 0;
    $hashData = "";
    foreach ($inputData as $key => $value) {
        if ($i == 1) {
            $hashData = $hashData . '&' . urlencode($key) . "=" . urlencode($value);
        } else {
            $hashData = $hashData . urlencode($key) . "=" . urlencode($value);
            $i = 1;
        }
    }
    
    $secureHash = hash_hmac('sha512', $hashData, $vnp_HashSecret);
    $validSignature = ($_GET["vnp_SecureHash"] === $secureHash);
    
    // Get order from database
    $order_query = $conn->prepare("SELECT * FROM donhang WHERE ma_donhang = ?");
    $order_query->bind_param("s", $vnp_TxnRef);
    $order_query->execute();
    $result = $order_query->get_result();
    
    if ($result->num_rows === 0) {
        $success = false;
        $message = 'Không tìm thấy đơn hàng';
        error_log("Order not found: $vnp_TxnRef");
    } else {
        $order = $result->fetch_assoc();
        $order_id = $order['id'];
        $order_user_id = $order['id_user']; 
        
        // Update payment status based on response code
        if ($vnp_ResponseCode == "00") {
            // Payment successful
            $success = true;
            $transaction_status = "Thành công";
            
            // Update order status
            $update_stmt = $conn->prepare("
                UPDATE donhang 
                SET trang_thai_thanh_toan = 1, 
                    ma_giao_dich = ?,
                    trang_thai_don_hang = 2
                WHERE id = ?
            ");
            $update_stmt->bind_param("si", $vnp_TransactionNo, $order_id);
            $update_stmt->execute();
            
            // Log order status change
            $log_query = $conn->prepare("
                INSERT INTO donhang_lichsu (id_donhang, hanh_dong, nguoi_thuchien, ghi_chu)
                VALUES (?, 'Thanh toán VNPAY thành công', 'Hệ thống', ?)
            ");
            $log_note = "Thanh toán qua VNPAY với mã giao dịch: " . $vnp_TransactionNo;
            $log_query->bind_param("is", $order_id, $log_note);
            $log_query->execute();
            
            $message = 'Thanh toán thành công';
            error_log("Payment successful for order: $vnp_TxnRef");
        } else {
            // Payment failed
            $success = false;
            $transaction_status = "Thất bại";
            $message = 'Thanh toán không thành công. Mã lỗi: ' . $vnp_ResponseCode;
            error_log("Payment failed for order: $vnp_TxnRef, Error code: $vnp_ResponseCode");
            
            // Log payment failure
            $log_query = $conn->prepare("
                INSERT INTO donhang_lichsu (id_donhang, hanh_dong, nguoi_thuchien, ghi_chu)
                VALUES (?, 'Thanh toán VNPAY thất bại', 'Hệ thống', ?)
            ");
            $log_note = "Thanh toán qua VNPAY thất bại. Mã lỗi: " . $vnp_ResponseCode;
            $log_query->bind_param("is", $order_id, $log_note);
            $log_query->execute();
        }
        
        // Record payment transaction
        try {
            $payment_log = $conn->prepare("
                INSERT INTO payment_logs (order_id, user_id, payment_method, transaction_id, amount, status, response_code, payment_data, created_at)
                VALUES (?, ?, 'vnpay', ?, ?, ?, ?, ?, NOW())
            ");
            $payment_data = json_encode($_GET);
            $payment_log->bind_param("iisdiss", $order_id, $order_user_id, $vnp_TransactionNo, $vnp_Amount, $transaction_status, $vnp_ResponseCode, $payment_data);
            $payment_log->execute();
        } catch (Exception $e) {
            error_log("Error recording payment log: " . $e->getMessage());
        }
    }
} else {
    $success = false;
    $message = 'Không nhận được phản hồi từ VNPAY';
    error_log("No response from VNPAY");
}

// Format payment date if available
$formatted_payment_date = '';
if (!empty($vnp_PayDate)) {
    try {
        $payment_date = DateTime::createFromFormat('YmdHis', $vnp_PayDate);
        if ($payment_date) {
            $formatted_payment_date = $payment_date->format('d/m/Y H:i:s');
        }
    } catch (Exception $e) {
        error_log("Error formatting payment date: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kết quả thanh toán - Bug Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>
    <?php include('includes/head.php'); ?>
    <?php include('includes/header.php'); ?>
    
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow">
                    <div class="card-body text-center p-5">
                        <?php if ($success): ?>
                            <div class="text-success mb-4">
                                <i class="bi bi-check-circle-fill" style="font-size: 5rem;"></i>
                            </div>
                            <h2 class="mb-4">Thanh toán thành công!</h2>
                            <p class="mb-4">Cảm ơn bạn đã đặt hàng. Đơn hàng của bạn đã được xác nhận.</p>
                            <div class="d-grid gap-3">
                                <a href="chitiet-donhang.php?id=<?php echo $order_id; ?>" class="btn btn-primary">
                                    Xem chi tiết đơn hàng
                                </a>
                                <a href="index.php" class="btn btn-outline-secondary">
                                    Tiếp tục mua sắm
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="text-danger mb-4">
                                <i class="bi bi-x-circle-fill" style="font-size: 5rem;"></i>
                            </div>
                            <h2 class="mb-4">Thanh toán không thành công</h2>
                            <p class="mb-4"><?php echo htmlspecialchars($message); ?></p>
                            <div class="d-grid gap-3">
                                <a href="thanhtoan.php" class="btn btn-primary">
                                    Thử lại thanh toán
                                </a>
                                <a href="giohang.php" class="btn btn-outline-secondary">
                                    Quay lại giỏ hàng
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Thông tin giao dịch -->
                <?php if (!empty($vnp_TransactionNo)): ?>
                <div class="card mt-4">
                    <div class="card-header bg-light">
                        <h5 class="card-title mb-0">Thông tin giao dịch</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Mã đơn hàng:</span>
                                <strong><?php echo htmlspecialchars($vnp_TxnRef); ?></strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Số tiền:</span>
                                <strong><?php echo number_format($vnp_Amount, 0, ',', '.'); ?>₫</strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Mã giao dịch VNPAY:</span>
                                <strong><?php echo htmlspecialchars($vnp_TransactionNo); ?></strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Ngân hàng:</span>
                                <strong><?php echo htmlspecialchars($vnp_BankCode ?? 'N/A'); ?></strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Thời gian:</span>
                                <strong><?php echo $formatted_payment_date ?: 'N/A'; ?></strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Trạng thái:</span>
                                <strong class="<?php echo $success ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo htmlspecialchars($transaction_status); ?>
                                </strong>
                            </li>
                        </ul>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php include('includes/footer.php'); ?>
</body>
</html>