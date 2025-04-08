<?php
session_start();
include('config/config.php');

// Kiểm tra đăng nhập
if (!isset($_SESSION['user']) || $_SESSION['user']['logged_in'] !== true) {
    header('Location: dangnhap.php?redirect=donhang.php');
    exit();
}

$user_id = $_SESSION['user']['id'];

// Lấy danh sách đơn hàng của người dùng
$stmt = $conn->prepare("
    SELECT dh.*, 
           COUNT(dhct.id_chitiet) as total_items
    FROM donhang dh
    LEFT JOIN donhang_chitiet dhct ON dh.id_donhang = dhct.id_donhang
    WHERE dh.id_nguoidung = ?
    GROUP BY dh.id_donhang
    ORDER BY dh.ngaytao DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$orders = [];

while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đơn hàng của tôi - Bug Shop</title>
    <link rel="stylesheet" href="node_modules/bootstrap/dist/css/bootstrap.css">
    <script src="node_modules/bootstrap/dist/js/bootstrap.bundle.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .order-status-badge {
            min-width: 110px;
        }
    </style>
</head>
<body>
    <?php include('includes/header.php'); ?>
    
    <div class="container py-5">
        <h1 class="mb-4">Đơn hàng của tôi</h1>
        
        <!-- Hiển thị thông báo lỗi hoặc thành công -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success" role="alert">
                <?php 
                    echo $_SESSION['success_message']; 
                    unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger" role="alert">
                <?php 
                    echo $_SESSION['error_message']; 
                    unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>
        
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Trang chủ</a></li>
                <li class="breadcrumb-item"><a href="taikhoan.php">Tài khoản</a></li>
                <li class="breadcrumb-item active" aria-current="page">Đơn hàng của tôi</li>
            </ol>
        </nav>
        
        <?php if (empty($orders)): ?>
            <div class="text-center py-5">
                <div class="mb-4">
                    <i class="bi bi-bag-x" style="font-size: 3rem; color: #ccc;"></i>
                </div>
                <h3>Bạn chưa có đơn hàng nào</h3>
                <p class="text-muted">Hãy khám phá các sản phẩm của chúng tôi và đặt hàng ngay</p>
                <a href="sanpham.php" class="btn btn-primary mt-3">Mua sắm ngay</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Mã đơn hàng</th>
                            <th>Ngày đặt</th>
                            <th>Tổng tiền</th>
                            <th>Sản phẩm</th>
                            <th>Trạng thái</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td>#<?php echo $order['id_donhang']; ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($order['ngaytao'])); ?></td>
                                <td class="fw-bold"><?php echo number_format($order['tongtien'], 0, ',', '.'); ?>₫</td>
                                <td><?php echo $order['total_items']; ?> sản phẩm</td>
                                <td>
                                    <?php
                                    switch ($order['trangthai']) {
                                        case 1:
                                            echo '<span class="badge bg-info order-status-badge">Chờ xác nhận</span>';
                                            break;
                                        case 2:
                                            echo '<span class="badge bg-primary order-status-badge">Đang xử lý</span>';
                                            break;
                                        case 3:
                                            echo '<span class="badge bg-warning text-dark order-status-badge">Đang giao hàng</span>';
                                            break;
                                        case 4:
                                            echo '<span class="badge bg-success order-status-badge">Đã giao</span>';
                                            break;
                                        case 5:
                                            echo '<span class="badge bg-danger order-status-badge">Đã hủy</span>';
                                            break;
                                        case 6:
                                            echo '<span class="badge bg-secondary order-status-badge">Hoàn trả</span>';
                                            break;
                                        default:
                                            echo '<span class="badge bg-dark order-status-badge">Không xác định</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <a href="chitietdonhang.php?id=<?php echo $order['id_donhang']; ?>" class="btn btn-sm btn-outline-primary">
                                        Chi tiết
                                    </a>
                                    
                                    <?php if ($order['trangthai'] == 1): ?>
                                        <button type="button" class="btn btn-sm btn-outline-danger ms-1" 
                                                data-bs-toggle="modal" data-bs-target="#cancelModal<?php echo $order['id_donhang']; ?>">
                                            Hủy
                                        </button>
                                        
                                        <!-- Modal xác nhận hủy đơn -->
                                        <div class="modal fade" id="cancelModal<?php echo $order['id_donhang']; ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Xác nhận hủy đơn hàng</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <p>Bạn có chắc chắn muốn hủy đơn hàng #<?php echo $order['id_donhang']; ?>?</p>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
                                                        <a href="huydonhang.php?id=<?php echo $order['id_donhang']; ?>" class="btn btn-danger">
                                                            Xác nhận hủy
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <?php include('includes/footer.php'); ?>
</body>
</html>