<?php
session_start();
include('config/config.php');
include('config/vnpay_config.php');

// Ghi log
$log_dir = __DIR__ . '/logs';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}
$log_file = $log_dir . '/vnpay_return_' . date('Y-m-d') . '.log';
$log_data = date('Y-m-d H:i:s') . " | Return data: " . json_encode($_GET) . "\n";
file_put_contents($log_file, $log_data, FILE_APPEND);

// Lấy thông tin trả về từ VNPAY
$vnp_ResponseCode = isset($_GET['vnp_ResponseCode']) ? $_GET['vnp_ResponseCode'] : '';
$vnp_TxnRef = isset($_GET['vnp_TxnRef']) ? $_GET['vnp_TxnRef'] : ''; // This is ma_donhang
$vnp_Amount = isset($_GET['vnp_Amount']) ? $_GET['vnp_Amount'] / 100 : 0; // Chia 100 vì VNPAY gửi số tiền * 100
$vnp_TransactionNo = isset($_GET['vnp_TransactionNo']) ? $_GET['vnp_TransactionNo'] : '';
$vnp_BankCode = isset($_GET['vnp_BankCode']) ? $_GET['vnp_BankCode'] : '';
$vnp_PayDate = isset($_GET['vnp_PayDate']) ? $_GET['vnp_PayDate'] : '';
$vnp_OrderInfo = isset($_GET['vnp_OrderInfo']) ? $_GET['vnp_OrderInfo'] : '';
$vnp_SecureHash = isset($_GET['vnp_SecureHash']) ? $_GET['vnp_SecureHash'] : '';

// Kiểm tra tính hợp lệ của giao dịch
$inputData = array();
foreach ($_GET as $key => $value) {
    if (substr($key, 0, 4) == "vnp_") {
        $inputData[$key] = $value;
    }
}
unset($inputData['vnp_SecureHash']);
ksort($inputData);
$i = 0;
$hashData = "";
foreach ($inputData as $key => $value) {
    if ($i == 1) {
        $hashData .= '&' . urlencode($key) . "=" . urlencode($value);
    } else {
        $hashData .= urlencode($key) . "=" . urlencode($value);
        $i = 1;
    }
}

$secureHash = hash_hmac('sha512', $hashData, $vnp_HashSecret);
$isValidSignature = ($secureHash == $vnp_SecureHash);

// DEBUG
error_log("VNPAY return signature validation: " . ($isValidSignature ? "Valid" : "Invalid"));

// Kiểm tra xem đơn hàng đã tồn tại trong cơ sở dữ liệu chưa - UPDATED COLUMN NAMES
$order_query = $conn->prepare("SELECT id, ma_donhang, thanh_tien, trang_thai_don_hang FROM donhang WHERE ma_donhang = ?");
$order_query->bind_param("s", $vnp_TxnRef);
$order_query->execute();
$order_result = $order_query->get_result();
$order_exists = $order_result->num_rows > 0;

if ($order_exists) {
    $order_data = $order_result->fetch_assoc();
    $order_id = $order_data['id'];
    $current_status = $order_data['trang_thai_don_hang'];
    $order_amount = $order_data['thanh_tien'];
    
    // Nếu đơn hàng đã được xử lý thanh toán trước đó
    if ($current_status >= 2) {
        $_SESSION['payment_message'] = 'Đơn hàng này đã được xử lý thanh toán trước đó!';
        header('Location: thanh-toan-thanh-cong.php?orderId=' . $order_id);
        exit();
    }
} else {
    error_log("Order not found: " . $vnp_TxnRef);
}

// Kiểm tra kết quả giao dịch từ VNPAY
if ($isValidSignature) {
    if ($vnp_ResponseCode == '00') {
        // Thanh toán thành công
        if ($order_exists) {
            // Cập nhật trạng thái đơn hàng thành "Đã xác nhận" (trạng thái 2)
            $update_order = $conn->prepare("
                UPDATE donhang 
                SET trang_thai_don_hang = 2, 
                    phuong_thuc_thanh_toan = 'vnpay', 
                    trang_thai_thanh_toan = 1,
                    ma_giao_dich = ?,
                    ngay_capnhat = NOW() 
                WHERE id = ?
            ");
            $update_order->bind_param("si", $vnp_TransactionNo, $order_id);
            $update_order->execute();
            
            // Ghi lịch sử đơn hàng
            $action = "Thanh toán VNPAY thành công";
            $performer = "Hệ thống";
            $note = "Thanh toán qua VNPAY với mã giao dịch: " . $vnp_TransactionNo;
            
            $history_query = $conn->prepare("
                INSERT INTO donhang_lichsu 
                (id_donhang, hanh_dong, nguoi_thuchien, ghi_chu, ngay_thaydoi) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $history_query->bind_param("isss", $order_id, $action, $performer, $note);
            $history_query->execute();
            
            $_SESSION['payment_success'] = true;
            $_SESSION['payment_message'] = 'Thanh toán thành công qua VNPAY!';
            header('Location: thanh-toan-thanh-cong.php?orderId=' . $order_id);
            exit();
        } else {
            // Đơn hàng không tồn tại
            $_SESSION['payment_error'] = 'Không tìm thấy đơn hàng trong hệ thống!';
            header('Location: thanh-toan-that-bai.php');
            exit();
        }
    } else {
        // Thanh toán thất bại/bị hủy
        if ($order_exists) {
            // Ghi lịch sử đơn hàng
            $action = "Thanh toán VNPAY thất bại";
            $performer = "Hệ thống";
            $note = "Thanh toán qua VNPAY thất bại. Mã giao dịch: " . $vnp_TransactionNo . ". Mã lỗi: " . $vnp_ResponseCode;
            
            $history_query = $conn->prepare("
                INSERT INTO donhang_lichsu 
                (id_donhang, hanh_dong, nguoi_thuchien, ghi_chu, ngay_thaydoi) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $history_query->bind_param("isss", $order_id, $action, $performer, $note);
            $history_query->execute();
            
            // Cập nhật đơn hàng với thông tin thất bại
            $update_order = $conn->prepare("
                UPDATE donhang 
                SET trang_thai_thanh_toan = 0,
                    ma_giao_dich = ?,
                    ghi_chu = CONCAT(IFNULL(ghi_chu, ''), '\nThanh toán VNPAY thất bại. Mã lỗi: ', ?),
                    ngay_capnhat = NOW() 
                WHERE id = ?
            ");
            $update_order->bind_param("ssi", $vnp_TransactionNo, $vnp_ResponseCode, $order_id);
            $update_order->execute();
        }
        
        $_SESSION['payment_error'] = 'Thanh toán không thành công. Mã lỗi: ' . $vnp_ResponseCode;
        header('Location: thanh-toan-that-bai.php');
        exit();
    }
} else {
    // Chữ ký không hợp lệ
    $_SESSION['payment_error'] = 'Chữ ký không hợp lệ!';
    header('Location: thanh-toan-that-bai.php');
    exit();
}