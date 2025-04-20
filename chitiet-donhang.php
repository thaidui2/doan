<?php
session_start();
include('config/config.php');

// Kiểm tra đăng nhập
if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    header('Location: dangnhap.php?redirect=donhang.php');
    exit();
}

$user_id = $_SESSION['user']['id'];
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Lấy thông tin đơn hàng - Cập nhật tên bảng và cột
$stmt = $conn->prepare("
    SELECT * FROM donhang
    WHERE id = ? AND id_user = ?
");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Đơn hàng không tồn tại hoặc không thuộc người dùng này
    header('Location: donhang.php');
    exit();
}

$order = $result->fetch_assoc();

// Lấy chi tiết sản phẩm trong đơn hàng - Cập nhật schema để sử dụng thuoc_tinh thay vì kichthuoc và mausac
$stmt = $conn->prepare("
    SELECT dhct.*, sp.tensanpham, sp.hinhanh, 
           size.gia_tri AS ten_kichthuoc, 
           color.gia_tri AS ten_mau,
           color.ma_mau
    FROM donhang_chitiet dhct
    JOIN sanpham sp ON dhct.id_sanpham = sp.id
    LEFT JOIN sanpham_bien_the sbt ON dhct.id_bienthe = sbt.id
    LEFT JOIN thuoc_tinh size ON sbt.id_size = size.id AND size.loai = 'size'
    LEFT JOIN thuoc_tinh color ON sbt.id_mau = color.id AND color.loai = 'color'
    WHERE dhct.id_donhang = ?
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$items_result = $stmt->get_result();
$items = [];

while ($item = $items_result->fetch_assoc()) {
    $items[] = $item;
}

// Mảng trạng thái đơn hàng - Cập nhật trạng thái
$order_statuses = [
    1 => ['name' => 'Chờ xác nhận', 'class' => 'bg-warning text-dark'],
    2 => ['name' => 'Đã xác nhận', 'class' => 'bg-primary'],
    3 => ['name' => 'Đang giao hàng', 'class' => 'bg-info'],
    4 => ['name' => 'Đã giao', 'class' => 'bg-success'],
    5 => ['name' => 'Đã hủy', 'class' => 'bg-danger']
];
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi tiết đơn hàng #<?php echo $order['ma_donhang']; ?> - Bug Shop</title>
    
    <style>
        .product-img {
            width: 60px;
            height: 60px;
            object-fit: cover;
        }
        
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        
        .timeline-item {
            position: relative;
            padding-bottom: 15px;
            padding-left: 20px;
            border-left: 2px solid #dee2e6;
        }
        
        .timeline-item:last-child {
            border-left: 2px solid transparent;
        }
        
        .timeline-point {
            position: absolute;
            left: -9px;
            top: 0;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 2px solid #fff;
        }
        
        .timeline-point-active {
            background-color: #28a745;
        }
        
        .timeline-point-inactive {
            background-color: #dee2e6;
        }
        
        .timeline-item-active {
            font-weight: 600;
        }
        
        .timeline-item-inactive {
            color: #6c757d;
        }
    </style>
</head>
<body>
    <?php 
    include('includes/head.php');
    include('includes/header.php'); ?>
    
    <div class="container py-5">
        <h1 class="mb-4">Chi tiết đơn hàng #<?php echo $order['ma_donhang']; ?></h1>
        
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Trang chủ</a></li>
                <li class="breadcrumb-item"><a href="taikhoan.php">Tài khoản</a></li>
                <li class="breadcrumb-item"><a href="donhang.php">Đơn hàng của tôi</a></li>
                <li class="breadcrumb-item active" aria-current="page">Đơn hàng #<?php echo $order['ma_donhang']; ?></li>
            </ol>
        </nav>
        
        <div class="row">
            <div class="col-lg-8">
                <!-- Thông tin đơn hàng -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Thông tin đơn hàng</h5>
                            
                            <?php
                            $status_id = $order['trang_thai_don_hang'];
                            $status = isset($order_statuses[$status_id]) ? $order_statuses[$status_id] : ['name' => 'Không xác định', 'class' => 'bg-secondary'];
                            echo '<span class="badge ' . $status['class'] . '">' . $status['name'] . '</span>';
                            ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Mã đơn hàng:</strong> <?php echo $order['ma_donhang']; ?></p>
                                <p class="mb-1"><strong>Ngày đặt hàng:</strong> <?php echo date('d/m/Y H:i', strtotime($order['ngay_dat'])); ?></p>
                                <p class="mb-1">
                                    <strong>Phương thức thanh toán:</strong> 
                                    <?php 
                                    switch($order['phuong_thuc_thanh_toan']) {
                                        case 'cod':
                                            echo 'Thanh toán khi nhận hàng (COD)';
                                            break;
                                        case 'bank_transfer':
                                            echo 'Chuyển khoản ngân hàng';
                                            break;
                                        case 'momo':
                                            echo 'Ví MoMo';
                                            break;
                                        case 'vnpay':
                                            echo 'VNPay';
                                            break;
                                        default:
                                            echo ucfirst($order['phuong_thuc_thanh_toan']);
                                    }
                                    ?>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Người nhận:</strong> <?php echo $order['ho_ten']; ?></p>
                                <p class="mb-1"><strong>Số điện thoại:</strong> <?php echo $order['sodienthoai']; ?></p>
                                <p class="mb-1"><strong>Email:</strong> <?php echo $order['email']; ?></p>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <strong>Địa chỉ giao hàng:</strong>
                            <p class="mb-0">
                                <?php echo $order['diachi']; ?>, <?php echo $order['phuong_xa']; ?>, 
                                <?php echo $order['quan_huyen']; ?>, <?php echo $order['tinh_tp']; ?>
                            </p>
                        </div>
                        
                        <?php if(!empty($order['ghi_chu'])): ?>
                            <div>
                                <strong>Ghi chú:</strong>
                                <p class="mb-0"><?php echo $order['ghi_chu']; ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Danh sách sản phẩm -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Sản phẩm đã đặt</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Sản phẩm</th>
                                        <th class="text-center">Số lượng</th>
                                        <th class="text-end">Đơn giá</th>
                                        <th class="text-end">Thành tiền</th>
                                        <?php if ($order['trang_thai_don_hang'] == 4): ?>
                                        <th class="text-center">Thao tác</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($items as $item): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="<?php echo !empty($item['hinhanh']) ? 
                                                    (strpos($item['hinhanh'], 'uploads/') !== false ? $item['hinhanh'] : 'uploads/products/' . $item['hinhanh']) : 
                                                    'images/no-image.png'; ?>" 
                                                     alt="<?php echo htmlspecialchars($item['tensanpham']); ?>" 
                                                     class="product-img rounded me-3">
                                                <div>
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($item['tensp'] ?? $item['tensanpham']); ?></h6>
                                                    <small class="text-muted">
                                                        <?php echo $item['thuoc_tinh']; ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center"><?php echo $item['soluong']; ?></td>
                                        <td class="text-end"><?php echo number_format($item['gia'], 0, ',', '.'); ?>₫</td>
                                        <td class="text-end fw-bold"><?php echo number_format($item['thanh_tien'], 0, ',', '.'); ?>₫</td>
                                        <?php if ($order['trang_thai_don_hang'] == 4): ?>
                                        <td class="text-center">
                                            <div class="d-flex flex-column gap-2">
                                                <?php 
                                                // Ensure da_danh_gia exists and cast to boolean for consistent evaluation
                                                $has_review = isset($item['da_danh_gia']) ? (bool)$item['da_danh_gia'] : false;
                                                if(!$has_review): 
                                                ?>
                                                <a href="danhgia.php?id_sp=<?php echo $item['id_sanpham']; ?>&id_dh=<?php echo $order_id; ?>" 
                                                   class="btn btn-sm btn-primary" title="Đánh giá sản phẩm">
                                                    <i class="bi bi-star-fill me-1"></i> Đánh giá
                                                </a>
                                                <?php else: ?>
                                                <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i> Đã đánh giá</span>
                                                <?php endif; ?>
                                                <a href="donhoantra.php?order_id=<?php echo $order_id; ?>&product_id=<?php echo $item['id_sanpham']; ?>" 
                                                   class="btn btn-sm btn-outline-danger" title="Yêu cầu hoàn trả">
                                                    <i class="bi bi-arrow-return-left me-1"></i> Hoàn trả
                                                </a>
                                            </div>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Tổng tiền -->
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Tổng tiền sản phẩm:</span>
                            <span><?php echo number_format($order['tong_tien'], 0, ',', '.'); ?>₫</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Phí vận chuyển:</span>
                            <span><?php echo number_format($order['phi_vanchuyen'], 0, ',', '.'); ?>₫</span>
                        </div>
                        <?php if ($order['giam_gia'] > 0): ?>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Giảm giá:</span>
                            <span class="text-danger">-<?php echo number_format($order['giam_gia'], 0, ',', '.'); ?>₫</span>
                        </div>
                        <?php endif; ?>
                        <div class="d-flex justify-content-between pt-2 border-top">
                            <span class="fw-bold">Tổng thanh toán:</span>
                            <span class="fw-bold text-danger fs-5"><?php echo number_format($order['thanh_tien'], 0, ',', '.'); ?>₫</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <!-- Trạng thái đơn hàng -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Trạng thái đơn hàng</h5>
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                            <!-- Chờ xác nhận -->
                            <div class="timeline-item <?php echo $order['trang_thai_don_hang'] >= 1 ? 'timeline-item-active' : 'timeline-item-inactive'; ?>">
                                <div class="timeline-point <?php echo $order['trang_thai_don_hang'] >= 1 ? 'timeline-point-active' : 'timeline-point-inactive'; ?>"></div>
                                <div class="mb-1">Chờ xác nhận</div>
                                <?php if($order['trang_thai_don_hang'] >= 1): ?>
                                    <div class="small text-muted"><?php echo date('d/m/Y H:i', strtotime($order['ngay_dat'])); ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Đã xác nhận -->
                            <div class="timeline-item <?php echo $order['trang_thai_don_hang'] >= 2 ? 'timeline-item-active' : 'timeline-item-inactive'; ?>">
                                <div class="timeline-point <?php echo $order['trang_thai_don_hang'] >= 2 ? 'timeline-point-active' : 'timeline-point-inactive'; ?>"></div>
                                <div class="mb-1">Đã xác nhận</div>
                                <?php if($order['trang_thai_don_hang'] >= 2): ?>
                                    <div class="small text-muted">Đơn hàng đã được xác nhận</div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Đang giao hàng -->
                            <div class="timeline-item <?php echo $order['trang_thai_don_hang'] >= 3 ? 'timeline-item-active' : 'timeline-item-inactive'; ?>">
                                <div class="timeline-point <?php echo $order['trang_thai_don_hang'] >= 3 ? 'timeline-point-active' : 'timeline-point-inactive'; ?>"></div>
                                <div class="mb-1">Đang giao hàng</div>
                                <?php if($order['trang_thai_don_hang'] >= 3): ?>
                                    <div class="small text-muted">Đơn hàng đang được vận chuyển</div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Đã giao -->
                            <div class="timeline-item <?php echo $order['trang_thai_don_hang'] >= 4 ? 'timeline-item-active' : 'timeline-item-inactive'; ?>">
                                <div class="timeline-point <?php echo $order['trang_thai_don_hang'] >= 4 ? 'timeline-point-active' : 'timeline-point-inactive'; ?>"></div>
                                <div class="mb-1">Đã giao</div>
                                <?php if($order['trang_thai_don_hang'] >= 4): ?>
                                    <div class="small text-muted">Đơn hàng đã được giao thành công</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Các nút thao tác -->
                <div class="d-grid gap-2">
                    <a href="donhang.php" class="btn btn-outline-primary">
                        <i class="bi bi-arrow-left"></i> Quay lại danh sách
                    </a>
                    
                    <?php if($order['trang_thai_don_hang'] == 1): ?>
                        <button type="button" class="btn btn-outline-danger" 
                                data-bs-toggle="modal" data-bs-target="#cancelModal">
                            <i class="bi bi-x-circle"></i> Hủy đơn hàng
                        </button>
                        
                        <!-- Modal xác nhận hủy đơn -->
                        <div class="modal fade" id="cancelModal" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Xác nhận hủy đơn hàng</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p>Bạn có chắc chắn muốn hủy đơn hàng #<?php echo $order['ma_donhang']; ?>?</p>
                                        <p class="text-muted small">Sau khi hủy, bạn sẽ không thể khôi phục lại đơn hàng này.</p>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                                        <a href="huydonhang.php?id=<?php echo $order_id; ?>" class="btn btn-danger">
                                            Xác nhận hủy
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <a href="lienhe.php" class="btn btn-outline-secondary">
                        <i class="bi bi-headset"></i> Liên hệ hỗ trợ
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <?php include('includes/footer.php'); ?>
</body>
</html>
