<?php
// Set page title
$page_title = 'Quản lý nhân viên';

// Include header (will check for login)
include('includes/header.php');

// Include database connection
include('../config/config.php');

// Check if user has permission to view admins
checkPermissionRedirect('admin_view');

// Variables for filtering and searching
$search_keyword = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? (int)$_GET['status'] : -1;
$level_filter = isset($_GET['level']) ? (int)$_GET['level'] : -1;
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'id_admin';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'ASC';

// Build query
$query = "SELECT a.*, GROUP_CONCAT(r.ten_role SEPARATOR ', ') AS roles_list
          FROM admin a
          LEFT JOIN admin_roles ar ON a.id_admin = ar.id_admin
          LEFT JOIN roles r ON ar.id_role = r.id_role";

// Add search conditions
$where_conditions = [];
if (!empty($search_keyword)) {
    $search_keyword = $conn->real_escape_string($search_keyword);
    $where_conditions[] = "(a.ten_admin LIKE '%$search_keyword%' OR a.taikhoan LIKE '%$search_keyword%' OR a.email LIKE '%$search_keyword%')";
}

if ($status_filter !== -1) {
    $where_conditions[] = "a.trang_thai = $status_filter";
}

if ($level_filter !== -1) {
    $where_conditions[] = "a.cap_bac = $level_filter";
}

// Combine conditions
if (!empty($where_conditions)) {
    $query .= " WHERE " . implode(" AND ", $where_conditions);
}

// Add grouping
$query .= " GROUP BY a.id_admin";

// Add sorting
$valid_sort_columns = ['id_admin', 'ho_ten', 'ten_dang_nhap', 'email', 'cap_bac', 'trang_thai'];
if (!in_array($sort_by, $valid_sort_columns)) {
    $sort_by = 'id_admin';
}

$sort_order = ($sort_order === 'DESC') ? 'DESC' : 'ASC';
$query .= " ORDER BY a.$sort_by $sort_order";

// Pagination
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($current_page - 1) * $per_page;

// Count total rows for pagination
$count_result = $conn->query("SELECT COUNT(*) as total FROM admin");
$total_rows = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $per_page);

// Add limit for pagination
$query .= " LIMIT $offset, $per_page";

// Execute query
$result = $conn->query($query);
?>

<!-- Include sidebar -->
<?php include('includes/sidebar.php'); ?>

<!-- Main content -->
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Quản lý nhân viên</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <?php if (hasPermission('admin_add')): ?>
            <a href="add_admin.php" class="btn btn-sm btn-primary">
                <i class="bi bi-person-plus"></i> Thêm nhân viên mới
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php
    // Display success/error messages if they exist
    if (isset($_SESSION['success_message'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                ' . $_SESSION['success_message'] . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>';
        unset($_SESSION['success_message']);
    }

    if (isset($_SESSION['error_message'])) {
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                ' . $_SESSION['error_message'] . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>';
        unset($_SESSION['error_message']);
    }
    ?>

    <!-- Search and filter form -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3" id="searchForm">
                <div class="col-md-4">
                    <label for="search" class="form-label">Tìm kiếm</label>
                    <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search_keyword); ?>" placeholder="Tên, tài khoản, email...">
                </div>
                <div class="col-md-2">
                    <label for="level" class="form-label">Cấp bậc</label>
                    <select class="form-select" id="level" name="level">
                        <option value="-1" <?php echo $level_filter === -1 ? 'selected' : ''; ?>>Tất cả</option>
                        <option value="3" <?php echo $level_filter === 3 ? 'selected' : ''; ?>>Super Admin</option>
                        <option value="2" <?php echo $level_filter === 2 ? 'selected' : ''; ?>>Admin</option>
                        <option value="1" <?php echo $level_filter === 1 ? 'selected' : ''; ?>>Nhân viên</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label">Trạng thái</label>
                    <select class="form-select" id="status" name="status">
                        <option value="-1" <?php echo $status_filter === -1 ? 'selected' : ''; ?>>Tất cả</option>
                        <option value="1" <?php echo $status_filter === 1 ? 'selected' : ''; ?>>Hoạt động</option>
                        <option value="0" <?php echo $status_filter === 0 ? 'selected' : ''; ?>>Bị khóa</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="sort" class="form-label">Sắp xếp theo</label>
                    <select class="form-select" id="sort" name="sort">
                        <option value="id_admin" <?php echo $sort_by === 'id_admin' ? 'selected' : ''; ?>>ID</option>
                        <option value="ho_ten" <?php echo $sort_by === 'ho_ten' ? 'selected' : ''; ?>>Tên</option>
                        <option value="cap_bac" <?php echo $sort_by === 'cap_bac' ? 'selected' : ''; ?>>Cấp bậc</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="order" class="form-label">Thứ tự</label>
                    <select class="form-select" id="order" name="order">
                        <option value="ASC" <?php echo $sort_order === 'ASC' ? 'selected' : ''; ?>>Tăng dần</option>
                        <option value="DESC" <?php echo $sort_order === 'DESC' ? 'selected' : ''; ?>>Giảm dần</option>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i> Tìm kiếm
                    </button>
                    <a href="admins.php" class="btn btn-secondary ms-2">
                        <i class="bi bi-x-circle"></i> Xóa bộ lọc
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Admins table -->
    <div class="card">
        <div class="card-header bg-white">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Danh sách nhân viên</h5>
                <span class="badge bg-secondary"><?php echo $total_rows; ?> nhân viên</span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">ID</th>
                            <th scope="col">Tên nhân viên</th>
                            <th scope="col">Tài khoản</th>
                            <th scope="col">Email</th>
                            <th scope="col">Vai trò</th>
                            <th scope="col">Cấp bậc</th>
                            <th scope="col">Trạng thái</th>
                            <th scope="col">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($admin = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $admin['id_admin']; ?></td>
                                    <td><?php echo htmlspecialchars($admin['ten_admin']); ?></td>
                                    <td><?php echo htmlspecialchars($admin['taikhoan']); ?></td>
                                    <td><?php echo htmlspecialchars($admin['email'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if (!empty($admin['roles_list'])): ?>
                                            <?php echo htmlspecialchars($admin['roles_list']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Chưa có vai trò</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($admin['cap_bac'] == 3): ?>
                                            <span class="badge bg-danger">Super Admin</span>
                                        <?php elseif ($admin['cap_bac'] == 2): ?>
                                            <span class="badge bg-primary">Admin</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Nhân viên</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($admin['trang_thai'] == 1): ?>
                                            <span class="badge bg-success">Hoạt động</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Bị khóa</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <!-- View admin button -->
                                            <a href="view_admin.php?id=<?php echo $admin['id_admin']; ?>" class="btn btn-outline-primary">
                                                <i class="bi bi-eye"></i> Xem
                                            </a>
                                            
                                            <!-- Edit button, displayed only if current admin has permission and is not trying to edit a Super Admin (unless they are a Super Admin themselves) -->
                                            <?php if (hasPermission('admin_edit') && ($admin['cap_bac'] < 3 || $_SESSION['admin_level'] == 3)): ?>
                                            <a href="edit_admin.php?id=<?php echo $admin['id_admin']; ?>" class="btn btn-outline-secondary">
                                                <i class="bi bi-pencil"></i> Sửa
                                            </a>
                                            <?php endif; ?>
                                            
                                            <!-- Toggle status button, displayed only if current admin has permission and is not trying to modify a Super Admin (unless they are a Super Admin themselves) -->
                                            <?php if (hasPermission('admin_edit') && ($admin['cap_bac'] < 3 || $_SESSION['admin_level'] == 3) && $admin['id_admin'] != $_SESSION['admin_id']): ?>
                                            <button type="button" class="btn btn-outline-<?php echo $admin['trang_thai'] ? 'warning' : 'success'; ?> toggle-status" 
                                                    data-id="<?php echo $admin['id_admin']; ?>" 
                                                    data-status="<?php echo $admin['trang_thai']; ?>"
                                                    data-name="<?php echo htmlspecialchars($admin['ten_admin']); ?>">
                                                <?php if ($admin['trang_thai']): ?>
                                                    <i class="bi bi-lock"></i> Khóa
                                                <?php else: ?>
                                                    <i class="bi bi-unlock"></i> Mở khóa
                                                <?php endif; ?>
                                            </button>
                                            <?php endif; ?>
                                            
                                            <!-- Delete button, displayed only if current admin has delete permission and is a Super Admin, and is not trying to delete themselves or another Super Admin -->
                                            <?php if (hasPermission('admin_delete') && $_SESSION['admin_level'] == 3 && $admin['cap_bac'] < 3 && $admin['id_admin'] != $_SESSION['admin_id']): ?>
                                            <button type="button" class="btn btn-outline-danger delete-admin" 
                                                    data-id="<?php echo $admin['id_admin']; ?>"
                                                    data-name="<?php echo htmlspecialchars($admin['ten_admin']); ?>">
                                                <i class="bi bi-trash"></i> Xóa
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <div class="text-muted">Không tìm thấy nhân viên nào</div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $current_page - 1; ?>&search=<?php echo urlencode($search_keyword); ?>&status=<?php echo $status_filter; ?>&level=<?php echo $level_filter; ?>&sort=<?php echo $sort_by; ?>&order=<?php echo $sort_order; ?>">
                        <i class="bi bi-chevron-left"></i> Trước
                    </a>
                </li>
                
                <?php
                $start_page = max(1, $current_page - 2);
                $end_page = min($total_pages, $current_page + 2);
                
                if ($start_page > 1) {
                    echo '<li class="page-item"><a class="page-link" href="?page=1&search=' . urlencode($search_keyword) . '&status=' . $status_filter . '&level=' . $level_filter . '&sort=' . $sort_by . '&order=' . $sort_order . '">1</a></li>';
                    if ($start_page > 2) {
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                }
                
                for ($i = $start_page; $i <= $end_page; $i++) {
                    echo '<li class="page-item ' . (($i == $current_page) ? 'active' : '') . '">';
                    echo '<a class="page-link" href="?page=' . $i . '&search=' . urlencode($search_keyword) . '&status=' . $status_filter . '&level=' . $level_filter . '&sort=' . $sort_by . '&order=' . $sort_order . '">' . $i . '</a>';
                    echo '</li>';
                }
                
                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) {
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                    echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&search=' . urlencode($search_keyword) . '&status=' . $status_filter . '&level=' . $level_filter . '&sort=' . $sort_by . '&order=' . $sort_order . '">' . $total_pages . '</a></li>';
                }
                ?>
                
                <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $current_page + 1; ?>&search=<?php echo urlencode($search_keyword); ?>&status=<?php echo $status_filter; ?>&level=<?php echo $level_filter; ?>&sort=<?php echo $sort_by; ?>&order=<?php echo $sort_order; ?>">
                        Tiếp <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</main>

<!-- Toggle Status Modal -->
<div class="modal fade" id="toggleStatusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="toggleStatusTitle">Thay đổi trạng thái</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="toggleStatusForm" action="process_admin_status.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="admin_id" id="toggleStatusAdminId" value="">
                    <input type="hidden" name="new_status" id="toggleStatusNewStatus" value="">
                    <p id="toggleStatusMessage"></p>
                    
                    <div id="lockReasonContainer" class="mb-3">
                        <label for="lockReason" class="form-label">Lý do khóa tài khoản</label>
                        <textarea class="form-control" id="lockReason" name="reason" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn" id="toggleStatusSubmitBtn">Xác nhận</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Admin Modal -->
<div class="modal fade" id="deleteAdminModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Xác nhận xóa nhân viên</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="deleteAdminForm" action="process_admin_delete.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="admin_id" id="deleteAdminId" value="">
                    <p>Bạn có chắc chắn muốn xóa nhân viên <strong id="deleteAdminName"></strong>?</p>
                    <p class="text-danger">Lưu ý: Hành động này không thể hoàn tác và có thể ảnh hưởng đến tính toàn vẹn của dữ liệu!</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-danger">Xác nhận xóa</button>
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
        // Toggle admin status
        const toggleStatusButtons = document.querySelectorAll(".toggle-status");
        toggleStatusButtons.forEach(button => {
            button.addEventListener("click", function() {
                const adminId = this.getAttribute("data-id");
                const currentStatus = parseInt(this.getAttribute("data-status"));
                const adminName = this.getAttribute("data-name");
                const newStatus = currentStatus === 1 ? 0 : 1;
                
                document.getElementById("toggleStatusAdminId").value = adminId;
                document.getElementById("toggleStatusNewStatus").value = newStatus;
                document.getElementById("toggleStatusMessage").textContent = newStatus === 1 
                    ? `Bạn có chắc chắn muốn mở khóa tài khoản của nhân viên ${adminName}?` 
                    : `Bạn có chắc chắn muốn khóa tài khoản của nhân viên ${adminName}?`;
                
                // Show/hide reason field based on action
                document.getElementById("lockReasonContainer").style.display = newStatus === 1 ? "none" : "block";
                
                // Set button text and style
                document.getElementById("toggleStatusTitle").textContent = newStatus === 1 ? "Mở khóa tài khoản" : "Khóa tài khoản";
                const submitBtn = document.getElementById("toggleStatusSubmitBtn");
                submitBtn.textContent = newStatus === 1 ? "Mở khóa" : "Khóa";
                submitBtn.className = newStatus === 1 ? "btn btn-success" : "btn btn-danger";
                
                // Show modal
                const modal = new bootstrap.Modal(document.getElementById("toggleStatusModal"));
                modal.show();
            });
        });
        
        // Delete admin
        const deleteButtons = document.querySelectorAll(".delete-admin");
        deleteButtons.forEach(button => {
            button.addEventListener("click", function() {
                const adminId = this.getAttribute("data-id");
                const adminName = this.getAttribute("data-name");
                
                document.getElementById("deleteAdminId").value = adminId;
                document.getElementById("deleteAdminName").textContent = adminName;
                
                // Show modal
                const modal = new bootstrap.Modal(document.getElementById("deleteAdminModal"));
                modal.show();
            });
        });
    });
</script>
';

// Include footer
include('includes/footer.php');
?>
