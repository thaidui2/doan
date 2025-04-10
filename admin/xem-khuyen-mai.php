<?php
// Thiết lập tiêu đề trang
$page_title = 'Chi tiết mã khuyến mãi';

// Include header (kiểm tra đăng nhập)
include('includes/header.php');

// Include database connection
include('../config/config.php');

// Kiểm tra quyền truy cập
if (!hasPermission('promo_view')) {
    $_SESSION['error_message'] = "Bạn không có quyền truy cập chức năng này!";
    header('Location: index.php');
    exit();
}

// Lấy ID khuyến mãi từ tham số URL
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    $_SESSION['error_message'] = "ID khuyến mãi không hợp lệ!";
    header('Location: khuyen-mai.php');
    exit();
}

// Lấy thông tin chi tiết khuyến mãi
$query = "
    SELECT km.*, u.tenuser, u.ten_shop
    FROM khuyen_mai km
    LEFT JOIN users u ON km.id_nguoiban = u.id_user
    WHERE km.id = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $_SESSION['error_message'] = "Không tìm thấy mã khuyến mãi với ID: $id";
    header('Location: khuyen-mai.php');
    exit();
}

$promo = $result->fetch_assoc();

// Lấy danh sách sản phẩm áp dụng (nếu có)
$products = [];
if ($promo['ap_dung_sanpham']) {
    $product_query = "
        SELECT s.id_sanpham, s.tensanpham, s.gia, s.hinhanh
        FROM khuyen_mai_sanpham kms
        JOIN sanpham s ON kms.id_sanpham = s.id_sanpham
        WHERE kms.id_khuyen_mai = ?
    ";
    $stmt = $conn->prepare($product_query);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $product_result = $stmt->get_result();
    
    while ($product = $product_result->fetch_assoc()) {
        $products[] = $product;
    }
}

// Lấy danh sách loại sản phẩm áp dụng (nếu có)
$categories = [];
if ($promo['ap_dung_loai']) {
    $category_query = "
        SELECT l.id_loai, l.tenloai, l.hinhanh
        FROM khuyen_mai_loai kml
        JOIN loaisanpham l ON kml.id_loai = l.id_loai
        WHERE kml.id_khuyen_mai = ?
    ";
    $stmt = $conn->prepare($category_query);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $category_result = $stmt->get_result();
    
    while ($category = $category_result->fetch_assoc()) {
        $categories[] = $category;
    }
}

// Lấy lịch sử sử dụng mã giảm giá
$usage_query = "
    SELECT kml.*, dh.id_donhang, u.tenuser, dh.ngaytao
    FROM khuyen_mai_lichsu kml
    JOIN donhang dh ON kml.id_donhang = dh.id_donhang
    JOIN users u ON kml.id_nguoidung = u.id_user
    WHERE kml.id_khuyen_mai = ?
    ORDER BY kml.ngay_su_dung DESC
    LIMIT 10
";
$stmt = $conn->prepare($usage_query);
$stmt->bind_param('i', $id);
$stmt->execute();
$usage_result = $stmt->get_result();

$usages = [];
while ($usage = $usage_result->fetch_assoc()) {
    $usages[] = $usage;
}

// Tính trạng thái hiển thị của khuyến mãi
$now = new DateTime();
$start_date = new DateTime($promo['ngay_bat_dau']);
$end_date = new DateTime($promo['ngay_ket_thuc']);

$status_class = 'bg-secondary';
$status_text = 'Không hoạt động';

if ($promo['trang_thai'] == 1) {
    if ($now > $end_date) {
        $status_class = 'bg-danger';
        $status_text = 'Hết hạn';
    } elseif ($now < $start_date) {
        $status_class = 'bg-info';
        $status_text = 'Sắp diễn ra';
    } else {
        $status_class = 'bg-success';
        $status_text = 'Đang hoạt động';
    }
} else {
    $status_class = 'bg-secondary';
    $status_text = 'Không kích hoạt';
}
?>

<!-- Include sidebar -->
<?php include('includes/sidebar.php'); ?>

<!-- Main content -->
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Trang chủ</a></li>
            <li class="breadcrumb-item"><a href="khuyen-mai.php">Quản lý khuyến mãi</a></li>
            <li class="breadcrumb-item active" aria-current="page">Chi tiết mã khuyến mãi</li>
        </ol>
    </nav>
    
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">
            <i class="bi bi-ticket-perforated me-2"></i>Chi tiết mã khuyến mãi: 
            <span class="text-primary"><?php echo htmlspecialchars($promo['ma_code']); ?></span>
        </h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <?php if (hasPermission('promo_edit')): ?>
            <a href="chinh-sua-khuyen-mai.php?id=<?php echo $promo['id']; ?>" class="btn btn-sm btn-outline-secondary me-2">
                <i class="bi bi-pencil"></i> Chỉnh sửa
            </a>
            <?php endif; ?>
            <a href="khuyen-mai.php" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-arrow-left"></i> Quay lại danh sách
            </a>
        </div>
    </div>
    
    <?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i><?php echo $_SESSION['success_message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle me-2"></i><?php echo $_SESSION['error_message']; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>
    
    <div class="row">
        <!-- Thông tin cơ bản -->
        <div class="col-md-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-info-circle me-2"></i>Thông tin mã khuyến mãi
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-lg-6">
                            <table class="table table-borderless">
                                <tbody>
                                    <tr>
                                        <th width="40%" class="ps-0">ID:</th>
                                        <td><strong><?php echo $promo['id']; ?></strong></td>
                                    </tr>
                                    <tr>
                                        <th class="ps-0">Mã code:</th>
                                        <td>
                                            <strong class="d-flex align-items-center">
                                                <span class="fs-5 text-uppercase fw-bold"><?php echo htmlspecialchars($promo['ma_code']); ?></span>
                                                <button class="btn btn-sm btn-light ms-2" 
                                                    onclick="copyToClipboard('<?php echo htmlspecialchars($promo['ma_code']); ?>')"
                                                    title="Sao chép mã">
                                                    <i class="bi bi-clipboard"></i>
                                                </button>
                                            </strong>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="ps-0">Loại giảm giá:</th>
                                        <td>
                                            <?php if ($promo['loai_giam_gia'] == 1): ?>
                                                <span class="badge bg-primary">Phần trăm (%)</span>
                                            <?php else: ?>
                                                <span class="badge bg-info">Số tiền cố định</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="ps-0">Giá trị:</th>
                                        <td>
                                            <?php if ($promo['loai_giam_gia'] == 1): ?>
                                                <span class="fw-bold"><?php echo number_format($promo['gia_tri'], 0); ?>%</span>
                                            <?php else: ?>
                                                <span class="fw-bold"><?php echo number_format($promo['gia_tri'], 0); ?>₫</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php if ($promo['gia_tri_giam_toi_da'] > 0): ?>
                                    <tr>
                                        <th class="ps-0">Giảm tối đa:</th>
                                        <td><?php echo number_format($promo['gia_tri_giam_toi_da'], 0); ?>₫</td>
                                    </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <th class="ps-0">Giá trị đơn tối thiểu:</th>
                                        <td>
                                            <?php if ($promo['gia_tri_don_toi_thieu'] > 0): ?>
                                                <?php echo number_format($promo['gia_tri_don_toi_thieu'], 0); ?>₫
                                            <?php else: ?>
                                                <span class="text-muted">Không giới hạn</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="col-lg-6">
                            <table class="table table-borderless">
                                <tbody>
                                    <tr>
                                        <th width="40%" class="ps-0">Trạng thái:</th>
                                        <td><span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                                    </tr>
                                    <tr>
                                        <th class="ps-0">Thời gian áp dụng:</th>
                                        <td>
                                            <div class="small">
                                                <i class="bi bi-calendar-event me-1"></i> 
                                                Từ: <strong><?php echo date('d/m/Y H:i', strtotime($promo['ngay_bat_dau'])); ?></strong>
                                            </div>
                                            <div class="small mt-1">
                                                <i class="bi bi-calendar-x me-1"></i>
                                                Đến: <strong><?php echo date('d/m/Y H:i', strtotime($promo['ngay_ket_thuc'])); ?></strong>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="ps-0">Số lượt sử dụng:</th>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <span class="fw-bold"><?php echo $promo['so_luong_da_dung']; ?> / <?php echo $promo['so_luong']; ?></span>
                                                
                                                <?php 
                                                $usage_percent = $promo['so_luong'] > 0 ? 
                                                    ($promo['so_luong_da_dung'] / $promo['so_luong']) * 100 : 0;
                                                ?>
                                                
                                                <div class="progress ms-2" style="height: 6px; width: 100px;">
                                                    <div class="progress-bar bg-<?php echo $usage_percent >= 80 ? 'danger' : 'primary'; ?>" 
                                                        role="progressbar" 
                                                        style="width: <?php echo $usage_percent; ?>%" 
                                                        aria-valuenow="<?php echo $usage_percent; ?>" 
                                                        aria-valuemin="0" 
                                                        aria-valuemax="100"></div>
                                                </div>
                                            </div>
                                            
                                            <?php if ($usage_percent >= 80 && $usage_percent < 100): ?>
                                                <small class="text-warning"><i class="bi bi-exclamation-triangle"></i> Sắp hết số lượng</small>
                                            <?php elseif ($usage_percent >= 100): ?>
                                                <small class="text-danger"><i class="bi bi-x-circle"></i> Đã hết số lượng</small>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="ps-0">Tạo bởi:</th>
                                        <td>
                                            <?php if (!empty($promo['ten_shop'])): ?>
                                                <?php echo htmlspecialchars($promo['tenuser']); ?> 
                                                <span class="badge bg-success">Shop: <?php echo htmlspecialchars($promo['ten_shop']); ?></span>
                                            <?php else: ?>
                                                <?php echo htmlspecialchars($promo['tenuser']); ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="ps-0">Ngày tạo:</th>
                                        <td><?php echo date('d/m/Y H:i:s', strtotime($promo['ngay_tao'])); ?></td>
                                    </tr>
                                    <?php if ($promo['ngay_capnhat']): ?>
                                    <tr>
                                        <th class="ps-0">Cập nhật lần cuối:</th>
                                        <td><?php echo date('d/m/Y H:i:s', strtotime($promo['ngay_capnhat'])); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <?php if (!empty($promo['mo_ta'])): ?>
                    <div class="border-top pt-3 mt-2">
                        <h6 class="fw-bold">Mô tả:</h6>
                        <p><?php echo nl2br(htmlspecialchars($promo['mo_ta'])); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Phần áp dụng -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-layers me-2"></i>Phạm vi áp dụng
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!$promo['ap_dung_sanpham'] && !$promo['ap_dung_loai']): ?>
                        <div class="alert alert-success mb-0">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            Áp dụng cho <strong>tất cả sản phẩm</strong> trong cửa hàng.
                        </div>
                    <?php else: ?>
                        <ul class="nav nav-tabs" id="applyTab" role="tablist">
                            <?php if ($promo['ap_dung_sanpham']): ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="products-tab" data-bs-toggle="tab" data-bs-target="#products" type="button" role="tab" aria-controls="products" aria-selected="true">
                                    <i class="bi bi-box me-2"></i>Sản phẩm (<?php echo count($products); ?>)
                                </button>
                            </li>
                            <?php endif; ?>
                            
                            <?php if ($promo['ap_dung_loai']): ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?php echo !$promo['ap_dung_sanpham'] ? 'active' : ''; ?>" id="categories-tab" data-bs-toggle="tab" data-bs-target="#categories" type="button" role="tab" aria-controls="categories" aria-selected="false">
                                    <i class="bi bi-grid me-2"></i>Danh mục (<?php echo count($categories); ?>)
                                </button>
                            </li>
                            <?php endif; ?>
                        </ul>
                        
                        <div class="tab-content pt-3" id="applyTabContent">
                            <?php if ($promo['ap_dung_sanpham']): ?>
                            <div class="tab-pane fade show active" id="products" role="tabpanel" aria-labelledby="products-tab">
                                <?php if (count($products) > 0): ?>
                                    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3">
                                        <?php foreach ($products as $product): ?>
                                            <div class="col">
                                                <div class="card h-100">
                                                    <div class="position-relative">
                                                        <img src="../uploads/products/<?php echo $product['hinhanh']; ?>" 
                                                             class="card-img-top" alt="<?php echo htmlspecialchars($product['tensanpham']); ?>"
                                                             style="height: 150px; object-fit: cover;">
                                                    </div>
                                                    <div class="card-body">
                                                        <h6 class="card-title"><?php echo htmlspecialchars($product['tensanpham']); ?></h6>
                                                        <p class="card-text text-primary fw-bold"><?php echo number_format($product['gia'], 0); ?>đ</p>
                                                        <a href="../chi-tiet.php?id=<?php echo $product['id_sanpham']; ?>" class="btn btn-sm btn-outline-secondary" target="_blank">
                                                            <i class="bi bi-eye"></i> Xem sản phẩm
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-warning">
                                        <i class="bi bi-exclamation-triangle me-2"></i>
                                        Không có sản phẩm nào được áp dụng cho mã giảm giá này.
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($promo['ap_dung_loai']): ?>
                            <div class="tab-pane fade <?php echo !$promo['ap_dung_sanpham'] ? 'show active' : ''; ?>" id="categories" role="tabpanel" aria-labelledby="categories-tab">
                                <?php if (count($categories) > 0): ?>
                                    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-3">
                                        <?php foreach ($categories as $category): ?>
                                            <div class="col">
                                                <div class="card text-center h-100">
                                                    <?php if ($category['hinhanh']): ?>
                                                    <img src="../uploads/categories/<?php echo $category['hinhanh']; ?>" 
                                                         class="card-img-top" alt="<?php echo htmlspecialchars($category['tenloai']); ?>"
                                                         style="height: 120px; object-fit: cover;">
                                                    <?php else: ?>
                                                    <div class="bg-light d-flex align-items-center justify-content-center" style="height: 120px;">
                                                        <i class="bi bi-grid fs-1 text-secondary"></i>
                                                    </div>
                                                    <?php endif; ?>
                                                    <div class="card-body">
                                                        <h6 class="card-title"><?php echo htmlspecialchars($category['tenloai']); ?></h6>
                                                        <a href="../danh-muc.php?id=<?php echo $category['id_loai']; ?>" class="btn btn-sm btn-outline-secondary" target="_blank">
                                                            <i class="bi bi-eye"></i> Xem danh mục
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-warning">
                                        <i class="bi bi-exclamation-triangle me-2"></i>
                                        Không có danh mục nào được áp dụng cho mã giảm giá này.
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Lịch sử sử dụng và thống kê -->
        <div class="col-md-4">
            <!-- Trạng thái và thống kê -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-graph-up me-2"></i>Thống kê sử dụng
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Hiển thị trạng thái hiện tại của mã -->
                    <div class="mb-3 p-3 border rounded">
                        <div class="d-flex align-items-center">
                            <div class="rounded-circle bg-<?php echo $status_class; ?> d-flex align-items-center justify-content-center" 
                                 style="width: 50px; height: 50px; color: white;">
                                <?php if ($status_text == 'Đang hoạt động'): ?>
                                    <i class="bi bi-check-circle fs-3"></i>
                                <?php elseif ($status_text == 'Sắp diễn ra'): ?>
                                    <i class="bi bi-clock fs-3"></i>
                                <?php elseif ($status_text == 'Hết hạn'): ?>
                                    <i class="bi bi-calendar-x fs-3"></i>
                                <?php else: ?>
                                    <i class="bi bi-x-circle fs-3"></i>
                                <?php endif; ?>
                            </div>
                            <div class="ms-3">
                                <span class="d-block fw-bold fs-5"><?php echo $status_text; ?></span>
                                <?php
                                if ($status_text == 'Đang hoạt động') {
                                    $days_left = $now->diff($end_date)->days;
                                    echo '<small class="text-muted">Còn ' . $days_left . ' ngày nữa sẽ hết hạn</small>';
                                } elseif ($status_text == 'Sắp diễn ra') {
                                    $days_to_start = $now->diff($start_date)->days;
                                    echo '<small class="text-muted">Sẽ bắt đầu sau ' . $days_to_start . ' ngày nữa</small>';
                                } elseif ($status_text == 'Hết hạn') {
                                    $days_ago = $now->diff($end_date)->days;
                                    echo '<small class="text-muted">Đã hết hạn ' . $days_ago . ' ngày trước</small>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Thống kê lượt sử dụng -->
                    <div class="mb-3">
                        <h6 class="fw-bold">Lượt sử dụng</h6>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar bg-<?php echo $usage_percent >= 80 ? 'danger' : 'primary'; ?>" 
                                role="progressbar" 
                                style="width: <?php echo $usage_percent; ?>%" 
                                aria-valuenow="<?php echo $usage_percent; ?>" 
                                aria-valuemin="0" 
                                aria-valuemax="100"></div>
                        </div>
                        <div class="d-flex justify-content-between mt-2 small">
                            <span>Đã dùng: <?php echo $promo['so_luong_da_dung']; ?></span>
                            <span>Còn lại: <?php echo $promo['so_luong'] - $promo['so_luong_da_dung']; ?></span>
                            <span>Tổng: <?php echo $promo['so_luong']; ?></span>
                        </div>
                    </div>
                    
                    <!-- Thống kê giá trị đã giảm (lấy từ bảng lịch sử) -->
                    <?php
                    $total_discount_query = "SELECT SUM(gia_tri_giam) as total FROM khuyen_mai_lichsu WHERE id_khuyen_mai = ?";
                    $stmt = $conn->prepare($total_discount_query);
                    $stmt->bind_param('i', $id);
                    $stmt->execute();
                    $total_discount_result = $stmt->get_result();
                    $total_discount = $total_discount_result->fetch_assoc()['total'] ?: 0;
                    ?>
                    
                    <div class="mb-3">
                        <h6 class="fw-bold">Tổng giá trị đã giảm:</h6>
                        <h4 class="text-danger fw-bold"><?php echo number_format($total_discount, 0); ?>₫</h4>
                    </div>
                    
                    <!-- Thời gian còn lại -->
                    <?php if ($now <= $end_date && $promo['trang_thai'] == 1): ?>
                    <div class="mb-3">
                        <h6 class="fw-bold">Thời gian còn lại:</h6>
                        <div class="d-flex align-items-center">
                            <div class="timer-container" id="countdown-timer" data-end="<?php echo date('Y-m-d H:i:s', strtotime($promo['ngay_ket_thuc'])); ?>">
                                <div class="d-flex">
                                    <div class="timer-box">
                                        <span class="days">00</span>
                                        <small>Ngày</small>
                                    </div>
                                    <div class="timer-box">
                                        <span class="hours">00</span>
                                        <small>Giờ</small>
                                    </div>
                                    <div class="timer-box">
                                        <span class="minutes">00</span>
                                        <small>Phút</small>
                                    </div>
                                    <div class="timer-box">
                                        <span class="seconds">00</span>
                                        <small>Giây</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Các nút hành động -->
                    <div class="d-grid gap-2 mt-4">
                        <?php if (hasPermission('promo_edit')): ?>
                            <?php if ($promo['trang_thai']): ?>
                                <a href="khuyen-mai.php?action=toggle_status&id=<?php echo $promo['id']; ?>" 
                                   class="btn btn-warning"
                                   onclick="return confirm('Bạn có chắc muốn vô hiệu hóa mã khuyến mãi này?');">
                                    <i class="bi bi-x-circle me-2"></i>Vô hiệu hóa mã
                                </a>
                            <?php else: ?>
                                <a href="khuyen-mai.php?action=toggle_status&id=<?php echo $promo['id']; ?>" 
                                   class="btn btn-success"
                                   onclick="return confirm('Bạn có chắc muốn kích hoạt mã khuyến mãi này?');">
                                    <i class="bi bi-check-circle me-2"></i>Kích hoạt mã
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php if (hasPermission('promo_delete')): ?>
                            <a href="khuyen-mai.php?action=delete&id=<?php echo $promo['id']; ?>" 
                               class="btn btn-outline-danger"
                               onclick="return confirm('Bạn có chắc muốn xóa mã khuyến mãi này? Hành động này không thể hoàn tác!');">
                                <i class="bi bi-trash me-2"></i>Xóa mã khuyến mãi
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Lịch sử sử dụng -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-clock-history me-2"></i>Lịch sử sử dụng
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (count($usages) > 0): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($usages as $usage): ?>
                                <div class="list-group-item list-group-item-action py-3">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="mb-1">Đơn hàng #<?php echo $usage['id_donhang']; ?></h6>
                                            <p class="mb-1">
                                                <i class="bi bi-person me-1"></i>
                                                <?php echo htmlspecialchars($usage['tenuser']); ?>
                                            </p>
                                        </div>
                                        <div class="text-end">
                                            <span class="text-danger d-block fw-bold">-<?php echo number_format($usage['gia_tri_giam'], 0); ?>₫</span>
                                            <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($usage['ngay_su_dung'])); ?></small>
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <a href="don-hang.php?id=<?php echo $usage['id_donhang']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i> Xem đơn hàng
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php 
                        // Kiểm tra xem có nhiều hơn 10 lượt sử dụng không
                        $total_usage_query = "SELECT COUNT(*) as count FROM khuyen_mai_lichsu WHERE id_khuyen_mai = ?";
                        $stmt = $conn->prepare($total_usage_query);
                        $stmt->bind_param('i', $id);
                        $stmt->execute();
                        $total_usage_result = $stmt->get_result();
                        $total_usage = $total_usage_result->fetch_assoc()['count'];
                        
                        if ($total_usage > 10):
                        ?>
                        <div class="text-center my-3">
                            <a href="khuyen-mai-lichsu.php?id=<?php echo $promo['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                Xem tất cả <?php echo $total_usage; ?> lượt sử dụng
                            </a>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="p-4 text-center text-muted">
                            <i class="bi bi-ticket-perforated fs-2 mb-2"></i>
                            <p class="mb-0">Chưa có lượt sử dụng nào!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
.timer-container {
    width: 100%;
}
.timer-box {
    text-align: center;
    margin-right: 10px;
    background-color: #f8f9fa;
    border-radius: 5px;
    padding: 8px 10px;
    min-width: 60px;
}
.timer-box span {
    font-size: 1.5rem;
    font-weight: bold;
    display: block;
}
.timer-box small {
    font-size: 0.75rem;
    color: #6c757d;
}
</style>

<script>
// Hàm sao chép mã code vào clipboard
function copyToClipboard(text) {
    const el = document.createElement('textarea');
    el.value = text;
    document.body.appendChild(el);
    el.select();
    document.execCommand('copy');
    document.body.removeChild(el);
    
    // Hiển thị thông báo
    alert('Đã sao chép mã: ' + text);
}

// Đếm ngược thời gian
document.addEventListener("DOMContentLoaded", function() {
    const countdownElement = document.getElementById('countdown-timer');
    if (!countdownElement) return;
    
    const endDate = new Date(countdownElement.dataset.end).getTime();
    
    const daysElement = countdownElement.querySelector('.days');
    const hoursElement = countdownElement.querySelector('.hours');
    const minutesElement = countdownElement.querySelector('.minutes');
    const secondsElement = countdownElement.querySelector('.seconds');
    
    function updateCountdown() {
        const now = new Date().getTime();
        const distance = endDate - now;
        
        if (distance < 0) {
            daysElement.textContent = '00';
            hoursElement.textContent = '00';
            minutesElement.textContent = '00';
            secondsElement.textContent = '00';
            return;
        }
        
        const days = Math.floor(distance / (1000 * 60 * 60 * 24));
        const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((distance % (1000 * 60)) / 1000);
        
        daysElement.textContent = days < 10 ? '0' + days : days;
        hoursElement.textContent = hours < 10 ? '0' + hours : hours;
        minutesElement.textContent = minutes < 10 ? '0' + minutes : minutes;
        secondsElement.textContent = seconds < 10 ? '0' + seconds : seconds;
    }
    
    updateCountdown();
    setInterval(updateCountdown, 1000);
});
</script>

<?php
// Include footer
include('includes/footer.php');
?>