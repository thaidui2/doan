<?php
// Set page title
$page_title = 'Quản lý tài khoản quản trị';

// Include header (with authentication check)
include('includes/header.php');

// Include database connection
include('../config/config.php');

// Check if user has admin permission
if ($admin_level < 2) { // Only super admin can access
    $_SESSION['error_message'] = "Bạn không có quyền truy cập trang này.";
    header('Location: index.php');
    exit();
}

// Process form actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Delete admin
    if (isset($_POST['delete_admin'])) {
        $admin_id = (int)$_POST['admin_id'];
        
        // Prevent deleting your own account
        if ($admin_id === $_SESSION['admin_id']) {
            $_SESSION['error_message'] = "Bạn không thể xóa tài khoản của chính mình!";
        } else {
            // Check if user exists and is an admin
            $check_query = $conn->prepare("SELECT id, ten FROM users WHERE id = ? AND (loai_user = 1 OR loai_user = 2)");
            $check_query->bind_param("i", $admin_id);
            $check_query->execute();
            $result = $check_query->get_result();
            
            if ($result->num_rows > 0) {
                $admin_info = $result->fetch_assoc();
                
                // Delete admin
                $delete_query = $conn->prepare("DELETE FROM users WHERE id = ?");
                $delete_query->bind_param("i", $admin_id);
                
                if ($delete_query->execute()) {
                    // Delete related permissions
                    $conn->query("DELETE FROM quyen_han WHERE id_user = $admin_id");
                    
                    // Log the activity
                    $details = "Xóa tài khoản quản trị: " . $admin_info['ten'] . " (ID: $admin_id)";
                    logAdminActivity($conn, $_SESSION['admin_id'], 'delete', 'admin', $admin_id, $details);
                    
                    $_SESSION['success_message'] = "Đã xóa tài khoản quản trị thành công!";
                } else {
                    $_SESSION['error_message'] = "Không thể xóa tài khoản: " . $conn->error;
                }
            } else {
                $_SESSION['error_message'] = "Không tìm thấy tài khoản quản trị!";
            }
        }
        
        header('Location: admins.php');
        exit();
    }
    
    // Toggle admin status
    if (isset($_POST['toggle_status'])) {
        $admin_id = (int)$_POST['admin_id'];
        $new_status = (int)$_POST['new_status'];
        
        // Prevent toggling your own account
        if ($admin_id === $_SESSION['admin_id']) {
            $_SESSION['error_message'] = "Bạn không thể thay đổi trạng thái tài khoản của chính mình!";
        } else {
            $status_query = $conn->prepare("UPDATE users SET trang_thai = ? WHERE id = ? AND (loai_user = 1 OR loai_user = 2)");
            $status_query->bind_param("ii", $new_status, $admin_id);
            
            if ($status_query->execute() && $status_query->affected_rows > 0) {
                $status_text = $new_status ? 'kích hoạt' : 'vô hiệu hóa';
                
                // Get admin name for log
                $name_query = $conn->prepare("SELECT ten FROM users WHERE id = ?");
                $name_query->bind_param("i", $admin_id);
                $name_query->execute();
                $admin_name = $name_query->get_result()->fetch_assoc()['ten'];
                
                // Log the activity
                $details = "Đã $status_text tài khoản quản trị: $admin_name (ID: $admin_id)";
                logAdminActivity($conn, $_SESSION['admin_id'], 'update', 'admin', $admin_id, $details);
                
                $_SESSION['success_message'] = "Đã $status_text tài khoản quản trị thành công!";
            } else {
                $_SESSION['error_message'] = "Không thể thay đổi trạng thái tài khoản: " . $conn->error;
            }
        }
        
        header('Location: admins.php');
        exit();
    }
}

// Get search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? (int)$_GET['role'] : -1;
$status_filter = isset($_GET['status']) ? (int)$_GET['status'] : -1; // Fixed: removed extra closing parenthesis

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Base query
$query = "SELECT * FROM users WHERE loai_user > 0"; // Only get admins (loai_user = 1 or 2)

// Apply filters
if (!empty($search)) {
    $search_term = '%' . $conn->real_escape_string($search) . '%';
    $query .= " AND (ten LIKE '$search_term' OR email LIKE '$search_term' OR taikhoan LIKE '$search_term')";
}

if ($role_filter !== -1) {
    $query .= " AND loai_user = $role_filter";
}

if ($status_filter !== -1) {
    $query .= " AND trang_thai = $status_filter";
}

// Count total for pagination
$count_query = $conn->query($query); // Fixed: Removed extra parenthesis here
$total_admins = $count_query->num_rows;
$total_pages = ceil($total_admins / $limit);

// Add sorting and pagination
$query .= " ORDER BY loai_user DESC, id ASC LIMIT $offset, $limit";

// Execute the query
$admins = $conn->query($query);
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2"><i class="bi bi-people-fill me-2"></i>Quản lý tài khoản quản trị</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="add_admin.php" class="btn btn-sm btn-primary">
                <i class="bi bi-person-plus-fill"></i> Thêm quản trị viên
            </a>
        </div>
    </div>
    
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-1"></i> <?php echo $_SESSION['success_message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-1"></i> <?php echo $_SESSION['error_message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>
    
    <!-- Filter Form -->
    <div class="card mb-4 shadow-sm">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0">Tìm kiếm và lọc</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="admins.php" class="row g-3">
                <div class="col-md-4">
                    <label for="search" class="form-label">Tìm kiếm</label>
                    <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Tên, email, tài khoản...">
                </div>
                
                <div class="col-md-3">
                    <label for="role" class="form-label">Vai trò</label>
                    <select class="form-select" id="role" name="role">
                        <option value="-1">Tất cả vai trò</option>
                        <option value="2" <?php echo $role_filter === 2 ? 'selected' : ''; ?>>Quản trị viên</option>
                        <option value="1" <?php echo $role_filter === 1 ? 'selected' : ''; ?>>Nhân viên</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="status" class="form-label">Trạng thái</label>
                    <select class="form-select" id="status" name="status">
                        <option value="-1">Tất cả trạng thái</option>
                        <option value="1" <?php echo $status_filter === 1 ? 'selected' : ''; ?>>Đang hoạt động</option>
                        <option value="0" <?php echo $status_filter === 0 ? 'selected' : ''; ?>>Đã khóa</option>
                    </select>
                </div>
                
                <div class="col-md-2 d-flex align-items-end">
                    <div class="d-grid gap-2 w-100">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> Lọc
                        </button>
                        <a href="admins.php" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle"></i> Xóa lọc
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Admin List -->
    <div class="card shadow-sm">
        <div class="card-header bg-white py-3">
            <div class="row align-items-center">
                <div class="col">
                    <h5 class="mb-0">Danh sách tài khoản quản trị</h5>
                </div>
                <div class="col-auto">
                    <span class="text-muted">Tổng số: <?php echo $total_admins; ?> tài khoản</span>
                </div>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">Tài khoản</th>
                        <th scope="col">Họ tên</th>
                        <th scope="col">Email</th>
                        <th scope="col">Vai trò</th>
                        <th scope="col">Ngày tạo</th>
                        <th scope="col" class="text-center">Trạng thái</th>
                        <th scope="col" class="text-end">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($admins->num_rows > 0): ?>
                        <?php while ($admin = $admins->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $admin['id']; ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if (!empty($admin['anh_dai_dien'])): ?>
                                            <img src="../<?php echo $admin['anh_dai_dien']; ?>" alt="Avatar" width="32" height="32" class="rounded-circle me-2">
                                        <?php else: ?>
                                            <div class="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
                                                <i class="bi bi-person"></i>
                                            </div>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($admin['taikhoan']); ?>
                                        <?php if ($admin['id'] == $_SESSION['admin_id']): ?>
                                            <span class="badge bg-primary ms-2">Bạn</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($admin['ten']); ?></td>
                                <td><?php echo htmlspecialchars($admin['email'] ?? 'Không có'); ?></td>
                                <td>
                                    <?php if ($admin['loai_user'] == 2): ?>
                                        <span class="badge bg-danger">Quản trị viên</span>
                                    <?php elseif ($admin['loai_user'] == 1): ?>
                                        <span class="badge bg-success">Nhân viên</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($admin['ngay_tao'])); ?></td>
                                <td class="text-center">
                                    <?php if ($admin['trang_thai'] == 1): ?>
                                        <span class="badge bg-success">Đang hoạt động</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Đã khóa</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <a href="edit_admin.php?id=<?php echo $admin['id']; ?>" class="btn btn-outline-primary" title="Chỉnh sửa">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        
                                        <?php if ($admin['id'] != $_SESSION['admin_id']): ?>
                                            <!-- Status Toggle Button -->
                                            <form method="post" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn <?php echo $admin['trang_thai'] ? 'khóa' : 'kích hoạt'; ?> tài khoản này?');">
                                                <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                                                <input type="hidden" name="new_status" value="<?php echo $admin['trang_thai'] ? '0' : '1'; ?>">
                                                <button type="submit" name="toggle_status" class="btn btn-outline-<?php echo $admin['trang_thai'] ? 'warning' : 'success'; ?>" title="<?php echo $admin['trang_thai'] ? 'Khóa tài khoản' : 'Kích hoạt tài khoản'; ?>">
                                                    <i class="bi bi-<?php echo $admin['trang_thai'] ? 'lock' : 'unlock'; ?>"></i>
                                                </button>
                                            </form>
                                            
                                            <!-- Delete Button -->
                                            <form method="post" class="d-inline" onsubmit="return confirm('Bạn có chắc muốn xóa tài khoản này? Hành động này không thể hoàn tác!');">
                                                <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                                                <button type="submit" name="delete_admin" class="btn btn-outline-danger" title="Xóa tài khoản">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-4">
                                <div class="text-muted">
                                    <i class="bi bi-search display-6 d-block mb-3"></i>
                                    <p>Không tìm thấy tài khoản quản trị nào</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="card-footer bg-white py-3">
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center mb-0">
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo $role_filter; ?>&status=<?php echo $status_filter; ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>
                        
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $start_page + 4);
                        
                        if ($end_page - $start_page < 4) {
                            $start_page = max(1, $end_page - 4);
                        }
                        
                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo $role_filter; ?>&status=<?php echo $status_filter; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo $role_filter; ?>&status=<?php echo $status_filter; ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include('includes/footer.php'); ?>
