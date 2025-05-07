<?php
// Kiểm tra đăng nhập và quyền admin
session_start();
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_loai']) || $_SESSION['admin_loai'] < 1) {
    header('Location: login.php');
    exit();
}

// Kết nối database
require_once '../config/config.php';

// Tiêu đề trang và thiết lập
$page_title = 'Xuất dữ liệu đơn hàng';
$current_page = 'orders';

// Thiết lập tham số tìm kiếm và lọc - sử dụng cùng tham số như trang orders.php
$search = $_GET['search'] ?? '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$customer = isset($_GET['customer']) ? intval($_GET['customer']) : '';
$payment_method = isset($_GET['payment_method']) ? $_GET['payment_method'] : '';
$payment_status = isset($_GET['payment_status']) ? $_GET['payment_status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$sort = $_GET['sort'] ?? 'newest';

// Xây dựng câu truy vấn
$query = "SELECT d.*, u.ten as customer_name, u.email as customer_email, u.sodienthoai as customer_phone 
          FROM donhang d
          LEFT JOIN users u ON d.id_user = u.id
          WHERE 1=1";

$params = [];
$param_types = "";

// Thêm điều kiện tìm kiếm
if (!empty($search)) {
    $search_term = "%$search%";
    $query .= " AND (d.ma_donhang LIKE ? OR d.ho_ten LIKE ? OR d.email LIKE ? OR d.sodienthoai LIKE ?)";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
    $param_types .= "ssss";
}

// Lọc theo trạng thái đơn hàng
if ($status !== '') {
    $query .= " AND d.trang_thai_don_hang = ?";
    $params[] = $status;
    $param_types .= "i";
}

// Lọc theo khách hàng
if ($customer !== '') {
    $query .= " AND d.id_user = ?";
    $params[] = $customer;
    $param_types .= "i";
}

// Lọc theo phương thức thanh toán
if ($payment_method !== '') {
    $query .= " AND d.phuong_thuc_thanh_toan = ?";
    $params[] = $payment_method;
    $param_types .= "s";
}

// Lọc theo trạng thái thanh toán
if ($payment_status !== '') {
    $query .= " AND d.trang_thai_thanh_toan = ?";
    $params[] = $payment_status;
    $param_types .= "i";
}

// Lọc theo ngày
if (!empty($date_from)) {
    $query .= " AND DATE(d.ngay_dat) >= ?";
    $params[] = $date_from;
    $param_types .= "s";
}

if (!empty($date_to)) {
    $query .= " AND DATE(d.ngay_dat) <= ?";
    $params[] = $date_to;
    $param_types .= "s";
}

// Sắp xếp
switch ($sort) {
    case 'oldest':
        $query .= " ORDER BY d.ngay_dat ASC";
        break;
    case 'highest':
        $query .= " ORDER BY d.thanh_tien DESC";
        break;
    case 'lowest':
        $query .= " ORDER BY d.thanh_tien ASC";
        break;
    default: // newest
        $query .= " ORDER BY d.ngay_dat DESC";
}

// Thực hiện truy vấn để lấy tất cả đơn hàng phù hợp (không giới hạn số lượng)
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$orders = $stmt->get_result();

// Hàm hiển thị trạng thái đơn hàng
function getOrderStatusName($status) {
    switch ($status) {
        case 1:
            return "Chờ xác nhận";
        case 2:
            return "Đã xác nhận";
        case 3:
            return "Đang giao hàng";
        case 4:
            return "Đã giao";
        case 5:
            return "Đã hủy";
        default:
            return "Không xác định";
    }
}

// Hàm hiển thị trạng thái thanh toán
function getPaymentStatusName($status) {
    return $status ? "Đã thanh toán" : "Chưa thanh toán";
}

// Hàm hiển thị phương thức thanh toán
function getPaymentMethodName($method) {
    switch ($method) {
        case 'cod':
            return "Tiền mặt khi nhận hàng";
        case 'vnpay':
            return "VNPAY";
        case 'bank_transfer':
            return "Chuyển khoản ngân hàng";
        default:
            return "COD";
    }
}

// Format tiền VNĐ
function formatVND($amount) {
    return number_format($amount, 0, ',', '.');
}

// Ghi log hoạt động
$log_sql = "INSERT INTO nhat_ky (id_user, hanh_dong, doi_tuong_loai, doi_tuong_id, chi_tiet, ip_address) 
           VALUES (?, 'export', 'order', ?, ?, ?)";
$log_stmt = $conn->prepare($log_sql);
$admin_id = $_SESSION['admin_id'];
$log_detail = "Xuất danh sách đơn hàng ra Excel";
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$log_stmt->bind_param('iiss', $admin_id, $admin_id, $log_detail, $ip);
$log_stmt->execute();

// Định nghĩa các màu sắc cho bảng
$headerBgColor = "#4472C4";  // Xanh đậm
$headerTextColor = "#FFFFFF"; // Trắng
$subHeaderBgColor = "#5B9BD5"; // Xanh nhạt hơn
$altRowColor = "#E9EFF7"; // Xanh rất nhạt
$borderColor = "#CCCCCC"; // Xám nhạt
$statusColors = array(
    1 => "#FFC000", // Vàng - Chờ xác nhận
    2 => "#00B0F0", // Xanh dương - Đã xác nhận
    3 => "#92D050", // Xanh lá nhạt - Đang giao hàng
    4 => "#00B050", // Xanh lá đậm - Đã giao
    5 => "#FF0000"  // Đỏ - Đã hủy
);

// Tính tổng số đơn và doanh thu
$totalOrders = 0;
$totalRevenue = 0;
$totalsByStatus = array(
    1 => 0, // Chờ xác nhận
    2 => 0, // Đã xác nhận
    3 => 0, // Đang giao hàng
    4 => 0, // Đã giao
    5 => 0  // Đã hủy
);

if ($orders && $orders->num_rows > 0) {
    $totalOrders = $orders->num_rows;
    $orderData = [];
    
    while ($order = $orders->fetch_assoc()) {
        $orderData[] = $order;
        $totalRevenue += $order['thanh_tien'];
        if (isset($order['trang_thai_don_hang']) && isset($totalsByStatus[$order['trang_thai_don_hang']])) {
            $totalsByStatus[$order['trang_thai_don_hang']]++;
        }
    }
    
    // Reset con trỏ kết quả để sử dụng lại
    mysqli_data_seek($orders, 0);
}

// Tùy chọn xác định đây là tải xuống Excel
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="DonHang_' . date('Y-m-d_H-i-s') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

// Lấy thông tin về cài đặt cửa hàng
$store_info_sql = "SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('site_name', 'contact_email', 'contact_phone', 'address') LIMIT 4";
$store_info_result = $conn->query($store_info_sql);
$store_info = [];

if ($store_info_result && $store_info_result->num_rows > 0) {
    while ($row = $store_info_result->fetch_assoc()) {
        $store_info[$row['setting_key']] = $row['setting_value'];
    }
}

$store_name = $store_info['site_name'] ?? 'Bug Shop';
$store_email = $store_info['contact_email'] ?? 'contact@bugshop.com';
$store_phone = $store_info['contact_phone'] ?? '0123456789';
$store_address = $store_info['address'] ?? 'Số 123, Đường ABC, Quận XYZ, TP. HCM';

// Xây dựng tựa đề cho bộ lọc
$filter_title = [];
if (!empty($search)) $filter_title[] = "Tìm kiếm: " . htmlspecialchars($search);
if ($status !== '') $filter_title[] = "Trạng thái: " . getOrderStatusName($status);
if ($payment_method !== '') $filter_title[] = "Phương thức thanh toán: " . getPaymentMethodName($payment_method);
if ($payment_status !== '') $filter_title[] = "Trạng thái thanh toán: " . getPaymentStatusName($payment_status);
if (!empty($date_from)) $filter_title[] = "Từ ngày: " . date('d/m/Y', strtotime($date_from));
if (!empty($date_to)) $filter_title[] = "Đến ngày: " . date('d/m/Y', strtotime($date_to));
$filter_text = !empty($filter_title) ? implode(" | ", $filter_title) : "Tất cả đơn hàng";

// Tạo nội dung Excel với HTML
echo '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style type="text/css">
    /* Định dạng tổng thể */
    body {
        font-family: Arial, sans-serif;
        font-size: 11pt;
    }
    
    /* Định dạng bảng chung */
    table {
        border-collapse: collapse;
        width: 100%;
        margin-bottom: 20px;
    }
    
    /* Định dạng tiêu đề */
    .header-section {
        margin-bottom: 20px;
    }
    
    .company-name {
        font-size: 18pt;
        font-weight: bold;
        color: #4472C4;
    }
    
    .report-title {
        font-size: 16pt;
        font-weight: bold;
        margin: 10px 0;
    }
    
    .report-date {
        font-style: italic;
        color: #666666;
    }
    
    /* Định dạng phần thống kê */
    .summary-table {
        width: 50%;
    }
    
    .summary-table th {
        background-color: #4472C4;
        color: white;
        text-align: left;
        padding: 5px;
    }
    
    .summary-table td {
        padding: 5px;
        border: 1px solid #CCCCCC;
    }
    
    /* Định dạng bảng dữ liệu chính */
    .data-table th {
        background-color: ' . $headerBgColor . ';
        color: ' . $headerTextColor . ';
        font-weight: bold;
        text-align: center;
        padding: 8px;
        border: 1px solid ' . $borderColor . ';
    }
    
    .data-table td {
        padding: 6px;
        border: 1px solid ' . $borderColor . ';
        vertical-align: top;
    }
    
    .data-table tr:nth-child(even) {
        background-color: ' . $altRowColor . ';
    }
    
    /* Định dạng cột trạng thái */
    .status-waiting {
        background-color: ' . $statusColors[1] . ';
        color: #000000;
        padding: 3px 6px;
        border-radius: 3px;
        font-weight: bold;
    }
    
    .status-confirmed {
        background-color: ' . $statusColors[2] . ';
        color: #000000;
        padding: 3px 6px;
        border-radius: 3px;
        font-weight: bold;
    }
    
    .status-shipping {
        background-color: ' . $statusColors[3] . ';
        color: #000000;
        padding: 3px 6px;
        border-radius: 3px;
        font-weight: bold;
    }
    
    .status-delivered {
        background-color: ' . $statusColors[4] . ';
        color: #FFFFFF;
        padding: 3px 6px;
        border-radius: 3px;
        font-weight: bold;
    }
    
    .status-canceled {
        background-color: ' . $statusColors[5] . ';
        color: #FFFFFF;
        padding: 3px 6px;
        border-radius: 3px;
        font-weight: bold;
    }
    
    /* Định dạng trang cuối */
    .footer {
        font-size: 10pt;
        color: #666666;
        margin-top: 20px;
        text-align: center;
    }
</style>
</head>
<body>
    <!-- Phần đầu báo cáo -->
    <div class="header-section">
        <div class="company-name">' . $store_name . '</div>
        <div>' . $store_address . '</div>
        <div>Email: ' . $store_email . ' | ĐT: ' . $store_phone . '</div>
        <div class="report-title">BÁO CÁO ĐƠN HÀNG</div>
        <div class="report-date">Ngày xuất: ' . date('d/m/Y H:i:s') . '</div>
        <div>Bộ lọc: ' . $filter_text . '</div>
    </div>
    
    <!-- Phần tổng quan thống kê -->
    <table class="summary-table">
        <tr>
            <th colspan="2">THỐNG KÊ ĐƠN HÀNG</th>
        </tr>
        <tr>
            <td>Tổng số đơn hàng:</td>
            <td>' . $totalOrders . ' đơn</td>
        </tr>
        <tr>
            <td>Tổng doanh thu:</td>
            <td>' . formatVND($totalRevenue) . ' ₫</td>
        </tr>
        <tr>
            <td>Chờ xác nhận:</td>
            <td>' . $totalsByStatus[1] . ' đơn</td>
        </tr>
        <tr>
            <td>Đã xác nhận:</td>
            <td>' . $totalsByStatus[2] . ' đơn</td>
        </tr>
        <tr>
            <td>Đang giao hàng:</td>
            <td>' . $totalsByStatus[3] . ' đơn</td>
        </tr>
        <tr>
            <td>Đã giao:</td>
            <td>' . $totalsByStatus[4] . ' đơn</td>
        </tr>
        <tr>
            <td>Đã hủy:</td>
            <td>' . $totalsByStatus[5] . ' đơn</td>
        </tr>
    </table>
    
    <!-- Bảng dữ liệu đơn hàng -->
    <h3 style="background-color: ' . $subHeaderBgColor . '; color: white; padding: 8px;">CHI TIẾT ĐƠN HÀNG</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th>STT</th>
                <th>Mã đơn hàng</th>
                <th>Ngày đặt</th>
                <th>Khách hàng</th>
                <th>Liên hệ</th>
                <th>Địa chỉ</th>
                <th>Tổng tiền</th>
                <th>Phí vận chuyển</th>
                <th>Thanh toán</th>
                <th>Trạng thái TT</th>
                <th>Trạng thái ĐH</th>
                <th>Ghi chú</th>
            </tr>
        </thead>
        <tbody>';

if ($orders && $orders->num_rows > 0) {
    $count = 1;
    while ($order = $orders->fetch_assoc()) {
        // Xác định class trạng thái đơn hàng để tô màu
        $status_class = '';
        switch ($order['trang_thai_don_hang']) {
            case 1:
                $status_class = 'status-waiting';
                break;
            case 2:
                $status_class = 'status-confirmed';
                break;
            case 3:
                $status_class = 'status-shipping';
                break;
            case 4:
                $status_class = 'status-delivered';
                break;
            case 5:
                $status_class = 'status-canceled';
                break;
            default:
                $status_class = '';
        }
        
        // Địa chỉ đầy đủ
        $address_parts = [];
        if (!empty($order['diachi'])) $address_parts[] = $order['diachi'];
        if (!empty($order['phuong_xa'])) $address_parts[] = $order['phuong_xa'];
        if (!empty($order['quan_huyen'])) $address_parts[] = $order['quan_huyen'];
        if (!empty($order['tinh_tp'])) $address_parts[] = $order['tinh_tp'];
        $full_address = implode(', ', $address_parts);
        
        echo '<tr>
            <td style="text-align: center;">' . $count . '</td>
            <td style="text-align: center; font-weight: bold;">' . $order['ma_donhang'] . '</td>
            <td>' . date('d/m/Y H:i', strtotime($order['ngay_dat'])) . '</td>
            <td>' . htmlspecialchars($order['customer_name'] ?? $order['ho_ten']) . '</td>
            <td>
                <div>' . htmlspecialchars($order['sodienthoai']) . '</div>
                ' . (!empty($order['email']) ? '<div>' . htmlspecialchars($order['email']) . '</div>' : '') . '
            </td>
            <td>' . htmlspecialchars($full_address) . '</td>
            <td style="text-align: right; font-weight: bold;">' . formatVND($order['thanh_tien']) . '</td>
            <td style="text-align: right;">' . formatVND($order['phi_vanchuyen'] ?? 0) . '</td>
            <td>' . getPaymentMethodName($order['phuong_thuc_thanh_toan']) . '</td>
            <td style="text-align: center;">' . ($order['trang_thai_thanh_toan'] ? '<span style="color: green; font-weight: bold;">✓</span>' : '<span style="color: #FFA500; font-weight: bold;">⏱</span>') . '</td>
            <td style="text-align: center;"><span class="' . $status_class . '">' . getOrderStatusName($order['trang_thai_don_hang']) . '</span></td>
            <td>' . htmlspecialchars($order['ghi_chu'] ?? '') . '</td>
        </tr>';
        $count++;
    }
} else {
    echo '<tr><td colspan="12" style="text-align: center; padding: 20px;">Không có đơn hàng nào</td></tr>';
}

echo '</tbody>
    </table>';

// Thêm thông tin chi tiết đơn hàng
$query_details = "SELECT d.id, d.ma_donhang, dc.id_sanpham, sp.tensanpham,
                 dc.id_bienthe, dc.gia, dc.soluong, dc.thanh_tien, dc.id AS chitiet_id,
                 dc.thuoc_tinh
                 FROM donhang d
                 JOIN donhang_chitiet dc ON d.id = dc.id_donhang
                 JOIN sanpham sp ON dc.id_sanpham = sp.id
                 WHERE 1=1 ";

// Áp dụng các điều kiện lọc tương tự như với đơn hàng
if (!empty($search)) {
    $query_details .= " AND (d.ma_donhang LIKE ? OR d.ho_ten LIKE ? OR d.email LIKE ? OR d.sodienthoai LIKE ?)";
}
if ($status !== '') {
    $query_details .= " AND d.trang_thai_don_hang = ?";
}
if ($customer !== '') {
    $query_details .= " AND d.id_user = ?";
}
if ($payment_method !== '') {
    $query_details .= " AND d.phuong_thuc_thanh_toan = ?";
}
if ($payment_status !== '') {
    $query_details .= " AND d.trang_thai_thanh_toan = ?";
}
if (!empty($date_from)) {
    $query_details .= " AND DATE(d.ngay_dat) >= ?";
}
if (!empty($date_to)) {
    $query_details .= " AND DATE(d.ngay_dat) <= ?";
}

// Nhóm và sắp xếp chi tiết đơn hàng
$query_details .= " GROUP BY dc.id ORDER BY d.id DESC, dc.id ASC";

// Thực hiện truy vấn chi tiết sản phẩm
$stmt_details = $conn->prepare($query_details);
if (!empty($params)) {
    $stmt_details->bind_param($param_types, ...$params);
}
$stmt_details->execute();
$order_details = $stmt_details->get_result();

echo '<h3 style="background-color: ' . $subHeaderBgColor . '; color: white; padding: 8px; margin-top: 30px;">CHI TIẾT SẢN PHẨM</h3>
<table class="data-table">
    <thead>
        <tr>
            <th>STT</th>
            <th>Mã đơn hàng</th>
            <th>Tên sản phẩm</th>
            <th>Thuộc tính</th>
            <th>Đơn giá</th>
            <th>Số lượng</th>
            <th>Thành tiền</th>
        </tr>
    </thead>
    <tbody>';

if ($order_details && $order_details->num_rows > 0) {
    $count = 1;
    while ($detail = $order_details->fetch_assoc()) {
        echo '<tr>
            <td style="text-align: center;">' . $count . '</td>
            <td style="text-align: center;">' . htmlspecialchars($detail['ma_donhang']) . '</td>
            <td>' . htmlspecialchars($detail['tensanpham']) . '</td>
            <td>' . htmlspecialchars($detail['thuoc_tinh'] ?? '') . '</td>
            <td style="text-align: right;">' . formatVND($detail['gia']) . '</td>
            <td style="text-align: center;">' . $detail['soluong'] . '</td>
            <td style="text-align: right; font-weight: bold;">' . formatVND($detail['thanh_tien']) . '</td>
        </tr>';
        $count++;
    }
} else {
    echo '<tr><td colspan="7" style="text-align: center; padding: 20px;">Không có thông tin chi tiết sản phẩm</td></tr>';
}

echo '</tbody>
</table>

<div class="footer">
    Báo cáo này được tạo tự động từ hệ thống ' . $store_name . ' - Ngày xuất: ' . date('d/m/Y H:i:s') . '<br>
    © ' . date('Y') . ' ' . $store_name . ' - Mọi quyền được bảo lưu.
</div>

</body>
</html>';

exit;
?>