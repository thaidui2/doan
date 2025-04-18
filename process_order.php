<?php
session_start();
require_once('config/config.php');

// Kích hoạt hiển thị lỗi chi tiết
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Ghi log để debug
function logToFile($message) {
    file_put_contents('order_debug.log', 
        date('[Y-m-d H:i:s] ') . $message . PHP_EOL, 
        FILE_APPEND);
}

// Thêm ngay sau phần khai báo function logToFile
function logError($message, $data = null) {
    $log_message = date('[Y-m-d H:i:s] ') . $message;
    if ($data !== null) {
        $log_message .= "\n" . print_r($data, true);
    }
    file_put_contents('order_error.log', $log_message . PHP_EOL, FILE_APPEND);
}

// Nâng cấp hàm savePaymentInfo để hỗ trợ thanh toán thất bại
function savePaymentInfo($conn, $order_id, $payment_method, $amount, $note = '', $is_success = true) {
    // Tạo mã giao dịch cho các phương thức không có mã sẵn
    $transaction_id = $payment_method . '-' . $order_id . '-' . time();
    $bank_name = null;
    $status = $is_success ? 1 : 0; // 1: Thành công, 0: Thất bại
    
    // Xử lý theo từng phương thức
    switch($payment_method) {
        case 'cod':
            $note = $is_success ? 'Thanh toán khi nhận hàng' : 'Thanh toán COD thất bại: ' . $note;
            break;
        case 'bank_transfer':
            $note = empty($note) ? 
                ($is_success ? 'Chuyển khoản ngân hàng, chờ xác nhận' : 'Chuyển khoản thất bại') : 
                $note;
            $status = $is_success ? 0 : 0; // Chờ xác nhận khi thành công, thất bại khi không thành công
            // Lấy thông tin ngân hàng nếu có
            if (isset($_POST['bank_name'])) {
                $bank_name = $_POST['bank_name'];
            }
            break;
        case 'vnpay':
            $note = $is_success ? 
                'Thanh toán VNPAY thành công' : 
                'Thanh toán VNPAY thất bại: ' . $note;
            break;
    }
    
    // Lưu thông tin thanh toán
    $query = $conn->prepare(
        "INSERT INTO thanh_toan (id_donhang, ma_giaodich, so_tien, phuong_thuc, ngan_hang, 
        ngay_thanhtoan, trang_thai, ghi_chu) VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)"
    );
    
    $query->bind_param("isdssis", $order_id, $transaction_id, $amount, $payment_method, 
                        $bank_name, $status, $note);
    
    return $query->execute();
}

logToFile('Bắt đầu xử lý đơn hàng');

// Redirect if form wasn't submitted
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Location: thanhtoan.php');
    exit;
}

// Check if user is logged in
$is_logged_in = isset($_SESSION['user']);
$user_id = $is_logged_in ? $_SESSION['user']['id'] : null;

// Get order details from form - Replace FILTER_SANITIZE_STRING with safer alternatives
$fullname = trim(strip_tags($_POST['fullname'] ?? ''));
$email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
$phone = trim(strip_tags($_POST['phone'] ?? ''));
$address = trim(strip_tags($_POST['address'] ?? ''));
$province = trim(strip_tags($_POST['province'] ?? ''));
$district = trim(strip_tags($_POST['district'] ?? ''));
$ward = trim(strip_tags($_POST['ward'] ?? ''));
$note = trim(strip_tags($_POST['note'] ?? ''));
$payment_method = trim(strip_tags($_POST['payment_method'] ?? 'cod'));

// Get address names (if available)
$province_name = trim(strip_tags($_POST['province_name'] ?? ''));
$district_name = trim(strip_tags($_POST['district_name'] ?? ''));
$ward_name = trim(strip_tags($_POST['ward_name'] ?? ''));

// Validate required fields
if (empty($fullname) || empty($email) || empty($phone) || empty($address) || 
    empty($province) || empty($district) || empty($ward)) {
    $_SESSION['error_message'] = 'Vui lòng điền đầy đủ thông tin giao hàng.';
    header('Location: thanhtoan.php');
    exit;
}

// Get discount information
$promo_code = trim(strip_tags($_POST['promo_code'] ?? ''));
$discount_amount = filter_input(INPUT_POST, 'discount_amount', FILTER_VALIDATE_FLOAT) ?: 0;
$discount_id = filter_input(INPUT_POST, 'discount_id', FILTER_VALIDATE_INT) ?: 0;

// Check if we are processing "Buy Now" or regular checkout
$buy_now = isset($_GET['buy_now']) && $_GET['buy_now'] == '1';

try {
    // Start transaction
    $conn->begin_transaction();
    
    // Prepare order items based on checkout type
    $order_items = [];
    $total_amount = 0;
    
    if ($buy_now) {
        if (!isset($_SESSION['buy_now_cart'])) {
            throw new Exception('Không tìm thấy thông tin sản phẩm để thanh toán.');
        }
        
        $cart_item = $_SESSION['buy_now_cart'];
        $order_items[] = $cart_item;
        $total_amount = $cart_item['gia'] * $cart_item['so_luong'];
    } else {
        // Get cart information
        $session_id = session_id();
        
        // Get cart ID - updated for new schema
        if ($user_id) {
            $stmt = $conn->prepare("SELECT id FROM giohang WHERE id_user = ?");
            $stmt->bind_param("i", $user_id);
        } else {
            $stmt = $conn->prepare("SELECT id FROM giohang WHERE session_id = ? AND id_user IS NULL");
            $stmt->bind_param("s", $session_id);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('Không tìm thấy giỏ hàng.');
        }
        
        $cart = $result->fetch_assoc();
        $cart_id = $cart['id'];
        
        // Get selected items to checkout or all items from cart
        if (isset($_SESSION['checkout_type']) && $_SESSION['checkout_type'] == 'selected' && !empty($_SESSION['checkout_items'])) {
            $selected_items = $_SESSION['checkout_items'];
            $placeholders = str_repeat('?,', count($selected_items) - 1) . '?';
            
            // Updated query with new schema
            $query = "
                SELECT gct.*, sp.tensanpham, sbt.id_sanpham,
                       size.gia_tri AS ten_kichthuoc, color.gia_tri AS ten_mau
                FROM giohang_chitiet gct
                JOIN sanpham_bien_the sbt ON gct.id_bienthe = sbt.id
                JOIN sanpham sp ON sbt.id_sanpham = sp.id
                LEFT JOIN thuoc_tinh size ON sbt.id_size = size.id AND size.loai = 'size'
                LEFT JOIN thuoc_tinh color ON sbt.id_mau = color.id AND color.loai = 'color'
                WHERE gct.id_giohang = ? AND gct.id IN ($placeholders)
            ";
            
            $types = "i" . str_repeat("i", count($selected_items));
            $stmt = $conn->prepare($query);
            $params = array_merge([$cart_id], $selected_items);
            $stmt->bind_param($types, ...$params);
        } else {
            // Get all items from cart - updated for new schema
            $stmt = $conn->prepare("
                SELECT gct.*, sp.tensanpham, sbt.id_sanpham,
                       size.gia_tri AS ten_kichthuoc, color.gia_tri AS ten_mau
                FROM giohang_chitiet gct
                JOIN sanpham_bien_the sbt ON gct.id_bienthe = sbt.id
                JOIN sanpham sp ON sbt.id_sanpham = sp.id
                LEFT JOIN thuoc_tinh size ON sbt.id_size = size.id AND size.loai = 'size'
                LEFT JOIN thuoc_tinh color ON sbt.id_mau = color.id AND color.loai = 'color'
                WHERE gct.id_giohang = ?
            ");
            $stmt->bind_param("i", $cart_id);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('Không có sản phẩm nào để thanh toán.');
        }
        
        while ($item = $result->fetch_assoc()) {
            $order_items[] = $item;
            $total_amount += $item['gia'] * $item['so_luong'];
        }
    }
    
    // Calculate shipping fee (can be customized)
    $shipping_fee = 30000; // Default shipping fee
    
    // Apply discount
    $discount = $discount_amount;
    
    // Calculate final total
    $final_total = $total_amount + $shipping_fee - $discount;
    
    // Generate unique order code
    $order_prefix = "BUG";
    $order_code = $order_prefix . date('ymd') . strtoupper(substr(uniqid(), -5));
    
    // Create new order - updated for new schema
    $order_query = $conn->prepare("
        INSERT INTO donhang (
            ma_donhang, id_user, ho_ten, email, sodienthoai,
            diachi, tinh_tp, quan_huyen, phuong_xa, 
            tong_tien, phi_vanchuyen, giam_gia, thanh_tien, 
            ma_giam_gia, phuong_thuc_thanh_toan, trang_thai_don_hang, ghi_chu
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    // Default status - pending
    $status = 1; 
    
    // Fix type string to match exactly 17 parameters (s=string, i=integer, d=double/float)
    // ma_donhang(s), id_user(i), ho_ten(s), email(s), sodienthoai(s), 
    // diachi(s), tinh_tp(s), quan_huyen(s), phuong_xa(s),
    // tong_tien(d), phi_vanchuyen(d), giam_gia(d), thanh_tien(d),
    // ma_giam_gia(s), phuong_thuc_thanh_toan(s), trang_thai_don_hang(i), ghi_chu(s)
    $order_query->bind_param(
        "sissssssddddssiss",
        $order_code, $user_id, $fullname, $email, $phone,
        $address, $province_name, $district_name, $ward_name,
        $total_amount, $shipping_fee, $discount, $final_total,
        $promo_code, $payment_method, $status, $note
    );
    
    $order_query->execute();
    $order_id = $conn->insert_id;
    
    // Add order items - updated for new schema
    foreach ($order_items as $item) {
        $product_id = $item['id_sanpham'] ?? $item['id_bienthe'];
        $variant_id = $item['id_bienthe'] ?? null;
        $item_name = $item['tensanpham'] ?? 'Sản phẩm không xác định';
        $price = $item['gia'];
        $quantity = $item['so_luong'];
        $item_total = $price * $quantity;
        
        // Format variant information
        $variant_info = [];
        if (!empty($item['ten_kichthuoc'])) {
            $variant_info[] = "Size: " . $item['ten_kichthuoc'];
        }
        if (!empty($item['ten_mau'])) {
            $variant_info[] = "Màu: " . $item['ten_mau'];
        }
        $variant_str = implode(", ", $variant_info);
        
        $item_query = $conn->prepare("
            INSERT INTO donhang_chitiet (
                id_donhang, id_sanpham, id_bienthe, tensp, thuoc_tinh, 
                gia, soluong, thanh_tien
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $item_query->bind_param(
            "iiissdid",
            $order_id, $product_id, $variant_id, $item_name, $variant_str,
            $price, $quantity, $item_total
        );
        
        $item_query->execute();
        
        // Update product stock quantity - updated for new schema
        if ($variant_id) {
            // Update variant stock
            $update_variant = $conn->prepare("
                UPDATE sanpham_bien_the 
                SET so_luong = so_luong - ? 
                WHERE id = ?
            ");
            $update_variant->bind_param("ii", $quantity, $variant_id);
            $update_variant->execute();
            
            // Update product total stock
            $update_product = $conn->prepare("
                UPDATE sanpham 
                SET so_luong = (SELECT SUM(so_luong) FROM sanpham_bien_the WHERE id_sanpham = ?),
                    da_ban = da_ban + ?
                WHERE id = ?
            ");
            $update_product->bind_param("iii", $product_id, $quantity, $product_id);
            $update_product->execute();
        }
    }
    
    // Clear cart after successful order
    if (!$buy_now && isset($cart_id)) {
        if (isset($_SESSION['checkout_type']) && $_SESSION['checkout_type'] == 'selected' && !empty($_SESSION['checkout_items'])) {
            // Delete only selected items
            foreach ($_SESSION['checkout_items'] as $cart_item_id) {
                $delete_item = $conn->prepare("DELETE FROM giohang_chitiet WHERE id = ? AND id_giohang = ?");
                $delete_item->bind_param("ii", $cart_item_id, $cart_id);
                $delete_item->execute();
            }
        } else {
            // Delete all items from cart
            $delete_cart = $conn->prepare("DELETE FROM giohang_chitiet WHERE id_giohang = ?");
            $delete_cart->bind_param("i", $cart_id);
            $delete_cart->execute();
        }
    }
    
    // Update coupon usage if applicable
    if ($discount_id > 0) {
        $update_coupon = $conn->prepare("
            UPDATE khuyen_mai 
            SET da_su_dung = da_su_dung + 1 
            WHERE id = ?
        ");
        $update_coupon->bind_param("i", $discount_id);
        $update_coupon->execute();
    }
    
    // Clear buy now session data
    if ($buy_now && isset($_SESSION['buy_now_cart'])) {
        unset($_SESSION['buy_now_cart']);
    }
    
    // Clear checkout session data
    if (isset($_SESSION['checkout_type'])) {
        unset($_SESSION['checkout_type']);
    }
    if (isset($_SESSION['checkout_items'])) {
        unset($_SESSION['checkout_items']);
    }
    
    // Commit transaction
    $conn->commit();
    
    // Store order information in session for thank you page
    $_SESSION['order_completed'] = [
        'order_id' => $order_id,
        'order_code' => $order_code,
        'total_amount' => $final_total,
        'payment_method' => $payment_method
    ];
    
    // Handle payment method selection
    $payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'cod';

    if ($payment_method == 'vnpay') {
        // Prepare payment info for VNPAY
        $_SESSION['payment_info'] = [
            'id' => $order_id,                       // Database ID
            'ma_donhang' => $order_code,            // Order reference code (e.g., BUG250418501A4)
            'amount' => $final_total,                // Final amount including shipping
            'order_desc' => 'Thanh toan don hang ' . $order_code . ' tai Bug Shop'
        ];
        
        // Redirect to VNPAY payment page
        header("Location: vnpay_create_payment.php");
        exit;
    } else {
        // COD or other payment methods
        // Redirect to success page with order ID
        $_SESSION['success_message'] = 'Đặt hàng thành công! Mã đơn hàng của bạn là ' . $order_code;
        header("Location: thanh-toan-thanh-cong.php?orderId=" . $order_id);
        exit;
    }
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    // Log the error
    error_log('Order processing error: ' . $e->getMessage());
    
    // Redirect with error message
    $_SESSION['error_message'] = 'Có lỗi xảy ra khi xử lý đơn hàng: ' . $e->getMessage();
    header('Location: thanhtoan.php');
    exit;
}
?>