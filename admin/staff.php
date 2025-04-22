<?php
require_once '../config/database.php';  // Uses PDO connection
require_once 'includes/functions.php';
require_once 'includes/auth_check.php';

// Handle AJAX request for staff activity - This must be at the top of the file
if (isset($_GET['action']) && $_GET['action'] == 'get_activity' && isset($_GET['staff_id'])) {
    header('Content-Type: application/json');
    
    $staff_id = (int)$_GET['staff_id'];
    $filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
    
    // Build date filter condition
    $date_filter = '';
    switch ($filter) {
        case 'today':
            $date_filter = "AND DATE(ngay_tao) = CURDATE()";
            break;
        case 'week':
            $date_filter = "AND ngay_tao >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $date_filter = "AND ngay_tao >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            break;
        default:
            $date_filter = ""; // All time
    }
    
    try {
        // Get staff info first
        $staff_stmt = $conn->prepare("SELECT ten FROM users WHERE id = ? AND loai_user > 0");
        $staff_stmt->execute([$staff_id]);
        $staff = $staff_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$staff) {
            echo json_encode(['error' => 'Nhân viên không tồn tại']);
            exit;
        }
        
        // Get activity logs for this staff
        $sql = "SELECT * FROM nhat_ky 
                WHERE id_user = ? $date_filter
                ORDER BY ngay_tao DESC 
                LIMIT 100";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$staff_id]);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format logs for display
        $formatted_logs = [];
        foreach ($logs as $log) {
            $formatted_logs[] = [
                'id' => $log['id'],
                'action' => getActionLabel($log['hanh_dong']),
                'object_type' => getObjectTypeLabel($log['doi_tuong_loai']),
                'object_id' => $log['doi_tuong_id'],
                'details' => $log['chi_tiet'],
                'ip' => $log['ip_address'],
                'date' => date('d/m/Y H:i:s', strtotime($log['ngay_tao']))
            ];
        }
        
        echo json_encode([
            'staff' => $staff,
            'logs' => $formatted_logs,
            'count' => count($logs)
        ]);
        exit;
        
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

// Set current page for sidebar highlighting
$current_page = 'staff';
$page_title = 'Quản lý nhân viên';

// Handle staff activation/deactivation
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $action = $_GET['action'];
    
    if ($action === 'activate') {
        // Activate staff - PDO style
        $stmt = $conn->prepare("UPDATE users SET trang_thai = 1 WHERE id = ? AND loai_user > 0");
        $stmt->execute([$id]);
        log_activity('unlock', 'admin', $id, "Đã kích hoạt tài khoản nhân viên ID: $id");
        set_flash_message('success', 'Kích hoạt tài khoản thành công!');
    } elseif ($action === 'deactivate') {
        // Cannot deactivate self
        if ($id == $_SESSION['admin_id']) {
            set_flash_message('error', 'Không thể vô hiệu hóa tài khoản của chính bạn!');
        } else {
            // Deactivate staff - PDO style
            $stmt = $conn->prepare("UPDATE users SET trang_thai = 0 WHERE id = ? AND loai_user > 0");
            $stmt->execute([$id]);
            log_activity('disable', 'admin', $id, "Đã vô hiệu hóa tài khoản nhân viên ID: $id");
            set_flash_message('success', 'Vô hiệu hóa tài khoản thành công!');
        }
    } elseif ($action === 'delete') {
        // Cannot delete self
        if ($id == $_SESSION['admin_id']) {
            set_flash_message('error', 'Không thể xóa tài khoản của chính bạn!');
        } else {
            // Check if the staff exists and is not an admin
            $stmt = $conn->prepare("SELECT loai_user FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Only allow deletion of non-admin staff (loai_user = 1)
            if ($user && $user['loai_user'] == 1) {
                $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND loai_user = 1");
                $stmt->execute([$id]);
                log_activity('delete', 'admin', $id, "Đã xóa tài khoản nhân viên ID: $id");
                set_flash_message('success', 'Xóa tài khoản nhân viên thành công!');
            } else {
                set_flash_message('error', 'Không thể xóa tài khoản quản trị viên!');
            }
        }
    }
    
    header("Location: staff.php");
    exit();
}

// Handle password reset
if (isset($_POST['reset_password'])) {
    $staff_id = (int)$_POST['staff_id'];
    $new_password = $_POST['new_password'];
    
    if (strlen($new_password) < 6) {
        set_flash_message('error', 'Mật khẩu phải có ít nhất 6 ký tự!');
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("UPDATE users SET matkhau = ? WHERE id = ? AND loai_user > 0");
        $stmt->execute([$hashed_password, $staff_id]);
        
        log_activity('reset_password', 'admin', $staff_id, "Đã đặt lại mật khẩu cho nhân viên ID: $staff_id");
        set_flash_message('success', 'Đặt lại mật khẩu thành công!');
    }
    
    header("Location: staff.php");
    exit();
}

// Handle staff creation/update
if (isset($_POST['save_staff'])) {
    $staff_id = isset($_POST['staff_id']) ? (int)$_POST['staff_id'] : 0;
    $username = trim($_POST['username']);
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $role = (int)$_POST['role'];
    $status = isset($_POST['status']) ? 1 : 0;
    
    // Validation
    $errors = [];
    
    if (empty($username)) $errors[] = "Tên đăng nhập không được để trống!";
    if (empty($name)) $errors[] = "Tên nhân viên không được để trống!";
    if (empty($email)) $errors[] = "Email không được để trống!";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Email không hợp lệ!";
    
    // Check username and email uniqueness
    if ($staff_id === 0) { // New staff
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE taikhoan = ?");
        $stmt->execute([$username]);
        if ($stmt->fetchColumn() > 0) $errors[] = "Tên đăng nhập đã tồn tại!";
        
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) $errors[] = "Email đã tồn tại!";
    } else { // Update staff
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE taikhoan = ? AND id != ?");
        $stmt->execute([$username, $staff_id]);
        if ($stmt->fetchColumn() > 0) $errors[] = "Tên đăng nhập đã tồn tại!";
        
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $staff_id]);
        if ($stmt->fetchColumn() > 0) $errors[] = "Email đã tồn tại!";
    }
    
    if (!empty($errors)) {
        foreach ($errors as $error) {
            set_flash_message('error', $error);
        }
    } else {
        if ($staff_id === 0) { // Creating new staff
            $password = isset($_POST['password']) ? trim($_POST['password']) : '';
            if (strlen($password) < 6) {
                set_flash_message('error', 'Mật khẩu phải có ít nhất 6 ký tự!');
                header("Location: staff.php");
                exit();
            }
            
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("INSERT INTO users (taikhoan, matkhau, ten, email, sodienthoai, loai_user, trang_thai, ngay_tao) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$username, $hashed_password, $name, $email, $phone, $role, $status]);
            
            $new_staff_id = $conn->lastInsertId();
            log_activity('create', 'admin', $new_staff_id, "Thêm nhân viên mới: $name");
            set_flash_message('success', 'Thêm nhân viên mới thành công!');
            
        } else { // Updating existing staff
            // If updating self, cannot change role or status
            if ($staff_id == $_SESSION['admin_id']) {
                // Get current role
                $stmt = $conn->prepare("SELECT loai_user FROM users WHERE id = ?");
                $stmt->execute([$staff_id]);
                $current_role = $stmt->fetchColumn();
                
                $role = $current_role; // Keep current role
                $status = 1; // Keep active
            }
            
            $stmt = $conn->prepare("UPDATE users SET taikhoan = ?, ten = ?, email = ?, sodienthoai = ?, loai_user = ?, trang_thai = ? WHERE id = ?");
            $stmt->execute([$username, $name, $email, $phone, $role, $status, $staff_id]);
            
            log_activity('update', 'admin', $staff_id, "Cập nhật thông tin nhân viên: $name (ID: $staff_id)");
            set_flash_message('success', 'Cập nhật thông tin nhân viên thành công!');
        }
    }
    
    header("Location: staff.php");
    exit();
}

// Fetch staff members
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$role_filter = isset($_GET['role']) ? $_GET['role'] : 'all';

$where_clauses = ["loai_user > 0"]; // Only get staff (not customers)
$params = [];

if (!empty($search)) {
    $where_clauses[] = "(ten LIKE ? OR taikhoan LIKE ? OR email LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
}

if ($status_filter !== 'all') {
    $where_clauses[] = "trang_thai = ?";
    $params[] = (int)$status_filter;
}

if ($role_filter !== 'all') {
    $where_clauses[] = "loai_user = ?";
    $params[] = (int)$role_filter;
}

$where_clause = implode(' AND ', $where_clauses);
$sql = "SELECT * FROM users WHERE $where_clause ORDER BY id DESC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$staff_members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Include header
include 'includes/header.php';

// Extra CSS for this page
$extra_css = '
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
    .staff-avatar {
        width: 45px;
        height: 45px;
        object-fit: cover;
        border-radius: 50%;
    }
    .action-btn {
        width: 32px;
        height: 32px;
        padding: 0;
        line-height: 32px;
        text-align: center;
    }
    .search-box {
        max-width: 300px;
    }
    .filter-box {
        max-width: 200px;
    }
</style>';

// Extra JS for this page - now using external file
$extra_js = '<script src="js/staff.js"></script>';

?>

<?php include 'includes/sidebar.php'; ?>

<!-- Main content -->
<main class="col-md-10 ms-sm-auto col-lg-10 px-md-4 py-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Quản lý nhân viên</h1>
        <div>
            <button id="add_staff_btn" class="btn btn-primary">
                <i class="fas fa-plus"></i> Thêm nhân viên
            </button>
        </div>
    </div>
    
    <!-- Flash messages -->
    <?php display_flash_message(); ?>
    
    <!-- Search and filter form -->
    <div class="row mb-4">
        <div class="col-12">
            <form method="get" action="staff.php" class="d-flex flex-wrap gap-2">
                <div class="search-box">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Tìm kiếm nhân viên..." value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-outline-secondary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                
                <div class="filter-box">
                    <select name="status" id="status_filter" class="form-select">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>Tất cả trạng thái</option>
                        <option value="1" <?php echo $status_filter === '1' ? 'selected' : ''; ?>>Đang hoạt động</option>
                        <option value="0" <?php echo $status_filter === '0' ? 'selected' : ''; ?>>Đã vô hiệu hóa</option>
                    </select>
                </div>
                
                <div class="filter-box">
                    <select name="role" id="role_filter" class="form-select">
                        <option value="all" <?php echo $role_filter === 'all' ? 'selected' : ''; ?>>Tất cả vai trò</option>
                        <option value="1" <?php echo $role_filter === '1' ? 'selected' : ''; ?>>Nhân viên</option>
                        <option value="2" <?php echo $role_filter === '2' ? 'selected' : ''; ?>>Quản trị viên</option>
                    </select>
                </div>
                
                <?php if (!empty($search) || $status_filter !== 'all' || $role_filter !== 'all'): ?>
                <div class="ms-2">
                    <a href="staff.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i> Xóa bộ lọc
                    </a>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
    
    <!-- Staff list -->
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">ID</th>
                            <th scope="col">Tên nhân viên</th>
                            <th scope="col">Tên đăng nhập</th>
                            <th scope="col">Email</th>
                            <th scope="col">SĐT</th>
                            <th scope="col">Vai trò</th>
                            <th scope="col">Trạng thái</th>
                            <th scope="col">Ngày tạo</th>
                            <th scope="col" class="text-end">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($staff_members) > 0): ?>
                            <?php foreach ($staff_members as $staff): ?>
                                <tr>
                                    <td><?php echo $staff['id']; ?></td>
                                    <td><?php echo htmlspecialchars($staff['ten']); ?></td>
                                    <td><?php echo htmlspecialchars($staff['taikhoan']); ?></td>
                                    <td><?php echo htmlspecialchars($staff['email']); ?></td>
                                    <td><?php echo $staff['sodienthoai'] ? htmlspecialchars($staff['sodienthoai']) : '--'; ?></td>
                                    <td>
                                        <?php if ($staff['loai_user'] == 2): ?>
                                            <span class="badge bg-primary">Quản trị viên</span>
                                        <?php else: ?>
                                            <span class="badge bg-info">Nhân viên</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($staff['trang_thai'] == 1): ?>
                                            <span class="badge bg-success">Hoạt động</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Vô hiệu</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($staff['ngay_tao'])); ?></td>
                                    <td>
                                        <div class="d-flex justify-content-end gap-1">
                                            <button class="btn btn-sm btn-outline-primary edit-staff-btn" 
                                                data-staff-id="<?php echo $staff['id']; ?>"
                                                data-staff-name="<?php echo htmlspecialchars($staff['ten']); ?>"
                                                data-staff-username="<?php echo htmlspecialchars($staff['taikhoan']); ?>"
                                                data-staff-email="<?php echo htmlspecialchars($staff['email']); ?>"
                                                data-staff-phone="<?php echo htmlspecialchars($staff['sodienthoai'] ?? ''); ?>"
                                                data-staff-role="<?php echo $staff['loai_user']; ?>"
                                                data-staff-status="<?php echo $staff['trang_thai']; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            
                                            <button class="btn btn-sm btn-outline-warning reset-password-btn"
                                                data-staff-id="<?php echo $staff['id']; ?>"
                                                data-staff-name="<?php echo htmlspecialchars($staff['ten']); ?>">
                                                <i class="fas fa-key"></i>
                                            </button>
                                            
                                            <!-- Add Activity Log Button -->
                                            <button class="btn btn-sm btn-outline-info view-activity-btn"
                                                data-staff-id="<?php echo $staff['id']; ?>"
                                                data-staff-name="<?php echo htmlspecialchars($staff['ten']); ?>">
                                                <i class="fas fa-history"></i>
                                            </button>
                                            
                                            <?php if ($staff['id'] != $_SESSION['admin_id']): ?>
                                                <?php if ($staff['trang_thai'] == 1): ?>
                                                    <a href="staff.php?action=deactivate&id=<?php echo $staff['id']; ?>" 
                                                       class="btn btn-sm btn-outline-danger" 
                                                       onclick="return confirm('Bạn có chắc chắn muốn vô hiệu hóa nhân viên này?');">
                                                        <i class="fas fa-ban"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <a href="staff.php?action=activate&id=<?php echo $staff['id']; ?>" 
                                                       class="btn btn-sm btn-outline-success">
                                                        <i class="fas fa-check"></i>
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <?php if ($staff['loai_user'] == 1): ?>
                                                    <a href="staff.php?action=delete&id=<?php echo $staff['id']; ?>" 
                                                       class="btn btn-sm btn-outline-danger" 
                                                       onclick="return confirm('Bạn có chắc chắn muốn xóa nhân viên này? Hành động này không thể hoàn tác!');">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center py-4">Không tìm thấy nhân viên nào</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- Staff Modal -->
<div class="modal fade" id="staffModal" tabindex="-1" aria-labelledby="staffModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="staffModalLabel">Thêm nhân viên mới</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="staff_form" method="post" action="staff.php">
                    <input type="hidden" id="staff_id" name="staff_id" value="">
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Tên nhân viên <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">Tên đăng nhập <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label">Số điện thoại</label>
                        <input type="tel" class="form-control" id="phone" name="phone">
                    </div>
                    
                    <div class="mb-3" id="password_container">
                        <label for="password" class="form-label">Mật khẩu <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="password" name="password" minlength="6">
                        <div class="form-text">Mật khẩu phải có ít nhất 6 ký tự</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="role" class="form-label">Vai trò <span class="text-danger">*</span></label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="1">Nhân viên</option>
                            <option value="2">Quản trị viên</option>
                        </select>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="status" name="status" value="1" checked>
                        <label class="form-check-label" for="status">Đang hoạt động</label>
                    </div>
                    
                    <div class="text-end">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" name="save_staff" class="btn btn-primary">Thêm mới</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="resetPasswordModalLabel">Đặt lại mật khẩu</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="post" action="staff.php">
                    <input type="hidden" id="reset_staff_id" name="staff_id" value="">
                    
                    <p>Bạn đang đặt lại mật khẩu cho nhân viên: <strong id="reset_staff_name"></strong></p>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">Mật khẩu mới <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                        <div class="form-text">Mật khẩu phải có ít nhất 6 ký tự</div>
                    </div>
                    
                    <div class="text-end">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button type="submit" name="reset_password" class="btn btn-warning">Đặt lại mật khẩu</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Add Staff Activity Modal -->
<div class="modal fade" id="staffActivityModal" tabindex="-1" aria-labelledby="staffActivityModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="staffActivityModalLabel">Hoạt động của nhân viên</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0" id="activity-staff-name"></h6>
                    <div class="d-flex align-items-center">
                        <label for="activity-date-filter" class="me-2">Lọc theo ngày:</label>
                        <select class="form-select form-select-sm" id="activity-date-filter">
                            <option value="all">Tất cả</option>
                            <option value="today">Hôm nay</option>
                            <option value="week">Tuần này</option>
                            <option value="month">Tháng này</option>
                        </select>
                    </div>
                </div>
                
                <div id="staff-activity-container">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Đang tải...</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Existing code...
    
    // Staff Activity Functionality
    const viewActivityButtons = document.querySelectorAll('.view-activity-btn');
    const staffActivityModal = new bootstrap.Modal(document.getElementById('staffActivityModal'));
    const staffActivityContainer = document.getElementById('staff-activity-container');
    const activityStaffName = document.getElementById('activity-staff-name');
    const activityDateFilter = document.getElementById('activity-date-filter');
    let currentStaffId = null;
    
    viewActivityButtons.forEach(button => {
        button.addEventListener('click', function() {
            const staffId = this.getAttribute('data-staff-id');
            const staffName = this.getAttribute('data-staff-name');
            currentStaffId = staffId;
            
            activityStaffName.textContent = `Nhân viên: ${staffName}`;
            activityDateFilter.value = 'all'; // Reset filter
            
            loadStaffActivity(staffId, 'all');
            staffActivityModal.show();
        });
    });
    
    activityDateFilter.addEventListener('change', function() {
        if (currentStaffId) {
            loadStaffActivity(currentStaffId, this.value);
        }
    });
    
    function loadStaffActivity(staffId, filter) {
        staffActivityContainer.innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Đang tải...</span>
                </div>
            </div>
        `;
        
        // Updated to use the new AJAX endpoint
        fetch(`ajax/staff_activity.php?staff_id=${staffId}&filter=${filter}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    staffActivityContainer.innerHTML = `
                        <div class="alert alert-danger">
                            ${data.error}
                        </div>
                    `;
                    return;
                }
                
                if (data.logs.length === 0) {
                    staffActivityContainer.innerHTML = `
                        <div class="alert alert-info">
                            Không có hoạt động nào được ghi nhận.
                        </div>
                    `;
                    return;
                }
                
                let html = `
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>Thời gian</th>
                                    <th>Hành động</th>
                                    <th>Đối tượng</th>
                                    <th>Chi tiết</th>
                                    <th>IP</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                
                data.logs.forEach(log => {
                    html += `
                        <tr>
                            <td>${log.date}</td>
                            <td><span class="badge bg-${getActionBadgeColor(log.action)}">${log.action}</span></td>
                            <td>${log.object_type} ${log.object_id ? '#' + log.object_id : ''}</td>
                            <td>${log.details ?? ''}</td>
                            <td><small>${log.ip ?? ''}</small></td>
                        </tr>
                    `;
                });
                
                html += `
                            </tbody>
                        </table>
                    </div>
                `;
                
                staffActivityContainer.innerHTML = html;
            })
            .catch(error => {
                staffActivityContainer.innerHTML = `
                    <div class="alert alert-danger">
                        Lỗi khi tải dữ liệu: ${error.message}
                    </div>
                `;
            });
    }
    
    function getActionBadgeColor(action) {
        const colors = {
            'Thêm mới': 'success',
            'Cập nhật': 'primary',
            'Xóa': 'danger',
            'Đăng nhập': 'info',
            'Đăng xuất': 'secondary',
            'Ẩn': 'warning',
            'Hiển thị': 'success',
            'Đặt nổi bật': 'primary',
            'Bỏ nổi bật': 'secondary',
            'Khóa': 'danger',
            'Mở khóa': 'success',
            'Vô hiệu hóa': 'danger',
            'Đặt lại mật khẩu': 'warning',
            'Cập nhật trạng thái': 'info'
        };
        
        return colors[action] || 'secondary';
    }
});
</script>

<?php include 'includes/footer.php'; ?>
