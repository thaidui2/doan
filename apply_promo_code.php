<?php
// filepath: c:\xampp\htdocs\bug_shop\apply_promo_code.php
session_start();
include('config/config.php');

// Ghi log để kiểm tra lỗi
$log_dir = __DIR__ . '/logs';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}
$log_file = $log_dir . '/promo_code_log_' . date('Y-m-d') . '.log';

try {
    // Kiểm tra request
    if (!isset($_POST['code']) || empty($_POST['code'])) {
        throw new Exception('Vui lòng nhập mã giảm giá');
    }

    $code = trim($_POST['code']);
    $total_amount = isset($_POST['total']) ? (float)$_POST['total'] : 0;
    $user_id = isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : null;
    $cart_items = isset($_POST['cart_items']) ? json_decode($_POST['cart_items'], true) : [];

    // Log thông tin đầu vào
    $log_data = date('Y-m-d H:i:s') . " | Request - Code: $code | Total: $total_amount | User: " . ($user_id ?? 'guest') . "\n";
    file_put_contents($log_file, $log_data, FILE_APPEND);

    // Lấy thông tin các sản phẩm đang mua
    $product_ids = [];
    $category_ids = [];
    
    if (!empty($cart_items)) {
        foreach ($cart_items as $item) {
            if (isset($item['id_sanpham']) && !in_array($item['id_sanpham'], $product_ids)) {
                $product_ids[] = $item['id_sanpham'];
            }
        }
        
        // Lấy danh mục của các sản phẩm
        if (!empty($product_ids)) {
            $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
            $stmt = $conn->prepare("SELECT id_sanpham, id_loai FROM sanpham WHERE id_sanpham IN ($placeholders)");
            
            $stmt->bind_param(str_repeat('i', count($product_ids)), ...$product_ids);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                if (!in_array($row['id_loai'], $category_ids)) {
                    $category_ids[] = $row['id_loai'];
                }
            }
        }
    }

    // Truy vấn thông tin mã giảm giá
    $stmt = $conn->prepare("
        SELECT * FROM khuyen_mai 
        WHERE ma_code = ? 
        AND trang_thai = 1 
        AND CURRENT_TIMESTAMP BETWEEN ngay_bat_dau AND ngay_ket_thuc
        AND (so_luong > so_luong_da_dung OR so_luong = 0)
    ");

    $stmt->bind_param("s", $code);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Mã giảm giá không hợp lệ hoặc đã hết hạn');
    }

    $promo = $result->fetch_assoc();

    // Kiểm tra giá trị đơn hàng tối thiểu
    if ($total_amount < $promo['gia_tri_don_toi_thieu']) {
        $min_order = number_format($promo['gia_tri_don_toi_thieu'], 0, ',', '.');
        throw new Exception("Giá trị đơn hàng tối thiểu để sử dụng mã này là {$min_order}₫");
    }

    // Kiểm tra điều kiện áp dụng theo sản phẩm hoặc danh mục
    if ($promo['ap_dung_sanpham'] == 1) {
        // Lấy danh sách sản phẩm được áp dụng
        $stmt = $conn->prepare("
            SELECT id_sanpham FROM khuyen_mai_sanpham 
            WHERE id_khuyen_mai = ?
        ");
        $stmt->bind_param("i", $promo['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $allowed_products = [];
        while ($row = $result->fetch_assoc()) {
            $allowed_products[] = $row['id_sanpham'];
        }
        
        // Kiểm tra xem có sản phẩm nào trong giỏ hàng thuộc diện áp dụng không
        $valid_products = array_intersect($product_ids, $allowed_products);
        
        if (empty($valid_products)) {
            throw new Exception('Mã giảm giá này không áp dụng cho sản phẩm nào trong giỏ hàng của bạn');
        }
    }
    
    if ($promo['ap_dung_loai'] == 1) {
        // Lấy danh sách loại được áp dụng
        $stmt = $conn->prepare("
            SELECT id_loai FROM khuyen_mai_loai
            WHERE id_khuyen_mai = ?
        ");
        $stmt->bind_param("i", $promo['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $allowed_categories = [];
        while ($row = $result->fetch_assoc()) {
            $allowed_categories[] = $row['id_loai'];
        }
        
        // Kiểm tra xem có sản phẩm nào trong giỏ hàng thuộc loại áp dụng không
        $valid_categories = array_intersect($category_ids, $allowed_categories);
        
        if (empty($valid_categories)) {
            throw new Exception('Mã giảm giá này không áp dụng cho loại sản phẩm nào trong giỏ hàng của bạn');
        }
    }

    // Tính toán giá trị giảm giá
    $discount_amount = 0;

    if ($promo['loai_giam_gia'] == 1) {  // Giảm theo phần trăm
        $discount_amount = $total_amount * ($promo['gia_tri'] / 100);
        
        // Kiểm tra giá trị giảm tối đa
        if ($promo['gia_tri_giam_toi_da'] > 0 && $discount_amount > $promo['gia_tri_giam_toi_da']) {
            $discount_amount = $promo['gia_tri_giam_toi_da'];
        }
    } else {  // Giảm theo số tiền cố định
        $discount_amount = $promo['gia_tri'];
        
        // Nếu giảm nhiều hơn tổng tiền đơn hàng
        if ($discount_amount > $total_amount) {
            $discount_amount = $total_amount;
        }
    }

    // Làm tròn về số nguyên
    $discount_amount = round($discount_amount);

    // Log thành công
    $log_data = date('Y-m-d H:i:s') . " | Success - Code: $code | Discount: $discount_amount\n";
    file_put_contents($log_file, $log_data, FILE_APPEND);

    // Trả về kết quả thành công
    echo json_encode([
        'success' => true, 
        'message' => 'Áp dụng mã giảm giá thành công!', 
        'discount_amount' => $discount_amount,
        'discount_id' => $promo['id'],
        'formatted_discount' => number_format($discount_amount, 0, ',', '.'),
        'formatted_total' => number_format($total_amount - $discount_amount + 30000, 0, ',', '.'),
        'promo_details' => [
            'code' => $promo['ma_code'],
            'type' => $promo['loai_giam_gia'],
            'value' => $promo['gia_tri'],
            'max_discount' => $promo['gia_tri_giam_toi_da'],
            'min_order' => $promo['gia_tri_don_toi_thieu'],
            'description' => $promo['mo_ta']
        ]
    ]);

} catch (Exception $e) {
    // Ghi log lỗi
    $log_data = date('Y-m-d H:i:s') . " | Error: " . $e->getMessage() . "\n";
    file_put_contents($log_file, $log_data, FILE_APPEND);
    
    // Trả về lỗi
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}