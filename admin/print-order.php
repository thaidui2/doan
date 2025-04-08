<?php
session_start();
include('../config/config.php');

// Kiểm tra đăng nhập
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// Kiểm tra id đơn hàng
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: orders.php');
    exit();
}

$order_id = (int)$_GET['id'];

// Lấy thông tin đơn hàng
$order_query = "SELECT * FROM donhang WHERE id_donhang = ?";
$stmt = $conn->prepare($order_query);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order_result = $stmt->get_result();

if ($order_result->num_rows === 0) {
    header('Location: orders.php');
    exit();
}

$order = $order_result->fetch_assoc();

// Lấy thông tin chi tiết đơn hàng
$items_query = "
    SELECT 
        dct.*, 
        sp.tensanpham,
        kt.tenkichthuoc,
        ms.tenmau
    FROM 
        donhang_chitiet dct
    LEFT JOIN 
        sanpham sp ON dct.id_sanpham = sp.id_sanpham
    LEFT JOIN 
        kichthuoc kt ON dct.id_kichthuoc = kt.id_kichthuoc
    LEFT JOIN 
        mausac ms ON dct.id_mausac = ms.id_mausac
    WHERE 
        dct.id_donhang = ?
";

$stmt = $conn->prepare($items_query);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$items_result = $stmt->get_result();

// Mảng trạng thái đơn hàng
$order_statuses = [
    1 => 'Chờ xác nhận',
    2 => 'Đang xử lý',
    3 => 'Đang giao hàng',
    4 => 'Đã giao',
    5 => 'Đã hủy',
    6 => 'Hoàn trả'
];

// Lấy phương thức thanh toán
$payment_methods = [
    'cod' => 'Tiền mặt khi nhận hàng (COD)',
    'bank_transfer' => 'Chuyển khoản ngân hàng',
    'momo' => 'Ví MoMo',
    'vnpay' => 'VNPay'
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>In đơn hàng #<?php echo $order_id; ?></title>
    <link rel="stylesheet" href="../node_modules/bootstrap/dist/css/bootstrap.min.css">
    <style>
        body {
            font-size: 14px;
        }
        .invoice-header {
            padding-bottom: 20px;
            border-bottom: 1px solid #ddd;
            margin-bottom: 20px;
        }
        .invoice-title {
            font-size: 28px;
            margin-bottom: 5px;
        }
        .invoice-details {
            margin-bottom: 20px;
        }
        .customer-details, .order-details {
            margin-bottom: 20px;
        }
        .table th, .table td {
            padding: 8px;
        }
        .table tfoot {
            font-weight: bold;
        }
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 12px;
            text-align: center;
        }
        @media print {
            .no-print {
                display: none;
            }
            body {
                padding: 0;
                margin: 0;
            }
            .container {
                width: 100%;
                max-width: 100%;
                padding: 0;
                margin: 0;
            }
            .invoice-container {
                border: none;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div class="container my-4">
        <div class="no-print mb-4">
            <button onclick="window.print();" class="btn btn-primary">
                <i class="bi bi-printer"></i> In đơn hàng
            </button>
            <button onclick="window.history.back();" class="btn btn-secondary ms-2">
                Quay lại
            </button>
        </div>
        
        <div class="invoice-container p-4 border rounded">
            <div class="invoice-header">
                <div class="row">
                    <div class="col-md-6">
                        <h1 class="invoice-title">HÓA ĐƠN</h1>
                        <div>#<?php echo $order_id; ?></div>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <div class="mb-2">
                            <strong>Bug Shop</strong>
                        </div>
                        <div>123 Đường ABC, Phường XYZ</div>
                        <div>Quận/Huyện, Tỉnh/Thành phố</div>
                        <div>Email: contact@bugshop.com</div>
                        <div>Điện thoại: 0123 456 789</div>
                    </div>
                </div>
            </div>
            
            <div class="row invoice-details">
                <div class="col-md-6 customer-details">
                    <h5 class="mb-2">Thông tin khách hàng</h5>
                    <div><strong>Tên:</strong> <?php echo htmlspecialchars($order['tennguoinhan']); ?></div>
                    <div><strong>Điện thoại:</strong> <?php echo htmlspecialchars($order['sodienthoai']); ?></div>
                    <?php if (!empty($order['email'])): ?>
                    <div><strong>Email:</strong> <?php echo htmlspecialchars($order['email']); ?></div>
                    <?php endif; ?>
                    <div>
                        <strong>Địa chỉ:</strong>
                        <?php 
                        $address_parts = [];
                        if (!empty($order['diachi'])) $address_parts[] = htmlspecialchars($order['diachi']);
                        if (!empty($order['phuong_xa'])) $address_parts[] = htmlspecialchars($order['phuong_xa']);
                        if (!empty($order['quan_huyen'])) $address_parts[] = htmlspecialchars($order['quan_huyen']);
                        if (!empty($order['tinh_tp'])) $address_parts[] = htmlspecialchars($order['tinh_tp']);
                        
                        echo implode(", ", $address_parts);
                        ?>
                    </div>
                </div>
                <div class="col-md-6 order-details text-md-end">
                    <h5 class="mb-2">Thông tin đơn hàng</h5>
                    <div><strong>Ngày đặt hàng:</strong> <?php echo date('d/m/Y H:i', strtotime($order['ngaytao'])); ?></div>
                    <div>
                        <strong>Trạng thái:</strong> 
                        <?php echo $order_statuses[$order['trangthai']] ?? 'Không xác định'; ?>
                    </div>
                    <div>
                        <strong>Phương thức thanh toán:</strong> 
                        <?php echo $payment_methods[$order['phuongthucthanhtoan']] ?? ucfirst($order['phuongthucthanhtoan']); ?>
                    </div>
                </div>
            </div>
            
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th class="text-center">STT</th>
                        <th>Sản phẩm</th>
                        <th class="text-center">Số lượng</th>
                        <th class="text-end">Đơn giá</th>
                        <th class="text-end">Thành tiền</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $index = 1;
                    $totalAmount = 0;
                    while ($item = $items_result->fetch_assoc()): 
                        $totalAmount += $item['thanh_tien'];
                    ?>
                    <tr>
                        <td class="text-center"><?php echo $index++; ?></td>
                        <td>
                            <?php if (!empty($item['tensanpham'])): ?>
                                <div class="fw-bold"><?php echo htmlspecialchars($item['tensanpham']); ?></div>
                            <?php else: ?>
                                <div class="fw-bold">Sản phẩm không còn tồn tại</div>
                            <?php endif; ?>
                            
                            <div class="small">
                                <?php if (!empty($item['tenkichthuoc'])): ?>
                                    <span class="me-2">Kích thước: <?php echo htmlspecialchars($item['tenkichthuoc']); ?></span>
                                <?php endif; ?>
                                
                                <?php if (!empty($item['tenmau'])): ?>
                                    <span>Màu: <?php echo htmlspecialchars($item['tenmau']); ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="text-center"><?php echo $item['soluong']; ?></td>
                        <td class="text-end"><?php echo number_format($item['gia'], 0, ',', '.'); ?> ₫</td>
                        <td class="text-end"><?php echo number_format($item['thanh_tien'], 0, ',', '.'); ?> ₫</td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="text-end">Tổng cộng:</td>
                        <td class="text-end"><?php echo number_format($totalAmount, 0, ',', '.'); ?> ₫</td>
                    </tr>
                    <tr>
                        <td colspan="4" class="text-end">Phí vận chuyển:</td>
                        <td class="text-end"><?php echo number_format($order['phivanchuyen'], 0, ',', '.'); ?> ₫</td>
                    </tr>
                    <tr>
                        <td colspan="4" class="text-end">Tổng thanh toán:</td>
                        <td class="text-end"><?php echo number_format($order['tongtien'], 0, ',', '.'); ?> ₫</td>
                    </tr>
                </tfoot>
            </table>
            
            <?php if (!empty($order['ghichu'])): ?>
            <div class="mt-3">
                <strong>Ghi chú:</strong>
                <div><?php echo nl2br(htmlspecialchars($order['ghichu'])); ?></div>
            </div>
            <?php endif; ?>
            
            <div class="footer">
                <p>Cảm ơn quý khách đã mua hàng tại Bug Shop!</p>
                <p>Mọi thắc mắc vui lòng liên hệ: 0123 456 789 hoặc email: contact@bugshop.com</p>
                <p>Hóa đơn này được tạo tự động và có giá trị không cần đóng dấu, chữ ký.</p>
            </div>
        </div>
    </div>
    
    <script>
    // Tự động in khi tải trang
    window.onload = function() {
        // window.print();
    }
    </script>
</body>
</html>