<?php
session_start();
include('config/config.php');

// Kiểm tra đăng nhập
if (!isset($_SESSION['user'])) {
    header('Location: dangnhap.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Lấy thông tin người dùng
$user_id = $_SESSION['user']['id'];

// Lấy danh sách yêu cầu hoàn trả
$query = $conn->prepare("
    SELECT 
        hr.*, 
        dh.ma_donhang, dh.trang_thai_don_hang,
        sp.tensanpham, sp.hinhanh
    FROM hoantra hr
    JOIN donhang dh ON hr.id_donhang = dh.id
    JOIN sanpham sp ON hr.id_sanpham = sp.id
    WHERE hr.id_nguoidung = ?
    ORDER BY hr.ngaytao DESC
");

$query->bind_param("i", $user_id);
$query->execute();
$result = $query->get_result();

// Mảng trạng thái hoàn trả
$return_statuses = [
    1 => ['name' => 'Chờ xác nhận', 'badge' => 'warning'],
    2 => ['name' => 'Đã xác nhận', 'badge' => 'info'],
    3 => ['name' => 'Đang xử lý', 'badge' => 'primary'],
    4 => ['name' => 'Hoàn thành', 'badge' => 'success'],
    5 => ['name' => 'Từ chối', 'badge' => 'danger']
];

// Default status for invalid values
$default_status = ['name' => 'Không xác định', 'badge' => 'secondary'];
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yêu cầu hoàn trả - Bug Shop</title>
    <link rel="stylesheet" href="node_modules/bootstrap/dist/css/bootstrap.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/index.css">
</head>
<body>
    <?php include('includes/header.php'); ?>
    
    <div class="container py-5">
        <div class="row">
            <div class="col-lg-3">
                <!-- Menu tài khoản người dùng -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Tài khoản của tôi</h5>
                        <div class="list-group list-group-flush">
                            <a href="taikhoan.php" class="list-group-item list-group-item-action">
                                <i class="bi bi-person me-2"></i> Thông tin tài khoản
                            </a>
                            <a href="donhang.php" class="list-group-item list-group-item-action">
                                <i class="bi bi-receipt me-2"></i> Đơn hàng của tôi
                            </a>
                            <a href="hoantra.php" class="list-group-item list-group-item-action active">
                                <i class="bi bi-arrow-return-left me-2"></i> Yêu cầu hoàn trả
                            </a>
                            <a href="wishlist.php" class="list-group-item list-group-item-action">
                                <i class="bi bi-heart me-2"></i> Sản phẩm yêu thích
                            </a>
                            <a href="logout.php" class="list-group-item list-group-item-action text-danger">
                                <i class="bi bi-box-arrow-right me-2"></i> Đăng xuất
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-9">
                <div class="card shadow-sm">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h4 class="card-title mb-0">Yêu cầu hoàn trả</h4>
                    </div>
                    
                    <div class="card-body p-0">
                        <?php if ($result->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th scope="col">Mã yêu cầu</th>
                                            <th scope="col">Sản phẩm</th>
                                            <th scope="col">Đơn hàng</th>
                                            <th scope="col">Ngày yêu cầu</th>
                                            <th scope="col">Trạng thái</th>
                                            <th scope="col">Thao tác</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = $result->fetch_assoc()): ?>
                                            <tr>
                                                <td>#HR<?php echo $row['id_hoantra']; ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php 
                                                        $product_image = !empty($row['hinhanh']) ? 
                                                            (strpos($row['hinhanh'], 'uploads/') === 0 ? $row['hinhanh'] : 'uploads/products/' . $row['hinhanh']) : 
                                                            'images/no-image.png';
                                                        ?>
                                                        <img src="<?php echo $product_image; ?>" alt="<?php echo htmlspecialchars($row['tensanpham']); ?>" class="img-thumbnail me-2" style="width: 40px; height: 40px; object-fit: cover;">
                                                        <div class="small"><?php echo htmlspecialchars($row['tensanpham']); ?></div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <a href="chitiet-donhang.php?id=<?php echo $row['id_donhang']; ?>" class="text-decoration-none">
                                                        #<?php echo $row['ma_donhang']; ?>
                                                    </a>
                                                </td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($row['ngaytao'])); ?></td>
                                                <td>
                                                    <?php 
                                                    // Ensure trangthai is an integer and validate it exists in our status array
                                                    $status_id = isset($row['trangthai']) ? (int)$row['trangthai'] : 0;
                                                    $status = isset($return_statuses[$status_id]) ? $return_statuses[$status_id] : $default_status;
                                                    ?>
                                                    <span class="badge bg-<?php echo $status['badge']; ?>">
                                                        <?php echo $status['name']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a href="chitiet-hoantra.php?id=<?php echo $row['id_hoantra']; ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-eye"></i> Chi tiết
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="p-5 text-center">
                                <div class="mb-4">
                                    <i class="bi bi-arrow-return-left text-muted display-4"></i>
                                </div>
                                <h5>Bạn chưa có yêu cầu hoàn trả nào</h5>
                                <p class="text-muted">Các yêu cầu hoàn trả sản phẩm sẽ được hiển thị ở đây</p>
                                <div class="mt-3">
                                    <a href="donhang.php" class="btn btn-outline-primary">Xem đơn hàng của tôi</a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include('includes/footer.php'); ?>
    
    <script src="node_modules/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>