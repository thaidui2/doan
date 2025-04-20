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

// Kiểm tra tham số
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: hoantra.php');
    exit;
}

$return_id = (int)$_GET['id'];

// Lấy thông tin chi tiết yêu cầu hoàn trả
$query = $conn->prepare("
    SELECT 
        hr.*,
        dh.ma_donhang, dh.trang_thai_don_hang, dh.ngay_dat,
        sp.tensanpham, sp.hinhanh, sp.gia,
        u.ten as ten_user, u.email, u.sodienthoai
    FROM hoantra hr
    JOIN donhang dh ON hr.id_donhang = dh.id
    JOIN sanpham sp ON hr.id_sanpham = sp.id
    JOIN users u ON hr.id_nguoidung = u.id
    WHERE hr.id_hoantra = ? AND hr.id_nguoidung = ?
");

$query->bind_param("ii", $return_id, $user_id);
$query->execute();
$result = $query->get_result();

// Kiểm tra xem yêu cầu hoàn trả có tồn tại không
if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "Không tìm thấy yêu cầu hoàn trả hoặc bạn không có quyền xem";
    header('Location: hoantra.php');
    exit;
}

$return_data = $result->fetch_assoc();

// Ensure trangthai is an integer to prevent type issues
$return_data['trangthai'] = (int)$return_data['trangthai'];

// Mảng trạng thái hoàn trả
$return_statuses = [
    1 => ['name' => 'Chờ xác nhận', 'badge' => 'warning', 'description' => 'Yêu cầu của bạn đang chờ xét duyệt'],
    2 => ['name' => 'Đã xác nhận', 'badge' => 'info', 'description' => 'Yêu cầu của bạn đã được xác nhận'],
    3 => ['name' => 'Đang xử lý', 'badge' => 'primary', 'description' => 'Yêu cầu của bạn đang được xử lý'],
    4 => ['name' => 'Hoàn thành', 'badge' => 'success', 'description' => 'Yêu cầu của bạn đã được hoàn thành'],
    5 => ['name' => 'Từ chối', 'badge' => 'danger', 'description' => 'Yêu cầu của bạn đã bị từ chối']
];

// Default status values if the status code is invalid
if (!isset($return_statuses[$return_data['trangthai']])) {
    $return_data['trangthai'] = 1; // Default to pending if status is invalid
}

// Xử lý hình ảnh sản phẩm
$product_image = !empty($return_data['hinhanh']) ? 
    (strpos($return_data['hinhanh'], 'uploads/') === 0 ? $return_data['hinhanh'] : 'uploads/products/' . $return_data['hinhanh']) : 
    'images/no-image.png';
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi tiết yêu cầu hoàn trả - Bug Shop</title>
    <link rel="stylesheet" href="node_modules/bootstrap/dist/css/bootstrap.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/index.css">
</head>
<body>
    <?php include('includes/header.php'); ?>
    
    <div class="container py-5">
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Trang chủ</a></li>
                <li class="breadcrumb-item"><a href="hoantra.php">Yêu cầu hoàn trả</a></li>
                <li class="breadcrumb-item active" aria-current="page">Chi tiết yêu cầu #HR<?php echo $return_id; ?></li>
            </ol>
        </nav>

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
                <!-- Trạng thái yêu cầu -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="card-title mb-0">Yêu cầu hoàn trả #HR<?php echo $return_id; ?></h4>
                            <span class="badge bg-<?php echo $return_statuses[$return_data['trangthai']]['badge']; ?> fs-6">
                                <?php echo $return_statuses[$return_data['trangthai']]['name']; ?>
                            </span>
                        </div>
                        <p class="text-muted mt-2 mb-0">
                            <i class="bi bi-info-circle me-1"></i>
                            <?php echo $return_statuses[$return_data['trangthai']]['description']; ?>
                        </p>
                        <p class="text-muted mt-2 mb-0">
                            Ngày tạo: <?php echo date('d/m/Y H:i', strtotime($return_data['ngaytao'])); ?>
                        </p>
                        <?php if (!empty($return_data['ngaycapnhat'])): ?>
                            <p class="text-muted mb-0">
                                Cập nhật gần nhất: <?php echo date('d/m/Y H:i', strtotime($return_data['ngaycapnhat'])); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Thông tin sản phẩm -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">Thông tin sản phẩm</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-2">
                                <img src="<?php echo $product_image; ?>" alt="<?php echo htmlspecialchars($return_data['tensanpham']); ?>" class="img-fluid rounded">
                            </div>
                            <div class="col-md-10">
                                <h5><?php echo htmlspecialchars($return_data['tensanpham']); ?></h5>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <p class="mb-1"><strong>Đơn hàng:</strong> <a href="chitiet-donhang.php?id=<?php echo $return_data['id_donhang']; ?>">#<?php echo $return_data['ma_donhang']; ?></a></p>
                                        <p class="mb-1"><strong>Ngày đặt hàng:</strong> <?php echo date('d/m/Y', strtotime($return_data['ngay_dat'])); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="mb-1"><strong>Giá sản phẩm:</strong> <?php echo number_format($return_data['gia'], 0, ',', '.'); ?> đ</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Chi tiết yêu cầu hoàn trả -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">Chi tiết yêu cầu</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <h6>Lý do hoàn trả</h6>
                            <p class="mb-0"><?php echo htmlspecialchars($return_data['lydo']); ?></p>
                        </div>
                        <div class="mb-0">
                            <h6>Mô tả chi tiết</h6>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($return_data['mota_chitiet'])); ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Phản hồi từ cửa hàng -->
                <?php if(!empty($return_data['phan_hoi']) || $return_data['trangthai'] == 5): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">Phản hồi từ cửa hàng</h5>
                    </div>
                    <div class="card-body">
                        <?php if(!empty($return_data['phan_hoi'])): ?>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($return_data['phan_hoi'])); ?></p>
                        <?php elseif($return_data['trangthai'] == 5): ?>
                            <p class="mb-0 text-danger">Yêu cầu hoàn trả của bạn đã bị từ chối.</p>
                        <?php endif; ?>
                        
                        <?php if($return_data['trangthai'] == 5): ?>
                            <div class="mt-4">
                                <p>Bạn có thể tạo yêu cầu hoàn trả mới nếu cần:</p>
                                <a href="donhoantra.php?order_id=<?php echo $return_data['id_donhang']; ?>&product_id=<?php echo $return_data['id_sanpham']; ?>" class="btn btn-primary">
                                    <i class="bi bi-plus-circle"></i> Tạo yêu cầu hoàn trả mới
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Các lưu ý -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">Lưu ý</h5>
                    </div>
                    <div class="card-body">
                        <ul class="mb-0">
                            <li>Vui lòng giữ nguyên sản phẩm và bao bì cho đến khi yêu cầu hoàn trả được giải quyết.</li>
                            <li>Chúng tôi sẽ liên hệ với bạn qua email hoặc số điện thoại trong vòng 24-48 giờ.</li>
                            <li>Nếu yêu cầu được chấp nhận, chúng tôi sẽ hướng dẫn cách gửi sản phẩm về cho chúng tôi.</li>
                            <li>Sau khi nhận được sản phẩm hoàn trả, chúng tôi sẽ hoàn tiền cho bạn trong vòng 7-14 ngày làm việc.</li>
                        </ul>
                    </div>
                </div>

                <div class="text-center">
                    <a href="hoantra.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Quay lại danh sách
                    </a>
                    
                    <?php if($return_data['trangthai'] == 1): ?>
                    <a href="huy-hoantra.php?id=<?php echo $return_id; ?>" class="btn btn-danger ms-2" onclick="return confirm('Bạn có chắc chắn muốn hủy yêu cầu hoàn trả này không?');">
                        <i class="bi bi-x-circle"></i> Hủy yêu cầu
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php include('includes/footer.php'); ?>
    
    <script src="node_modules/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
