<?php
session_start();
include('config/config.php');

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: thanhtoan.php');
    exit();
}

// Variable to track if user is logged in
$is_logged_in = isset($_SESSION['user']) && $_SESSION['user']['logged_in'] === true;

// Get payment method - enforce VNPAY for guest users
$payment_method = $_POST['payment_method'];
if (!$is_logged_in && $payment_method !== 'vnpay') {
    $payment_method = 'vnpay'; // Force VNPAY for non-logged in users
}

// Get form data
$ho_ten = $_POST['ho_ten'];
$email = $_POST['email'] ?? '';
$sodienthoai = $_POST['sodienthoai'];
$diachi = $_POST['diachi'];
$tinh_tp = $_POST['tinh_tp'];
$quan_huyen = $_POST['quan_huyen'];
$phuong_xa = $_POST['phuong_xa'] ?? '';
$ghi_chu = $_POST['ghi_chu'] ?? '';

// Validate required fields
if (empty($ho_ten) || empty($sodienthoai) || empty($diachi) || empty($tinh_tp) || empty($quan_huyen)) {
    $_SESSION['error_message'] = 'Vui lòng điền đầy đủ thông tin giao hàng.';
    header('Location: thanhtoan.php');
    exit();
}

// Generate order code
$order_code = 'BUG' . date('ymd') . substr(time(), -3) . strtoupper(substr(md5(rand()), 0, 4));

// Get user ID if logged in, otherwise null
$user_id = $is_logged_in ? $_SESSION['user']['id'] : null;

// Start transaction
$conn->begin_transaction();

try {
    // Create order
    $order_stmt = $conn->prepare("
        INSERT INTO donhang (
            ma_donhang, id_user, ho_ten, email, sodienthoai, diachi, 
            tinh_tp, quan_huyen, phuong_xa, tong_tien, phi_vanchuyen, 
            giam_gia, thanh_tien, ma_giam_gia, phuong_thuc_thanh_toan, 
            trang_thai_thanh_toan, trang_thai_don_hang, ghi_chu
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 1, ?
        )
    ");

    // ...existing code for order processing...

    // Create payment info for VNPAY if selected
    if ($payment_method === 'vnpay') {
        $_SESSION['payment_info'] = [
            'id' => $order_id,
            'ma_donhang' => $order_code,
            'amount' => $total_amount,
            'order_desc' => "Thanh toán đơn hàng " . $order_code
        ];

        // Commit transaction before redirecting
        $conn->commit();
        
        // Redirect to VNPAY processing
        header('Location: vnpay_create_payment.php');
        exit();
    }
    
    // Process other payment methods (only for logged-in users)
    // ...existing code...

    // Commit the transaction
    $conn->commit();
    
    // Redirect based on payment method
    // ...existing code...
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    $_SESSION['error_message'] = 'Có lỗi xảy ra: ' . $e->getMessage();
    header('Location: thanhtoan.php');
    exit();
}
?>