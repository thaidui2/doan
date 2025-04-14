<?php
// Đặt tiêu đề trang
$page_title = 'Chi tiết yêu cầu hoàn trả';

// Include header (sẽ kiểm tra đăng nhập)
include('includes/header.php');

// Include kết nối database
include('../config/config.php');

// Lấy ID yêu cầu hoàn trả
$return_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($return_id <= 0) {
    echo '<script>alert("ID yêu cầu hoàn trả không hợp lệ!"); window.location.href = "returns.php";</script>';
    exit();
}

// Lấy thông tin chi tiết yêu cầu hoàn trả
$stmt = $conn->prepare("
    SELECT hr.*, 
           u.tenuser, u.email,
           sp.tensanpham, sp.hinhanh, sp.gia, 
           dh.id_donhang, dh.id_donhang AS ma_donhang,
           dhct.soluong, dhct.thanh_tien
    FROM hoantra hr
    JOIN users u ON hr.id_nguoidung = u.id_user
    JOIN sanpham sp ON hr.id_sanpham = sp.id_sanpham
    JOIN donhang dh ON hr.id_donhang = dh.id_donhang
    JOIN donhang_chitiet dhct ON dh.id_donhang = dhct.id_donhang AND sp.id_sanpham = dhct.id_sanpham
    WHERE hr.id_hoantra = ?
");

$stmt->bind_param("i", $return_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo '<script>alert("Không tìm thấy thông tin yêu cầu hoàn trả!"); window.location.href = "returns.php";</script>';
    exit();
}

$return_info = $result->fetch_assoc();

// Mảng trạng thái hoàn trả
$return_statuses = [
    1 => ['name' => 'Chờ xác nhận', 'badge' => 'warning'],
    2 => ['name' => 'Đã xác nhận', 'badge' => 'info'],
    3 => ['name' => 'Đang xử lý', 'badge' => 'primary'],
    4 => ['name' => 'Hoàn thành', 'badge' => 'success'],
    5 => ['name' => 'Từ chối', 'badge' => 'danger']
];

// Xử lý form cập nhật trạng thái
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = (int)$_POST['status'];
    $admin_note = trim($_POST['admin_note']);
    
    // Validate
    if (!array_key_exists($new_status, $return_statuses)) {
        $error_message = "Trạng thái không hợp lệ!";
    } else {
        // Cập nhật trạng thái
        $update_stmt = $conn->prepare("
            UPDATE hoantra 
            SET trangthai = ?, phan_hoi = ?
            WHERE id_hoantra = ?
        ");
        
        $update_stmt->bind_param("isi", $new_status, $admin_note, $return_id);
        
        if ($update_stmt->execute()) {
            // Nếu cập nhật thành công
            $success_message = "Cập nhật trạng thái thành công!";
            
            // Cập nhật trạng thái đơn hàng nếu hoàn trả được chấp nhận
            if ($new_status == 4) { // Hoàn thành
                // Đổi trạng thái đơn hàng sang "Hoàn trả" (trạng thái 6)
                $order_update = $conn->prepare("
                    UPDATE donhang 
                    SET trangthai = 6 
                    WHERE id_donhang = ?
                ");
                $order_update->bind_param("i", $return_info['id_donhang']);
                $order_update->execute();
            }
            
            // Thêm đoạn này: Cập nhật trạng thái sản phẩm theo trạng thái yêu cầu hoàn trả
            $product_status = 1; // Mặc định: hiển thị bình thường
            
            if ($new_status == 2 || $new_status == 3) {
                // Nếu đã xác nhận hoặc đang xử lý hoàn trả, đánh dấu sản phẩm "đang hoàn trả"
                $product_status = 3; // Trạng thái: đang hoàn trả
            }
            
            // Cập nhật trạng thái sản phẩm
            $product_update = $conn->prepare("
                UPDATE sanpham 
                SET trangthai = ? 
                WHERE id_sanpham = ?
            ");
            $product_update->bind_param("ii", $product_status, $return_info['id_sanpham']);
            $product_update->execute();
            
            // Refresh thông tin
            $stmt->execute();
            $result = $stmt->get_result();
            $return_info = $result->fetch_assoc();
        } else {
            $error_message = "Có lỗi xảy ra khi cập nhật trạng thái!";
        }
    }
}
?>

<!-- Include sidebar -->
<?php include('includes/sidebar.php'); ?>

<!-- Main content -->
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Trang chủ</a></li>
            <li class="breadcrumb-item"><a href="returns.php">Quản lý hoàn trả</a></li>
            <li class="breadcrumb-item active" aria-current="page">Chi tiết yêu cầu #<?php echo $return_id; ?></li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Chi tiết yêu cầu hoàn trả #<?php echo $return_id; ?></h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="returns.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Quay lại danh sách
            </a>
        </div>
    </div>

    <?php if ($success_message): ?>
    <div class="alert alert-success" role="alert">
        <?php echo $success_message; ?>
    </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
    <div class="alert alert-danger" role="alert">
        <?php echo $error_message; ?>
    </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-8">
            <!-- Thông tin yêu cầu hoàn trả -->
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Thông tin yêu cầu</h5>
                    <span class="badge bg-<?php echo $return_statuses[$return_info['trangthai']]['badge']; ?>">
                        <?php echo $return_statuses[$return_info['trangthai']]['name']; ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p><strong>Yêu cầu #:</strong> <?php echo $return_id; ?></p>
                            <p><strong>Ngày tạo:</strong> <?php echo date('d/m/Y H:i', strtotime($return_info['ngaytao'])); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Đơn hàng:</strong> <a href="order-detail.php?id=<?php echo $return_info['id_donhang']; ?>">#<?php echo $return_info['id_donhang']; ?></a></p>
                            <p><strong>Trạng thái:</strong> 
                                <span class="badge bg-<?php echo $return_statuses[$return_info['trangthai']]['badge']; ?>">
                                    <?php echo $return_statuses[$return_info['trangthai']]['name']; ?>
                                </span>
                            </p>
                        </div>
                    </div>
                    
                    <h6 class="fw-bold mb-3">Lý do hoàn trả:</h6>
                    <div class="mb-4 p-3 bg-light rounded">
                        <?php echo $return_info['lydo']; ?>
                    </div>
                    
                    <h6 class="fw-bold mb-3">Mô tả chi tiết:</h6>
                    <div class="mb-4 p-3 bg-light rounded">
                        <?php echo nl2br(htmlspecialchars($return_info['mota_chitiet'])); ?>
                    </div>
                    
                    <?php if (!empty($return_info['phan_hoi'])): ?>
                    <h6 class="fw-bold mb-3 text-primary">Phản hồi của Admin:</h6>
                    <div class="mb-4 p-3 bg-light rounded border-start border-primary border-4">
                        <?php echo nl2br(htmlspecialchars($return_info['phan_hoi'])); ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Thông tin sản phẩm -->
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Thông tin sản phẩm</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-2">
                            <img src="<?php echo !empty($return_info['hinhanh']) ? '../uploads/products/'.$return_info['hinhanh'] : '../assets/img/default-product.jpg'; ?>" 
                                class="img-fluid rounded" alt="<?php echo $return_info['tensanpham']; ?>">
                        </div>
                        <div class="col-md-10">
                            <h5><?php echo $return_info['tensanpham']; ?></h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Mã sản phẩm:</strong> #<?php echo $return_info['id_sanpham']; ?></p>
                                    <p><strong>Đơn giá:</strong> <?php echo number_format($return_info['gia'], 0, ',', '.'); ?>đ</p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Số lượng:</strong> <?php echo $return_info['soluong']; ?></p>
                                    <p><strong>Thành tiền:</strong> <?php echo number_format($return_info['thanh_tien'], 0, ',', '.'); ?>đ</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <!-- Thông tin khách hàng -->
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Thông tin khách hàng</h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <img src="../assets/img/default-avatar.png" 
                            class="rounded-circle" alt="Avatar" style="width: 80px; height: 80px; object-fit: cover;">
                        <h5 class="mt-3"><?php echo $return_info['tenuser']; ?></h5>
                        <p class="text-muted"><?php echo $return_info['email']; ?></p>
                    </div>
                    <div class="d-grid">
                        <a href="customer-detail.php?id=<?php echo $return_info['id_nguoidung']; ?>" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-person"></i> Xem thông tin chi tiết
                        </a>
                    </div>
                </div>
            </div>

            <!-- Form cập nhật trạng thái -->
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Cập nhật trạng thái</h5>
                </div>
                <div class="card-body">
                    <form action="" method="post">
                        <div class="mb-3">
                            <label for="status" class="form-label">Trạng thái</label>
                            <select class="form-select" id="status" name="status" required>
                                <?php foreach ($return_statuses as $id => $status): ?>
                                <option value="<?php echo $id; ?>" <?php echo $return_info['trangthai'] == $id ? 'selected' : ''; ?>>
                                    <?php echo $status['name']; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="admin_note" class="form-label">Phản hồi</label>
                            <textarea class="form-control" id="admin_note" name="admin_note" rows="5"><?php echo $return_info['phan_hoi']; ?></textarea>
                            <div class="form-text">Phản hồi sẽ hiển thị cho khách hàng.</div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" name="update_status" class="btn btn-primary">
                                <i class="bi bi-save"></i> Cập nhật
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Lịch sử cập nhật -->
            <div class="card mt-4 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Hướng dẫn cập nhật</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <span class="badge bg-warning">Chờ xác nhận</span>
                            <small class="d-block text-muted">Yêu cầu hoàn trả mới, cần xác nhận</small>
                        </li>
                        <li class="mb-2">
                            <span class="badge bg-info">Đã xác nhận</span>
                            <small class="d-block text-muted">Đã xác nhận yêu cầu và đang chờ khách gửi hàng trả</small>
                            <small class="d-block text-danger">* Sản phẩm sẽ được đánh dấu "Đang hoàn trả"</small>
                        </li>
                        <li class="mb-2">
                            <span class="badge bg-primary">Đang xử lý</span>
                            <small class="d-block text-muted">Đã nhận được hàng hoàn trả và đang xử lý</small>
                            <small class="d-block text-danger">* Sản phẩm sẽ được đánh dấu "Đang hoàn trả"</small>
                        </li>
                        <li class="mb-2">
                            <span class="badge bg-success">Hoàn thành</span>
                            <small class="d-block text-muted">Đã hoàn tiền hoặc gửi sản phẩm mới cho khách</small>
                            <small class="d-block text-success">* Sản phẩm sẽ được chuyển về trạng thái hiển thị bình thường</small>
                        </li>
                        <li>
                            <span class="badge bg-danger">Từ chối</span>
                            <small class="d-block text-muted">Yêu cầu hoàn trả không hợp lệ và bị từ chối</small>
                            <small class="d-block text-success">* Sản phẩm sẽ được chuyển về trạng thái hiển thị bình thường</small>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include('includes/footer.php'); ?>