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
$vnp_TxnRef = isset($_GET['vnp_TxnRef']) ? $_GET['vnp_TxnRef'] : '';
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

// Kiểm tra xem đơn hàng đã tồn tại trong cơ sở dữ liệu chưa
$order_id = $vnp_TxnRef;
$order_query = $conn->prepare("SELECT id_donhang, tongtien, trangthai FROM donhang WHERE id_donhang = ?");
$order_query->bind_param("i", $order_id);
$order_query->execute();
$order_result = $order_query->get_result();
$order_exists = $order_result->num_rows > 0;

if ($order_exists) {
    $order_data = $order_result->fetch_assoc();
    $current_status = $order_data['trangthai'];
    $order_amount = $order_data['tongtien'];
    
    // Nếu đơn hàng đã được xử lý thanh toán trước đó
    if ($current_status >= 2) {
        $_SESSION['payment_message'] = 'Đơn hàng này đã được xử lý thanh toán trước đó!';
        header('Location: thanh-toan-thanh-cong.php?orderId=' . $order_id);
        exit();
    }
}

// Kiểm tra kết quả giao dịch từ VNPAY
if ($isValidSignature) {
    if ($vnp_ResponseCode == '00') {
        // Thanh toán thành công
        // Cập nhật trạng thái đơn hàng
        if ($order_exists) {
            // Cập nhật trạng thái đơn hàng thành "Đang xử lý" (trạng thái 2)
            $update_order = $conn->prepare("UPDATE donhang SET trangthai = 2, phuongthucthanhtoan = 'vnpay', ngaycapnhat = NOW() WHERE id_donhang = ?");
            $update_order->bind_param("i", $order_id);
            $update_order->execute();
            
            // Ghi lịch sử đơn hàng
            $action = "Thanh toán VNPAY thành công";
            $performer = "Hệ thống";
            $note = "Thanh toán qua VNPAY với mã giao dịch: " . $vnp_TransactionNo;
            
            $history_query = $conn->prepare("INSERT INTO donhang_lichsu (id_donhang, hanh_dong, nguoi_thuchien, ghi_chu, ngay_thaydoi) VALUES (?, ?, ?, ?, NOW())");
            $history_query->bind_param("isss", $order_id, $action, $performer, $note);
            $history_query->execute();
            
            // Lưu thông tin thanh toán
            $method = "vnpay";
            $bank_query = $conn->prepare("INSERT INTO thanh_toan (id_donhang, ma_giaodich, so_tien, phuong_thuc, ngan_hang, ngay_thanhtoan, trang_thai, ghi_chu) VALUES (?, ?, ?, ?, ?, NOW(), 1, ?)");
            $status = 1; // Thành công
            $payment_date = date('Y-m-d H:i:s', strtotime($vnp_PayDate));
            $bank_query->bind_param("isdsss", $order_id, $vnp_TransactionNo, $vnp_Amount, $method, $vnp_BankCode, $vnp_OrderInfo);
            $bank_query->execute();
            
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
        // Thanh toán thất bại/bị hủy nhưng vẫn lưu vào database
        if ($order_exists) {
            // Lưu thông tin thanh toán thất bại vào database
            $method = "vnpay";
            $bank_query = $conn->prepare("INSERT INTO thanh_toan (id_donhang, ma_giaodich, so_tien, phuong_thuc, ngan_hang, ngay_thanhtoan, trang_thai, ghi_chu) VALUES (?, ?, ?, ?, ?, NOW(), 0, ?)");
            $status = 0; // Thất bại
            $error_note = 'Thanh toán thất bại qua VNPAY. Mã lỗi: ' . $vnp_ResponseCode;
            $bank_query->bind_param("isdsss", $order_id, $vnp_TransactionNo, $vnp_Amount, $method, $vnp_BankCode, $error_note);
            $bank_query->execute();
            
            // Ghi lịch sử đơn hàng
            $action = "Thanh toán VNPAY thất bại";
            $performer = "Hệ thống";
            $note = "Thanh toán qua VNPAY thất bại với mã giao dịch: " . $vnp_TransactionNo . ". Mã lỗi: " . $vnp_ResponseCode;
            
            $history_query = $conn->prepare("INSERT INTO donhang_lichsu (id_donhang, hanh_dong, nguoi_thuchien, ghi_chu, ngay_thaydoi) VALUES (?, ?, ?, ?, NOW())");
            $history_query->bind_param("isss", $order_id, $action, $performer, $note);
            $history_query->execute();
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