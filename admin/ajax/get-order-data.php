<?php
session_start();
header('Content-Type: application/json');

// Kiểm tra đăng nhập admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Không có quyền truy cập.']);
    exit();
}

include('../../config/config.php');

// Kiểm tra có ID đơn hàng không
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID đơn hàng không hợp lệ.']);
    exit();
}

$order_id = (int)$_GET['id'];

// Lấy thông tin đơn hàng
$order_query = "SELECT * FROM donhang WHERE id_donhang = ?";
$stmt = $conn->prepare($order_query);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy đơn hàng.']);
    exit();
}

$order = $result->fetch_assoc();

// Lấy thông tin chi tiết đơn hàng
$items_query = "
    SELECT dct.*, sp.tensanpham, sp.hinhanh, kt.tenkichthuoc, ms.tenmau 
    FROM donhang_chitiet dct
    LEFT JOIN sanpham sp ON dct.id_sanpham = sp.id_sanpham
    LEFT JOIN kichthuoc kt ON dct.id_kichthuoc = kt.id_kichthuoc
    LEFT JOIN mausac ms ON dct.id_mausac = ms.id_mausac
    WHERE dct.id_donhang = ?
";

$stmt = $conn->prepare($items_query);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$items_result = $stmt->get_result();

$order_items = [];
while ($item = $items_result->fetch_assoc()) {
    // Xử lý hình ảnh
    if (!empty($item['hinhanh'])) {
        $item['image_url'] = '../uploads/products/' . $item['hinhanh'];
    } else {
        $item['image_url'] = '../images/no-image.png';
    }
    
    $order_items[] = $item;
}

// Trạng thái đơn hàng
$status_labels = [
    1 => 'Chờ xác nhận',
    2 => 'Đang xử lý',
    3 => 'Đang giao hàng',
    4 => 'Đã giao',
    5 => 'Đã hủy',
    6 => 'Hoàn trả'
];

$status_badges = [
    1 => 'warning',
    2 => 'info',
    3 => 'primary',
    4 => 'success',
    5 => 'danger',
    6 => 'secondary'
];

// Lấy thông tin khách hàng nếu có
$customer = null;
if ($order['id_nguoidung']) {
    $user_query = "SELECT * FROM users WHERE id_user = ?";
    $stmt = $conn->prepare($user_query);
    $stmt->bind_param("i", $order['id_nguoidung']);
    $stmt->execute();
    $user_result = $stmt->get_result();
    
    if ($user_result->num_rows > 0) {
        $customer = $user_result->fetch_assoc();
    }
}

// Kết quả trả về
$response = [
    'success' => true,
    'order' => $order,
    'items' => $order_items,
    'status_label' => $status_labels[$order['trangthai']] ?? 'Không xác định',
    'status_badge' => $status_badges[$order['trangthai']] ?? 'secondary',
    'customer' => $customer
];

echo json_encode($response);
?>
