<?php
session_start();
include('config/config.php');

// Kiểm tra đăng nhập
if (!isset($_SESSION['user']) || $_SESSION['user']['logged_in'] !== true) {
    header('Location: dangnhap.php?redirect=donhang.php');
    exit();
}

$user_id = $_SESSION['user']['id'];
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Lấy thông tin đơn hàng
$stmt = $conn->prepare("
    SELECT * FROM donhang
    WHERE id_donhang = ? AND id_nguoidung = ?
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

// Lấy chi tiết sản phẩm trong đơn hàng
$stmt = $conn->prepare("
    SELECT dhct.*, sp.tensanpham, sp.hinhanh, 
           kt.tenkichthuoc, ms.tenmau, ms.mamau
    FROM donhang_chitiet dhct
    JOIN sanpham sp ON dhct.id_sanpham = sp.id_sanpham
    LEFT JOIN kichthuoc kt ON dhct.id_kichthuoc = kt.id_kichthuoc
    LEFT JOIN mausac ms ON dhct.id_mausac = ms.id_mausac
    WHERE dhct.id_donhang = ?
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$items_result = $stmt->get_result();
$items = [];

while ($item = $items_result->fetch_assoc()) {
    $items[] = $item;
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi tiết đơn hàng #<?php echo $order_id; ?> - Bug Shop</title>
    <link rel="stylesheet" href="node_modules/bootstrap/dist/css/bootstrap.css">
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
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
    <?php include('includes/header.php'); ?>
    
    <div class="container py-5">
        <h1 class="mb-4">Chi tiết đơn hàng #<?php echo $order_id; ?></h1>
        
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Trang chủ</a></li>
                <li class="breadcrumb-item"><a href="taikhoan.php">Tài khoản</a></li>
                <li class="breadcrumb-item"><a href="donhang.php">Đơn hàng của tôi</a></li>
                <li class="breadcrumb-item active" aria-current="page">Đơn hàng #<?php echo $order_id; ?></li>
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
                            $status_text = '';
                            $status_class = '';
                            
                            switch ($order['trangthai']) {
                                case 1:
                                    $status_text = 'Chờ xác nhận';
                                    $status_class = 'bg-info';
                                    break;
                                case 2:
                                    $status_text = 'Đang xử lý';
                                    $status_class = 'bg-primary';
                                    break;
                                case 3:
                                    $status_text = 'Đang giao hàng';
                                    $status_class = 'bg-warning text-dark';
                                    break;
                                case 4:
                                    $status_text = 'Đã giao';
                                    $status_class = 'bg-success';
                                    break;
                                case 5:
                                    $status_text = 'Đã hủy';
                                    $status_class = 'bg-danger';
                                    break;
                                case 6:
                                    $status_text = 'Hoàn trả';
                                    $status_class = 'bg-secondary';
                                    break;
                                default:
                                    $status_text = 'Không xác định';
                                    $status_class = 'bg-dark';
                            }
                            
                            echo '<span class="badge ' . $status_class . '">' . $status_text . '</span>';
                            ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Mã đơn hàng:</strong> #<?php echo $order_id; ?></p>
                                <p class="mb-1"><strong>Ngày đặt hàng:</strong> <?php echo date('d/m/Y H:i', strtotime($order['ngaytao'])); ?></p>
                                <p class="mb-1">
                                    <strong>Phương thức thanh toán:</strong> 
                                    <?php 
                                    switch($order['phuongthucthanhtoan']) {
                                        case 'cod':
                                            echo 'Thanh toán khi nhận hàng (COD)';
                                            break;
                                        case 'bank_transfer':
                                            echo 'Chuyển khoản ngân hàng';
                                            break;
                                        case 'momo':
                                            echo 'Ví MoMo';
                                            break;
                                        default:
                                            echo $order['phuongthucthanhtoan'];
                                    }
                                    ?>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <p class="mb-1"><strong>Người nhận:</strong> <?php echo $order['tennguoinhan']; ?></p>
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
                        
                        <?php if(!empty($order['ghichu'])): ?>
                            <div>
                                <strong>Ghi chú:</strong>
                                <p class="mb-0"><?php echo $order['ghichu']; ?></p>
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
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($items as $item): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="<?php echo !empty($item['hinhanh']) ? 'uploads/products/' . $item['hinhanh'] : 'images/no-image.png'; ?>" 
                                                     alt="<?php echo $item['tensanpham']; ?>" 
                                                     class="product-img rounded me-3">
                                                <div>
                                                    <h6 class="mb-0"><?php echo $item['tensanpham']; ?></h6>
                                                    <small class="text-muted">
                                                        <?php if(!empty($item['tenkichthuoc'])): ?>
                                                            Size: <?php echo $item['tenkichthuoc']; ?> |
                                                        <?php endif; ?>
                                                        <?php if(!empty($item['tenmau'])): ?>
                                                            Màu: <?php echo $item['tenmau']; ?>
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center"><?php echo $item['soluong']; ?></td>
                                        <td class="text-end"><?php echo number_format($item['gia'], 0, ',', '.'); ?>₫</td>
                                        <td class="text-end fw-bold"><?php echo number_format($item['thanh_tien'], 0, ',', '.'); ?>₫</td>
                                        <td>
                                            <!-- Thêm nút đánh giá sản phẩm nếu đơn hàng đã giao -->
                                            <?php if ($order['trangthai'] == 4): // Đơn hàng đã giao ?>
                                                <a href="danhgia.php?product_id=<?php echo $item['id_sanpham']; ?>&order_id=<?php echo $order_id; ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-star"></i> Đánh giá
                                                </a>
                                            <?php endif; ?>
                                        </td>
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
                            <span><?php echo number_format($order['tongtien'] - $order['phivanchuyen'], 0, ',', '.'); ?>₫</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Phí vận chuyển:</span>
                            <span><?php echo number_format($order['phivanchuyen'], 0, ',', '.'); ?>₫</span>
                        </div>
                        <div class="d-flex justify-content-between pt-2 border-top">
                            <span class="fw-bold">Tổng thanh toán:</span>
                            <span class="fw-bold text-danger fs-5"><?php echo number_format($order['tongtien'], 0, ',', '.'); ?>₫</span>
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
                            <div class="timeline-item <?php echo $order['trangthai'] >= 1 ? 'timeline-item-active' : 'timeline-item-inactive'; ?>">
                                <div class="timeline-point <?php echo $order['trangthai'] >= 1 ? 'timeline-point-active' : 'timeline-point-inactive'; ?>"></div>
                                <div class="mb-1">Chờ xác nhận</div>
                                <?php if($order['trangthai'] >= 1): ?>
                                    <div class="small text-muted"><?php echo date('d/m/Y H:i', strtotime($order['ngaytao'])); ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Đang xử lý -->
                            <div class="timeline-item <?php echo $order['trangthai'] >= 2 ? 'timeline-item-active' : 'timeline-item-inactive'; ?>">
                                <div class="timeline-point <?php echo $order['trangthai'] >= 2 ? 'timeline-point-active' : 'timeline-point-inactive'; ?>"></div>
                                <div class="mb-1">Đang xử lý</div>
                                <?php if($order['trangthai'] >= 2): ?>
                                    <div class="small text-muted">Đơn hàng đã được xác nhận</div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Đang giao hàng -->
                            <div class="timeline-item <?php echo $order['trangthai'] >= 3 ? 'timeline-item-active' : 'timeline-item-inactive'; ?>">
                                <div class="timeline-point <?php echo $order['trangthai'] >= 3 ? 'timeline-point-active' : 'timeline-point-inactive'; ?>"></div>
                                <div class="mb-1">Đang giao hàng</div>
                                <?php if($order['trangthai'] >= 3): ?>
                                    <div class="small text-muted">Đơn hàng đang được vận chuyển</div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Đã giao -->
                            <div class="timeline-item <?php echo $order['trangthai'] >= 4 ? 'timeline-item-active' : 'timeline-item-inactive'; ?>">
                                <div class="timeline-point <?php echo $order['trangthai'] >= 4 ? 'timeline-point-active' : 'timeline-point-inactive'; ?>"></div>
                                <div class="mb-1">Đã giao</div>
                                <?php if($order['trangthai'] >= 4): ?>
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
                    
                    <?php if($order['trangthai'] == 1): ?>
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
                                        <p>Bạn có chắc chắn muốn hủy đơn hàng #<?php echo $order_id; ?>?</p>
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
                    
                    <?php if($order['trangthai'] == 4): ?>
                        <a href="danhgia.php?product_id=<?php echo $item['id_sanpham']; ?>&order_id=<?php echo $order_id; ?>"  class="btn btn-success">
                            <i class="bi bi-star"></i> Đánh giá sản phẩm
                        </a>
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