<?php
// Start session
session_start();

// Check if the admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// Include database connection and permissions
include('../config/config.php');
include('includes/permissions.php');

// Check permission
if (!hasPermission('report_export')) {
    header('Location: index.php');
    exit();
}

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=danh-gia-san-pham-' . date('Y-m-d') . '.csv');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM to fix UTF-8 in Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add CSV headers
fputcsv($output, [
    'ID', 
    'Sản phẩm', 
    'ID Sản phẩm',
    'Khách hàng', 
    'ID Khách hàng',
    'Điểm đánh giá', 
    'Nội dung',
    'Hình ảnh', 
    'Trạng thái', 
    'Ngày đánh giá',
    'ID đơn hàng'
]);

// Variables for filtering and searching
$search_keyword = isset($_GET['search']) ? trim($_GET['search']) : '';
$rating_filter = isset($_GET['rating']) ? (int)$_GET['rating'] : 0;
$status_filter = isset($_GET['status']) ? (int)$_GET['status'] : -1;
$product_filter = isset($_GET['product']) ? (int)$_GET['product'] : 0;

// Build query
$query = "SELECT dg.*, sp.tensanpham, u.tenuser, u.taikhoan
          FROM danhgia dg
          JOIN sanpham sp ON dg.id_sanpham = sp.id_sanpham
          JOIN users u ON dg.id_user = u.id_user";

// Add search conditions
$where_conditions = [];
if (!empty($search_keyword)) {
    $search_keyword = $conn->real_escape_string($search_keyword);
    $where_conditions[] = "(dg.noidung LIKE '%$search_keyword%' OR sp.tensanpham LIKE '%$search_keyword%' OR u.tenuser LIKE '%$search_keyword%' OR u.taikhoan LIKE '%$search_keyword%')";
}

if ($rating_filter > 0) {
    $where_conditions[] = "dg.diemdanhgia = $rating_filter";
}

if ($status_filter !== -1) {
    $where_conditions[] = "dg.trangthai = $status_filter";
}

if ($product_filter > 0) {
    $where_conditions[] = "dg.id_sanpham = $product_filter";
}

// Combine conditions
if (!empty($where_conditions)) {
    $query .= " WHERE " . implode(" AND ", $where_conditions);
}

// Execute query
$result = $conn->query($query);

// Export data
while ($row = $result->fetch_assoc()) {
    $status = ($row['trangthai'] == 1) ? 'Hiển thị' : 'Đã ẩn';
    
    fputcsv($output, [
        $row['id_danhgia'],
        $row['tensanpham'],
        $row['id_sanpham'],
        $row['tenuser'] . ' (' . $row['taikhoan'] . ')',
        $row['id_user'],
        $row['diemdanhgia'] . ' sao',
        $row['noidung'],
        $row['hinhanh'],
        $status,
        date('d/m/Y H:i:s', strtotime($row['ngaydanhgia'])),
        $row['id_donhang']
    ]);
}

// Log the export action
$admin_id = $_SESSION['admin_id'];
$action_type = 'export_reviews';
$details = "Xuất dữ liệu đánh giá sản phẩm";
$ip_address = $_SERVER['REMOTE_ADDR'];

$log_stmt = $conn->prepare("
    INSERT INTO admin_actions 
    (admin_id, action_type, target_type, target_id, details, ip_address) 
    VALUES (?, ?, 'report', 0, ?, ?)
");

$log_stmt->bind_param("isss", $admin_id, $action_type, $details, $ip_address);
$log_stmt->execute();

fclose($output);
exit;
