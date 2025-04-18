<?php
session_start();
include 'config/config.php';
include 'config/vnpay_config.php';

// Check if payment info exists in session
if (!isset($_SESSION['payment_info'])) {
    $_SESSION['error_message'] = 'Không tìm thấy thông tin thanh toán';
    header('Location: thanhtoan.php');
    exit();
}

$payment_info = $_SESSION['payment_info'];

// Get order information
$order_id = $payment_info['id'];
$order_code = $payment_info['ma_donhang'];
$amount = $payment_info['amount'];
$order_desc = $payment_info['order_desc'] ?? 'Thanh toán đơn hàng';

// Allow guest checkout - don't require user to be logged in
$user_id = isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : null;

// Create VNPAY payment URL
$vnp_TxnRef = $order_code; // Mã đơn hàng
$vnp_OrderInfo = 'Thanh toan don hang ' . $order_code . ' tai Bug Shop';
$vnp_OrderType = 'billpayment';
$vnp_Amount = $amount * 100; // Amount in VND, converted to smallest unit
$vnp_Locale = 'vn';
$vnp_BankCode = '';
$vnp_IpAddr = $_SERVER['REMOTE_ADDR'];

// Create the payment parameter array with correct values
$inputData = array(
    "vnp_Version" => "2.1.0",
    "vnp_TmnCode" => $vnp_TmnCode,
    "vnp_Amount" => $vnp_Amount,
    "vnp_Command" => "pay",
    "vnp_CreateDate" => date('YmdHis'),
    "vnp_CurrCode" => "VND",
    "vnp_IpAddr" => $vnp_IpAddr,
    "vnp_Locale" => $vnp_Locale,
    "vnp_OrderInfo" => $vnp_OrderInfo,
    "vnp_OrderType" => $vnp_OrderType,
    "vnp_ReturnUrl" => $vnp_ReturnUrl,
    "vnp_TxnRef" => $vnp_TxnRef,
);

// Add bank code if specified
if (isset($vnp_BankCode) && $vnp_BankCode != "") {
    $inputData['vnp_BankCode'] = $vnp_BankCode;
}

// Log the payment data for debugging
error_log("VNPAY Payment Data: " . json_encode($inputData));

// Create the payment URL using the helper function
$vnp_Url = vnpay_create_payment_url($inputData, $vnp_HashSecret, $vnp_Url);

// Save payment attempt to database for tracking
try {
    $savePayment = $conn->prepare("
        INSERT INTO payment_logs (order_id, user_id, payment_method, amount, payment_data, status, created_at)
        VALUES (?, ?, 'vnpay', ?, ?, 'pending', NOW())
    ");
    $paymentData = json_encode($inputData);
    $savePayment->bind_param("iids", $order_id, $user_id, $amount, $paymentData);
    $savePayment->execute();
} catch (Exception $e) {
    error_log("Error saving payment log: " . $e->getMessage());
    // Continue anyway - this is just for logging
}

// Debug the final URL
error_log("VNPAY Final URL: " . $vnp_Url);

// Redirect to VNPAY payment page
header('Location: ' . $vnp_Url);
exit();
?>