<?php
session_start();
include('config/config.php');

// Debug logging
function debug_log($message, $data = null)
{
    $log_message = date('Y-m-d H:i:s') . ' - ' . $message;
    if ($data !== null) {
        $log_message .= ' - ' . print_r($data, true);
    }
    file_put_contents('debug_order.log', $log_message . "\n", FILE_APPEND);
}

debug_log('Order processing started', $_POST);

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: thanhtoan.php');
    exit();
}

// Variable to track if user is logged in
$is_logged_in = isset($_SESSION['user']) && $_SESSION['user']['logged_in'] === true;

// Get payment method and validate it - enforce VNPAY for guest users
$payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'cod';
debug_log('Original payment method', $payment_method);

if (!$is_logged_in && $payment_method === 'cod') {
    $payment_method = 'vnpay'; // Force VNPAY for non-logged in users
}

debug_log('Final payment method', $payment_method);

// Get form data
$ho_ten = $_POST['fullname'] ?? '';
$email = $_POST['email'] ?? '';
$sodienthoai = $_POST['phone'] ?? '';
$diachi = $_POST['address'] ?? '';

// Xử lý đặc biệt cho địa chỉ để tránh lỗi phường/xã = '0'
$tinh_tp = $_POST['province_name'] ?? $_POST['province'] ?? '';
$quan_huyen = $_POST['district_name'] ?? $_POST['district'] ?? '';
$phuong_xa = '';

// Ưu tiên lấy ward_name nếu có
if (isset($_POST['ward_name']) && !empty($_POST['ward_name']) && $_POST['ward_name'] !== '0') {
    $phuong_xa = $_POST['ward_name'];
    debug_log('Using ward_name value for phuong_xa', $phuong_xa);
}
// Nếu không, thử lấy ward (code)
else if (isset($_POST['ward']) && !empty($_POST['ward']) && $_POST['ward'] !== '0') {
    $phuong_xa = $_POST['ward'];
    debug_log('Using ward code value for phuong_xa', $phuong_xa);
}

// Log các giá trị để debug
debug_log('Raw address POST values', [
    'province' => $_POST['province'] ?? 'not set',
    'province_name' => $_POST['province_name'] ?? 'not set',
    'district' => $_POST['district'] ?? 'not set',
    'district_name' => $_POST['district_name'] ?? 'not set',
    'ward' => $_POST['ward'] ?? 'not set',
    'ward_name' => $_POST['ward_name'] ?? 'not set',
]);

$ghi_chu = $_POST['note'] ?? '';

// Debug address fields
debug_log('Address information', [
    'province' => [
        'code' => $_POST['province'] ?? 'not set',
        'name' => $_POST['province_name'] ?? 'not set'
    ],
    'district' => [
        'code' => $_POST['district'] ?? 'not set',
        'name' => $_POST['district_name'] ?? 'not set'
    ],
    'ward' => [
        'code' => $_POST['ward'] ?? 'not set',
        'name' => $_POST['ward_name'] ?? 'not set'
    ]
]);

// Check if using manual address mode
$manual_mode = isset($_POST['manual_address_mode']) && $_POST['manual_address_mode'] == '1';
$manual_address = $_POST['manual_full_address'] ?? '';

// In manual mode, we use the full address value for all fields if needed
if ($manual_mode && !empty($manual_address)) {
    // Use manual address as fallback for empty fields
    if (empty($tinh_tp))
        $tinh_tp = $manual_address;
    if (empty($quan_huyen))
        $quan_huyen = $manual_address;
    if (empty($phuong_xa))
        $phuong_xa = $manual_address;

    debug_log('Using manual address mode', $manual_address);
}

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

// Check if this is a "Buy Now" purchase - check both POST and GET
$buy_now = (isset($_POST['buy_now']) && $_POST['buy_now'] == '1') ||
    (isset($_GET['buy_now']) && $_GET['buy_now'] == '1');

debug_log('Is Buy Now?', $buy_now);

// Get order items and calculate totals
$order_items = [];
$total_amount = 0;

if ($buy_now && isset($_SESSION['buy_now_cart'])) {
    // Process "Buy Now" item
    debug_log('Processing Buy Now cart', $_SESSION['buy_now_cart']);
    $item = $_SESSION['buy_now_cart'];
    $order_items[] = [
        'id_sanpham' => $item['id_sanpham'],
        'id_bienthe' => $item['id_bienthe'],
        'tensp' => $item['ten_san_pham'],
        'thuoc_tinh' => 'Size: ' . $item['ten_size'] . ', Màu: ' . $item['ten_mau'],
        'gia' => $item['gia'],
        'soluong' => $item['so_luong'],
        'thanh_tien' => $item['thanh_tien']
    ];
    $total_amount = $item['thanh_tien'];
} else {
    // Process regular cart checkout
    debug_log('Processing regular cart checkout');
    $session_id = session_id();

    // Determine which items to checkout
    if (isset($_SESSION['checkout_type']) && $_SESSION['checkout_type'] == 'selected' && !empty($_SESSION['checkout_items'])) {
        $selected_items = $_SESSION['checkout_items'];
        $placeholders = str_repeat('?,', count($selected_items) - 1) . '?';

        // Get cart ID
        if ($user_id) {
            $cart_stmt = $conn->prepare("SELECT id FROM giohang WHERE id_user = ?");
            $cart_stmt->bind_param("i", $user_id);
        } else {
            $cart_stmt = $conn->prepare("SELECT id FROM giohang WHERE session_id = ? AND id_user IS NULL");
            $cart_stmt->bind_param("s", $session_id);
        }
        $cart_stmt->execute();
        $cart_result = $cart_stmt->get_result();

        if ($cart_result->num_rows === 0) {
            throw new Exception("Không tìm thấy giỏ hàng");
        }

        $cart_id = $cart_result->fetch_assoc()['id'];

        // Get selected items
        $types = "i" . str_repeat("i", count($selected_items));
        $params = array_merge([$cart_id], $selected_items);

        $query = "
            SELECT gct.*, 
                   sp.tensanpham, 
                   sp.id as id_sanpham,
                   size.gia_tri AS ten_kichthuoc,
                   color.gia_tri AS ten_mau
            FROM giohang_chitiet gct
            JOIN sanpham_bien_the sbt ON gct.id_bienthe = sbt.id
            JOIN sanpham sp ON sbt.id_sanpham = sp.id
            LEFT JOIN thuoc_tinh size ON sbt.id_size = size.id AND size.loai = 'size'
            LEFT JOIN thuoc_tinh color ON sbt.id_mau = color.id AND color.loai = 'color'
            WHERE gct.id_giohang = ? AND gct.id IN ($placeholders)
        ";

        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
    } else {
        // Get all items in cart
        if ($user_id) {
            $query = "
                SELECT gct.*, 
                       sp.tensanpham, 
                       sp.id as id_sanpham,
                       size.gia_tri AS ten_kichthuoc,
                       color.gia_tri AS ten_mau
                FROM giohang_chitiet gct
                JOIN giohang g ON gct.id_giohang = g.id
                JOIN sanpham_bien_the sbt ON gct.id_bienthe = sbt.id
                JOIN sanpham sp ON sbt.id_sanpham = sp.id
                LEFT JOIN thuoc_tinh size ON sbt.id_size = size.id AND size.loai = 'size'
                LEFT JOIN thuoc_tinh color ON sbt.id_mau = color.id AND color.loai = 'color'
                WHERE g.id_user = ?
            ";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $user_id);
        } else {
            $query = "
                SELECT gct.*, 
                       sp.tensanpham, 
                       sp.id as id_sanpham,
                       size.gia_tri AS ten_kichthuoc,
                       color.gia_tri AS ten_mau
                FROM giohang_chitiet gct
                JOIN giohang g ON gct.id_giohang = g.id
                JOIN sanpham_bien_the sbt ON gct.id_bienthe = sbt.id
                JOIN sanpham sp ON sbt.id_sanpham = sp.id
                LEFT JOIN thuoc_tinh size ON sbt.id_size = size.id AND size.loai = 'size'
                LEFT JOIN thuoc_tinh color ON sbt.id_mau = color.id AND color.loai = 'color'
                WHERE g.session_id = ? AND g.id_user IS NULL
            ";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("s", $session_id);
        }
    }

    $stmt->execute();
    $result = $stmt->get_result();

    while ($item = $result->fetch_assoc()) {
        $thuoc_tinh = 'Size: ' . ($item['ten_kichthuoc'] ?? 'N/A') . ', Màu: ' . ($item['ten_mau'] ?? 'N/A');
        $thanh_tien = $item['gia'] * $item['so_luong'];
        $total_amount += $thanh_tien;

        $order_items[] = [
            'id_sanpham' => $item['id_sanpham'],
            'id_bienthe' => $item['id_bienthe'],
            'tensp' => $item['tensanpham'],
            'thuoc_tinh' => $thuoc_tinh,
            'gia' => $item['gia'],
            'soluong' => $item['so_luong'],
            'thanh_tien' => $thanh_tien
        ];
    }
}

debug_log('Order items', $order_items);
debug_log('Total amount', $total_amount);

// Apply shipping fee and discounts
$shipping_fee = 30000;
$discount_amount = isset($_POST['discount_amount']) ? floatval($_POST['discount_amount']) : 0;
$final_amount = $total_amount + $shipping_fee - $discount_amount;
$promo_code = isset($_POST['promo_code']) ? $_POST['promo_code'] : '';

// Start transaction
$conn->begin_transaction();

try {
    debug_log('Preparing order insertion with payment method', $payment_method);

    // Create order - ENSURE payment_method is included in bind_param
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

    if (!$order_stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }    // Log data before binding, especially phuong_xa
    debug_log('Final address values before DB insertion', [
        'tinh_tp' => $tinh_tp,
        'quan_huyen' => $quan_huyen,
        'phuong_xa' => $phuong_xa,
        'phuong_xa_type' => gettype($phuong_xa),
        'phuong_xa_empty' => empty($phuong_xa),
    ]);

    // Ensure phuong_xa is never empty, null or "0"
    if (empty($phuong_xa) || $phuong_xa === "0") {
        // If empty, use quan_huyen as fallback
        $phuong_xa = $quan_huyen;
        debug_log('Replaced empty phuong_xa with quan_huyen value', $phuong_xa);
    }

    // Kiểm tra lại lần cuối
    if (empty($phuong_xa) || $phuong_xa === "0") {
        $phuong_xa = $tinh_tp;
        debug_log('Replaced empty phuong_xa with tinh_tp value as last resort', $phuong_xa);
    }

    // Đảm bảo không có giá trị rỗng cuối cùng
    $tinh_tp = !empty($tinh_tp) ? $tinh_tp : 'Không xác định';
    $quan_huyen = !empty($quan_huyen) ? $quan_huyen : 'Không xác định';
    $phuong_xa = !empty($phuong_xa) ? $phuong_xa : 'Không xác định';

    // Log lại giá trị cuối cùng
    debug_log('FINAL address values that will be saved to DB', [
        'tinh_tp' => $tinh_tp,
        'quan_huyen' => $quan_huyen,
        'phuong_xa' => $phuong_xa,
    ]);

    // Make sure to bind the payment_method parameter
    $order_stmt->bind_param(
        "sissssssddddssss",
        $order_code,
        $user_id,
        $ho_ten,
        $email,
        $sodienthoai,
        $diachi,
        $tinh_tp,
        $quan_huyen,
        $phuong_xa, // Vẫn giữ tham số này để không phải thay đổi cấu trúc SQL
        $total_amount,
        $shipping_fee,
        $discount_amount,
        $final_amount,
        $promo_code,
        $payment_method,
        $ghi_chu
    );

    if (!$order_stmt->execute()) {
        throw new Exception("Execute failed: " . $order_stmt->error);
    }

    $order_id = $conn->insert_id;

    // Cập nhật lại giá trị phường/xã trong một câu lệnh riêng biệt
    // để tránh lỗi khi binding nhiều tham số
    $update_ward_stmt = $conn->prepare("UPDATE donhang SET phuong_xa = ? WHERE id = ?");
    $update_ward_stmt->bind_param("si", $phuong_xa, $order_id);
    $update_ward_stmt->execute();

    // Log thông tin sau khi cập nhật
    debug_log('Order inserted and ward updated successfully', [
        'order_id' => $order_id,
        'payment_method' => $payment_method,
        'phuong_xa' => $phuong_xa
    ]);

    // Insert order details
    if (!empty($order_items)) {
        $detail_stmt = $conn->prepare("
            INSERT INTO donhang_chitiet (
                id_donhang, id_sanpham, id_bienthe, tensp, thuoc_tinh,
                gia, soluong, thanh_tien
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($order_items as $item) {
            $detail_stmt->bind_param(
                "iiissidi",
                $order_id,
                $item['id_sanpham'],
                $item['id_bienthe'],
                $item['tensp'],
                $item['thuoc_tinh'],
                $item['gia'],
                $item['soluong'],
                $item['thanh_tien']
            );

            if (!$detail_stmt->execute()) {
                throw new Exception("Failed to insert order details: " . $detail_stmt->error);
            }

            // Update product inventory - fixed and improved with error handling
            $update_inventory = $conn->prepare("
                UPDATE sanpham_bien_the SET so_luong = so_luong - ? 
                WHERE id = ?
            ");

            if (!$update_inventory) {
                throw new Exception("Failed to prepare inventory update: " . $conn->error);
            }

            $update_inventory->bind_param("ii", $item['soluong'], $item['id_bienthe']);

            if (!$update_inventory->execute()) {
                throw new Exception("Failed to update inventory for product variant ID: " . $item['id_bienthe'] . " - Error: " . $update_inventory->error);
            }

            // Also update the main sanpham table's quantity
            $update_product_inventory = $conn->prepare("
                UPDATE sanpham SET so_luong = so_luong - ? 
                WHERE id = ?
            ");

            if (!$update_product_inventory) {
                throw new Exception("Failed to prepare product inventory update: " . $conn->error);
            }

            $update_product_inventory->bind_param("ii", $item['soluong'], $item['id_sanpham']);

            if (!$update_product_inventory->execute()) {
                throw new Exception("Failed to update product inventory for ID: " . $item['id_sanpham'] . " - Error: " . $update_product_inventory->error);
            }

            // Log inventory update for debugging
            debug_log('Inventory updated', [
                'product_id' => $item['id_sanpham'],
                'variant_id' => $item['id_bienthe'],
                'quantity_reduced' => $item['soluong'],
                'variant_rows_affected' => $update_inventory->affected_rows,
                'product_rows_affected' => $update_product_inventory->affected_rows
            ]);

            // If no rows were affected, the inventory might be insufficient
            if ($update_inventory->affected_rows == 0) {
                debug_log('Warning: Inventory update affected 0 rows', [
                    'variant_id' => $item['id_bienthe'],
                    'quantity' => $item['soluong']
                ]);
            }
        }

        debug_log('Order details inserted successfully');
    }

    // Create payment info for VNPAY if selected
    if ($payment_method === 'vnpay') {
        $_SESSION['payment_info'] = [
            'id' => $order_id,
            'ma_donhang' => $order_code,
            'amount' => $final_amount,
            'order_desc' => "Thanh toán đơn hàng " . $order_code
        ];

        // Commit transaction before redirecting
        $conn->commit();

        // Redirect to VNPAY processing
        header('Location: vnpay_create_payment.php');
        exit();
    }

    // For COD and other methods, complete the order
    // Add order to history
    $action = "Tạo đơn hàng";
    $nguoi_thuchien = $is_logged_in ? "Người dùng" : "Khách vãng lai";
    $note = "Đơn hàng mới được tạo với phương thức thanh toán: " . $payment_method;

    $history_stmt = $conn->prepare("
        INSERT INTO donhang_lichsu (id_donhang, hanh_dong, nguoi_thuchien, ghi_chu)
        VALUES (?, ?, ?, ?)
    ");
    $history_stmt->bind_param("isss", $order_id, $action, $nguoi_thuchien, $note);
    $history_stmt->execute();

    // Clear cart items if not buy_now (they were already processed)
    if (!$buy_now) {
        if ($user_id) {
            $cart_query = $conn->prepare("SELECT id FROM giohang WHERE id_user = ?");
            $cart_query->bind_param("i", $user_id);
        } else {
            $cart_query = $conn->prepare("SELECT id FROM giohang WHERE session_id = ? AND id_user IS NULL");
            $cart_query->bind_param("s", $session_id);
        }
        $cart_query->execute();
        $cart_result = $cart_query->get_result();

        if ($cart_result->num_rows > 0) {
            $cart_id = $cart_result->fetch_assoc()['id'];

            // Delete either selected items or all items
            if (isset($_SESSION['checkout_type']) && $_SESSION['checkout_type'] == 'selected' && !empty($_SESSION['checkout_items'])) {
                $selected_items = $_SESSION['checkout_items'];
                $placeholders = str_repeat('?,', count($selected_items) - 1) . '?';

                $delete_params = array_merge([$cart_id], $selected_items);
                $delete_types = "i" . str_repeat("i", count($selected_items));

                $delete_stmt = $conn->prepare("DELETE FROM giohang_chitiet WHERE id_giohang = ? AND id IN ($placeholders)");
                $delete_stmt->bind_param($delete_types, ...$delete_params);
                $delete_stmt->execute();
            } else {
                $delete_stmt = $conn->prepare("DELETE FROM giohang_chitiet WHERE id_giohang = ?");
                $delete_stmt->bind_param("i", $cart_id);
                $delete_stmt->execute();
            }
        }
    }

    // Update promo code usage count if a promo code was used
    if (!empty($promo_code)) {
        debug_log('Updating promo code usage count', ['code' => $promo_code]);

        // Check if the promo code exists
        $promo_check = $conn->prepare("SELECT id FROM khuyen_mai WHERE ma_khuyenmai = ?");
        $promo_check->bind_param("s", $promo_code);
        $promo_check->execute();
        $promo_result = $promo_check->get_result();

        if ($promo_result->num_rows > 0) {
            // Increment the usage count
            $update_promo = $conn->prepare("UPDATE khuyen_mai SET da_su_dung = da_su_dung + 1 WHERE ma_khuyenmai = ?");
            $update_promo->bind_param("s", $promo_code);

            if (!$update_promo->execute()) {
                debug_log('Failed to update promo code usage count', ['error' => $conn->error]);
            } else {
                debug_log('Successfully updated promo code usage count', ['affected_rows' => $update_promo->affected_rows]);
            }
        }
    }

    // Commit the transaction
    $conn->commit();

    // Clear checkout session data
    if ($buy_now) {
        unset($_SESSION['buy_now_cart']);
    } else {
        unset($_SESSION['checkout_items']);
        unset($_SESSION['checkout_type']);
    }

    // Redirect to success page
    $_SESSION['order_success'] = [
        'order_id' => $order_id,
        'order_code' => $order_code
    ];
    header('Location: dathang_thanhcong.php?order_id=' . $order_id);
    exit();

} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    debug_log('Error processing order', $e->getMessage());
    $_SESSION['error_message'] = 'Có lỗi xảy ra: ' . $e->getMessage();
    header('Location: thanhtoan.php');
    exit();
}
?>