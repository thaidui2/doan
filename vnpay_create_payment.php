<?php
session_start();
include('config/config.php');
include('config/vnpay_config.php');

if (!isset($_SESSION['payment_info']) || !is_array($_SESSION['payment_info'])) {
    $_SESSION['error_message'] = 'Không tìm thấy thông tin thanh toán!';
    header('Location: thanhtoan.php');
    exit();
}

$payment_info = $_SESSION['payment_info'];

// Thông tin đơn hàng
$order_id = isset($payment_info['order_id']) ? $payment_info['order_id'] : time(); // Mã đơn hàng
$amount = $payment_info['amount']; // Số tiền thanh toán
$order_desc = isset($payment_info['order_desc']) ? $payment_info['order_desc'] : 'Thanh toan don hang Bug Shop';
$bank_code = ''; // Để trống để hiển thị tất cả ngân hàng
$language = 'vn';

// Tạo tham số cho VNPAY
$vnp_Params = array(
    "vnp_Version" => "2.1.0",
    "vnp_Command" => "pay",
    "vnp_TmnCode" => $vnp_TmnCode,
    "vnp_Amount" => $amount * 100, // VNPAY yêu cầu số tiền * 100 (để xử lý 2 số thập phân)
    "vnp_CreateDate" => date('YmdHis'),
    "vnp_CurrCode" => "VND",
    "vnp_IpAddr" => $_SERVER['REMOTE_ADDR'],
    "vnp_Locale" => $language,
    "vnp_OrderInfo" => $order_desc,
    "vnp_OrderType" => "other",
    "vnp_ReturnUrl" => $vnp_Returnurl,
    "vnp_TxnRef" => $order_id,
);

if (!empty($bank_code)) {
    $vnp_Params["vnp_BankCode"] = $bank_code;
}

// Sắp xếp các tham số theo thứ tự a-z
ksort($vnp_Params);

// Tạo chuỗi query từ mảng params
$query = "";
$i = 0;
$hashdata = "";

foreach ($vnp_Params as $key => $value) {
    if ($i == 1) {
        $hashdata .= '&' . urlencode($key) . "=" . urlencode($value);
    } else {
        $hashdata .= urlencode($key) . "=" . urlencode($value);
        $i = 1;
    }
    $query .= urlencode($key) . "=" . urlencode($value) . '&';
}

// Xóa dấu & cuối cùng
$query = substr($query, 0, strlen($query) - 1);

// Tạo checksum
$vnp_SecureHash = hash_hmac('sha512', $hashdata, $vnp_HashSecret);
$vnpUrl = $vnp_Url . "?" . $query . '&vnp_SecureHash=' . $vnp_SecureHash;

// Lưu thông tin thanh toán vào SESSION để kiểm tra khi VNPAY callback
$_SESSION['vnpay_payment'] = [
    'order_id' => $order_id,
    'amount' => $amount,
    'created_at' => time()
];

// Ghi log
$log_dir = __DIR__ . '/logs';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}
$log_file = $log_dir . '/vnpay_request_' . date('Y-m-d') . '.log';
$log_data = date('Y-m-d H:i:s') . " | Order ID: $order_id | Amount: $amount | URL: $vnpUrl\n";
file_put_contents($log_file, $log_data, FILE_APPEND);

// Chuyển hướng đến trang thanh toán VNPAY
header('Location: ' . $vnpUrl);
exit();