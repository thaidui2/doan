<?php
date_default_timezone_set('Asia/Ho_Chi_Minh');

$vnp_TmnCode = "HQTPW4RO"; // Mã website tại VNPAY
$vnp_HashSecret = "MKDMH902XG9NGN32YE766M2PNR8PG3KH"; // Chuỗi bí mật
$vnp_Url = "https://sandbox.vnpayment.vn/paymentv2/vpcpay.html"; // URL thanh toán của VNPAY
$vnp_Returnurl = "http://localhost/bug_shop/vnpay_return.php"; // URL callback khi thanh toán xong
$vnp_apiUrl = "http://sandbox.vnpayment.vn/merchant_webapi/api/transaction"; // API URL để kiểm tra giao dịch

