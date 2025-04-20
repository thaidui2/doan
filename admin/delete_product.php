<?php
// Start session for managing messages
session_start();

// Include database connection
require_once '../config/config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// Check if product ID is provided
if (!isset($_POST['product_id']) || empty($_POST['product_id'])) {
    $_SESSION['error_message'] = "ID sản phẩm không hợp lệ";
    header('Location: products.php');
    exit();
}

$product_id = (int)$_POST['product_id'];

// Begin transaction to ensure data consistency
$conn->begin_transaction();

try {
    // First, get product details for logging
    $product_query = $conn->prepare("SELECT tensanpham FROM sanpham WHERE id = ?");
    $product_query->bind_param("i", $product_id);
    $product_query->execute();
    $result = $product_query->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Không tìm thấy sản phẩm với ID: $product_id");
    }
    
    $product = $result->fetch_assoc();
    $product_name = $product['tensanpham'];
    
    // Delete related records first (maintain proper order to avoid foreign key constraint issues)
    
    // 1. Delete product images
    $delete_images = $conn->prepare("DELETE FROM sanpham_hinhanh WHERE id_sanpham = ?");
    $delete_images->bind_param("i", $product_id);
    $delete_images->execute();
    
    // 2. Delete product variants
    $delete_variants = $conn->prepare("DELETE FROM sanpham_bien_the WHERE id_sanpham = ?");
    $delete_variants->bind_param("i", $product_id);
    $delete_variants->execute();
    
    // 3. Delete from wishlist
    $delete_wishlist = $conn->prepare("DELETE FROM yeu_thich WHERE id_sanpham = ?");
    $delete_wishlist->bind_param("i", $product_id);
    $delete_wishlist->execute();
    
    // 4. Finally delete the product
    $delete_product = $conn->prepare("DELETE FROM sanpham WHERE id = ?");
    $delete_product->bind_param("i", $product_id);
    $delete_product->execute();
    
    if ($delete_product->affected_rows === 0) {
        throw new Exception("Không thể xóa sản phẩm. Vui lòng thử lại.");
    }
    
    // Log the action
    $admin_id = $_SESSION['admin_id'];
    $log_query = $conn->prepare("
        INSERT INTO nhat_ky (id_user, hanh_dong, doi_tuong_loai, doi_tuong_id, chi_tiet, ip_address)
        VALUES (?, 'delete', 'product', ?, ?, ?)
    ");
    
    $log_details = "Xóa sản phẩm: $product_name (ID: $product_id)";
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    
    $log_query->bind_param("iiss", $admin_id, $product_id, $log_details, $ip_address);
    $log_query->execute();
    
    // Commit transaction if everything is ok
    $conn->commit();
    
    $_SESSION['success_message'] = "Đã xóa thành công sản phẩm: $product_name";
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    $_SESSION['error_message'] = $e->getMessage();
}

// Redirect back to products page
header('Location: products.php');
exit();
?>
