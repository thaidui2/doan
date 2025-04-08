<?php
session_start();
include('../config/config.php');

// Kiểm tra đăng nhập
if (!isset($_SESSION['user']) || $_SESSION['user']['logged_in'] !== true) {
    header('Location: ../dangnhap.php?redirect=seller/don-hang.php');
    exit();
}

$user_id = $_SESSION['user']['id'];

// Kiểm tra quyền seller
$check_seller = $conn->prepare("SELECT * FROM users WHERE id_user = ? AND loai_user = 1 AND trang_thai = 1");
$check_seller->bind_param("i", $user_id);
$check_seller->execute();
$result = $check_seller->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "Bạn không có quyền truy cập trang người bán!";
    header("Location: ../index.php");
    exit();
}

/**
 * Cập nhật số lượng sản phẩm sau khi đơn hàng hoàn thành
 * 
 * @param int $order_id ID đơn hàng đã hoàn thành
 */
function updateProductQuantity($order_id) {
    global $conn;
    
    // Lấy tất cả sản phẩm trong đơn hàng
    $order_items_query = $conn->prepare("
        SELECT id_sanpham, id_kichthuoc, id_mausac, soluong 
        FROM donhang_chitiet 
        WHERE id_donhang = ?
    ");
    $order_items_query->bind_param("i", $order_id);
    $order_items_query->execute();
    $result = $order_items_query->get_result();
    
    while($item = $result->fetch_assoc()) {
        // Giảm số lượng trong bảng chi tiết sản phẩm (biến thể)
        if($item['id_kichthuoc'] && $item['id_mausac']) {
            $update_variant = $conn->prepare("
                UPDATE sanpham_chitiet 
                SET soluong = GREATEST(0, soluong - ?) 
                WHERE id_sanpham = ? AND id_kichthuoc = ? AND id_mausac = ?
            ");
            $update_variant->bind_param("iiii", $item['soluong'], $item['id_sanpham'], 
                                              $item['id_kichthuoc'], $item['id_mausac']);
            $update_variant->execute();
        }
        
        // Cập nhật tổng số lượng trong bảng sản phẩm
        $update_total = $conn->prepare("
            UPDATE sanpham SET soluong = (
                SELECT COALESCE(SUM(soluong), 0) 
                FROM sanpham_chitiet 
                WHERE id_sanpham = ?
            ) WHERE id_sanpham = ?
        ");
        $update_total->bind_param("ii", $item['id_sanpham'], $item['id_sanpham']);
        $update_total->execute();
    }
    
    // Ghi log
    $log_file = fopen("../logs/inventory_update.txt", "a");
    fwrite($log_file, date('Y-m-d H:i:s') . " - Cập nhật tồn kho cho đơn hàng #$order_id\n");
    fclose($log_file);
}

// Xử lý cập nhật trạng thái đơn hàng
if (isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $order_id = (int)$_POST['order_id'];
    $new_status = (int)$_POST['new_status'];
    $note = isset($_POST['note']) ? trim($_POST['note']) : '';
    
    // Kiểm tra đơn hàng có sản phẩm của người bán này hay không
    $check_order = $conn->prepare("
        SELECT dh.id_donhang, dh.trangthai
        FROM donhang dh
        WHERE dh.id_donhang = ?
        AND EXISTS (
            SELECT 1
            FROM donhang_chitiet dc
            JOIN sanpham sp ON dc.id_sanpham = sp.id_sanpham
            WHERE dc.id_donhang = dh.id_donhang
            AND sp.id_nguoiban = ?
        )
    ");
    
    $check_order->bind_param("ii", $order_id, $user_id);
    $check_order->execute();
    $result = $check_order->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['error_message'] = "Bạn không có quyền cập nhật đơn hàng này!";
        header("Location: don-hang.php");
        exit();
    }
    
    $order = $result->fetch_assoc();
    $current_status = $order['trangthai'];
    
    // Kiểm tra logic trạng thái đơn hàng
    $valid_transition = true;
    if ($new_status < $current_status && $current_status != 5 && $current_status != 6) {
        // Không cho phép đổi trạng thái lùi, trừ khi đã hủy hoặc hoàn trả
        $valid_transition = false;
    }
    
    if ($current_status == 4 && $new_status != 6) {
        // Đơn đã giao chỉ có thể chuyển sang hoàn trả
        $valid_transition = false;
    }
    
    if ($current_status == 5 || $current_status == 6) {
        // Đơn đã hủy hoặc hoàn trả không thể thay đổi trạng thái
        $valid_transition = false;
    }
    
    if (!$valid_transition) {
        $_SESSION['error_message'] = "Không thể thay đổi trạng thái đơn hàng theo cách này!";
        header("Location: don-hang-chi-tiet.php?id=" . $order_id);
        exit();
    }
    
    try {
        // Bắt đầu transaction
        $conn->begin_transaction();
        
        // Cập nhật trạng thái
        $update_stmt = $conn->prepare("UPDATE donhang SET trangthai = ?, ngaycapnhat = NOW() WHERE id_donhang = ?");
        $update_stmt->bind_param("ii", $new_status, $order_id);
        $update_stmt->execute();
        
        // Get seller's name
        $seller_name = $_SESSION['user']['tenuser'] . " (Shop: " . $_SESSION['user']['ten_shop'] . ")";
        
        // Lưu lịch sử
        $status_names = [
            1 => 'Chờ xác nhận',
            2 => 'Đang xử lý',
            3 => 'Đang giao hàng',
            4 => 'Đã giao',
            5 => 'Đã hủy',
            6 => 'Hoàn trả'
        ];
        
        $change_description = "Thay đổi trạng thái từ \"" . $status_names[$current_status] . "\" sang \"" . $status_names[$new_status] . "\"";
        if (!empty($note)) {
            $change_description .= ". Ghi chú: " . $note;
        }
        
        $log_stmt = $conn->prepare("
            INSERT INTO donhang_lichsu (id_donhang, hanh_dong, nguoi_thuchien, ghi_chu)
            VALUES (?, 'Cập nhật trạng thái', ?, ?)
        ");
        
        $log_stmt->bind_param("iss", $order_id, $seller_name, $change_description);
        $log_stmt->execute();
        
        // Gọi hàm cập nhật số lượng sản phẩm khi trạng thái đơn hàng là "Đã giao"
        if ($new_status == 4) {
            updateProductQuantity($order_id);
        }
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['success_message'] = "Cập nhật trạng thái đơn hàng thành công!";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_message'] = "Lỗi khi cập nhật trạng thái đơn hàng: " . $e->getMessage();
    }
    
    header("Location: don-hang-chi-tiet.php?id=" . $order_id);
    exit();
} else {
    header("Location: don-hang.php");
    exit();
}
?>
