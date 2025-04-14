<?php
// Set page title
$page_title = 'Chi tiết khách hàng';

// Include header
include('includes/header.php');

// Include database connection
include('../config/config.php');

// Get customer ID from URL
$customer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Check for valid ID
if ($customer_id <= 0) {
    header('Location: customers.php');
    exit();
}

// Get customer details
$stmt = $conn->prepare("
    SELECT u.*
    FROM users u
    WHERE u.id_user = ?
");
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$result = $stmt->get_result();

// Check if customer exists
if ($result->num_rows === 0) {
    header('Location: customers.php');
    exit();
}

$customer = $result->fetch_assoc();

// Get customer's orders
$order_stmt = $conn->prepare("
    SELECT dh.*, COUNT(dct.id_chitiet) as total_items 
    FROM donhang dh
    LEFT JOIN donhang_chitiet dct ON dh.id_donhang = dct.id_donhang
    WHERE dh.id_nguoidung = ?
    GROUP BY dh.id_donhang
    ORDER BY dh.ngaytao DESC
");
$order_stmt->bind_param("i", $customer_id);
$order_stmt->execute();
$orders_result = $order_stmt->get_result();

// Order status labels
$order_statuses = [
    1 => ['name' => 'Chờ xác nhận', 'badge' => 'warning'],
    2 => ['name' => 'Đang xử lý', 'badge' => 'info'],
    3 => ['name' => 'Đang giao hàng', 'badge' => 'primary'],
    4 => ['name' => 'Đã giao', 'badge' => 'success'],
    5 => ['name' => 'Đã hủy', 'badge' => 'danger'],
    6 => ['name' => 'Hoàn trả', 'badge' => 'secondary']
];
?>

<!-- Include sidebar -->
<?php include('includes/sidebar.php'); ?>

<!-- Add custom CSS for this page -->
<style>
    /* Profile card enhancements */
    .profile-card {
        border-radius: 15px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        transition: transform 0.3s;
    }
    
    .profile-card:hover {
        transform: translateY(-5px);
    }
    
    .profile-avatar {
        width: 120px;
        height: 120px;
        object-fit: cover;
        border: 4px solid #fff;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }
    
    .profile-avatar-placeholder {
        width: 120px;
        height: 120px;
        font-size: 3rem;
        background-color: #6c757d;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        margin: 0 auto;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }
    
    /* Info list styling */
    .info-list {
        list-style: none;
        padding-left: 0;
    }
    
    .info-list li {
        padding: 8px 0;
        border-bottom: 1px solid #f0f0f0;
        display: flex;
        align-items: center;
    }
    
    .info-list li:last-child {
        border-bottom: none;
    }
    
    .info-list li i {
        width: 24px;
        color: #6c757d;
        margin-right: 10px;
    }
    
    /* Tabs enhancements */
    .nav-tabs .nav-link {
        font-weight: 500;
        padding: 10px 20px;
        border: none;
        border-bottom: 3px solid transparent;
        color: #495057;
        transition: all 0.2s;
    }
    
    .nav-tabs .nav-link.active {
        color: #007bff;
        background: transparent;
        border-bottom: 3px solid #007bff;
    }
    
    .nav-tabs .nav-link:hover:not(.active) {
        border-bottom: 3px solid #e9ecef;
    }
    
    /* Orders table */
    .table-hover tr:hover {
        background-color: rgba(0,123,255,0.03);
    }
    
    /* Reviews styling */
    .review-card {
        border-left: 4px solid #007bff;
        margin-bottom: 15px;
    }
    
    .review-rating {
        color: #ffc107;
    }
    
    /* Notes styling */
    .note-card {
        border-radius: 10px;
        margin-bottom: 15px;
        transition: transform 0.2s;
    }
    
    .note-card:hover {
        transform: translateY(-3px);
    }
    
    .badge-soft {
        font-weight: 500;
        padding: 5px 10px;
    }
    
    /* Button styling */
    .btn-action {
        border-radius: 50px;
        padding: 0.25rem 0.75rem;
    }
</style>

<!-- Main content -->
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="customers.php" class="text-decoration-none">Khách hàng</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($customer['tenuser']); ?></li>
        </ol>
    </nav>
    
    <!-- Page header -->
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-2 mb-4 border-bottom">
        <h1 class="h2 fw-bold">
            <i class="bi bi-person-badge me-2 text-primary"></i>
            <?php echo htmlspecialchars($customer['tenuser']); ?>
        </h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="edit_customer.php?id=<?php echo $customer_id; ?>" class="btn btn-sm btn-outline-primary me-2">
                <i class="bi bi-pencil"></i> Chỉnh sửa
            </a>
            <a href="customers.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Quay lại
            </a>
        </div>
    </div>
    
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> <?php echo $_SESSION['success_message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $_SESSION['error_message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>
    
    <div class="row g-4">
        <!-- Customer Info Card -->
        <div class="col-md-4 mb-4">
            <div class="card profile-card h-100">
                <div class="card-body p-4">
                    <!-- Profile Header -->
                    <div class="text-center mb-4">
                        <?php if (!empty($customer['anh_dai_dien'])): ?>
                            <img src="../uploads/users/<?php echo $customer['anh_dai_dien']; ?>" 
                                 alt="Profile" 
                                 class="rounded-circle profile-avatar mb-3">
                        <?php else: ?>
                            <div class="profile-avatar-placeholder mb-3">
                                <i class="bi bi-person"></i>
                            </div>
                        <?php endif; ?>
                        
                        <h4 class="fw-bold mb-1"><?php echo htmlspecialchars($customer['tenuser']); ?></h4>
                        <p class="text-muted mb-2">@<?php echo htmlspecialchars($customer['taikhoan']); ?></p>
                        
                        <div class="d-flex justify-content-center gap-2 mb-3">
                            <span class="badge <?php echo $customer['trang_thai'] ? 'bg-success' : 'bg-danger'; ?> badge-soft">
                                <?php echo $customer['trang_thai'] ? 'Đang hoạt động' : 'Đã khóa'; ?>
                            </span>
                            <span class="badge bg-<?php echo $customer['loai_user'] == 0 ? 'info' : 'primary'; ?> badge-soft">
                                <?php echo $customer['loai_user'] == 0 ? 'Người mua' : 'Người dùng'; ?>
                            </span>
                            <span class="badge bg-<?php echo $customer['trang_thai_xac_thuc'] ? 'success' : 'warning'; ?> badge-soft">
                                <?php echo $customer['trang_thai_xac_thuc'] ? 'Đã xác thực' : 'Chưa xác thực'; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="border-top border-bottom py-3 mb-4">
                        <h6 class="text-uppercase text-muted mb-3 small fw-bold"><i class="bi bi-info-circle me-2"></i>Thông tin liên hệ</h6>
                        <ul class="info-list mb-0">
                            <li>
                                <i class="bi bi-envelope"></i>
                                <span><?php echo htmlspecialchars($customer['email'] ?: 'Chưa cập nhật'); ?></span>
                            </li>
                            <li>
                                <i class="bi bi-telephone"></i>
                                <span><?php echo htmlspecialchars($customer['sdt']); ?></span>
                            </li>
                            <li>
                                <i class="bi bi-geo-alt"></i>
                                <span><?php echo htmlspecialchars($customer['diachi'] ?: 'Chưa cập nhật'); ?></span>
                            </li>
                        </ul>
                    </div>
                    
                    <div class="mb-4">
                        <h6 class="text-uppercase text-muted mb-3 small fw-bold"><i class="bi bi-clock-history me-2"></i>Thông tin tài khoản</h6>
                        <ul class="info-list mb-0">
                            <li>
                                <i class="bi bi-calendar-check"></i>
                                <span>Ngày đăng ký: <strong><?php echo date('d/m/Y H:i', strtotime($customer['ngay_tao'])); ?></strong></span>
                            </li>
                            <li>
                                <i class="bi bi-bag-check"></i>
                                <span>Đơn hàng: <strong><?php echo $orders_result->num_rows; ?></strong></span>
                            </li>
                        </ul>
                    </div>
                    
                    <?php if ($customer['trang_thai'] == 0 && !empty($customer['ly_do_khoa'])): ?>
                        <div class="alert alert-danger mt-2 mb-4">
                            <h6 class="fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i>Lý do khóa tài khoản</h6>
                            <p class="mb-0 mt-1"><?php echo nl2br(htmlspecialchars($customer['ly_do_khoa'])); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="card-footer bg-white py-3 border-top">
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="resetPasswordBtn" 
                                data-id="<?php echo $customer['id_user']; ?>" 
                                data-bs-toggle="modal" 
                                data-bs-target="#resetPasswordModal">
                            <i class="bi bi-key"></i> Đặt lại mật khẩu
                        </button>
                        
                        <button type="button" class="btn btn-sm <?php echo $customer['trang_thai'] ? 'btn-outline-danger' : 'btn-outline-success'; ?>"
                                id="toggleStatusBtn"
                                data-id="<?php echo $customer['id_user']; ?>"
                                data-status="<?php echo $customer['trang_thai']; ?>"
                                data-bs-toggle="modal" 
                                data-bs-target="#toggleStatusModal">
                            <?php if ($customer['trang_thai']): ?>
                                <i class="bi bi-lock"></i> Khóa tài khoản
                            <?php else: ?>
                                <i class="bi bi-unlock"></i> Mở khóa tài khoản
                            <?php endif; ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Customer Activity Card -->
        <div class="col-md-8 mb-4">
            <div class="card h-100 shadow-sm">
                <div class="card-header bg-white py-3">
                    <ul class="nav nav-tabs card-header-tabs" id="customerTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="orders-tab" data-bs-toggle="tab" data-bs-target="#orders" type="button" role="tab">
                                <i class="bi bi-cart3 me-1"></i> Đơn hàng 
                                <span class="badge bg-secondary rounded-pill ms-1"><?php echo $orders_result->num_rows; ?></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="reviews-tab" data-bs-toggle="tab" data-bs-target="#reviews" type="button" role="tab">
                                <i class="bi bi-star me-1"></i> Đánh giá
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="notes-tab" data-bs-toggle="tab" data-bs-target="#notes" type="button" role="tab">
                                <i class="bi bi-journal-text me-1"></i> Ghi chú
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body p-0">
                    <div class="tab-content" id="customerTabContent">
                        <!-- Orders Tab -->
                        <div class="tab-pane fade show active p-4" id="orders" role="tabpanel">
                            <?php if ($orders_result->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th scope="col" width="80">ID</th>
                                                <th scope="col" width="120">Ngày đặt</th>
                                                <th scope="col">Sản phẩm</th>
                                                <th scope="col" width="120">Tổng tiền</th>
                                                <th scope="col" width="130">Trạng thái</th>
                                                <th scope="col" width="80" class="text-end">Xem</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($order = $orders_result->fetch_assoc()): ?>
                                                <tr>
                                                    <td><strong>#<?php echo $order['id_donhang']; ?></strong></td>
                                                    <td><span class="text-muted"><?php echo date('d/m/Y', strtotime($order['ngaytao'])); ?></span><br>
                                                    <small><?php echo date('H:i', strtotime($order['ngaytao'])); ?></small></td>
                                                    <td>
                                                        <span class="badge bg-light text-dark border">
                                                            <?php echo $order['total_items']; ?> sản phẩm
                                                        </span>
                                                    </td>
                                                    <td><strong class="text-primary"><?php echo number_format($order['tongtien'], 0, ',', '.'); ?>₫</strong></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $order_statuses[$order['trangthai']]['badge']; ?>">
                                                            <?php echo $order_statuses[$order['trangthai']]['name']; ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-end">
                                                        <a href="order-detail.php?id=<?php echo $order['id_donhang']; ?>" 
                                                           class="btn btn-sm btn-outline-primary rounded-circle" 
                                                           title="Xem chi tiết đơn hàng">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <div class="py-5">
                                        <i class="bi bi-cart text-muted" style="font-size: 4rem;"></i>
                                        <p class="mt-3 text-muted">Khách hàng chưa có đơn hàng nào</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Reviews Tab -->
                        <div class="tab-pane fade p-4" id="reviews" role="tabpanel">
                            <?php
                            // Get customer's reviews
                            $reviews_stmt = $conn->prepare("
                                SELECT dg.*, sp.tensanpham 
                                FROM danhgia dg
                                JOIN sanpham sp ON dg.id_sanpham = sp.id_sanpham
                                WHERE dg.id_user = ?
                                ORDER BY dg.ngaydanhgia DESC
                            ");
                            $reviews_stmt->bind_param("i", $customer_id);
                            $reviews_stmt->execute();
                            $reviews_result = $reviews_stmt->get_result();
                            ?>
                            
                            <?php if ($reviews_result->num_rows > 0): ?>
                                <div class="reviews-container">
                                    <?php while ($review = $reviews_result->fetch_assoc()): ?>
                                        <div class="card review-card mb-3 shadow-sm">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <h6 class="mb-0 fw-bold">
                                                        <a href="../product-detail.php?id=<?php echo $review['id_sanpham']; ?>" 
                                                           class="text-decoration-none" target="_blank">
                                                           <?php echo htmlspecialchars($review['tensanpham']); ?>
                                                        </a>
                                                    </h6>
                                                    <span class="text-muted small"><?php echo date('d/m/Y', strtotime($review['ngaydanhgia'])); ?></span>
                                                </div>
                                                
                                                <div class="review-rating mb-2">
                                                    <?php for ($i = 0; $i < 5; $i++): ?>
                                                        <?php if ($i < $review['diemdanhgia']): ?>
                                                            <i class="bi bi-star-fill"></i>
                                                        <?php else: ?>
                                                            <i class="bi bi-star"></i>
                                                        <?php endif; ?>
                                                    <?php endfor; ?>
                                                </div>
                                                
                                                <p class="card-text"><?php echo htmlspecialchars($review['noidung'] ?: 'Không có nội dung đánh giá'); ?></p>
                                                
                                                <?php if (!empty($review['hinhanh'])): ?>
                                                    <div class="mt-2">
                                                        <img src="../uploads/reviews/<?php echo $review['hinhanh']; ?>" 
                                                             alt="Review Image" 
                                                             class="img-thumbnail" 
                                                             style="max-height: 100px;">
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <div class="py-5">
                                        <i class="bi bi-star text-muted" style="font-size: 4rem;"></i>
                                        <p class="mt-3 text-muted">Khách hàng chưa có đánh giá nào</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Notes Tab -->
                        <div class="tab-pane fade p-4" id="notes" role="tabpanel">
                            <?php
                            // Check if customer_notes table exists
                            $table_check = $conn->query("SHOW TABLES LIKE 'customer_notes'");
                            
                            if ($table_check->num_rows === 0) {
                                // Create notes table if it doesn't exist
                                $conn->query("CREATE TABLE customer_notes (
                                    id INT(11) NOT NULL AUTO_INCREMENT,
                                    id_user INT(11) NOT NULL,
                                    note TEXT NOT NULL,
                                    created_by VARCHAR(100) NOT NULL,
                                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                    PRIMARY KEY (id),
                                    KEY id_user (id_user)
                                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
                            }
                            
                            // Get existing notes
                            $notes_stmt = $conn->prepare("
                                SELECT * FROM customer_notes
                                WHERE id_user = ?
                                ORDER BY created_at DESC
                            ");
                            $notes_stmt->bind_param("i", $customer_id);
                            $notes_stmt->execute();
                            $notes_result = $notes_stmt->get_result();
                            ?>
                            
                            <div class="row">
                                <div class="col-lg-4 order-lg-2">
                                    <div class="card shadow-sm mb-4">
                                        <div class="card-header bg-light py-3">
                                            <h6 class="card-title mb-0 fw-bold"><i class="bi bi-plus-circle me-2"></i>Thêm ghi chú mới</h6>
                                        </div>
                                        <div class="card-body">
                                            <form id="addNoteForm" action="process_customer_note.php" method="post">
                                                <input type="hidden" name="customer_id" value="<?php echo $customer_id; ?>">
                                                <div class="mb-3">
                                                    <textarea class="form-control" id="noteContent" name="note" rows="5" placeholder="Nhập nội dung ghi chú..." required></textarea>
                                                </div>
                                                <button type="submit" class="btn btn-primary w-100">
                                                    <i class="bi bi-save me-1"></i> Lưu ghi chú
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-lg-8 order-lg-1">
                                    <h6 class="fw-bold text-muted mb-3"><i class="bi bi-journals me-2"></i>Danh sách ghi chú</h6>
                                    
                                    <div class="notes-list">
                                        <?php if ($notes_result->num_rows > 0): ?>
                                            <?php while ($note = $notes_result->fetch_assoc()): ?>
                                                <div class="card note-card shadow-sm mb-3">
                                                    <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                                                        <span class="fw-bold"><i class="bi bi-person-circle me-2"></i><?php echo htmlspecialchars($note['created_by']); ?></span>
                                                        <span class="text-muted small"><?php echo date('d/m/Y H:i', strtotime($note['created_at'])); ?></span>
                                                    </div>
                                                    <div class="card-body py-3">
                                                        <p class="card-text"><?php echo nl2br(htmlspecialchars($note['note'])); ?></p>
                                                    </div>
                                                </div>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <div class="text-center py-4 mb-3 bg-light rounded">
                                                <i class="bi bi-sticky text-muted" style="font-size: 2.5rem;"></i>
                                                <p class="text-muted mt-2">Chưa có ghi chú nào</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-key me-2 text-primary"></i>Đặt lại mật khẩu</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="resetPasswordForm" action="process_reset_password.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="customer_id" id="resetPasswordUserId" value="<?php echo $customer_id; ?>">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> 
                        Hành động này sẽ tạo một mật khẩu mới cho người dùng.
                    </div>
                    <div class="mb-3">
                        <label for="newPassword" class="form-label">Mật khẩu mới</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="newPassword" name="new_password" required>
                            <button class="btn btn-outline-secondary" type="button" id="generatePasswordBtn">
                                <i class="bi bi-magic"></i> Tạo
                            </button>
                        </div>
                        <div class="form-text">Mật khẩu phải có ít nhất 8 ký tự</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i> Xác nhận
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Toggle Status Modal -->
<div class="modal fade" id="toggleStatusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="toggleStatusTitle">Thay đổi trạng thái tài khoản</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="toggleStatusForm" action="process_toggle_status.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="customer_id" value="<?php echo $customer_id; ?>">
                    <input type="hidden" name="new_status" id="newStatusInput" value="<?php echo $customer['trang_thai'] ? '0' : '1'; ?>">
                    
                    <div id="lockAccountContent" class="<?php echo $customer['trang_thai'] ? '' : 'd-none'; ?>">
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i> 
                            Bạn sắp khóa tài khoản của người dùng này.
                        </div>
                        <div class="mb-3">
                            <label for="lockReason" class="form-label">Lý do khóa</label>
                            <textarea class="form-control" id="lockReason" name="reason" rows="3"></textarea>
                        </div>
                    </div>
                    
                    <div id="unlockAccountContent" class="<?php echo $customer['trang_thai'] ? 'd-none' : ''; ?>">
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle-fill me-2"></i> 
                            Bạn sắp mở khóa tài khoản của người dùng này.
                        </div>
                        <p>Người dùng sẽ có thể đăng nhập và sử dụng tài khoản bình thường.</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary" id="toggleStatusSubmitBtn">
                        <?php if ($customer['trang_thai']): ?>
                            <i class="bi bi-lock-fill"></i> Khóa tài khoản
                        <?php else: ?>
                            <i class="bi bi-unlock-fill"></i> Mở khóa tài khoản
                        <?php endif; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php 
// JavaScript for the page
$page_specific_js = '
<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Password generator
        document.getElementById("generatePasswordBtn").addEventListener("click", function() {
            const length = 12;
            const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+";
            let password = "";
            
            for (let i = 0; i < length; i++) {
                const randomIndex = Math.floor(Math.random() * charset.length);
                password += charset[randomIndex];
            }
            
            document.getElementById("newPassword").value = password;
        });
        
        // Toggle status modal content
        const toggleStatusBtn = document.getElementById("toggleStatusBtn");
        if (toggleStatusBtn) {
            toggleStatusBtn.addEventListener("click", function() {
                const currentStatus = parseInt(this.getAttribute("data-status"));
                const newStatus = currentStatus ? 0 : 1;
                
                // Update form content based on action
                document.getElementById("newStatusInput").value = newStatus;
                
                if (newStatus === 1) {
                    // Unlocking
                    document.getElementById("toggleStatusTitle").textContent = "Mở khóa tài khoản";
                    document.getElementById("unlockAccountContent").classList.remove("d-none");
                    document.getElementById("lockAccountContent").classList.add("d-none");
                    document.getElementById("toggleStatusSubmitBtn").innerHTML = "<i class=\"bi bi-unlock-fill\"></i> Mở khóa tài khoản";
                    document.getElementById("toggleStatusSubmitBtn").className = "btn btn-success";
                } else {
                    // Locking
                    document.getElementById("toggleStatusTitle").textContent = "Khóa tài khoản";
                    document.getElementById("lockAccountContent").classList.remove("d-none");
                    document.getElementById("unlockAccountContent").classList.add("d-none");
                    document.getElementById("toggleStatusSubmitBtn").innerHTML = "<i class=\"bi bi-lock-fill\"></i> Khóa tài khoản";
                    document.getElementById("toggleStatusSubmitBtn").className = "btn btn-danger";
                }
            });
        }
    });
</script>
';

// Include footer
include('includes/footer.php');
?>