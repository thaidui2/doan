<?php
// Start session
session_start();

// Check if user is logged in as admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// Include database connection
include('../config/config.php');
include('includes/permissions.php');

// Check permission
if (!hasPermission('log_view') && !hasPermission('report_export')) {
    header('Location: index.php');
    exit();
}

// Get filter parameters
$search_keyword = isset($_GET['search']) ? trim($_GET['search']) : '';
$admin_filter = isset($_GET['admin_id']) ? (int)$_GET['admin_id'] : 0;
$action_filter = isset($_GET['action_type']) ? trim($_GET['action_type']) : '';
$target_filter = isset($_GET['target_type']) ? trim($_GET['target_type']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Default sorting
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// Build query
$query = "
    SELECT aa.*, a.ho_ten AS admin_name, a.ten_dang_nhap AS admin_username
    FROM admin_actions aa
    LEFT JOIN admin a ON aa.admin_id = a.id_admin
    WHERE 1=1
";

// Add search conditions
$where_conditions = [];
if (!empty($search_keyword)) {
    $search_keyword = $conn->real_escape_string($search_keyword);
    $where_conditions[] = "(aa.details LIKE '%$search_keyword%' OR a.ho_ten LIKE '%$search_keyword%' OR a.ten_dang_nhap LIKE '%$search_keyword%')";
}

if ($admin_filter > 0) {
    $where_conditions[] = "aa.admin_id = $admin_filter";
}

if (!empty($action_filter)) {
    $action_filter = $conn->real_escape_string($action_filter);
    $where_conditions[] = "aa.action_type = '$action_filter'";
}

if (!empty($target_filter)) {
    $target_filter = $conn->real_escape_string($target_filter);
    $where_conditions[] = "aa.target_type = '$target_filter'";
}

if (!empty($date_from)) {
    $date_from = $conn->real_escape_string($date_from);
    $where_conditions[] = "DATE(aa.created_at) >= '$date_from'";
}

if (!empty($date_to)) {
    $date_to = $conn->real_escape_string($date_to);
    $where_conditions[] = "DATE(aa.created_at) <= '$date_to'";
}

// Combine conditions
if (!empty($where_conditions)) {
    $query .= " AND " . implode(" AND ", $where_conditions);
}

// Add sorting
$valid_sort_columns = ['id', 'admin_id', 'action_type', 'target_type', 'target_id', 'created_at'];
if (!in_array($sort_by, $valid_sort_columns)) {
    $sort_by = 'created_at';
}

$sort_order = ($sort_order === 'ASC') ? 'ASC' : 'DESC';
$query .= " ORDER BY aa.$sort_by $sort_order";

// Execute query
$result = $conn->query($query);

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=nhat_ky_hoat_dong_' . date('Y-m-d_His') . '.csv');

// Create a file pointer connected to the output stream
$output = fopen('php://output', 'w');

// Set UTF-8 BOM for proper Excel handling of UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Set column headers
fputcsv($output, [
    'ID', 
    'Thời gian', 
    'ID Admin', 
    'Tên Admin', 
    'Username', 
    'Hành động', 
    'Loại đối tượng', 
    'ID đối tượng', 
    'Chi tiết', 
    'Địa chỉ IP'
]);

// Output each row of the data
while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['id'],
        $row['created_at'],
        $row['admin_id'],
        $row['admin_name'],
        $row['admin_username'],
        $row['action_type'],
        $row['target_type'],
        $row['target_id'],
        $row['details'],
        $row['ip_address']
    ]);
}

// Log the export action
$admin_id = $_SESSION['admin_id'];
$admin_name = $_SESSION['admin_name'] ?? $_SESSION['admin_username'] ?? 'Admin';

// Create log entry
$log_stmt = $conn->prepare("
    INSERT INTO admin_actions (admin_id, action_type, target_type, target_id, details, ip_address) 
    VALUES (?, ?, ?, ?, ?, ?)
");

$action = 'export';
$target_type = 'logs';
$target_id = 0;
$details = "Export nhật ký hoạt động bởi $admin_name";
$ip = $_SERVER['REMOTE_ADDR'];

$log_stmt->bind_param("ississ", $admin_id, $action, $target_type, $target_id, $details, $ip);
$log_stmt->execute();

// Close the database connection
$conn->close();
?>
