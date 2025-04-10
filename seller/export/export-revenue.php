<?php
session_start();
require_once('../../vendor/autoload.php'); // Đường dẫn chính xác tới autoload
require('../../config/config.php');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

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

// Tạo spreadsheet mới
$spreadsheet = new Spreadsheet();

// ===== SHEET 1: TỔNG QUAN =====
$overview = $spreadsheet->getActiveSheet();
$overview->setTitle('Tổng quan');

// Thiết lập tiêu đề và thông tin cửa hàng
$overview->setCellValue('A1', 'BUG SHOP - BÁO CÁO DOANH THU');
$overview->mergeCells('A1:F1');
$overview->getStyle('A1')->getFont()->setBold(true)->setSize(16);
$overview->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$overview->setCellValue('A3', 'BÁO CÁO DOANH THU CHI TIẾT');
$overview->mergeCells('A3:F3');
$overview->getStyle('A3')->getFont()->setBold(true)->setSize(14);
$overview->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$overview->setCellValue('A4', 'Từ ngày:');
$overview->setCellValue('B4', date('d/m/Y', strtotime($start_date)));
$overview->setCellValue('D4', 'Đến ngày:');
$overview->setCellValue('E4', date('d/m/Y', strtotime($end_date)));

// Thêm thông tin cửa hàng
$overview->setCellValue('A6', 'THÔNG TIN CỬA HÀNG');
$overview->mergeCells('A6:F6');
$overview->getStyle('A6')->getFont()->setBold(true);

$overview->setCellValue('A7', 'Tên shop:');
$overview->setCellValue('B7', $seller['ten_shop'] ?? 'Chưa đặt tên shop');

$overview->setCellValue('A8', 'Người bán:');
$overview->setCellValue('B8', $seller['tenuser']);

$overview->setCellValue('A9', 'Liên hệ:');
$overview->setCellValue('B9', $seller['email']);
$overview->setCellValue('C9', $seller['sdt']);

// Tổng doanh thu trong khoảng thời gian
$total_revenue_query = $conn->prepare("
    SELECT COALESCE(SUM(dc.thanh_tien), 0) as total_revenue
    FROM donhang_chitiet dc
    JOIN donhang dh ON dc.id_donhang = dh.id_donhang
    JOIN sanpham sp ON dc.id_sanpham = sp.id_sanpham
    WHERE sp.id_nguoiban = ? AND dh.trangthai = 4
    AND (dh.ngaytao BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY))
");
$total_revenue_query->bind_param("iss", $user_id, $start_date, $end_date);
$total_revenue_query->execute();
$total_revenue = $total_revenue_query->get_result()->fetch_assoc()['total_revenue'];

// Tổng số đơn hàng
$total_orders_query = $conn->prepare("
    SELECT COUNT(DISTINCT dh.id_donhang) as total_orders
    FROM donhang dh
    JOIN donhang_chitiet dc ON dh.id_donhang = dc.id_donhang
    JOIN sanpham sp ON dc.id_sanpham = sp.id_sanpham
    WHERE sp.id_nguoiban = ? AND dh.trangthai = 4
    AND (dh.ngaytao BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY))
");
$total_orders_query->bind_param("iss", $user_id, $start_date, $end_date);
$total_orders_query->execute();
$total_orders = $total_orders_query->get_result()->fetch_assoc()['total_orders'];

// Tổng số sản phẩm đã bán
$total_products_sold_query = $conn->prepare("
    SELECT COALESCE(SUM(dc.soluong), 0) as total_sold
    FROM donhang_chitiet dc
    JOIN donhang dh ON dc.id_donhang = dh.id_donhang
    JOIN sanpham sp ON dc.id_sanpham = sp.id_sanpham
    WHERE sp.id_nguoiban = ? AND dh.trangthai = 4
    AND (dh.ngaytao BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY))
");
$total_products_sold_query->bind_param("iss", $user_id, $start_date, $end_date);
$total_products_sold_query->execute();
$total_products_sold = $total_products_sold_query->get_result()->fetch_assoc()['total_sold'];

// Điền dữ liệu tổng quan
$overview->setCellValue('A11', 'TỔNG QUAN DOANH THU');
$overview->mergeCells('A11:F11');
$overview->getStyle('A11')->getFont()->setBold(true);

$overview->setCellValue('A13', 'Chỉ tiêu');
$overview->setCellValue('B13', 'Giá trị');
$overview->getStyle('A13:B13')->getFont()->setBold(true);

$overview->setCellValue('A14', 'Tổng doanh thu');
$overview->setCellValue('B14', $total_revenue);
$overview->getStyle('B14')->getNumberFormat()->setFormatCode('#,##0 "₫"');

$overview->setCellValue('A15', 'Tổng số đơn hàng');
$overview->setCellValue('B15', $total_orders);

$overview->setCellValue('A16', 'Tổng sản phẩm đã bán');
$overview->setCellValue('B16', $total_products_sold);

// Giá trị đơn trung bình
$avg_order_value = $total_orders > 0 ? $total_revenue / $total_orders : 0;
$overview->setCellValue('A17', 'Giá trị đơn trung bình');
$overview->setCellValue('B17', $avg_order_value);
$overview->getStyle('B17')->getNumberFormat()->setFormatCode('#,##0 "₫"');

// Định dạng và tạo các bảng tổng quan
$overview->setCellValue('A7', 'TỔNG QUAN KẾT QUẢ KINH DOANH');
$overview->getStyle('A7')->getFont()->setBold(true);
$overview->getStyle('A7')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('4472C4');
$overview->getStyle('A7')->getFont()->getColor()->setARGB(Color::COLOR_WHITE);
$overview->mergeCells('A7:F7');

// Tô màu và tạo viền cho các bảng
$overview->getStyle('A8:C12')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
$overview->getStyle('A8:C8')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('D9E1F2');
$overview->getStyle('A8:C8')->getFont()->setBold(true);

// Định dạng số tiền
$overview->getStyle('B10')->getNumberFormat()->setFormatCode('#,##0 "₫"');

// ===== SHEET 2: DANH MỤC =====
$spreadsheet->createSheet();
$categorySheet = $spreadsheet->getSheet(1);
$categorySheet->setTitle('Doanh thu theo danh mục');

// ===== ĐIỀN DỮ LIỆU SHEET DANH MỤC =====
// Tiêu đề bảng
$categorySheet->setCellValue('A1', 'DOANH THU THEO DANH MỤC');
$categorySheet->mergeCells('A1:F1');
$categorySheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$categorySheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$categorySheet->setCellValue('A3', 'STT');
$categorySheet->setCellValue('B3', 'Danh mục');
$categorySheet->setCellValue('C3', 'Số đơn hàng');
$categorySheet->setCellValue('D3', 'Số sản phẩm đã bán');
$categorySheet->setCellValue('E3', 'Doanh thu');
$categorySheet->setCellValue('F3', 'Tỷ lệ %');
$categorySheet->getStyle('A3:F3')->getFont()->setBold(true);

// Truy vấn dữ liệu danh mục
$category_revenue_query = $conn->prepare("
    SELECT 
        lsp.id_loai, 
        lsp.tenloai, 
        COUNT(DISTINCT dh.id_donhang) as order_count,
        COALESCE(SUM(dc.soluong), 0) as quantity_sold,
        COALESCE(SUM(dc.thanh_tien), 0) as category_revenue
    FROM loaisanpham lsp
    LEFT JOIN sanpham sp ON lsp.id_loai = sp.id_loai AND sp.id_nguoiban = ?
    LEFT JOIN donhang_chitiet dc ON sp.id_sanpham = dc.id_sanpham
    LEFT JOIN donhang dh ON dc.id_donhang = dh.id_donhang AND dh.trangthai = 4 
        AND (dh.ngaytao BETWEEN ? AND DATE_ADD(?, INTERVAL 1 DAY))
    GROUP BY lsp.id_loai, lsp.tenloai
    ORDER BY category_revenue DESC
");
$category_revenue_query->bind_param("iss", $user_id, $start_date, $end_date);
$category_revenue_query->execute();
$categories_result = $category_revenue_query->get_result();

// Điền dữ liệu danh mục
$row = 4;
$stt = 1;
while ($category = $categories_result->fetch_assoc()) {
    $percentage = $total_revenue > 0 ? ($category['category_revenue'] / $total_revenue) * 100 : 0;
    
    $categorySheet->setCellValue('A' . $row, $stt);
    $categorySheet->setCellValue('B' . $row, $category['tenloai']);
    $categorySheet->setCellValue('C' . $row, $category['order_count']);
    $categorySheet->setCellValue('D' . $row, $category['quantity_sold']);
    $categorySheet->setCellValue('E' . $row, $category['category_revenue']);
    $categorySheet->setCellValue('F' . $row, $percentage);
    
    // Định dạng số
    $categorySheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode('#,##0 "₫"');
    $categorySheet->getStyle('F' . $row)->getNumberFormat()->setFormatCode('0.00"%"');
    
    $row++;
    $stt++;
}

// ===== SHEET 3: SẢN PHẨM =====
$spreadsheet->createSheet();
$productSheet = $spreadsheet->getSheet(2);
$productSheet->setTitle('Top sản phẩm');

// ===== SHEET 4: KHÁCH HÀNG =====
$spreadsheet->createSheet();
$customerSheet = $spreadsheet->getSheet(3);
$customerSheet->setTitle('Top khách hàng');

// ===== SHEET 5: THỜI GIAN =====
$spreadsheet->createSheet();
$timeSheet = $spreadsheet->getSheet(4);
$timeSheet->setTitle('Doanh thu theo ngày');

// Đặt sheet đầu tiên là active
$spreadsheet->setActiveSheetIndex(0);

// Xuất file
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="BaoCao_DoanhThu_' . date('Ymd_His') . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;