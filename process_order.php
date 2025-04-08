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

// Lấy thông tin giỏ hàng
$session_id = session_id();
$user_id = isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : null;

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
    // Không có giỏ hàng, chuyển hướng về trang giỏ hàng
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

// Thêm phí vận chuyển
$shipping_fee = 30000;
$grand_total = $total_amount + $shipping_fee;

// Bắt đầu transaction
$conn->begin_transaction();

try {
    // 1. Tạo đơn hàng mới
    $status = 1; // Chờ xử lý
    
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
    
    // 4. Commit transaction
    $conn->commit();
    
    // 5. Lưu ID đơn hàng vào session
    $_SESSION['order_id'] = $order_id;
    
    // Thêm đoạn code này vào process_order.php sau khi tạo đơn hàng thành công
    $_SESSION['last_order_id'] = $order_id;
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