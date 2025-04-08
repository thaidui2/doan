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
$stmt = $conn->prepare("SELECT * FROM users WHERE id_user = ? AND loai_user = 0");
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

<!-- Main content -->
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Trang chủ</a></li>
            <li class="breadcrumb-item"><a href="customers.php">Quản lý khách hàng</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($customer['tenuser']); ?></li>
        </ol>
    </nav>
    
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Thông tin khách hàng</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="edit_customer.php?id=<?php echo $customer_id; ?>" class="btn btn-sm btn-outline-secondary me-2">
                <i class="bi bi-pencil"></i> Chỉnh sửa
            </a>
            <a href="customers.php" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-arrow-left"></i> Quay lại
            </a>
        </div>
    </div>
    
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['success_message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['error_message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>
    
    <div class="row">
        <!-- Customer Info Card -->
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Thông tin cá nhân</h5>
                    <span class="badge <?php echo $customer['trang_thai'] ? 'bg-success' : 'bg-danger'; ?>">
                        <?php echo $customer['trang_thai'] ? 'Đang hoạt động' : 'Đã khóa'; ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <?php if (!empty($customer['anh_dai_dien'])): ?>
                            <img src="../uploads/users/<?php echo $customer['anh_dai_dien']; ?>" alt="Profile" class="rounded-circle img-thumbnail" style="width: 120px; height: 120px; object-fit: cover;">
                        <?php else: ?>
                            <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center mx-auto" style="width: 120px; height: 120px; font-size: 3rem;">
                                <i class="bi bi-person"></i>
                            </div>
                        <?php endif; ?>
                        <h5 class="mt-3"><?php echo htmlspecialchars($customer['tenuser']); ?></h5>
                        <p class="text-muted"><?php echo htmlspecialchars($customer['taikhoan']); ?></p>
                    </div>
                    
                    <hr>
                    
                    <div class="mb-3">
                        <h6 class="text-muted">Thông tin liên hệ</h6>
                        <p class="mb-1"><i class="bi bi-envelope me-2"></i> <?php echo htmlspecialchars($customer['email'] ?: 'Chưa cập nhật'); ?></p>
                        <p class="mb-1"><i class="bi bi-telephone me-2"></i> <?php echo htmlspecialchars($customer['sdt']); ?></p>
                        <p class="mb-1"><i class="bi bi-geo-alt me-2"></i> <?php echo htmlspecialchars($customer['diachi'] ?: 'Chưa cập nhật'); ?></p>
                    </div>
                    
                    <hr>
                    
                    <div class="mb-3">
                        <h6 class="text-muted">Thông tin tài khoản</h6>
                        <p class="mb-1"><i class="bi bi-calendar-check me-2"></i> Ngày đăng ký: <?php echo date('d/m/Y H:i', strtotime($customer['ngay_tao'])); ?></p>
                        <p class="mb-1">
                            <i class="bi bi-shield-check me-2"></i> 
                            Trạng thái xác thực: 
                            <?php if ($customer['trang_thai_xac_thuc']): ?>
                                <span class="badge bg-success">Đã xác thực</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">Chưa xác thực</span>
                            <?php endif; ?>
                        </p>
                        <p class="mb-1">
                            <i class="bi bi-person-badge me-2"></i> 
                            Loại tài khoản: 
                            <?php if ($customer['loai_user'] == 0): ?>
                                <span class="badge bg-info">Người mua</span>
                            <?php else: ?>
                                <span class="badge bg-primary">Người bán</span>
                            <?php endif; ?>
                        </p>

                        <?php if ($customer['loai_user'] == 1): ?>
                        <hr>
                        <div class="mb-3">
                            <h6 class="text-muted">Thông tin cửa hàng</h6>
                            <p class="mb-1"><strong><?php echo htmlspecialchars($customer['ten_shop'] ?: 'Chưa đặt tên shop'); ?></strong></p>
                            <p class="mb-1"><?php echo $customer['mo_ta_shop'] ? nl2br(htmlspecialchars($customer['mo_ta_shop'])) : 'Chưa có mô tả'; ?></p>
                            <p class="mb-1">
                                <i class="bi bi-calendar3 me-2"></i> Ngày trở thành người bán: 
                                <?php echo $customer['ngay_tro_thanh_nguoi_ban'] ? date('d/m/Y', strtotime($customer['ngay_tro_thanh_nguoi_ban'])) : 'N/A'; ?>
                            </p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-footer bg-white">
                    <div class="d-flex justify-content-between">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="resetPasswordBtn" 
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

                        <button type="button" class="btn btn-sm btn-outline-primary change-user-type"
                                data-id="<?php echo $customer['id_user']; ?>"
                                data-type="<?php echo $customer['loai_user']; ?>"
                                data-bs-toggle="modal" 
                                data-bs-target="#changeUserTypeModal">
                            <?php if ($customer['loai_user'] == 0): ?>
                                <i class="bi bi-shop"></i> Nâng cấp thành người bán
                            <?php else: ?>
                                <i class="bi bi-person"></i> Chuyển thành người mua
                            <?php endif; ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Customer Activity Card -->
        <div class="col-md-8 mb-4">
            <div class="card h-100">
                <div class="card-header bg-white py-3">
                    <ul class="nav nav-tabs card-header-tabs" id="customerTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="orders-tab" data-bs-toggle="tab" data-bs-target="#orders" type="button" role="tab" aria-controls="orders" aria-selected="true">
                                Đơn hàng <span class="badge bg-secondary ms-1"><?php echo $orders_result->num_rows; ?></span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="reviews-tab" data-bs-toggle="tab" data-bs-target="#reviews" type="button" role="tab" aria-controls="reviews" aria-selected="false">
                                Đánh giá
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="activity-tab" data-bs-toggle="tab" data-bs-target="#activity" type="button" role="tab" aria-controls="activity" aria-selected="false">
                                Hoạt động
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="notes-tab" data-bs-toggle="tab" data-bs-target="#notes" type="button" role="tab" aria-controls="notes" aria-selected="false">
                                Ghi chú
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="customerTabContent">
                        <!-- Orders Tab -->
                        <div class="tab-pane fade show active" id="orders" role="tabpanel" aria-labelledby="orders-tab">
                            <?php if ($orders_result->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Ngày đặt</th>
                                                <th>Sản phẩm</th>
                                                <th>Tổng tiền</th>
                                                <th>Trạng thái</th>
                                                <th>Hành động</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($order = $orders_result->fetch_assoc()): ?>
                                                <tr>
                                                    <td>#<?php echo $order['id_donhang']; ?></td>
                                                    <td><?php echo date('d/m/Y H:i', strtotime($order['ngaytao'])); ?></td>
                                                    <td><?php echo $order['total_items']; ?> sản phẩm</td>
                                                    <td><?php echo number_format($order['tongtien'], 0, ',', '.'); ?>₫</td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $order_statuses[$order['trangthai']]['badge']; ?>">
                                                            <?php echo $order_statuses[$order['trangthai']]['name']; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <a href="order-detail.php?id=<?php echo $order['id_donhang']; ?>" class="btn btn-sm btn-outline-primary">
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
                                    <i class="bi bi-cart text-muted" style="font-size: 3rem;"></i>
                                    <p class="mt-3 text-muted">Khách hàng chưa có đơn hàng nào</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Reviews Tab -->
                        <div class="tab-pane fade" id="reviews" role="tabpanel" aria-labelledby="reviews-tab">
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
                                <div class="list-group">
                                    <?php while ($review = $reviews_result->fetch_assoc()): ?>
                                        <div class="list-group-item list-group-item-action">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($review['tensanpham']); ?></h6>
                                                <small><?php echo date('d/m/Y', strtotime($review['ngaydanhgia'])); ?></small>
                                            </div>
                                            <p class="mb-1"><?php echo htmlspecialchars($review['noidung']); ?></p>
                                            <div>
                                                <?php for ($i = 0; $i < 5; $i++): ?>
                                                    <?php if ($i < $review['diemdanhgia']): ?>
                                                        <i class="bi bi-star-fill text-warning"></i>
                                                    <?php else: ?>
                                                        <i class="bi bi-star text-warning"></i>
                                                    <?php endif; ?>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="bi bi-star text-muted" style="font-size: 3rem;"></i>
                                    <p class="mt-3 text-muted">Khách hàng chưa có đánh giá nào</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Activity Tab -->
                        <div class="tab-pane fade" id="activity" role="tabpanel" aria-labelledby="activity-tab">
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i> Chức năng đang được phát triển
                            </div>
                        </div>
                        
                        <!-- Notes Tab -->
                        <div class="tab-pane fade" id="notes" role="tabpanel" aria-labelledby="notes-tab">
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
                            
                            <form id="addNoteForm" action="process_customer_note.php" method="post" class="mb-4">
                                <input type="hidden" name="customer_id" value="<?php echo $customer_id; ?>">
                                <div class="mb-3">
                                    <label for="noteContent" class="form-label">Thêm ghi chú mới</label>
                                    <textarea class="form-control" id="noteContent" name="note" rows="3" required></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Lưu ghi chú
                                </button>
                            </form>
                            
                            <hr>
                            
                            <div class="notes-list">
                                <?php if ($notes_result->num_rows > 0): ?>
                                    <?php while ($note = $notes_result->fetch_assoc()): ?>
                                        <div class="card mb-3">
                                            <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                                                <span><i class="bi bi-person-circle me-2"></i><?php echo htmlspecialchars($note['created_by']); ?></span>
                                                <small><?php echo date('d/m/Y H:i', strtotime($note['created_at'])); ?></small>
                                            </div>
                                            <div class="card-body py-2">
                                                <p class="card-text"><?php echo nl2br(htmlspecialchars($note['note'])); ?></p>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="bi bi-sticky text-muted" style="font-size: 2rem;"></i>
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
</main>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Đặt lại mật khẩu</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="resetPasswordForm" action="process_reset_password.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="customer_id" id="resetPasswordUserId" value="<?php echo $customer_id; ?>">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> 
                        Hành động này sẽ tạo một mật khẩu ngẫu nhiên mới cho người dùng.
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
                        <i class="bi bi-check-lg"></i> Xác nhận
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
                    document.getElementById("toggleStatusSubmitBtn").innerHTML = \'<i class="bi bi-unlock-fill"></i> Mở khóa tài khoản\';
                    document.getElementById("toggleStatusSubmitBtn").className = "btn btn-success";
                } else {
                    // Locking
                    document.getElementById("toggleStatusTitle").textContent = "Khóa tài khoản";
                    document.getElementById("lockAccountContent").classList.remove("d-none");
                    document.getElementById("unlockAccountContent").classList.add("d-none");
                    document.getElementById("toggleStatusSubmitBtn").innerHTML = \'<i class="bi bi-lock-fill"></i> Khóa tài khoản\';
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
