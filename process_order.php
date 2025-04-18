<?php
session_start();
include('config/config.php');

// Debug information
error_log("Process Order - POST data: " . print_r($_POST, true));

// Redirect if no payment method or necessary data is missing
if (!isset($_POST['payment_method'])) {
    $_SESSION['error_message'] = 'Vui lòng chọn phương thức thanh toán';
    header('Location: thanhtoan.php');
    exit();
}

// Get order information from POST
$payment_method = $_POST['payment_method'];
$fullname = $_POST['fullname'] ?? '';
$email = $_POST['email'] ?? '';
$phone = $_POST['phone'] ?? '';
$address = $_POST['address'] ?? '';
$province = $_POST['province_name'] ?? '';
$district = $_POST['district_name'] ?? '';
$ward = $_POST['ward_name'] ?? '';
$note = $_POST['note'] ?? '';
$promo_code = $_POST['promo_code'] ?? '';
$discount_amount = floatval($_POST['discount_amount'] ?? 0);
$discount_id = intval($_POST['discount_id'] ?? 0);

// Check required fields
if (empty($fullname) || empty($phone) || empty($email) || empty($address) || empty($province) || empty($district)) {
    $_SESSION['error_message'] = 'Vui lòng điền đầy đủ thông tin thanh toán';
    header('Location: thanhtoan.php');
    exit();
}

// Get user information
$user_id = isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : null;

// Generate order code
$order_code = 'BUG' . date('ymd') . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 5));

// Determine if we're processing a "Buy Now" order or normal checkout
$is_buy_now = isset($_GET['buy_now']) && $_GET['buy_now'] == 1;

// Process the order based on the type
if ($is_buy_now) {
    // Buy Now checkout
    if (!isset($_SESSION['buy_now_cart'])) {
        $_SESSION['error_message'] = 'Không tìm thấy thông tin sản phẩm để mua ngay';
        header('Location: sanpham.php');
        exit();
    }
    
    $item = $_SESSION['buy_now_cart'];
    $product_id = $item['id_sanpham'];
    $variant_id = $item['id_bienthe'] ?? null;
    $quantity = $item['so_luong'];
    $price = $item['gia'];
    $total_amount = $price * $quantity;
    
    // Apply shipping fee
    $shipping_fee = 30000; // Standard shipping fee
    // Check if eligible for free shipping based on amount
    if ($total_amount >= 500000) {
        $shipping_fee = 0;
    }
    
    // Apply discount if available
    if (!empty($promo_code) && $discount_amount > 0) {
        $total_with_discount = $total_amount - $discount_amount;
        if ($total_with_discount < 0) $total_with_discount = 0;
    } else {
        $total_with_discount = $total_amount;
    }
    
    // Calculate final amount
    $final_amount = $total_with_discount + $shipping_fee;
    
    // Create order in database
    $stmt = $conn->prepare("
        INSERT INTO donhang (ma_donhang, id_user, ho_ten, email, sodienthoai, diachi, tinh_tp, quan_huyen, 
                           phuong_xa, tong_tien, phi_vanchuyen, giam_gia, thanh_tien, ma_giam_gia, 
                           phuong_thuc_thanh_toan, trang_thai_thanh_toan, trang_thai_don_hang, ghi_chu)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $payment_status = 0; // Default: not paid
    $order_status = 1;  // Default: pending
    
    $stmt->bind_param(
        "sisssssssdddssiiss",  
        $order_code, $user_id, $fullname, $email, $phone, $address, $province, $district, 
        $ward, $total_amount, $shipping_fee, $discount_amount, $final_amount, $promo_code, 
        $payment_method, $payment_status, $order_status, $note
    );
    
    if (!$stmt->execute()) {
        error_log("Error creating order: " . $conn->error);
        $_SESSION['error_message'] = 'Có lỗi xảy ra khi tạo đơn hàng';
        header('Location: thanhtoan.php');
        exit();
    }
    
    $order_id = $conn->insert_id;
    
    // Get product name
    $product_stmt = $conn->prepare("SELECT tensanpham FROM sanpham WHERE id = ?");
    $product_stmt->bind_param("i", $product_id);
    $product_stmt->execute();
    $product_result = $product_stmt->get_result();
    
    if ($product_result->num_rows > 0) {
        $product = $product_result->fetch_assoc();
        $product_name = $product['tensanpham'];
    } else {
        $product_name = "Sản phẩm #" . $product_id;
    }
    
    // Get variant attributes
    $variant_attr = "";
    if ($variant_id) {
        $variant_stmt = $conn->prepare("
            SELECT size.gia_tri AS size_name, color.gia_tri AS color_name
            FROM sanpham_bien_the AS sbt
            LEFT JOIN thuoc_tinh AS size ON sbt.id_size = size.id
            LEFT JOIN thuoc_tinh AS color ON sbt.id_mau = color.id
            WHERE sbt.id = ?
        ");
        $variant_stmt->bind_param("i", $variant_id);
        $variant_stmt->execute();
        $variant_result = $variant_stmt->get_result();
        
        if ($variant_result->num_rows > 0) {
            $variant = $variant_result->fetch_assoc();
            $variant_attr = "Size: " . $variant['size_name'] . ", Màu: " . $variant['color_name'];
        }
    }
    
    // Add order detail
    $detail_stmt = $conn->prepare("
        INSERT INTO donhang_chitiet (id_donhang, id_sanpham, id_bienthe, tensp, thuoc_tinh, gia, soluong, thanh_tien)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $item_total = $price * $quantity;
    
    $detail_stmt->bind_param(
        "iiissdid", 
        $order_id, $product_id, $variant_id, $product_name, $variant_attr, 
        $price, $quantity, $item_total
    );
    
    if (!$detail_stmt->execute()) {
        error_log("Error adding order detail: " . $conn->error);
    }
    
    // Record order creation in history
    $action = "Tạo đơn hàng";
    $user_name = $user_id ? (isset($_SESSION['user']['ten']) ? $_SESSION['user']['ten'] : 'Người dùng') : 'Khách vãng lai';
    $log_note = "Đơn hàng mới được tạo với phương thức thanh toán: " . $payment_method;
    
    $log_stmt = $conn->prepare("
        INSERT INTO donhang_lichsu (id_donhang, hanh_dong, nguoi_thuchien, ghi_chu)
        VALUES (?, ?, ?, ?)
    ");
    
    $log_stmt->bind_param("isss", $order_id, $action, $user_name, $log_note);
    $log_stmt->execute();
    
    // Clear buy now session data
    unset($_SESSION['buy_now_cart']);
} else {
    // Regular cart checkout
    // Obtain the cart first
    $session_id = session_id();
    
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
        $_SESSION['error_message'] = 'Không tìm thấy giỏ hàng';
        header('Location: giohang.php');
        exit();
    }
    
    $cart = $result->fetch_assoc();
    $cart_id = $cart['id'];
    
    // Determine if we're checking out selected items or entire cart
    $selected_items = isset($_POST['selected_items']) ? $_POST['selected_items'] : [];
    
    // Get cart items to checkout
    if (!empty($selected_items)) {
        $placeholders = str_repeat('?,', count($selected_items) - 1) . '?';
        
        $query = "
            SELECT gct.*, 
                sbt.id_sanpham,
                sp.tensanpham, 
                sp.hinhanh,
                size.gia_tri AS ten_kichthuoc,
                color.gia_tri AS ten_mau
            FROM giohang_chitiet gct
            JOIN sanpham_bien_the sbt ON gct.id_bienthe = sbt.id
            JOIN sanpham sp ON sbt.id_sanpham = sp.id
            LEFT JOIN thuoc_tinh size ON sbt.id_size = size.id AND size.loai = 'size'
            LEFT JOIN thuoc_tinh color ON sbt.id_mau = color.id AND color.loai = 'color'
            WHERE gct.id_giohang = ? AND gct.id IN ($placeholders)
        ";
        
        $types = "i" . str_repeat("i", count($selected_items));
        $params = array_merge([$cart_id], $selected_items);
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
    } else {
        $stmt = $conn->prepare("
            SELECT gct.*, 
                sbt.id_sanpham,
                sp.tensanpham, 
                sp.hinhanh,
                size.gia_tri AS ten_kichthuoc,
                color.gia_tri AS ten_mau
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
    $items = $stmt->get_result();
    
    if ($items->num_rows === 0) {
        $_SESSION['error_message'] = 'Không có sản phẩm nào để thanh toán';
        header('Location: giohang.php');
        exit();
    }
    
    // Calculate totals
    $total_amount = 0;
    $checkout_items = [];
    
    while ($item = $items->fetch_assoc()) {
        $checkout_items[] = $item;
        $total_amount += $item['gia'] * $item['so_luong'];
    }
    
    // Apply shipping fee
    $shipping_fee = 30000; // Standard shipping fee
    // Check if eligible for free shipping based on amount
    if ($total_amount >= 500000) {
        $shipping_fee = 0;
    }
    
    // Apply discount if available
    if (!empty($promo_code) && $discount_amount > 0) {
        $total_with_discount = $total_amount - $discount_amount;
        if ($total_with_discount < 0) $total_with_discount = 0;
    } else {
        $total_with_discount = $total_amount;
    }
    
    // Calculate final amount
    $final_amount = $total_with_discount + $shipping_fee;
    
    // Create order in database
    $stmt = $conn->prepare("
        INSERT INTO donhang (ma_donhang, id_user, ho_ten, email, sodienthoai, diachi, tinh_tp, quan_huyen, 
                           phuong_xa, tong_tien, phi_vanchuyen, giam_gia, thanh_tien, ma_giam_gia, 
                           phuong_thuc_thanh_toan, trang_thai_thanh_toan, trang_thai_don_hang, ghi_chu)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $payment_status = 0; // Default: not paid
    $order_status = 1;  // Default: pending
    
    $stmt->bind_param(
        "sisssssssdddssiiss",  
        $order_code, $user_id, $fullname, $email, $phone, $address, $province, $district, 
        $ward, $total_amount, $shipping_fee, $discount_amount, $final_amount, $promo_code, 
        $payment_method, $payment_status, $order_status, $note
    );
    
    if (!$stmt->execute()) {
        error_log("Error creating order: " . $conn->error);
        $_SESSION['error_message'] = 'Có lỗi xảy ra khi tạo đơn hàng';
        header('Location: thanhtoan.php');
        exit();
    }
    
    $order_id = $conn->insert_id;
    
    // Add order details for each item
    foreach ($checkout_items as $item) {
        $product_id = $item['id_sanpham'];
        $variant_id = $item['id_bienthe'];
        $product_name = $item['tensanpham'];
        $price = $item['gia'];
        $quantity = $item['so_luong'];
        $variant_attr = "";
        
        if (!empty($item['ten_kichthuoc'])) {
            $variant_attr .= "Size: " . $item['ten_kichthuoc'];
        }
        
        if (!empty($item['ten_mau'])) {
            if (!empty($variant_attr)) {
                $variant_attr .= ", ";
            }
            $variant_attr .= "Màu: " . $item['ten_mau'];
        }
        
        $item_total = $price * $quantity;
        
        $detail_stmt = $conn->prepare("
            INSERT INTO donhang_chitiet (id_donhang, id_sanpham, id_bienthe, tensp, thuoc_tinh, gia, soluong, thanh_tien)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $detail_stmt->bind_param(
            "iiissdid", 
            $order_id, $product_id, $variant_id, $product_name, $variant_attr, 
            $price, $quantity, $item_total
        );
        
        if (!$detail_stmt->execute()) {
            error_log("Error adding order detail: " . $conn->error);
        }
        
        // Remove checked out items from cart if successfully added to order
        if ($detail_stmt->affected_rows > 0) {
            $delete_stmt = $conn->prepare("DELETE FROM giohang_chitiet WHERE id = ? AND id_giohang = ?");
            $delete_stmt->bind_param("ii", $item['id'], $cart_id);
            $delete_stmt->execute();
        }
    }
    
    // Record order creation in history
    $action = "Tạo đơn hàng";
    $user_name = $user_id ? (isset($_SESSION['user']['ten']) ? $_SESSION['user']['ten'] : 'Người dùng') : 'Khách vãng lai';
    $log_note = "Đơn hàng mới được tạo với phương thức thanh toán: " . $payment_method;
    
    $log_stmt = $conn->prepare("
        INSERT INTO donhang_lichsu (id_donhang, hanh_dong, nguoi_thuchien, ghi_chu)
        VALUES (?, ?, ?, ?)
    ");
    
    $log_stmt->bind_param("isss", $order_id, $action, $user_name, $log_note);
    $log_stmt->execute();
}

// Process by payment method
if ($payment_method == 'cod') {
    // For COD, redirect to success page
    $_SESSION['order_success'] = true;
    $_SESSION['last_order_id'] = $order_id;
    header("Location: dathang_thanhcong.php?id=$order_id");
    exit();
} else if ($payment_method == 'vnpay') {
    // For VNPAY, prepare payment info and redirect to VNPAY
    $_SESSION['payment_info'] = [
        'id' => $order_id,
        'ma_donhang' => $order_code,
        'amount' => $final_amount,
        'order_desc' => "Thanh toán đơn hàng $order_code"
    ];
    
    header("Location: vnpay_create_payment.php");
    exit();
} else {
    // Fallback for other payment methods
    $_SESSION['order_success'] = true;
    $_SESSION['last_order_id'] = $order_id;
    header("Location: order_success.php?id=$order_id");
    exit();
}
?>