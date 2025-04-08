<?php
// Set page title
$page_title = 'Nhật ký hoạt động';

// Include header (will check for login)
include('includes/header.php');

// Include database connection
include('../config/config.php');

// Check if user has permission to view logs
checkPermissionRedirect('log_view');

// Variables for filtering and searching
$search_keyword = isset($_GET['search']) ? trim($_GET['search']) : '';
$admin_filter = isset($_GET['admin_id']) ? (int)$_GET['admin_id'] : 0;
$action_filter = isset($_GET['action_type']) ? trim($_GET['action_type']) : '';
$target_filter = isset($_GET['target_type']) ? trim($_GET['target_type']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Default sorting
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// Build query
$query = "
    SELECT aa.*, a.ten_admin AS admin_name, a.taikhoan AS admin_username
    FROM admin_actions aa
    LEFT JOIN admin a ON aa.admin_id = a.id_admin
    WHERE 1=1
";

// Add search conditions
$where_conditions = [];
if (!empty($search_keyword)) {
    $search_keyword = $conn->real_escape_string($search_keyword);
    $where_conditions[] = "(aa.details LIKE '%$search_keyword%' OR a.ten_admin LIKE '%$search_keyword%' OR a.taikhoan LIKE '%$search_keyword%')";
}

if ($admin_filter > 0) {
    $where_conditions[] = "aa.admin_id = $admin_filter";
}

if (!empty($action_filter)) {
    $action_filter = $conn->real_escape_string($action_filter);
    $where_conditions[] = "aa.action_type = '$action_filter'";
}

if (!empty($target_filter)) {
    $target_filter = $conn->real_escape_string($target_filter);
    $where_conditions[] = "aa.target_type = '$target_filter'";
}

if (!empty($date_from)) {
    $date_from = $conn->real_escape_string($date_from);
    $where_conditions[] = "DATE(aa.created_at) >= '$date_from'";
}

if (!empty($date_to)) {
    $date_to = $conn->real_escape_string($date_to);
    $where_conditions[] = "DATE(aa.created_at) <= '$date_to'";
}

// Combine conditions
if (!empty($where_conditions)) {
    $query .= " AND " . implode(" AND ", $where_conditions);
}

// Add sorting
$valid_sort_columns = ['id', 'admin_id', 'action_type', 'target_type', 'target_id', 'created_at'];
if (!in_array($sort_by, $valid_sort_columns)) {
    $sort_by = 'created_at';
}

$sort_order = ($sort_order === 'ASC') ? 'ASC' : 'DESC';
$query .= " ORDER BY aa.$sort_by $sort_order";

// Pagination
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($current_page - 1) * $per_page;

// Get total count for pagination
$count_query = str_replace("SELECT aa.*, a.ten_admin AS admin_name, a.taikhoan AS admin_username", "SELECT COUNT(*) as total", $query);
$count_result = $conn->query($count_query);
$total_rows = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $per_page);

// Add limit for pagination
$query .= " LIMIT $offset, $per_page";

// Execute query
$result = $conn->query($query);

// Get list of admins for filter
$admins_query = "SELECT id_admin, ten_admin, taikhoan FROM admin ORDER BY ten_admin";
$admins_result = $conn->query($admins_query);

// Get unique action types for filter
$actions_query = "SELECT DISTINCT action_type FROM admin_actions ORDER BY action_type";
$actions_result = $conn->query($actions_query);

// Get unique target types for filter
$targets_query = "SELECT DISTINCT target_type FROM admin_actions ORDER BY target_type";
$targets_result = $conn->query($targets_query);
?>

<!-- Include sidebar -->
<?php include('includes/sidebar.php'); ?>

<!-- Main content -->
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Nhật ký hoạt động</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="#" class="btn btn-sm btn-outline-secondary me-2" id="toggleFilters">
                <i class="bi bi-funnel"></i> Bộ lọc
            </a>
            <button class="btn btn-sm btn-outline-secondary" id="exportLogsBtn">
                <i class="bi bi-download"></i> Xuất CSV
            </button>
        </div>
    </div>

    <!-- Filter options -->
    <div class="card mb-4 <?php echo empty($search_keyword) && $admin_filter <= 0 && empty($action_filter) && empty($target_filter) && empty($date_from) && empty($date_to) ? 'd-none' : ''; ?>" id="filterCard">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-4">
                    <label for="search" class="form-label">Tìm kiếm</label>
                    <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search_keyword); ?>" placeholder="Tìm trong chi tiết...">
                </div>
                <div class="col-md-4">
                    <label for="admin_id" class="form-label">Người thực hiện</label>
                    <select class="form-select" id="admin_id" name="admin_id">
                        <option value="0">Tất cả</option>
                        <?php while ($admin = $admins_result->fetch_assoc()): ?>
                            <option value="<?php echo $admin['id_admin']; ?>" <?php echo $admin_filter == $admin['id_admin'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($admin['ten_admin'] . ' (' . $admin['taikhoan'] . ')'); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="action_type" class="form-label">Loại hành động</label>
                    <select class="form-select" id="action_type" name="action_type">
                        <option value="">Tất cả</option>
                        <?php while ($action = $actions_result->fetch_assoc()): ?>
                            <option value="<?php echo $action['action_type']; ?>" <?php echo $action_filter == $action['action_type'] ? 'selected' : ''; ?>>
                                <?php echo ucfirst(htmlspecialchars($action['action_type'])); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="target_type" class="form-label">Đối tượng</label>
                    <select class="form-select" id="target_type" name="target_type">
                        <option value="">Tất cả</option>
                        <?php while ($target = $targets_result->fetch_assoc()): ?>
                            <option value="<?php echo $target['target_type']; ?>" <?php echo $target_filter == $target['target_type'] ? 'selected' : ''; ?>>
                                <?php echo ucfirst(htmlspecialchars($target['target_type'])); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="date_from" class="form-label">Từ ngày</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $date_from; ?>">
                </div>
                <div class="col-md-4">
                    <label for="date_to" class="form-label">Đến ngày</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $date_to; ?>">
                </div>
                <div class="col-12">
                    <input type="hidden" name="sort" value="<?php echo $sort_by; ?>">
                    <input type="hidden" name="order" value="<?php echo $sort_order; ?>">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i> Tìm kiếm
                    </button>
                    <a href="activity-logs.php" class="btn btn-secondary ms-2">
                        <i class="bi bi-x-circle"></i> Xóa bộ lọc
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Logs table -->
    <div class="card">
        <div class="card-header bg-white">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Lịch sử hoạt động</h5>
                <span class="badge bg-secondary"><?php echo $total_rows; ?> bản ghi</span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">
                                <a href="?admin_id=<?php echo $admin_filter; ?>&action_type=<?php echo urlencode($action_filter); ?>&target_type=<?php echo urlencode($target_filter); ?>&search=<?php echo urlencode($search_keyword); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&sort=id&order=<?php echo $sort_by === 'id' && $sort_order === 'ASC' ? 'DESC' : 'ASC'; ?>" class="text-decoration-none text-dark">
                                    ID
                                    <?php if ($sort_by === 'id'): ?>
                                        <i class="bi bi-caret-<?php echo $sort_order === 'ASC' ? 'up' : 'down'; ?>-fill"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th scope="col">
                                <a href="?admin_id=<?php echo $admin_filter; ?>&action_type=<?php echo urlencode($action_filter); ?>&target_type=<?php echo urlencode($target_filter); ?>&search=<?php echo urlencode($search_keyword); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&sort=created_at&order=<?php echo $sort_by === 'created_at' && $sort_order === 'ASC' ? 'DESC' : 'ASC'; ?>" class="text-decoration-none text-dark">
                                    Thời gian
                                    <?php if ($sort_by === 'created_at'): ?>
                                        <i class="bi bi-caret-<?php echo $sort_order === 'ASC' ? 'up' : 'down'; ?>-fill"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th scope="col">Người thực hiện</th>
                            <th scope="col">
                                <a href="?admin_id=<?php echo $admin_filter; ?>&action_type=<?php echo urlencode($action_filter); ?>&target_type=<?php echo urlencode($target_filter); ?>&search=<?php echo urlencode($search_keyword); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&sort=action_type&order=<?php echo $sort_by === 'action_type' && $sort_order === 'ASC' ? 'DESC' : 'ASC'; ?>" class="text-decoration-none text-dark">
                                    Hành động
                                    <?php if ($sort_by === 'action_type'): ?>
                                        <i class="bi bi-caret-<?php echo $sort_order === 'ASC' ? 'up' : 'down'; ?>-fill"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th scope="col">
                                <a href="?admin_id=<?php echo $admin_filter; ?>&action_type=<?php echo urlencode($action_filter); ?>&target_type=<?php echo urlencode($target_filter); ?>&search=<?php echo urlencode($search_keyword); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&sort=target_type&order=<?php echo $sort_by === 'target_type' && $sort_order === 'ASC' ? 'DESC' : 'ASC'; ?>" class="text-decoration-none text-dark">
                                    Đối tượng
                                    <?php if ($sort_by === 'target_type'): ?>
                                        <i class="bi bi-caret-<?php echo $sort_order === 'ASC' ? 'up' : 'down'; ?>-fill"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th scope="col">Chi tiết</th>
                            <th scope="col">IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($log = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $log['id']; ?></td>
                                    <td><?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?></td>
                                    <td>
                                        <?php if ($log['admin_name']): ?>
                                            <a href="view_admin.php?id=<?php echo $log['admin_id']; ?>">
                                                <?php echo htmlspecialchars($log['admin_name']); ?>
                                            </a>
                                        <?php else: ?>
                                            <?php echo 'ID: ' . $log['admin_id']; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $action_class = 'bg-secondary';
                                        switch ($log['action_type']) {
                                            case 'login':
                                                $action_class = 'bg-primary';
                                                break;
                                            case 'logout':
                                                $action_class = 'bg-secondary';
                                                break;
                                            case 'add':
                                            case 'create':
                                                $action_class = 'bg-success';
                                                break;
                                            case 'edit':
                                            case 'update':
                                                $action_class = 'bg-info';
                                                break;
                                            case 'delete':
                                                $action_class = 'bg-danger';
                                                break;
                                            case 'enable_account':
                                                $action_class = 'bg-success';
                                                break;
                                            case 'disable_account':
                                                $action_class = 'bg-warning text-dark';
                                                break;
                                        }
                                        ?>
                                        <span class="badge <?php echo $action_class; ?>">
                                            <?php echo ucfirst(htmlspecialchars($log['action_type'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark">
                                            <?php echo ucfirst(htmlspecialchars($log['target_type'])); ?> #<?php echo $log['target_id']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo nl2br(htmlspecialchars($log['details'])); ?></td>
                                    <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center p-4 text-muted">
                                    <i class="bi bi-clipboard-x mb-3" style="font-size: 2rem;"></i>
                                    <p>Không tìm thấy bản ghi nào</p>
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
                    <a class="page-link" href="?page=<?php echo $current_page - 1; ?>&admin_id=<?php echo $admin_filter; ?>&action_type=<?php echo urlencode($action_filter); ?>&target_type=<?php echo urlencode($target_filter); ?>&search=<?php echo urlencode($search_keyword); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&sort=<?php echo $sort_by; ?>&order=<?php echo $sort_order; ?>">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>
                
                <?php
                $start_page = max(1, $current_page - 2);
                $end_page = min($total_pages, $current_page + 2);
                
                if ($start_page > 1) {
                    echo '<li class="page-item"><a class="page-link" href="?page=1&admin_id=' . $admin_filter . '&action_type=' . urlencode($action_filter) . '&target_type=' . urlencode($target_filter) . '&search=' . urlencode($search_keyword) . '&date_from=' . $date_from . '&date_to=' . $date_to . '&sort=' . $sort_by . '&order=' . $sort_order . '">1</a></li>';
                    if ($start_page > 2) {
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                }
                
                for ($i = $start_page; $i <= $end_page; $i++) {
                    echo '<li class="page-item ' . (($i == $current_page) ? 'active' : '') . '">';
                    echo '<a class="page-link" href="?page=' . $i . '&admin_id=' . $admin_filter . '&action_type=' . urlencode($action_filter) . '&target_type=' . urlencode($target_filter) . '&search=' . urlencode($search_keyword) . '&date_from=' . $date_from . '&date_to=' . $date_to . '&sort=' . $sort_by . '&order=' . $sort_order . '">' . $i . '</a>';
                    echo '</li>';
                }
                
                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) {
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                    echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '&admin_id=' . $admin_filter . '&action_type=' . urlencode($action_filter) . '&target_type=' . urlencode($target_filter) . '&search=' . urlencode($search_keyword) . '&date_from=' . $date_from . '&date_to=' . $date_to . '&sort=' . $sort_by . '&order=' . $sort_order . '">' . $total_pages . '</a></li>';
                }
                ?>
                
                <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $current_page + 1; ?>&admin_id=<?php echo $admin_filter; ?>&action_type=<?php echo urlencode($action_filter); ?>&target_type=<?php echo urlencode($target_filter); ?>&search=<?php echo urlencode($search_keyword); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&sort=<?php echo $sort_by; ?>&order=<?php echo $sort_order; ?>">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</main>

<?php 
// JavaScript for the page
$page_specific_js = '
<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Toggle filters
        document.getElementById("toggleFilters").addEventListener("click", function(e) {
            e.preventDefault();
            document.getElementById("filterCard").classList.toggle("d-none");
        });
        
        // Export to CSV
        document.getElementById("exportLogsBtn").addEventListener("click", function() {
            let exportUrl = "export_logs.php?";
            const urlParams = new URLSearchParams(window.location.search);
            
            // Add all current filters to export URL
            if (urlParams.has("admin_id")) exportUrl += "&admin_id=" + urlParams.get("admin_id");
            if (urlParams.has("action_type")) exportUrl += "&action_type=" + urlParams.get("action_type");
            if (urlParams.has("target_type")) exportUrl += "&target_type=" + urlParams.get("target_type");
            if (urlParams.has("search")) exportUrl += "&search=" + urlParams.get("search");
            if (urlParams.has("date_from")) exportUrl += "&date_from=" + urlParams.get("date_from");
            if (urlParams.has("date_to")) exportUrl += "&date_to=" + urlParams.get("date_to");
            if (urlParams.has("sort")) exportUrl += "&sort=" + urlParams.get("sort");
            if (urlParams.has("order")) exportUrl += "&order=" + urlParams.get("order");
            
            window.location.href = exportUrl;
        });
    });
</script>
';

// Include footer
include('includes/footer.php');
?>
