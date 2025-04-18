<?php
session_start();
include('config/config.php');
header('Content-Type: application/json');

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get input parameters
$code = isset($_POST['code']) ? trim($_POST['code']) : '';
$order_total = isset($_POST['total']) ? (float)$_POST['total'] : 0;
$cart_items_json = isset($_POST['cart_items']) ? $_POST['cart_items'] : '[]';
$cart_items = json_decode($cart_items_json, true);

// Input validation
if (empty($code)) {
    echo json_encode(['success' => false, 'message' => 'Vui lòng nhập mã giảm giá']);
    exit;
}

if ($order_total <= 0) {
    echo json_encode(['success' => false, 'message' => 'Tổng đơn hàng không hợp lệ']);
    exit;
}

// Check if the promo code exists and is valid
$promo_query = $conn->prepare("
    SELECT * FROM khuyen_mai 
    WHERE ma_khuyenmai = ? 
    AND trang_thai = 1
    AND (ngay_bat_dau IS NULL OR ngay_bat_dau <= NOW())
    AND (ngay_ket_thuc IS NULL OR ngay_ket_thuc >= NOW())
");

$promo_query->bind_param("s", $code);
$promo_query->execute();
$result = $promo_query->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Mã giảm giá không tồn tại hoặc đã hết hạn']);
    exit;
}

$promo = $result->fetch_assoc();

// Check if the promo code is still available
if ($promo['so_luong'] !== null && $promo['da_su_dung'] >= $promo['so_luong']) {
    echo json_encode(['success' => false, 'message' => 'Mã giảm giá đã hết lượt sử dụng']);
    exit;
}

// Check minimum order value
if ($order_total < $promo['dieu_kien_toithieu']) {
    $min_amount = number_format($promo['dieu_kien_toithieu'], 0, ',', '.');
    echo json_encode([
        'success' => false, 
        'message' => "Đơn hàng tối thiểu {$min_amount}₫ để áp dụng mã này"
    ]);
    exit;
}

// Check if the promo has product/category restrictions
$valid_for_cart = true;
$promo_restrictions = false;

// Check for product/category restrictions
$restrictions_query = $conn->prepare("
    SELECT * FROM khuyen_mai_apdung WHERE id_khuyenmai = ?
");
$restrictions_query->bind_param("i", $promo['id']);
$restrictions_query->execute();
$restrictions_result = $restrictions_query->get_result();

if ($restrictions_result->num_rows > 0) {
    $promo_restrictions = true;
    $valid_products = [];
    $valid_categories = [];
    
    while ($restriction = $restrictions_result->fetch_assoc()) {
        if ($restriction['loai_doi_tuong'] == 2) { // Product
            $valid_products[] = $restriction['id_doi_tuong'];
        } elseif ($restriction['loai_doi_tuong'] == 1) { // Category
            $valid_categories[] = $restriction['id_doi_tuong'];
        } elseif ($restriction['loai_doi_tuong'] == 0) { // All products
            $promo_restrictions = false;
            break;
        }
    }
    
    if ($promo_restrictions) {
        $valid_for_cart = false;
        
        foreach ($cart_items as $item) {
            $product_id = isset($item['id_sanpham']) ? (int)$item['id_sanpham'] : 0;
            $product_category = 0;
            
            // Get product category if needed for category-based discounts
            if (!empty($valid_categories) && $product_id > 0) {
                $cat_query = $conn->prepare("SELECT id_danhmuc FROM sanpham WHERE id = ?");
                $cat_query->bind_param("i", $product_id);
                $cat_query->execute();
                $cat_result = $cat_query->get_result();
                if ($cat_result->num_rows > 0) {
                    $product_category = $cat_result->fetch_assoc()['id_danhmuc'];
                }
            }
            
            // Check if the product is valid for the promo
            if (in_array($product_id, $valid_products) || in_array($product_category, $valid_categories)) {
                $valid_for_cart = true;
                break;
            }
        }
        
        if (!$valid_for_cart) {
            echo json_encode([
                'success' => false, 
                'message' => 'Mã giảm giá không áp dụng cho sản phẩm trong giỏ hàng của bạn'
            ]);
            exit;
        }
    }
}

// Calculate the discount
$discount_amount = 0;

if ($promo['loai_giamgia'] == 0) { // Percentage discount
    $discount_amount = $order_total * ($promo['gia_tri'] / 100);
} else { // Fixed amount discount
    $discount_amount = $promo['gia_tri'];
}

// Ensure discount doesn't exceed order total
$discount_amount = min($discount_amount, $order_total);

// Calculate new total
$new_total = $order_total - $discount_amount + 30000; // Adding shipping fee

// Format numbers for display
$formatted_discount = number_format($discount_amount, 0, ',', '.');
$formatted_total = number_format($new_total, 0, ',', '.');

// Return success response
echo json_encode([
    'success' => true,
    'message' => 'Áp dụng mã giảm giá thành công!',
    'discount_amount' => $discount_amount,
    'formatted_discount' => $formatted_discount,
    'new_total' => $new_total,
    'formatted_total' => $formatted_total,
    'discount_id' => $promo['id'],
    'discount_type' => $promo['loai_giamgia'],
    'discount_value' => $promo['gia_tri']
]);