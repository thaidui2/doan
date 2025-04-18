<?php
date_default_timezone_set('Asia/Ho_Chi_Minh');

/**
 * VNPAY Payment Gateway Configuration
 */

// Thông tin cấu hình VNPAY
define('VNPAY_TMN_CODE', 'YOUR_TMN_CODE'); // Terminal ID provided by VNPAY
define('VNPAY_HASH_SECRET', 'YOUR_HASH_SECRET'); // Secret key provided by VNPAY
define('VNPAY_URL', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html'); // Sandbox URL, use real URL for production
define('VNPAY_RETURN_URL', 'https://yourdomain.com/vnpay_return.php'); // Return URL after payment
define('VNPAY_API_URL', 'https://sandbox.vnpayment.vn/merchant_webapi/api/transaction'); // API URL for transaction checking

// Config parameters for development/production environments
define('VNPAY_VERSION', '2.1.0');
define('VNPAY_CURRENCY', 'VND');
define('VNPAY_LOCALE', 'vn');

// Mapping between VNPAY response codes and messages
$vnp_ResponseCode = array(
    "00" => "Giao dịch thành công",
    "01" => "Giao dịch đã tồn tại",
    "02" => "Merchant không hợp lệ (kiểm tra lại vnp_TmnCode)",
    "03" => "Dữ liệu gửi sang không đúng định dạng",
    "04" => "Khởi tạo GD không thành công do Website đang bị tạm khóa",
    "05" => "Giao dịch không thành công do: Quý khách nhập sai mật khẩu quá số lần quy định. Xin quý khách vui lòng thực hiện lại giao dịch",
    "06" => "Giao dịch không thành công do Quý khách nhập sai mật khẩu",
    "07" => "Trừ tiền thành công. Giao dịch bị nghi ngờ (liên quan tới lừa đảo, giao dịch bất thường).",
    "09" => "Giao dịch không thành công do: Thẻ/Tài khoản của khách hàng chưa đăng ký dịch vụ InternetBanking tại ngân hàng.",
    "10" => "Giao dịch không thành công do: Khách hàng xác thực thông tin thẻ/tài khoản không đúng quá 3 lần",
    "11" => "Giao dịch không thành công do: Đã hết hạn chờ thanh toán. Xin quý khách vui lòng thực hiện lại giao dịch.",
    "12" => "Giao dịch không thành công do: Thẻ/Tài khoản của khách hàng bị khóa.",
    "13" => "Giao dịch không thành công do Quý khách nhập sai mật khẩu",
    "24" => "Giao dịch không thành công do: Khách hàng hủy giao dịch",
    "51" => "Giao dịch không thành công do: Tài khoản của quý khách không đủ số dư để thực hiện giao dịch.",
    "65" => "Giao dịch không thành công do: Tài khoản của Quý khách đã vượt quá hạn mức giao dịch trong ngày.",
    "75" => "Ngân hàng thanh toán đang bảo trì.",
    "79" => "Giao dịch không thành công do: KH nhập sai mật khẩu thanh toán quá số lần quy định. Xin quý khách vui lòng thực hiện lại giao dịch",
    "99" => "Các lỗi khác (lỗi còn lại, không có trong danh sách mã lỗi đã liệt kê)"
);

/**
 * Build VNPAY payment URL
 * 
 * @param array $vnp_Params Payment parameters
 * @param string $vnp_HashSecret Secret key provided by VNPAY
 * @param string $vnp_Url VNPAY payment gateway URL
 * @return string Full payment URL with query string
 */
function vnpay_create_payment_url($vnp_Params, $vnp_HashSecret, $vnp_Url) {
    // Remove empty parameters
    foreach ($vnp_Params as $key => $value) {
        if (empty($value)) {
            unset($vnp_Params[$key]);
        }
    }
    
    // Sort parameters by key
    ksort($vnp_Params);
    
    // Build query string
    $query = http_build_query($vnp_Params);
    
    // Create hash data
    $vnp_SecureHash = hash_hmac('sha512', $query, $vnp_HashSecret);
    $vnp_Url .= "?" . $query . '&vnp_SecureHash=' . $vnp_SecureHash;
    
    return $vnp_Url;
}

/**
 * Validate VNPAY returned data
 * 
 * @param array $vnp_Params Parameters from VNPAY return URL
 * @param string $vnp_HashSecret Secret key provided by VNPAY
 * @return bool True if data is valid
 */
function vnpay_validate_data($vnp_Params, $vnp_HashSecret) {
    // Get secure hash from response
    $vnp_SecureHash = $vnp_Params['vnp_SecureHash'];
    
    // Remove secure hash from params to validate
    unset($vnp_Params['vnp_SecureHash']);
    
    // Sort params by key
    ksort($vnp_Params);
    
    // Build query string
    $query = http_build_query($vnp_Params);
    
    // Create hash data
    $vnp_CalcHash = hash_hmac('sha512', $query, $vnp_HashSecret);
    
    // Compare hash values
    return $vnp_CalcHash === $vnp_SecureHash;
}

