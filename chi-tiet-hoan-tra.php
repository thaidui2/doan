<?php
session_start();
include('config/config.php');

// Kiểm tra đăng nhập
if (!isset($_SESSION['user']) || $_SESSION['user']['logged_in'] !== true) {
    header('Location: dangnhap.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

$user_id = $_SESSION['user']['id'];
$return_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($return_id <= 0) {
    header('Location: donhoantra.php');
    exit();
}

// Lấy thông tin chi tiết yêu cầu hoàn trả
$query = "SELECT hr.*, 
          dh.id_donhang, 
          sp.tensanpham, sp.hinhanh, sp.gia, 
          dhct.soluong, dhct.thanh_tien
          FROM hoantra hr
          JOIN donhang dh ON hr.id_donhang = dh.id_donhang
          JOIN sanpham sp ON hr.id_sanpham = sp.id_sanpham
          JOIN donhang_chitiet dhct ON dh.id_donhang = dhct.id_donhang AND sp.id_sanpham = dhct.id_sanpham
          WHERE hr.id_hoantra = ? AND hr.id_nguoidung = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $return_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: donhoantra.php');
    exit();
}

$return_info = $result->fetch_assoc();
$page_title = "Chi tiết yêu cầu hoàn trả #" . $return_id;

// Mảng trạng thái hoàn trả
$return_statuses = [
    1 => ['name' => 'Chờ xác nhận', 'class' => 'warning text-dark', 'icon' => 'bi-clock'],
    2 => ['name' => 'Đã xác nhận', 'class' => 'info', 'icon' => 'bi-check-circle'],
    3 => ['name' => 'Đang xử lý', 'class' => 'primary', 'icon' => 'bi-gear'],
    4 => ['name' => 'Hoàn thành', 'class' => 'success', 'icon' => 'bi-check-all'],
    5 => ['name' => 'Từ chối', 'class' => 'danger', 'icon' => 'bi-x-circle']
];
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Bug Shop</title>
    <?php include('includes/head.php'); ?>
</head>
<body>
    <?php include('includes/header.php'); ?>
    
    <div class="container py-5">
        <div class="row">
            <!-- Menu tài khoản -->
            <div class="col-lg-3 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-4">
                            <div class="flex-shrink-0">
                                <img src="assets/img/default-avatar.png" class="rounded-circle" alt="Avatar" width="60">
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h5 class="mb-0"><?php echo $_SESSION['user']['tenuser']; ?></h5>
                                <p class="text-muted mb-0 small">
                                    <i class="bi bi-envelope-fill"></i> <?php echo $_SESSION['user']['emailuser'] ?? $_SESSION['user']['username'] ?? ''; ?>
                                </p>
                            </div>
                        </div>
                        
                        <div class="list-group list-group-flush">
                            <a href="taikhoan.php" class="list-group-item list-group-item-action">
                                <i class="bi bi-person me-2"></i> Thông tin tài khoản
                            </a>
                            <a href="donhang.php" class="list-group-item list-group-item-action">
                                <i class="bi bi-receipt me-2"></i> Đơn hàng của tôi
                            </a>
                            <a href="donhoantra.php" class="list-group-item list-group-item-action active">
                                <i class="bi bi-arrow-return-left me-2"></i> Yêu cầu hoàn trả
                            </a>
                            <a href="wishlist.php" class="list-group-item list-group-item-action">
                                <i class="bi bi-heart me-2"></i> Sản phẩm yêu thích
                            </a>
                            <a href="doimatkhau.php" class="list-group-item list-group-item-action">
                                <i class="bi bi-key me-2"></i> Đổi mật khẩu
                            </a>
                            <a href="logout.php" class="list-group-item list-group-item-action text-danger">
                                <i class="bi bi-box-arrow-right me-2"></i> Đăng xuất
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Chi tiết yêu cầu hoàn trả -->
            <div class="col-lg-9">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="h4 mb-0">Chi tiết yêu cầu hoàn trả #<?php echo $return_id; ?></h2>
                    <a href="donhoantra.php" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-arrow-left"></i> Quay lại danh sách
                    </a>
                </div>
                
                <!-- Trạng thái yêu cầu -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-0">Trạng thái yêu cầu</h5>
                                <p class="text-muted mb-0 small">Cập nhật mới nhất: <?php echo date('d/m/Y H:i', strtotime($return_info['ngaycapnhat'])); ?></p>
                            </div>
                            <div>
                                <span class="badge bg-<?php echo $return_statuses[$return_info['trangthai']]['class']; ?> p-2">
                                    <i class="bi <?php echo $return_statuses[$return_info['trangthai']]['icon']; ?>"></i> 
                                    <?php echo $return_statuses[$return_info['trangthai']]['name']; ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="progress mt-4" style="height: 5px;">
                            <?php
                            $progress = 0;
                            switch ($return_info['trangthai']) {
                                case 1: $progress = 20; break;
                                case 2: $progress = 40; break;
                                case 3: $progress = 60; break;
                                case 4: $progress = 100; break;
                                case 5: $progress = 100; break; // Từ chối cũng là kết thúc quy trình
                            }
                            
                            $progress_class = 'bg-primary';
                            if ($return_info['trangthai'] == 4) {
                                $progress_class = 'bg-success';
                            } elseif ($return_info['trangthai'] == 5) {
                                $progress_class = 'bg-danger';
                            }
                            ?>
                            <div class="progress-bar <?php echo $progress_class; ?>" role="progressbar" 
                                style="width: <?php echo $progress; ?>%" 
                                aria-valuenow="<?php echo $progress; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                        
                        <div class="d-flex justify-content-between mt-2">
                            <div class="text-center" style="width: 20%;">
                                <div class="<?php echo $progress >= 20 ? 'text-primary' : 'text-muted'; ?> small">
                                    <i class="bi bi-1-circle<?php echo $progress >= 20 ? '-fill' : ''; ?>"></i>
                                </div>
                                <div class="small mt-1">Yêu cầu mới</div>
                            </div>
                            <div class="text-center" style="width: 20%;">
                                <div class="<?php echo $progress >= 40 ? 'text-primary' : 'text-muted'; ?> small">
                                    <i class="bi bi-2-circle<?php echo $progress >= 40 ? '-fill' : ''; ?>"></i>
                                </div>
                                <div class="small mt-1">Xác nhận</div>
                            </div>
                            <div class="text-center" style="width: 20%;">
                                <div class="<?php echo $progress >= 60 ? 'text-primary' : 'text-muted'; ?> small">
                                    <i class="bi bi-3-circle<?php echo $progress >= 60 ? '-fill' : ''; ?>"></i>
                                </div>
                                <div class="small mt-1">Đang xử lý</div>
                            </div>
                            <div class="text-center" style="width: 20%;">
                                <div class="<?php echo $progress >= 100 ? ($return_info['trangthai'] == 4 ? 'text-success' : 'text-danger') : 'text-muted'; ?> small">
                                    <i class="bi bi-4-circle<?php echo $progress >= 100 ? '-fill' : ''; ?>"></i>
                                </div>
                                <div class="small mt-1">Hoàn thành</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-lg-7">
                        <!-- Thông tin yêu cầu -->
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Thông tin yêu cầu</h5>
                            </div>
                            <div class="card-body">
                                <dl class="row mb-0">
                                    <dt class="col-sm-4">Mã yêu cầu:</dt>
                                    <dd class="col-sm-8">#<?php echo $return_info['id_hoantra']; ?></dd>
                                    
                                    <dt class="col-sm-4">Đơn hàng:</dt>
                                    <dd class="col-sm-8">
                                        <a href="chitietdonhang.php?id=<?php echo $return_info['id_donhang']; ?>">
                                            #<?php echo $return_info['id_donhang']; ?>
                                        </a>
                                    </dd>
                                    
                                    <dt class="col-sm-4">Ngày yêu cầu:</dt>
                                    <dd class="col-sm-8"><?php echo date('d/m/Y H:i', strtotime($return_info['ngaytao'])); ?></dd>
                                    
                                    <dt class="col-sm-4">Lý do hoàn trả:</dt>
                                    <dd class="col-sm-8"><?php echo $return_info['lydo']; ?></dd>
                                    
                                    <dt class="col-sm-4">Mô tả chi tiết:</dt>
                                    <dd class="col-sm-8">
                                        <div class="p-3 bg-light rounded small">
                                            <?php echo nl2br(htmlspecialchars($return_info['mota_chitiet'])); ?>
                                        </div>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                        
                        <!-- Phản hồi từ shop -->
                        <?php if (!empty($return_info['phan_hoi']) && ($return_info['trangthai'] == 2 || $return_info['trangthai'] == 3 || $return_info['trangthai'] == 4 || $return_info['trangthai'] == 5)): ?>
                        <div class="card border-0 shadow-sm mb-4 border-start border-4 border-primary">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Phản hồi từ shop</h5>
                            </div>
                            <div class="card-body">
                                <div class="p-3 bg-light rounded">
                                    <?php echo nl2br(htmlspecialchars($return_info['phan_hoi'])); ?>
                                </div>
                                
                                <?php if ($return_info['trangthai'] == 2): ?>
                                <div class="alert alert-info mt-3 mb-0">
                                    <i class="bi bi-info-circle-fill me-2"></i>
                                    Vui lòng đóng gói sản phẩm và gửi trả theo hướng dẫn của shop.
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-lg-5">
                        <!-- Thông tin sản phẩm -->
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Thông tin sản phẩm</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex">
                                    <img src="<?php echo !empty($return_info['hinhanh']) ? 'uploads/products/'.$return_info['hinhanh'] : 'assets/img/default-product.jpg'; ?>" 
                                         alt="<?php echo $return_info['tensanpham']; ?>" 
                                         class="img-thumbnail me-3" style="width: 80px; height: 80px; object-fit: cover;">
                                    <div>
                                        <h5 class="mb-1"><?php echo $return_info['tensanpham']; ?></h5>
                                        <p class="mb-1 text-muted">Số lượng: <?php echo $return_info['soluong']; ?></p>
                                        <p class="mb-0">
                                            <span class="fw-bold text-danger"><?php echo number_format($return_info['thanh_tien'], 0, ',', '.'); ?>₫</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Hướng dẫn quy trình hoàn trả -->
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Quy trình hoàn trả</h5>
                            </div>
                            <div class="card-body">
                                <ol class="ps-3 mb-0">
                                    <li class="mb-2">
                                        <span class="fw-bold">Gửi yêu cầu hoàn trả</span>
                                        <p class="mb-0 small text-muted">Bạn tạo yêu cầu hoàn trả với lý do cụ thể</p>
                                    </li>
                                    <li class="mb-2">
                                        <span class="fw-bold">Chờ xác nhận từ shop</span>
                                        <p class="mb-0 small text-muted">Shop sẽ xem xét và phản hồi yêu cầu của bạn</p>
                                    </li>
                                    <li class="mb-2">
                                        <span class="fw-bold">Gửi trả sản phẩm</span>
                                        <p class="mb-0 small text-muted">Đóng gói và gửi trả sản phẩm theo hướng dẫn</p>
                                    </li>
                                    <li class="mb-2">
                                        <span class="fw-bold">Shop kiểm tra sản phẩm</span>
                                        <p class="mb-0 small text-muted">Shop kiểm tra tình trạng sản phẩm hoàn trả</p>
                                    </li>
                                    <li>
                                        <span class="fw-bold">Hoàn tiền hoặc đổi sản phẩm</span>
                                        <p class="mb-0 small text-muted">Shop sẽ hoàn tiền hoặc gửi sản phẩm mới cho bạn</p>
                                    </li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include('includes/footer.php'); ?>
</body>
</html>