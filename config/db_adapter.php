<?php
/**
 * File adapter để hỗ trợ chuyển đổi giữa cấu trúc database cũ và mới
 * Các hàm này giúp đảm bảo các truy vấn tương thích với cấu trúc mới
 */

/**
 * Chuyển đổi truy vấn sản phẩm
 * 
 * @param mysqli $conn Kết nối database
 * @param string $sql Câu truy vấn gốc
 * @return mixed Kết quả truy vấn
 */
function queryProducts($conn, $sql) {
    // Thay thế các cột, bảng không còn tồn tại trong database mới
    $sql = str_replace("loaisanpham", "danhmuc", $sql);
    $sql = str_replace("id_loai", "id_danhmuc", $sql);
    $sql = str_replace("tenloai", "ten", $sql);
    $sql = str_replace("mausac", "thuoc_tinh", $sql); 
    $sql = str_replace("kichthuoc", "thuoc_tinh", $sql);
    
    return $conn->query($sql);
}

/**
 * Lấy chi tiết sản phẩm từ ID
 * 
 * @param mysqli $conn Kết nối database
 * @param int $product_id ID sản phẩm
 * @return array Thông tin sản phẩm
 */
function getProduct($conn, $product_id) {
    $stmt = $conn->prepare("
        SELECT sp.*, dm.ten as tendanhmuc 
        FROM sanpham sp
        LEFT JOIN danhmuc dm ON sp.id_danhmuc = dm.id
        WHERE sp.id = ?
    ");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

/**
 * Lấy biến thể sản phẩm
 * 
 * @param mysqli $conn Kết nối database
 * @param int $product_id ID sản phẩm
 * @return array Danh sách biến thể
 */
function getProductVariants($conn, $product_id) {
    $stmt = $conn->prepare("
        SELECT bt.*, 
               tm.ten as tenmau, tm.ma_mau as mamau,
               ts.ten as tensize
        FROM sanpham_bien_the bt
        JOIN thuoc_tinh tm ON bt.id_mau = tm.id AND tm.loai = 'color'
        JOIN thuoc_tinh ts ON bt.id_size = ts.id AND ts.loai = 'size'
        WHERE bt.id_sanpham = ?
    ");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    return $stmt->get_result();
}

/**
 * Lấy thông tin giỏ hàng
 * 
 * @param mysqli $conn Kết nối database
 * @param string $session_id Session ID
 * @param int $user_id ID người dùng (nếu đã đăng nhập)
 * @return array Thông tin giỏ hàng
 */
function getCart($conn, $session_id, $user_id = null) {
    if ($user_id) {
        $stmt = $conn->prepare("SELECT * FROM giohang WHERE id_user = ?");
        $stmt->bind_param("i", $user_id);
    } else {
        $stmt = $conn->prepare("SELECT * FROM giohang WHERE session_id = ? AND id_user IS NULL");
        $stmt->bind_param("s", $session_id);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

/**
 * Lấy chi tiết giỏ hàng
 * 
 * @param mysqli $conn Kết nối database
 * @param int $cart_id ID giỏ hàng
 * @return mysqli_result Kết quả truy vấn
 */
function getCartItems($conn, $cart_id) {
    $stmt = $conn->prepare("
        SELECT gc.*, sp.tensanpham, sp.hinhanh, 
               tm.ten as tenmau, tm.ma_mau as mamau,
               ts.ten as tensize
        FROM giohang_chitiet gc
        JOIN sanpham_bien_the bt ON gc.id_bienthe = bt.id
        JOIN sanpham sp ON bt.id_sanpham = sp.id
        JOIN thuoc_tinh tm ON bt.id_mau = tm.id AND tm.loai = 'color'
        JOIN thuoc_tinh ts ON bt.id_size = ts.id AND ts.loai = 'size'
        WHERE gc.id_giohang = ?
    ");
    $stmt->bind_param("i", $cart_id);
    $stmt->execute();
    return $stmt->get_result();
}

/**
 * Lấy đơn hàng theo ID
 * 
 * @param mysqli $conn Kết nối database
 * @param int $order_id ID đơn hàng
 * @return array Thông tin đơn hàng
 */
function getOrder($conn, $order_id) {
    $stmt = $conn->prepare("
        SELECT * FROM donhang WHERE id = ?
    ");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

/**
 * Lấy chi tiết đơn hàng
 * 
 * @param mysqli $conn Kết nối database
 * @param int $order_id ID đơn hàng
 * @return mysqli_result Kết quả truy vấn
 */
function getOrderItems($conn, $order_id) {
    $stmt = $conn->prepare("
        SELECT dc.*, sp.tensanpham, sp.hinhanh
        FROM donhang_chitiet dc
        JOIN sanpham sp ON dc.id_sanpham = sp.id
        WHERE dc.id_donhang = ?
    ");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    return $stmt->get_result();
}
?>
