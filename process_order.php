<?php
session_start();
include('config/config.php');

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

// Kiểm tra dữ liệu đầu vào
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

// Lấy thông tin người dùng từ form
$fullname = $_POST['fullname'] ?? '';
$phone = $_POST['phone'] ?? '';
$email = $_POST['email'] ?? '';
$address = $_POST['address'] ?? '';
$province = $_POST['province'] ?? '';
$district = $_POST['district'] ?? '';
$ward = $_POST['ward'] ?? '';
$province_name = $_POST['province_name'] ?? '';
$district_name = $_POST['district_name'] ?? '';
$ward_name = $_POST['ward_name'] ?? '';
$full_address = $_POST['full_address'] ?? '';
$payment_method = $_POST['payment_method'] ?? 'cod';
$note = $_POST['note'] ?? '';

// Xử lý mã giảm giá nếu có
$promo_code = isset($_POST['promo_code']) ? $_POST['promo_code'] : '';
$discount_amount = isset($_POST['discount_amount']) ? (int)$_POST['discount_amount'] : 0;
$discount_id = isset($_POST['discount_id']) ? (int)$_POST['discount_id'] : 0;

// Lấy thông tin giỏ hàng
$session_id = session_id();
$user_id = isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : null;
$buy_now = isset($_GET['buy_now']) || isset($_POST['buy_now']);

// Nếu là mua ngay và có thông tin mua ngay trong session, bỏ qua kiểm tra giỏ hàng
if ($buy_now && isset($_SESSION['buy_now_cart'])) {
    // Sử dụng thông tin từ session buy_now_cart
    $cart_items = [$_SESSION['buy_now_cart']];
    $total_amount = $_SESSION['buy_now_cart']['thanh_tien'];
    $order_items = $cart_items;
} else {
    // Xử lý giỏ hàng thông thường
    // Lấy ID giỏ hàng
    if ($user_id) {
        $stmt = $conn->prepare("SELECT id_giohang FROM giohang WHERE id_nguoidung = ?");
        $stmt->bind_param("i", $user_id);
    } else {
        $stmt = $conn->prepare("SELECT id_giohang FROM giohang WHERE session_id = ? AND id_nguoidung IS NULL");
        $stmt->bind_param("s", $session_id);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        // Nếu không phải mua ngay và không có giỏ hàng, chuyển hướng về trang giỏ hàng
        header('Location: giohang.php');
        exit();
    }

    $cart = $result->fetch_assoc();
    $cart_id = $cart['id_giohang'];

    // Xác định các sản phẩm cần thanh toán
    if (isset($_SESSION['checkout_type']) && $_SESSION['checkout_type'] == 'selected' && isset($_POST['selected_items'])) {
        // Nếu thanh toán các sản phẩm đã chọn
        $selected_items = $_POST['selected_items'];
        $placeholders = str_repeat('?,', count($selected_items) - 1) . '?';
        
        $query = "
            SELECT gct.*, sp.tensanpham, sp.hinhanh, kt.tenkichthuoc, ms.tenmau
            FROM giohang_chitiet gct
            JOIN sanpham sp ON gct.id_sanpham = sp.id_sanpham
            LEFT JOIN kichthuoc kt ON gct.id_kichthuoc = kt.id_kichthuoc
            LEFT JOIN mausac ms ON gct.id_mausac = ms.id_mausac
            WHERE gct.id_giohang = ? AND gct.id_chitiet IN ($placeholders)
        ";
        
        $types = "i" . str_repeat("i", count($selected_items));
        $params = array_merge([$cart_id], $selected_items);
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
    } else {
        // Nếu thanh toán tất cả sản phẩm trong giỏ hàng
        $stmt = $conn->prepare("
            SELECT gct.*, sp.tensanpham, sp.hinhanh, kt.tenkichthuoc, ms.tenmau
            FROM giohang_chitiet gct
            JOIN sanpham sp ON gct.id_sanpham = sp.id_sanpham
            LEFT JOIN kichthuoc kt ON gct.id_kichthuoc = kt.id_kichthuoc
            LEFT JOIN mausac ms ON gct.id_mausac = ms.id_mausac
            WHERE gct.id_giohang = ?
        ");
        $stmt->bind_param("i", $cart_id);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    // Tính tổng tiền và thu thập các sản phẩm
    $total_amount = 0;
    $order_items = [];

    while ($item = $result->fetch_assoc()) {
        $order_items[] = $item;
        $total_amount += $item['thanh_tien'];
    }

    if (empty($order_items)) {
        header('Location: giohang.php');
        exit();
    }
}

// Kiểm tra lại mã giảm giá nếu có
if (!empty($promo_code) && $discount_amount > 0 && $discount_id > 0) {
    // Kiểm tra mã giảm giá có hợp lệ không
    $promo_check = $conn->prepare("
        SELECT * FROM khuyen_mai 
        WHERE id = ? AND ma_code = ? AND trang_thai = 1 
        AND CURRENT_TIMESTAMP BETWEEN ngay_bat_dau AND ngay_ket_thuc
        AND so_luong > so_luong_da_dung
    ");
    $promo_check->bind_param("is", $discount_id, $promo_code);
    $promo_check->execute();
    
    if ($promo_check->get_result()->num_rows > 0) {
        // Trừ tiền giảm giá từ tổng tiền
        $total_amount -= $discount_amount;
        if ($total_amount < 0) $total_amount = 0;
        
        // Lưu thông tin mã giảm giá để sử dụng sau khi tạo đơn hàng
        $valid_promo = true;
        
        // Cập nhật số lượng đã sử dụng
        $update_promo = $conn->prepare("
            UPDATE khuyen_mai 
            SET so_luong_da_dung = so_luong_da_dung + 1 
            WHERE id = ?
        ");
        $update_promo->bind_param("i", $discount_id);
        $update_promo->execute();
        
        // KHÔNG lưu vào lịch sử ở đây, sẽ xử lý sau khi có order_id
    }
}

// Thêm phí vận chuyển
$shipping_fee = 30000;
$grand_total = $total_amount + $shipping_fee;

// Bắt đầu transaction
$conn->begin_transaction();

try {
    // 1. Tạo đơn hàng mới
    $status = 1; // Chờ xử lý
    
    // Kiểm tra phương thức thanh toán cho người chưa đăng nhập
    if (!isset($_SESSION['user']) && $payment_method == 'cod') {
        throw new Exception("Người dùng chưa đăng nhập không thể sử dụng phương thức thanh toán COD.");
    }
    
    // Nếu không có user_id, sử dụng câu lệnh SQL khác không chứa id_nguoidung
    if ($user_id === null) {
        $order_stmt = $conn->prepare("
            INSERT INTO donhang (tennguoinhan, sodienthoai, email, diachi, 
                             tinh_tp, quan_huyen, phuong_xa, tongtien, phivanchuyen,
                             phuongthucthanhtoan, ghichu, trangthai, ngaytao)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $order_stmt->bind_param(
            "sssssssddsis",
            $fullname, $phone, $email, $address, 
            $province_name, $district_name, $ward_name,
            $grand_total, $shipping_fee, $payment_method, $note, $status
        );
    } else {
        // Nếu có user_id, dùng câu lệnh SQL đầy đủ
        $order_stmt = $conn->prepare("
            INSERT INTO donhang (id_nguoidung, tennguoinhan, sodienthoai, email, diachi, 
                             tinh_tp, quan_huyen, phuong_xa, tongtien, phivanchuyen,
                             phuongthucthanhtoan, ghichu, trangthai, ngaytao)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $order_stmt->bind_param(
            "isssssssddssi",
            $user_id, $fullname, $phone, $email, $address, 
            $province_name, $district_name, $ward_name,
            $grand_total, $shipping_fee, $payment_method, $note, $status
        );
    }
    
    $order_stmt->execute();
    $order_id = $conn->insert_id;
    
    // Lưu thông tin sử dụng mã giảm giá vào lịch sử nếu có
    if (isset($valid_promo) && $valid_promo && $user_id) {
        $history_stmt = $conn->prepare("
            INSERT INTO khuyen_mai_lichsu (id_khuyen_mai, id_nguoidung, id_donhang, gia_tri_giam, ngay_su_dung)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $history_stmt->bind_param("iiid", $discount_id, $user_id, $order_id, $discount_amount);
        $history_stmt->execute();
    }
    
    // 2. Thêm chi tiết đơn hàng
    foreach ($order_items as $item) {
        logToFile("Thêm sản phẩm: {$item['tensanpham']} - ID kích thước: " . 
                  (isset($item['id_kichthuoc']) ? $item['id_kichthuoc'] : 'NULL') . 
                  ", ID màu sắc: " . (isset($item['id_mausac']) ? $item['id_mausac'] : 'NULL'));
        
        // Đảm bảo giá không bao giờ là NULL
        $price = $item['gia'];
        
        // Nếu giá từ giỏ hàng là NULL hoặc 0, lấy giá từ bảng sản phẩm
        if ($price === NULL || $price == 0) {
            $price_query = $conn->prepare("SELECT gia FROM sanpham WHERE id_sanpham = ?");
            $price_query->bind_param("i", $item['id_sanpham']);
            $price_query->execute();
            $price_result = $price_query->get_result();
            if ($price_result->num_rows > 0) {
                $price_data = $price_result->fetch_assoc();
                $price = $price_data['gia'];
            }
        }
        
        // Nếu vẫn không có giá, sử dụng giá từ thành tiền chia cho số lượng
        if ($price === NULL || $price == 0) {
            $price = $item['thanh_tien'] / $item['soluong'];
        }
        
        // Đảm bảo giá luôn là số dương
        $price = max(1, $price);
        
        // Thực hiện thêm vào bảng đơn hàng chi tiết với giá đã được đảm bảo
        $detail_stmt = $conn->prepare("
            INSERT INTO donhang_chitiet (id_donhang, id_sanpham, id_kichthuoc, id_mausac, 
                                      soluong, gia, thanh_tien)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $detail_stmt->bind_param(
            "iiiiiss",
            $order_id, $item['id_sanpham'], $item['id_kichthuoc'], $item['id_mausac'],
            $item['soluong'], $price, $item['thanh_tien']
        );
        
        if (!$detail_stmt->execute()) {
            throw new Exception("Lỗi thêm chi tiết đơn hàng: " . $detail_stmt->error . " cho sản phẩm: " . $item['tensanpham']);
        }
    }
    
    // 3. Xóa các sản phẩm đã đặt khỏi giỏ hàng
    if (isset($_SESSION['checkout_type']) && $_SESSION['checkout_type'] == 'selected' && isset($_POST['selected_items'])) {
        $selected_items = $_POST['selected_items'];
        $placeholders = str_repeat('?,', count($selected_items) - 1) . '?';
        
        $delete_query = "DELETE FROM giohang_chitiet WHERE id_giohang = ? AND id_chitiet IN ($placeholders)";
        
        $types = "i" . str_repeat("i", count($selected_items));
        $params = array_merge([$cart_id], $selected_items);
        
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->bind_param($types, ...$params);
        $delete_stmt->execute();
    } else {
        // Xóa tất cả sản phẩm trong giỏ hàng
        $delete_stmt = $conn->prepare("DELETE FROM giohang_chitiet WHERE id_giohang = ?");
        $delete_stmt->bind_param("i", $cart_id);
        $delete_stmt->execute();
    }
    
    // Sau khi hoàn thành đơn hàng, xóa thông tin mua ngay nếu có
    if ($buy_now && isset($_SESSION['buy_now_cart'])) {
        unset($_SESSION['buy_now_cart']);
    }
    
    // 4. Commit transaction
    $conn->commit();
    
    // 5. Lưu ID đơn hàng vào session
    $_SESSION['order_id'] = $order_id;
    
    // Thêm đoạn code này vào process_order.php sau khi tạo đơn hàng thành công
    $_SESSION['last_order_id'] = $order_id;

    // Sau khi đã insert đơn hàng thành công và có order_id
    // Thêm đoạn code này trước khi chuyển hướng cho thanh toán VNPAY
    if ($_POST['payment_method'] !== 'vnpay') {
        // Lưu thông tin thanh toán cho các phương thức không phải VNPAY
        savePaymentInfo($conn, $order_id, $_POST['payment_method'], $total_amount + $shipping_fee, $_POST['note'] ?? '');
    }

    // Còn với VNPAY, giữ nguyên code hiện tại vì sẽ được lưu sau khi thanh toán thành công
    if ($_POST['payment_method'] === 'vnpay') {
        // Lưu thông tin thanh toán vào session để sử dụng trong vnpay_create_payment.php
        $_SESSION['payment_info'] = [
            'order_id' => $order_id,
            'amount' => $total_amount + $shipping_fee,
            'order_desc' => 'Thanh toan don hang #' . $order_id . ' tai Bug Shop'
        ];

        // Chuyển hướng đến trang tạo thanh toán VNPAY
        header('Location: vnpay_create_payment.php');
        exit();
    }

    // Các phương thức thanh toán khác xử lý như hiện tại

    // Đối với COD hoặc chuyển khoản thì chuyển hướng về trang cảm ơn
    header("Location: dathang_thanhcong.php");
    exit;
    
    // 6. Xóa dữ liệu thanh toán từ session
    unset($_SESSION['checkout_type']);
    unset($_SESSION['checkout_items']);
    
} catch (Exception $e) {
    // Rollback transaction nếu có lỗi
    $conn->rollback();
    
    // Log lỗi chi tiết với dữ liệu
    $error_message = "Lỗi khi xử lý đơn hàng: " . $e->getMessage();
    logError($error_message, [
        'SQL Error' => $conn->error,
        'SQL State' => $conn->sqlstate,
        'Form Data' => $_POST,
        'User ID' => $user_id
    ]);
    error_log($error_message);
    
    // Hiển thị thông báo lỗi
    $_SESSION['error_message'] = "Có lỗi xảy ra khi xử lý đơn hàng: " . $e->getMessage();
    header('Location: thanhtoan.php?error=order_failed');
    exit();
}