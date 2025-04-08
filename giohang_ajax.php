<?php
session_start();
header('Content-Type: application/json');

// Log lỗi vào file
function logError($message, $data = null) {
    $log = date('Y-m-d H:i:s') . " - " . $message;
    if ($data) {
        $log .= " - Data: " . json_encode($data);
    }
    file_put_contents('cart_error.log', $log . "\n", FILE_APPEND);
}

// Debug session và request
$debug = [
    'session_id' => session_id(),
    'action' => $_GET['action'] ?? 'no_action',
    'item_id' => $_GET['item_id'] ?? 'no_item',
    'user_session' => isset($_SESSION['user_id']) ? 'exists' : 'not_exists'
];
logError("Request received", $debug);

include('config/config.php');

// Hàm cập nhật tổng tiền giỏ hàng
function updateCartTotal($conn, $cart_id) {
    $stmt = $conn->prepare("
        UPDATE giohang
        SET tong_tien = COALESCE((
            SELECT SUM(thanh_tien) FROM giohang_chitiet WHERE id_giohang = ?
        ), 0),
        ngay_capnhat = NOW()
        WHERE id_giohang = ?
    ");
    $stmt->bind_param("ii", $cart_id, $cart_id);
    $stmt->execute();
}

// Hàm lấy giỏ hàng hiện tại
function getCart($conn) {
    $session_id = session_id();
    
    // Kiểm tra nhiều cách lưu user_id trong session
    $user_id = null;
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
    } else if (isset($_SESSION['user']['id'])) {
        $user_id = $_SESSION['user']['id'];
    }
    
    if ($user_id) {
        $stmt = $conn->prepare("SELECT * FROM giohang WHERE id_nguoidung = ?");
        $stmt->bind_param("i", $user_id);
    } else {
        $stmt = $conn->prepare("SELECT * FROM giohang WHERE session_id = ? AND id_nguoidung IS NULL");
        $stmt->bind_param("s", $session_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->num_rows > 0 ? $result->fetch_assoc() : null;
}

// Xử lý các action
$action = $_GET['action'] ?? '';
$response = ['success' => false, 'message' => 'Không có hành động được chỉ định'];

// Lấy thông tin giỏ hàng
$cart = getCart($conn);

if (!$cart) {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy giỏ hàng']);
    exit;
}

$cart_id = $cart['id_giohang'];

switch ($action) {
    case 'remove':
        $item_id = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;
        
        if ($item_id > 0) {
            $delete = $conn->prepare("DELETE FROM giohang_chitiet WHERE id_chitiet = ? AND id_giohang = ?");
            $delete->bind_param("ii", $item_id, $cart_id);
            $result = $delete->execute();
            
            if ($result) {
                // Cập nhật tổng tiền giỏ hàng
                updateCartTotal($conn, $cart_id);
                
                // Lấy thông tin cập nhật của giỏ hàng
                $updated_cart = getCart($conn);
                $cart_total = $updated_cart['tong_tien'];
                
                // Đếm số lượng sản phẩm trong giỏ
                $count_stmt = $conn->prepare("SELECT SUM(soluong) as total_items FROM giohang_chitiet WHERE id_giohang = ?");
                $count_stmt->bind_param("i", $cart_id);
                $count_stmt->execute();
                $count_result = $count_stmt->get_result();
                $cart_count = $count_result->fetch_assoc()['total_items'] ?? 0;
                
                $response = [
                    'success' => true,
                    'message' => 'Đã xóa sản phẩm khỏi giỏ hàng',
                    'cart_total' => $cart_total,
                    'cart_count' => (int)$cart_count
                ];
            } else {
                $response = ['success' => false, 'message' => 'Không thể xóa sản phẩm'];
            }
        } else {
            $response = ['success' => false, 'message' => 'ID sản phẩm không hợp lệ'];
        }
        break;
        
    case 'clear_cart':
        // Xóa tất cả sản phẩm trong giỏ hàng
        $delete = $conn->prepare("DELETE FROM giohang_chitiet WHERE id_giohang = ?");
        $delete->bind_param("i", $cart_id);
        $result = $delete->execute();
        
        if ($result) {
            $affected_rows = $delete->affected_rows;
            
            // Cập nhật tổng tiền giỏ hàng về 0
            $update = $conn->prepare("UPDATE giohang SET tong_tien = 0, ngay_capnhat = NOW() WHERE id_giohang = ?");
            $update->bind_param("i", $cart_id);
            $update->execute();
            
            $response = [
                'success' => true,
                'message' => 'Đã xóa toàn bộ giỏ hàng',
                'cart_total' => 0,
                'cart_count' => 0,
                'affected_rows' => $affected_rows
            ];
            
            // Ghi log để debug
            logError("Clear cart successful", [
                'cart_id' => $cart_id,
                'affected_rows' => $affected_rows
            ]);
        } else {
            logError("Clear cart failed", [
                'cart_id' => $cart_id,
                'error' => $conn->error
            ]);
            $response = ['success' => false, 'message' => 'Không thể xóa giỏ hàng: ' . $conn->error];
        }
        break;
        
    default:
        $response = ['success' => false, 'message' => 'Hành động không hợp lệ'];
        break;
}

echo json_encode($response);
?>