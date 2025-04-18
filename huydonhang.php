<?php
session_start();
include('config/config.php');

// Kiểm tra đăng nhập
if (!isset($_SESSION['user'])) {
    $_SESSION['error_message'] = "Vui lòng đăng nhập để thực hiện chức năng này.";
    header('Location: dangnhap.php');
    exit;
}

// Kiểm tra dữ liệu nhận được từ form
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['order_id'])) {
    $_SESSION['error_message'] = "Yêu cầu không hợp lệ.";
    header('Location: donhang.php');
    exit;
}

$user_id = $_SESSION['user']['id'];
$order_id = (int)$_POST['order_id'];
$cancel_reason = isset($_POST['cancel_reason']) ? $_POST['cancel_reason'] : '';

// Nếu là lý do khác, lấy nội dung từ trường other_reason
if ($cancel_reason === 'other' && isset($_POST['other_reason']) && !empty($_POST['other_reason'])) {
    $cancel_reason = trim($_POST['other_reason']);
}

// Kiểm tra xem đơn hàng có tồn tại và thuộc về người dùng hiện tại không
$check_query = $conn->prepare("
    SELECT id, trang_thai_don_hang, ma_donhang FROM donhang 
    WHERE id = ? AND id_user = ? AND trang_thai_don_hang = 1
");
$check_query->bind_param("ii", $order_id, $user_id);
$check_query->execute();
$result = $check_query->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "Đơn hàng không tồn tại hoặc không thể hủy.";
    header('Location: donhang.php');
    exit;
}

$order = $result->fetch_assoc();

try {
    // Bắt đầu transaction
    $conn->begin_transaction();
    
    // Cập nhật trạng thái đơn hàng thành "Đã hủy" (trạng thái 5)
    $update_query = $conn->prepare("UPDATE donhang SET trang_thai_don_hang = 5, ghi_chu = CONCAT(IFNULL(ghi_chu, ''), '\nĐơn hàng đã bị hủy bởi khách hàng với lý do: ', ?) WHERE id = ?");
    $update_query->bind_param("si", $cancel_reason, $order_id);
    
    if (!$update_query->execute()) {
        throw new Exception("Không thể cập nhật trạng thái đơn hàng: " . $conn->error);
    }
    
    // Thêm vào lịch sử đơn hàng
    $user_name = $_SESSION['user']['tenuser'] ?? 'Khách hàng';
    $action = "Hủy đơn hàng";
    $note = "Đơn hàng đã bị hủy với lý do: " . $cancel_reason;
    
    $history_query = $conn->prepare("
        INSERT INTO donhang_lichsu (id_donhang, hanh_dong, nguoi_thuchien, ghi_chu) 
        VALUES (?, ?, ?, ?)
    ");
    $history_query->bind_param("isss", $order_id, $action, $user_name, $note);
    
    if (!$history_query->execute()) {
        throw new Exception("Không thể thêm vào lịch sử đơn hàng: " . $conn->error);
    }
    
    // Khôi phục số lượng sản phẩm trong kho
    $items_query = $conn->prepare("
        SELECT id_bienthe, soluong FROM donhang_chitiet 
        WHERE id_donhang = ? AND id_bienthe IS NOT NULL
    ");
    $items_query->bind_param("i", $order_id);
    $items_query->execute();
    $items_result = $items_query->get_result();
    
    while ($item = $items_result->fetch_assoc()) {
        // Cập nhật số lượng trong bảng biến thể sản phẩm
        $update_quantity = $conn->prepare("
            UPDATE sanpham_bien_the 
            SET so_luong = so_luong + ? 
            WHERE id = ?
        ");
        $update_quantity->bind_param("ii", $item['soluong'], $item['id_bienthe']);
        $update_quantity->execute();
        
        // Lấy id_sanpham từ biến thể để cập nhật tổng số lượng trong bảng sản phẩm
        $get_product_id = $conn->prepare("
            SELECT id_sanpham FROM sanpham_bien_the WHERE id = ?
        ");
        $get_product_id->bind_param("i", $item['id_bienthe']);
        $get_product_id->execute();
        $product_result = $get_product_id->get_result();
        
        if ($product_result->num_rows > 0) {
            $product_data = $product_result->fetch_assoc();
            $product_id = $product_data['id_sanpham'];
            
            // Cập nhật tổng số lượng trong bảng sản phẩm
            $update_product_quantity = $conn->prepare("
                UPDATE sanpham 
                SET so_luong = (
                    SELECT SUM(so_luong) 
                    FROM sanpham_bien_the 
                    WHERE id_sanpham = ?
                )
                WHERE id = ?
            ");
            $update_product_quantity->bind_param("ii", $product_id, $product_id);
            $update_product_quantity->execute();
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    $_SESSION['success_message'] = "Đơn hàng #" . $order['ma_donhang'] . " đã được hủy thành công.";
} catch (Exception $e) {
    // Rollback transaction nếu có lỗi
    $conn->rollback();
    $_SESSION['error_message'] = "Có lỗi xảy ra: " . $e->getMessage();
}

// Chuyển hướng về trang đơn hàng
header('Location: donhang.php');
exit;
?>