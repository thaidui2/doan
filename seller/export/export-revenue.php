<?php
session_start();
require('../../config/config.php');

// Kiểm tra đăng nhập
if (!isset($_SESSION['user']) || $_SESSION['user']['logged_in'] !== true) {
    header('Location: ../../dangnhap.php?redirect=seller/doanh-thu.php');
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
    header("Location: ../../index.php");
    exit();
}

// Lấy thông tin seller
$seller_info = $conn->prepare("SELECT * FROM users WHERE id_user = ?");
$seller_info->bind_param("i", $user_id);
$seller_info->execute();
$seller = $seller_info->get_result()->fetch_assoc();

// Lấy các tham số từ URL
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$category = isset($_GET['category']) ? (int)$_GET['category'] : 0;

// Tính khoảng thời gian của báo cáo
$start = new DateTime($start_date);
$end = new DateTime($end_date);
$interval = $start->diff($end);
$days_in_period = $interval->days + 1;

// Định dạng tên file
$shop_name = preg_replace('/[^a-zA-Z0-9_]/', '', $seller['ten_shop'] ?? $seller['tenuser']);
$filename = "BaoCao_DoanhThu_{$shop_name}_" . date('Ymd_His') . ".csv";

// Chuẩn bị xuất file CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Tạo file handle để ghi vào output
$output = fopen('php://output', 'w');

// UTF-8 BOM để Excel nhận dạng đúng encoding tiếng Việt
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// ===== TRANG BÌA BÁO CÁO =====
fputcsv($output, ['BUG SHOP - HỆ THỐNG BÁO CÁO DOANH THU']);
fputcsv($output, []);
fputcsv($output, ['BÁO CÁO DOANH THU CHI TIẾT']);
fputcsv($output, ['Từ ngày: ' . date('d/m/Y', strtotime($start_date)), 'Đến ngày: ' . date('d/m/Y', strtotime($end_date))]);
fputcsv($output, []);
fputcsv($output, ['THÔNG TIN CỬA HÀNG']);
fputcsv($output, ['Tên shop:', $seller['ten_shop'] ?? $seller['tenuser']]);
fputcsv($output, ['Người bán:', $seller['tenuser']]);
fputcsv($output, ['Liên hệ:', $seller['email_shop'] ?? $seller['email'], $seller['so_dien_thoai_shop'] ?? $seller['sdt']]);
fputcsv($output, ['Địa chỉ:', $seller['dia_chi_shop'] ?? $seller['diachi']]);
if ($category > 0) {
    // Lấy tên danh mục nếu có lọc theo danh mục
    $cat_query = $conn->prepare("SELECT tenloai FROM loaisanpham WHERE id_loai = ?");
    $cat_query->bind_param("i", $category);
    $cat_query->execute();
    $cat_result = $cat_query->get_result();
    if ($cat_row = $cat_result->fetch_assoc()) {
        fputcsv($output, ['Lọc theo danh mục:', $cat_row['tenloai']]);
    }
}
fputcsv($output, ['Ngày xuất báo cáo:', date('d/m/Y H:i:s')]);
fputcsv($output, []);
fputcsv($output, ['----------------------------------------------------------------']);
fputcsv($output, []);

// ===== PHẦN 1: TỔNG QUAN KẾT QUẢ KINH DOANH =====
// Lấy dữ liệu tổng quan
$overview_query = $conn->prepare("
    SELECT 
        COUNT(DISTINCT dh.id_donhang) as total_orders,
        COALESCE(SUM(dc.soluong), 0) as total_products,
        COALESCE(SUM(dc.thanh_tien), 0) as total_revenue,
        COUNT(DISTINCT sp.id_sanpham) as total_products_sold,
        COUNT(DISTINCT dh.id_nguoidung) as total_customers
    FROM donhang dh
    JOIN donhang_chitiet dc ON dh.id_donhang = dc.id_donhang
    JOIN sanpham sp ON dc.id_sanpham = sp.id_sanpham
    WHERE sp.id_nguoiban = ? AND dh.trangthai = 4
    AND (dh.ngaytao BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY))
    " . ($category > 0 ? "AND sp.id_loai = ?" : "")
);

if ($category > 0) {
    $overview_query->bind_param("issi", $user_id, $start_date, $end_date, $category);
} else {
    $overview_query->bind_param("iss", $user_id, $start_date, $end_date);
}
$overview_query->execute();
$overview = $overview_query->get_result()->fetch_assoc();

// Lấy dữ liệu tháng trước để so sánh
$prev_start_date = date('Y-m-d', strtotime('-1 month', strtotime($start_date)));
$prev_end_date = date('Y-m-d', strtotime('-1 month', strtotime($end_date)));

$prev_overview_query = $conn->prepare("
    SELECT 
        COUNT(DISTINCT dh.id_donhang) as total_orders,
        COALESCE(SUM(dc.thanh_tien), 0) as total_revenue
    FROM donhang dh
    JOIN donhang_chitiet dc ON dh.id_donhang = dc.id_donhang
    JOIN sanpham sp ON dc.id_sanpham = sp.id_sanpham
    WHERE sp.id_nguoiban = ? AND dh.trangthai = 4
    AND (dh.ngaytao BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY))
    " . ($category > 0 ? "AND sp.id_loai = ?" : "")
);

if ($category > 0) {
    $prev_overview_query->bind_param("issi", $user_id, $prev_start_date, $prev_end_date, $category);
} else {
    $prev_overview_query->bind_param("iss", $user_id, $prev_start_date, $prev_end_date);
}
$prev_overview_query->execute();
$prev_overview = $prev_overview_query->get_result()->fetch_assoc();

// Tính toán % tăng trưởng
$order_growth = ($prev_overview['total_orders'] > 0) ? 
    (($overview['total_orders'] - $prev_overview['total_orders']) / $prev_overview['total_orders'] * 100) : 0;
$revenue_growth = ($prev_overview['total_revenue'] > 0) ? 
    (($overview['total_revenue'] - $prev_overview['total_revenue']) / $prev_overview['total_revenue'] * 100) : 0;

// Số liệu trung bình
$avg_order_value = ($overview['total_orders'] > 0) ? 
    ($overview['total_revenue'] / $overview['total_orders']) : 0;
$avg_daily_revenue = ($days_in_period > 0) ? 
    ($overview['total_revenue'] / $days_in_period) : 0;
$avg_daily_orders = ($days_in_period > 0) ? 
    ($overview['total_orders'] / $days_in_period) : 0;

// Xuất thông tin tổng quan
fputcsv($output, ['PHẦN 1: TỔNG QUAN KẾT QUẢ KINH DOANH']);
fputcsv($output, []);
fputcsv($output, ['1.1. CHỈ SỐ CHÍNH']);
fputcsv($output, ['Chỉ tiêu', 'Giá trị', 'So với kỳ trước', '% Tăng trưởng']);
fputcsv($output, ['Tổng số đơn hàng', $overview['total_orders'], $prev_overview['total_orders'], number_format($order_growth, 2) . '%']);
fputcsv($output, ['Tổng doanh thu', number_format($overview['total_revenue'], 0, ',', '.') . ' VNĐ', 
    number_format($prev_overview['total_revenue'], 0, ',', '.') . ' VNĐ', 
    number_format($revenue_growth, 2) . '%']);
fputcsv($output, ['Tổng số sản phẩm đã bán', $overview['total_products'], '', '']);
fputcsv($output, ['Số lượng mặt hàng đã bán', $overview['total_products_sold'], '', '']);
fputcsv($output, ['Số lượng khách hàng', $overview['total_customers'], '', '']);
fputcsv($output, []);

fputcsv($output, ['1.2. CHỈ SỐ HIỆU QUẢ']);
fputcsv($output, ['Chỉ tiêu', 'Giá trị']);
fputcsv($output, ['Giá trị đơn hàng trung bình', number_format($avg_order_value, 0, ',', '.') . ' VNĐ']);
fputcsv($output, ['Doanh thu trung bình mỗi ngày', number_format($avg_daily_revenue, 0, ',', '.') . ' VNĐ']);
fputcsv($output, ['Số đơn hàng trung bình mỗi ngày', number_format($avg_daily_orders, 2)]);
fputcsv($output, ['Số sản phẩm trung bình mỗi đơn hàng', ($overview['total_orders'] > 0) ? 
    number_format($overview['total_products'] / $overview['total_orders'], 2) : 0]);
fputcsv($output, []);
fputcsv($output, ['----------------------------------------------------------------']);
fputcsv($output, []);

// ===== PHẦN 2: CHI TIẾT DOANH THU THEO DANH MỤC =====
$category_revenue_query = $conn->prepare("
    SELECT 
        lsp.id_loai, 
        lsp.tenloai, 
        COUNT(DISTINCT dh.id_donhang) as order_count,
        COALESCE(SUM(dc.soluong), 0) as quantity_sold,
        COALESCE(SUM(dc.thanh_tien), 0) as category_revenue,
        COUNT(DISTINCT sp.id_sanpham) as products_count
    FROM loaisanpham lsp
    LEFT JOIN sanpham sp ON lsp.id_loai = sp.id_loai AND sp.id_nguoiban = ? 
    LEFT JOIN donhang_chitiet dc ON sp.id_sanpham = dc.id_sanpham
    LEFT JOIN donhang dh ON dc.id_donhang = dh.id_donhang AND dh.trangthai = 4 
        AND (dh.ngaytao BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY))
    " . ($category > 0 ? "WHERE lsp.id_loai = ?" : "") . "
    GROUP BY lsp.id_loai
    ORDER BY category_revenue DESC
");

if ($category > 0) {
    $category_revenue_query->bind_param("issi", $user_id, $start_date, $end_date, $category);
} else {
    $category_revenue_query->bind_param("iss", $user_id, $start_date, $end_date);
}
$category_revenue_query->execute();
$category_revenue_result = $category_revenue_query->get_result();

fputcsv($output, ['PHẦN 2: CHI TIẾT DOANH THU THEO DANH MỤC']);
fputcsv($output, []);
fputcsv($output, ['STT', 'Danh mục', 'Số sản phẩm', 'Số đơn hàng', 'Số lượng đã bán', 'Doanh thu (VNĐ)', 'Tỷ lệ (%)', 'Giá trị TB/đơn (VNĐ)']);

// Tính tổng doanh thu để tính phần trăm
$total_revenue = $overview['total_revenue'];

// Xuất dữ liệu theo danh mục
$stt = 1;
while ($category = $category_revenue_result->fetch_assoc()) {
    $percentage = $total_revenue > 0 ? ($category['category_revenue'] / $total_revenue) * 100 : 0;
    $avg_order_value = $category['order_count'] > 0 ? $category['category_revenue'] / $category['order_count'] : 0;
    
    fputcsv($output, [
        $stt++,
        $category['tenloai'],
        $category['products_count'],
        $category['order_count'],
        $category['quantity_sold'],
        number_format($category['category_revenue'], 0, ',', '.'),
        number_format($percentage, 2) . '%',
        number_format($avg_order_value, 0, ',', '.')
    ]);
}

// Thêm dòng tổng cộng
fputcsv($output, []);
fputcsv($output, [
    '',
    'TỔNG CỘNG',
    $overview['total_products_sold'],
    $overview['total_orders'],
    $overview['total_products'],
    number_format($total_revenue, 0, ',', '.'),
    '100%',
    $overview['total_orders'] > 0 ? number_format($total_revenue / $overview['total_orders'], 0, ',', '.') : 0
]);
fputcsv($output, []);
fputcsv($output, ['----------------------------------------------------------------']);
fputcsv($output, []);

// ===== PHẦN 3: TOP SẢN PHẨM BÁN CHẠY =====
$top_products_query = $conn->prepare("
    SELECT 
        sp.id_sanpham,
        sp.tensanpham,
        lsp.tenloai as category_name,
        COUNT(DISTINCT dh.id_donhang) as order_count,
        SUM(dc.soluong) as quantity_sold,
        SUM(dc.thanh_tien) as product_revenue,
        sp.luotxem as view_count
    FROM sanpham sp
    JOIN loaisanpham lsp ON sp.id_loai = lsp.id_loai
    JOIN donhang_chitiet dc ON sp.id_sanpham = dc.id_sanpham
    JOIN donhang dh ON dc.id_donhang = dh.id_donhang 
    WHERE sp.id_nguoiban = ? AND dh.trangthai = 4
    AND (dh.ngaytao BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY))
    " . ($category > 0 ? "AND sp.id_loai = ?" : "") . "
    GROUP BY sp.id_sanpham, sp.tensanpham, lsp.tenloai, sp.luotxem
    ORDER BY quantity_sold DESC
    LIMIT 10
");

if ($category > 0) {
    $top_products_query->bind_param("issi", $user_id, $start_date, $end_date, $category);
} else {
    $top_products_query->bind_param("iss", $user_id, $start_date, $end_date);
}
$top_products_query->execute();
$top_products_result = $top_products_query->get_result();

fputcsv($output, ['PHẦN 3: TOP 10 SẢN PHẨM BÁN CHẠY']);
fputcsv($output, []);
fputcsv($output, ['STT', 'Tên sản phẩm', 'Danh mục', 'Đơn hàng', 'Số lượng đã bán', 'Doanh thu (VNĐ)', 'Tỷ lệ (%)', 'Lượt xem']);

$stt = 1;
while ($product = $top_products_result->fetch_assoc()) {
    $percentage = $total_revenue > 0 ? ($product['product_revenue'] / $total_revenue) * 100 : 0;
    
    fputcsv($output, [
        $stt++,
        $product['tensanpham'],
        $product['category_name'],
        $product['order_count'],
        $product['quantity_sold'],
        number_format($product['product_revenue'], 0, ',', '.'),
        number_format($percentage, 2) . '%',
        $product['view_count']
    ]);
}
fputcsv($output, []);
fputcsv($output, ['----------------------------------------------------------------']);
fputcsv($output, []);

// ===== PHẦN 4: TOP KHÁCH HÀNG MUA NHIỀU =====
$top_customers_query = $conn->prepare("
    SELECT 
        dh.id_nguoidung,
        dh.tennguoinhan, 
        dh.sodienthoai,
        dh.email,
        COUNT(DISTINCT dh.id_donhang) as order_count,
        SUM(dc.soluong) as quantity_purchased,
        SUM(dc.thanh_tien) as total_spent
    FROM donhang dh
    JOIN donhang_chitiet dc ON dh.id_donhang = dc.id_donhang
    JOIN sanpham sp ON dc.id_sanpham = sp.id_sanpham
    WHERE sp.id_nguoiban = ? AND dh.trangthai = 4
    AND (dh.ngaytao BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY))
    " . ($category > 0 ? "AND sp.id_loai = ?" : "") . "
    GROUP BY dh.id_nguoidung, dh.tennguoinhan, dh.sodienthoai, dh.email
    ORDER BY total_spent DESC
    LIMIT 5
");

if ($category > 0) {
    $top_customers_query->bind_param("issi", $user_id, $start_date, $end_date, $category);
} else {
    $top_customers_query->bind_param("iss", $user_id, $start_date, $end_date);
}
$top_customers_query->execute();
$top_customers_result = $top_customers_query->get_result();

fputcsv($output, ['PHẦN 4: TOP 5 KHÁCH HÀNG']);
fputcsv($output, []);
fputcsv($output, ['STT', 'Tên khách hàng', 'Số điện thoại', 'Email', 'Số đơn hàng', 'Số sản phẩm', 'Tổng chi tiêu (VNĐ)', 'TB/đơn hàng (VNĐ)']);

$stt = 1;
while ($customer = $top_customers_result->fetch_assoc()) {
    $avg_spent = $customer['order_count'] > 0 ? $customer['total_spent'] / $customer['order_count'] : 0;
    
    fputcsv($output, [
        $stt++,
        $customer['tennguoinhan'],
        $customer['sodienthoai'],
        $customer['email'] ?? 'Không có',
        $customer['order_count'],
        $customer['quantity_purchased'],
        number_format($customer['total_spent'], 0, ',', '.'),
        number_format($avg_spent, 0, ',', '.')
    ]);
}
fputcsv($output, []);
fputcsv($output, ['----------------------------------------------------------------']);
fputcsv($output, []);

// ===== PHẦN 5: PHÂN TÍCH THỜI GIAN =====
// Lấy doanh thu theo ngày trong khoảng thời gian
$daily_revenue_query = $conn->prepare("
    SELECT 
        DATE(dh.ngaytao) as sale_date,
        COUNT(DISTINCT dh.id_donhang) as daily_orders,
        SUM(dc.thanh_tien) as daily_revenue
    FROM donhang dh
    JOIN donhang_chitiet dc ON dh.id_donhang = dc.id_donhang
    JOIN sanpham sp ON dc.id_sanpham = sp.id_sanpham
    WHERE sp.id_nguoiban = ? AND dh.trangthai = 4
    AND (dh.ngaytao BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY))
    " . ($category > 0 ? "AND sp.id_loai = ?" : "") . "
    GROUP BY DATE(dh.ngaytao)
    ORDER BY sale_date
");

if ($category > 0) {
    $daily_revenue_query->bind_param("issi", $user_id, $start_date, $end_date, $category);
} else {
    $daily_revenue_query->bind_param("iss", $user_id, $start_date, $end_date);
}
$daily_revenue_query->execute();
$daily_revenue_result = $daily_revenue_query->get_result();

fputcsv($output, ['PHẦN 5: PHÂN TÍCH DOANH THU THEO THỜI GIAN']);
fputcsv($output, []);
fputcsv($output, ['Ngày', 'Số đơn hàng', 'Doanh thu (VNĐ)', 'TB/đơn hàng (VNĐ)', 'Tỷ lệ (%)']);

$total_days = 0;
$daily_data = [];

while ($day = $daily_revenue_result->fetch_assoc()) {
    $daily_data[] = $day;
    $total_days++;
}

foreach ($daily_data as $day) {
    $avg_order = $day['daily_orders'] > 0 ? $day['daily_revenue'] / $day['daily_orders'] : 0;
    $percentage = $total_revenue > 0 ? ($day['daily_revenue'] / $total_revenue) * 100 : 0;
    
    fputcsv($output, [
        date('d/m/Y', strtotime($day['sale_date'])),
        $day['daily_orders'],
        number_format($day['daily_revenue'], 0, ',', '.'),
        number_format($avg_order, 0, ',', '.'),
        number_format($percentage, 2) . '%'
    ]);
}
fputcsv($output, []);
fputcsv($output, ['----------------------------------------------------------------']);
fputcsv($output, []);

// ===== PHẦN FOOTER / CHÚ THÍCH =====
fputcsv($output, ['Chú thích báo cáo:']);
fputcsv($output, ['- Báo cáo chỉ bao gồm các đơn hàng đã hoàn thành (trạng thái: Đã giao)']);
fputcsv($output, ['- Tỷ lệ phần trăm thể hiện phần đóng góp vào tổng doanh thu']);
fputcsv($output, ['- Giá trị TB/đơn: Giá trị trung bình mỗi đơn hàng của danh mục']);
fputcsv($output, ['- Kỳ so sánh: ' . date('d/m/Y', strtotime($prev_start_date)) . ' đến ' . date('d/m/Y', strtotime($prev_end_date))]);
fputcsv($output, []);
fputcsv($output, ['Báo cáo được tạo tự động từ hệ thống Bug Shop']);
fputcsv($output, ['Ngày xuất báo cáo: ' . date('d/m/Y H:i:s')]);

// Đóng file handle
fclose($output);
exit;