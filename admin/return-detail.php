<?php
// Start output buffering to prevent "headers already sent" errors
ob_start();

// Set page title
$page_title = 'Chi tiết yêu cầu hoàn trả';

// Include header (which includes authentication checks)
include('includes/header.php');
include('../config/config.php');

// Get return request ID
$return_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($return_id <= 0) {
    $_SESSION['error_message'] = 'ID yêu cầu hoàn trả không hợp lệ';
    header('Location: returns.php');
    exit;
}

// Fetch return request details with joins to related tables
$stmt = $conn->prepare("
    SELECT hr.*, 
        dh.ma_donhang, dh.trang_thai_don_hang, dh.ngay_dat,
        sp.tensanpham, sp.hinhanh, sp.gia,
        u.ten as ten_user, u.email, u.sodienthoai
    FROM hoantra hr
    JOIN donhang dh ON hr.id_donhang = dh.id
    JOIN sanpham sp ON hr.id_sanpham = sp.id
    JOIN users u ON hr.id_nguoidung = u.id
    WHERE hr.id_hoantra = ?
");

$stmt->bind_param("i", $return_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = 'Không tìm thấy yêu cầu hoàn trả với ID này';
    header('Location: returns.php');
    exit;
}

$return_data = $result->fetch_assoc();

// Handle status update
if (isset($_POST['update_status'])) {
    $new_status = (int)$_POST['new_status'];
    $admin_note = trim($_POST['admin_note']);
    
    // Update return request status and admin note
    $update_stmt = $conn->prepare("
        UPDATE hoantra 
        SET trangthai = ?, 
            phan_hoi = ?, 
            ngaycapnhat = NOW()
        WHERE id_hoantra = ?
    ");
    
    $update_stmt->bind_param("isi", $new_status, $admin_note, $return_id);
    
    if ($update_stmt->execute()) {
        // Update order history
        $history_query = $conn->prepare("
            INSERT INTO donhang_lichsu (
                id_donhang, hanh_dong, nguoi_thuchien, ghi_chu
            ) VALUES (?, ?, ?, ?)
        ");
        
        $action = "Cập nhật yêu cầu hoàn trả";
        $admin_name = $_SESSION['admin_name'] ?? 'Quản trị viên';
        
        // Map status to text
        $status_texts = [
            1 => 'Chờ xác nhận',
            2 => 'Đã xác nhận',
            3 => 'Đang xử lý',
            4 => 'Hoàn thành',
            5 => 'Từ chối'
        ];
        
        $status_text = $status_texts[$new_status] ?? 'Không xác định';
        $note = "Cập nhật trạng thái hoàn trả sang \"$status_text\". Ghi chú: $admin_note";
        
        $history_query->bind_param("isss", $return_data['id_donhang'], $action, $admin_name, $note);
        $history_query->execute();
        
        // Log admin activity
        if (function_exists('logAdminActivity')) {
            $admin_id = $_SESSION['admin_id'] ?? 1;
            $details = "Cập nhật trạng thái hoàn trả #$return_id sang $status_text";
            logAdminActivity($conn, $admin_id, 'update', 'return', $return_id, $details);
        }
        
        $_SESSION['success_message'] = "Đã cập nhật trạng thái yêu cầu hoàn trả thành công";
        
        // Refresh data
        $return_data['trangthai'] = $new_status;
        $return_data['phan_hoi'] = $admin_note;
    } else {
        $_SESSION['error_message'] = "Lỗi khi cập nhật trạng thái: " . $conn->error;
    }
}

// Status mapping
$return_statuses = [
    1 => ['name' => 'Chờ xác nhận', 'badge' => 'warning', 'next' => [2, 5]],
    2 => ['name' => 'Đã xác nhận', 'badge' => 'info', 'next' => [3]],
    3 => ['name' => 'Đang xử lý', 'badge' => 'primary', 'next' => [4]],
    4 => ['name' => 'Hoàn thành', 'badge' => 'success', 'next' => []],
    5 => ['name' => 'Từ chối', 'badge' => 'danger', 'next' => []]
];

// Format product image path
$product_image = !empty($return_data['hinhanh']) ? 
    (strpos($return_data['hinhanh'], 'uploads/') === 0 ? '../' . $return_data['hinhanh'] : '../uploads/products/' . $return_data['hinhanh']) : 
    '../images/no-image.png';
?>

<!-- Main content -->
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="returns.php">Quản lý hoàn trả</a></li>
            <li class="breadcrumb-item active" aria-current="page">Chi tiết yêu cầu #<?php echo $return_id; ?></li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2">Chi tiết yêu cầu hoàn trả #<?php echo $return_id; ?></h1>
        <a href="returns.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Quay lại danh sách
        </a>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Left column: Return request details -->
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Thông tin yêu cầu hoàn trả</h5>
                    <span class="badge bg-<?php echo $return_statuses[$return_data['trangthai']]['badge']; ?>">
                        <?php echo $return_statuses[$return_data['trangthai']]['name']; ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <h6>Lý do hoàn trả:</h6>
                        <p class="p-3 bg-light rounded"><?php echo htmlspecialchars($return_data['lydo']); ?></p>
                    </div>
                    
                    <div class="mb-4">
                        <h6>Mô tả chi tiết từ khách hàng:</h6>
                        <p class="p-3 bg-light rounded"><?php echo nl2br(htmlspecialchars($return_data['mota_chitiet'])); ?></p>
                    </div>
                    
                    <?php if (!empty($return_data['phan_hoi'])): ?>
                    <div class="mb-4">
                        <h6>Phản hồi từ cửa hàng:</h6>
                        <div class="p-3 bg-light rounded">
                            <?php echo nl2br(htmlspecialchars($return_data['phan_hoi'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <h6 class="text-muted">Thông tin thời gian:</h6>
                                <p class="mb-1"><strong>Ngày tạo yêu cầu:</strong> <?php echo date('d/m/Y H:i', strtotime($return_data['ngaytao'])); ?></p>
                                <?php if (!empty($return_data['ngaycapnhat'])): ?>
                                <p class="mb-0"><strong>Ngày cập nhật:</strong> <?php echo date('d/m/Y H:i', strtotime($return_data['ngaycapnhat'])); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Update status form -->
            <?php if (in_array($return_data['trangthai'], [1, 2, 3])): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Cập nhật trạng thái</h5>
                </div>
                <div class="card-body">
                    <form method="post">
                        <div class="mb-3">
                            <label for="new_status" class="form-label">Trạng thái mới</label>
                            <select class="form-select" id="new_status" name="new_status" required>
                                <?php foreach ($return_statuses[$return_data['trangthai']]['next'] as $status_id): ?>
                                    <option value="<?php echo $status_id; ?>">
                                        <?php echo $return_statuses[$status_id]['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="admin_note" class="form-label">Ghi chú / phản hồi</label>
                            <textarea class="form-control" id="admin_note" name="admin_note" rows="3" required><?php echo htmlspecialchars($return_data['phan_hoi'] ?? ''); ?></textarea>
                        </div>
                        <button type="submit" name="update_status" class="btn btn-primary">Cập nhật trạng thái</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Right column: Product and customer info -->
        <div class="col-md-4">
            <!-- Product info -->
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Thông tin sản phẩm</h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-3">
                        <img src="<?php echo $product_image; ?>" alt="<?php echo htmlspecialchars($return_data['tensanpham']); ?>" class="img-fluid mb-2" style="max-height: 150px;">
                    </div>
                    <h5 class="card-title"><?php echo htmlspecialchars($return_data['tensanpham']); ?></h5>
                    <p class="card-text text-danger fw-bold"><?php echo number_format($return_data['gia'], 0, ',', '.'); ?>đ</p>
                    
                    <a href="../product-detail.php?id=<?php echo $return_data['id_sanpham']; ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                        <i class="bi bi-box-arrow-up-right"></i> Xem sản phẩm trên website
                    </a>
                </div>
            </div>
            
            <!-- Order info -->
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Thông tin đơn hàng</h5>
                </div>
                <div class="card-body">
                    <p><strong>Mã đơn hàng:</strong> #<?php echo $return_data['ma_donhang']; ?></p>
                    <p><strong>Ngày đặt hàng:</strong> <?php echo date('d/m/Y', strtotime($return_data['ngay_dat'])); ?></p>
                    <a href="order-detail.php?id=<?php echo $return_data['id_donhang']; ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-receipt"></i> Xem chi tiết đơn hàng
                    </a>
                </div>
            </div>
            
            <!-- Customer info -->
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Thông tin khách hàng</h5>
                </div>
                <div class="card-body">
                    <p><strong>Tên khách hàng:</strong> <?php echo htmlspecialchars($return_data['ten_user']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($return_data['email']); ?></p>
                    <p><strong>Số điện thoại:</strong> <?php echo htmlspecialchars($return_data['sodienthoai']); ?></p>
                    
                    <a href="customer-detail.php?id=<?php echo $return_data['id_nguoidung']; ?>" class="btn btn-sm btn-outline-info w-100">
                        <i class="bi bi-person"></i> Xem thông tin khách hàng
                    </a>
                </div>
            </div>
        </div>
    </div>
</main>

<?php 
include('includes/footer.php');
// End output buffering and send output
ob_end_flush();
?>