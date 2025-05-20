<?php
// Include database connection
require_once('../config/database.php');

// Include authentication check
require_once('includes/auth_check.php');

// Set page title
$page_title = 'In đơn hàng';

// Get order ID from URL
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$order_id) {
    // Redirect to orders list if no ID provided
    header('Location: orders.php');
    exit;
}

// Format tiền VNĐ
function formatVND($amount)
{
    return number_format($amount, 0, ',', '.') . ' ₫';
}

// Hàm hiển thị trạng thái đơn hàng
function getOrderStatusLabel($status)
{
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

// Hàm hiển thị phương thức thanh toán
function getPaymentMethodLabel($method)
{
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

// Fetch order details
try {
    // Get basic order information
    $order_stmt = $conn->prepare("
        SELECT d.*, u.ten AS customer_name, u.email AS customer_email, u.sodienthoai AS customer_phone
        FROM donhang d
        LEFT JOIN users u ON d.id_user = u.id
        WHERE d.id = ?
    ");
    $order_stmt->execute([$order_id]);
    $order = $order_stmt->fetch();

    if (!$order) {
        // Order not found
        header('Location: orders.php?error=not_found');
        exit;
    }    // Get order items with variant properties
    $items_stmt = $conn->prepare("
        SELECT 
            dc.*, 
            sp.tensanpham, 
            sp.hinhanh,
            sp.id as masanpham,
            m.gia_tri as mau_sac,
            s.gia_tri as kich_thuoc
        FROM donhang_chitiet dc
        JOIN sanpham sp ON dc.id_sanpham = sp.id 
        LEFT JOIN sanpham_bien_the spbt ON dc.id_bienthe = spbt.id
        LEFT JOIN thuoc_tinh m ON spbt.id_mau = m.id AND m.loai = 'color'
        LEFT JOIN thuoc_tinh s ON spbt.id_size = s.id AND s.loai = 'size'
        WHERE dc.id_donhang = ?
    ");
    $items_stmt->execute([$order_id]);
    $order_items = $items_stmt->fetchAll();

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>In đơn hàng #<?php echo $order['ma_donhang']; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom Print CSS -->
    <style>
        @media print {
            @page {
                size: A4;
                margin: 10mm;
            }

            body {
                font-size: 12pt;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .no-print {
                display: none !important;
            }

            .container {
                max-width: 100%;
                width: 100%;
                padding: 0;
                margin: 0;
            }

            .print-border {
                border: 1px solid #ddd !important;
            }

            .print-border-bottom {
                border-bottom: 1px solid #ddd !important;
            }

            table {
                page-break-inside: auto !important;
            }

            tr {
                page-break-inside: avoid !important;
                page-break-after: auto !important;
            }

            .table-items th {
                background-color: #f8f9fa !important;
            }

            .print-success {
                display: none !important;
            }
        }

        .print-heading {
            text-align: center;
            margin-bottom: 20px;
        }

        .company-info {
            text-align: center;
            margin-bottom: 30px;
        }

        .order-details,
        .customer-details {
            margin-bottom: 20px;
        }

        .table-items th,
        .table-items td {
            padding: 8px;
            vertical-align: middle;
        }

        .table-items th {
            background-color: #f8f9fa;
        }

        .total-section {
            margin-top: 20px;
        }

        .signature-section {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
            page-break-inside: avoid;
        }

        .signature-box {
            text-align: center;
            width: 200px;
        }

        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }

        .back-button {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1000;
        }

        .print-success {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(40, 167, 69, 0.9);
            color: white;
            padding: 15px 25px;
            border-radius: 5px;
            z-index: 1000;
        }
    </style>
</head>

<body>
    <a href="orders.php" class="btn btn-secondary back-button no-print">
        <i class="fas fa-arrow-left"></i> Quay lại
    </a>

    <button class="btn btn-primary print-button no-print" onclick="handlePrint();">
        <i class="fas fa-print"></i> In đơn hàng
    </button>

    <div id="printSuccess" class="print-success">
        Đã in đơn hàng thành công!
    </div>

    <div class="container mt-4">
        <div class="print-heading">
            <h2>HÓA ĐƠN BÁN HÀNG</h2>
        </div>

        <div class="company-info">
            <h3>BUG SHOP</h3>
            <p>Địa chỉ: 123 Đường ABC, Quận XYZ, Thành phố HCM</p>
            <p>Điện thoại: (028) 1234 5678 - Email: contact@bugshop.com</p>
        </div>

        <div class="row">
            <div class="col-md-6 order-details">
                <h5 class="print-border-bottom pb-2">Thông tin đơn hàng</h5>
                <p><strong>Mã đơn hàng:</strong> <?php echo $order['ma_donhang']; ?></p>
                <p><strong>Ngày đặt hàng:</strong> <?php echo date('d/m/Y H:i', strtotime($order['ngay_dat'])); ?></p>
                <p><strong>Phương thức thanh toán:</strong>
                    <?php echo getPaymentMethodLabel($order['phuong_thuc_thanh_toan']); ?></p>
                <p><strong>Trạng thái thanh toán:</strong>
                    <?php echo $order['trang_thai_thanh_toan'] ? 'Đã thanh toán' : 'Chưa thanh toán'; ?></p>
                <p><strong>Trạng thái đơn hàng:</strong>
                    <?php echo getOrderStatusLabel($order['trang_thai_don_hang']); ?></p>
            </div>

            <div class="col-md-6 customer-details">
                <h5 class="print-border-bottom pb-2">Thông tin khách hàng</h5>
                <p><strong>Tên khách hàng:</strong> <?php echo htmlspecialchars($order['ho_ten']); ?></p>
                <p><strong>Điện thoại:</strong> <?php echo htmlspecialchars($order['sodienthoai']); ?></p>
                <?php if (!empty($order['email'])): ?>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($order['email']); ?></p>
                <?php endif; ?>
                <p><strong>Địa chỉ:</strong>
                    <?php
                    $address_parts = array_filter([
                        $order['diachi'],
                        $order['phuong_xa'],
                        $order['quan_huyen'],
                        $order['tinh_tp']
                    ]);
                    echo htmlspecialchars(implode(', ', $address_parts));
                    ?>
                </p>
                <?php if (!empty($order['ghi_chu'])): ?>
                    <p><strong>Ghi chú:</strong> <?php echo htmlspecialchars($order['ghi_chu']); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="order-items mt-4">
            <h5 class="print-border-bottom pb-2">Chi tiết đơn hàng</h5>
            <div class="table-responsive">
                <table class="table table-bordered table-items">
                    <thead>
                        <tr>
                            <th>STT</th>
                            <th>Mã SP</th>
                            <th>Tên sản phẩm</th>
                            <th>Màu sắc</th>
                            <th>Kích thước</th>
                            <th>Giá</th>
                            <th>SL</th>
                            <th>Thành tiền</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $count = 1;
                        foreach ($order_items as $item): ?>
                            <tr>
                                <td><?php echo $count++; ?></td>
                                <td><?php echo htmlspecialchars($item['masanpham'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($item['tensanpham']); ?></td>
                                <td><?php echo !empty($item['mau_sac']) ? htmlspecialchars($item['mau_sac']) : 'N/A'; ?>
                                </td>
                                <td><?php echo !empty($item['kich_thuoc']) ? htmlspecialchars($item['kich_thuoc']) : 'N/A'; ?>
                                </td>
                                <td><?php echo formatVND($item['gia']); ?></td>
                                <td><?php echo $item['soluong']; ?></td>
                                <td><?php echo formatVND($item['thanh_tien']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="total-section">
            <div class="row">
                <div class="col-md-6"></div>
                <div class="col-md-6">
                    <table class="table table-bordered">
                        <tr>
                            <td><strong>Tạm tính:</strong></td>
                            <td class="text-end"><?php echo formatVND($order['tong_tien']); ?></td>
                        </tr>
                        <?php if ($order['giam_gia'] > 0): ?>
                            <tr>
                                <td><strong>Giảm giá:</strong></td>
                                <td class="text-end">- <?php echo formatVND($order['giam_gia']); ?></td>
                            </tr>
                        <?php endif; ?>
                        <tr>
                            <td><strong>Phí vận chuyển:</strong></td>
                            <td class="text-end"><?php echo formatVND($order['phi_vanchuyen']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Tổng cộng:</strong></td>
                            <td class="text-end"><strong><?php echo formatVND($order['thanh_tien']); ?></strong></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="signature-section">
            <div class="signature-box">
                <p><strong>Người bán hàng</strong></p>
                <p>(Ký, ghi rõ họ tên)</p>
            </div>
            <div class="signature-box">
                <p><strong>Người mua hàng</strong></p>
                <p>(Ký, ghi rõ họ tên)</p>
            </div>
        </div>

        <div class="footer-note text-center mt-5">
            <p><em>Cảm ơn quý khách đã mua hàng tại Bug Shop!</em></p>
        </div>
    </div>

    <!-- Font Awesome - add this if you're using Font Awesome icons -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/js/all.min.js"></script>

    <!-- Auto print when page loads (optional) -->
    <script>
        window.onload = function () {
            // Uncomment the line below if you want the print dialog to appear automatically
            // window.print();
        }

        function handlePrint() {
            window.print();
            showPrintSuccess();
        }

        function showPrintSuccess() {
            const successMsg = document.getElementById('printSuccess');
            successMsg.style.display = 'block';

            // Tự động ẩn thông báo sau 3 giây
            setTimeout(() => {
                successMsg.style.display = 'none';
            }, 3000);
        }

        // Thêm sự kiện afterprint để hiển thị thông báo thành công
        if (window.matchMedia) {
            const mediaQueryList = window.matchMedia('print');
            mediaQueryList.addListener((mql) => {
                if (!mql.matches) {
                    showPrintSuccess();
                }
            });
        }
    </script>
</body>

</html>