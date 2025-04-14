<?php
session_start();
include('config/config.php');

// Kiểm tra đăng nhập
if (!isset($_SESSION['user']) || $_SESSION['user']['logged_in'] !== true) {
    header('Location: dangnhap.php');
    exit();
}

$user_id = $_SESSION['user']['id'];

if (isset($_GET['id'])) {
    $order_id = (int)$_GET['id'];
    
    // Kiểm tra đơn hàng tồn tại và thuộc về người dùng hiện tại
    $stmt = $conn->prepare("
        SELECT trangthai, ghichu, tongtien, phuongthucthanhtoan 
        FROM donhang 
        WHERE id_donhang = ? AND id_nguoidung = ?
    ");
    $stmt->bind_param("ii", $order_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $order = $result->fetch_assoc();
        
        // Kiểm tra trạng thái đơn hàng
        if ($order['trangthai'] == 1 || ($order['trangthai'] == 2 && strpos(strtolower($order['ghichu']), 'vnpay') !== false)) {
            // Cập nhật trạng thái đơn hàng thành "Đã hủy"
            $update_stmt = $conn->prepare("UPDATE donhang SET trangthai = 5 WHERE id_donhang = ?");
            $update_stmt->bind_param("i", $order_id);
            
            if ($update_stmt->execute()) {
                // Xử lý hoàn tiền VNPAY nếu cần
                $is_vnpay = strpos(strtolower($order['ghichu']), 'vnpay') !== false;
                
                if ($is_vnpay) {
                    // Ghi log hoàn tiền VNPAY
                    $refund_log_stmt = $conn->prepare("
                        INSERT INTO refund_logs (order_id, amount, payment_method, status, created_at)
                        VALUES (?, ?, 'VNPAY', 'pending', NOW())
                    ");
                    $refund_log_stmt->bind_param("id", $order_id, $order['tongtien']);
                    $refund_log_stmt->execute();
                    
                    $_SESSION['success_message'] = "Đã hủy đơn hàng thành công. Tiền sẽ được hoàn về tài khoản của bạn trong 7-14 ngày làm việc.";
                } else {
                    $_SESSION['success_message'] = "Đã hủy đơn hàng thành công.";
                }
                
                // Gửi email thông báo hủy đơn
                // Code gửi email...
                
            } else {
                $_SESSION['error_message'] = "Không thể hủy đơn hàng. Vui lòng thử lại sau.";
            }
            
        } else {
            $_SESSION['error_message'] = "Không thể hủy đơn hàng ở trạng thái hiện tại.";
        }
    } else {
        $_SESSION['error_message'] = "Không tìm thấy đơn hàng.";
    }
    
    // Chuyển hướng về trang đơn hàng
    header('Location: donhang.php');
    exit();
} else {
    header('Location: donhang.php');
    exit();
}
?>