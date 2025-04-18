<?php
// Start output buffering to catch any unwanted output
ob_start();

// Set page title
$page_title = 'Quản lý danh mục';

// Handle delete operation before including header
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    // Include database connection first
    require_once('../config/config.php');
    
    // Check if category has products
    $check_products = $conn->prepare("SELECT COUNT(*) as count FROM sanpham WHERE id_danhmuc = ?");
    $check_products->bind_param("i", $id);
    $check_products->execute();
    $result = $check_products->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['count'] > 0) {
        $_SESSION['error_message'] = "Không thể xóa danh mục này vì có " . $row['count'] . " sản phẩm đang sử dụng!";
    } else {
        // Check if category has children
        $check_children = $conn->prepare("SELECT COUNT(*) as count FROM danhmuc WHERE danhmuc_cha = ?");
        $check_children->bind_param("i", $id);
        $check_children->execute();
        $result = $check_children->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['count'] > 0) {
            $_SESSION['error_message'] = "Không thể xóa danh mục này vì có " . $row['count'] . " danh mục con!";
        } else {
            // Delete category
            $delete = $conn->prepare("DELETE FROM danhmuc WHERE id = ?");
            $delete->bind_param("i", $id);
            
            if ($delete->execute()) {
                $_SESSION['success_message'] = "Đã xóa danh mục thành công!";
                
                // Log activity
                if (function_exists('logAdminActivity')) {
                    logAdminActivity($conn, $_SESSION['admin_id'] ?? null, 'delete', 'category', $id, "Xóa danh mục ID: $id");
                }
            } else {
                $_SESSION['error_message'] = "Có lỗi xảy ra khi xóa danh mục!";
            }
        }
    }
    
    header('Location: categories.php');
    exit;
}

// Handle status toggle - also before including header
if (isset($_GET['toggle_status']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    // Include database connection if not already included
    if (!isset($conn)) require_once('../config/config.php');
    
    // Get current status
    $get_status = $conn->prepare("SELECT trang_thai FROM danhmuc WHERE id = ?");
    $get_status->bind_param("i", $id);
    $get_status->execute();
    $result = $get_status->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $new_status = $row['trang_thai'] == 1 ? 0 : 1;
        
        // Update status
        $update = $conn->prepare("UPDATE danhmuc SET trang_thai = ? WHERE id = ?");
        $update->bind_param("ii", $new_status, $id);
        
        if ($update->execute()) {
            $_SESSION['success_message'] = $new_status == 1 ? "Đã kích hoạt danh mục!" : "Đã ẩn danh mục!";
            
            // Log activity
            if (function_exists('logAdminActivity')) {
                $status_text = $new_status == 1 ? "Kích hoạt" : "Ẩn";
                logAdminActivity($conn, $_SESSION['admin_id'] ?? null, 'update', 'category', $id, "$status_text danh mục ID: $id");
            }
        } else {
            $_SESSION['error_message'] = "Có lỗi xảy ra khi cập nhật trạng thái!";
        }
    }
    
    header('Location: categories.php');
    exit;
}

// Now include the header
include('includes/header.php');

// At this point we're past redirects, so including DB is safe if not done already
if (!isset($conn)) include('../config/config.php');

// Get search and filter parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$parent = isset($_GET['parent']) ? (int)$_GET['parent'] : -1;

// Build query with filters
$query = "SELECT c.*, 
          IFNULL(p.ten, 'Không có') as parent_name, 
          (SELECT COUNT(*) FROM sanpham WHERE id_danhmuc = c.id) as product_count
          FROM danhmuc c
          LEFT JOIN danhmuc p ON c.danhmuc_cha = p.id
          WHERE 1=1";

$params = [];
$types = "";

if (!empty($search)) {
    $query .= " AND (c.ten LIKE ? OR c.slug LIKE ?)";
    $search_param = "%" . $search . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

if ($status !== '') {
    $query .= " AND c.trang_thai = ?";
    $params[] = (int)$status;
    $types .= "i";
}

if ($parent >= 0) {
    if ($parent == 0) {
        $query .= " AND c.danhmuc_cha IS NULL";
    } else {
        $query .= " AND c.danhmuc_cha = ?";
        $params[] = $parent;
        $types .= "i";
    }
}

$query .= " ORDER BY c.thu_tu ASC, c.ten ASC";

// Prepare and execute the query
$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$categories = [];
while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
}

// Get all parent categories for filter dropdown
$parent_categories_query = "SELECT id, ten FROM danhmuc WHERE danhmuc_cha IS NULL ORDER BY ten ASC";
$parent_result = $conn->query($parent_categories_query);
$parent_categories = [];
while ($row = $parent_result->fetch_assoc()) {
    $parent_categories[$row['id']] = $row['ten'];
}
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Quản lý danh mục</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="add_category.php" class="btn btn-sm btn-primary">
                <i class="bi bi-plus-lg"></i> Thêm danh mục mới
            </a>
        </div>
    </div>

    <!-- Search and filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-4">
                    <label for="search" class="form-label">Tìm kiếm</label>
                    <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Tên hoặc slug danh mục...">
                </div>
                
                <div class="col-md-3">
                    <label for="parent" class="form-label">Danh mục cha</label>
                    <select class="form-select" id="parent" name="parent">
                        <option value="-1">Tất cả</option>
                        <option value="0" <?php echo $parent === 0 ? 'selected' : ''; ?>>Không có danh mục cha</option>
                        <?php foreach ($parent_categories as $id => $name): ?>
                            <option value="<?php echo $id; ?>" <?php echo $parent === $id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="status" class="form-label">Trạng thái</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Tất cả</option>
                        <option value="1" <?php echo $status === '1' ? 'selected' : ''; ?>>Hiển thị</option>
                        <option value="0" <?php echo $status === '0' ? 'selected' : ''; ?>>Ẩn</option>
                    </select>
                </div>
                
                <div class="col-md-3 d-flex align-items-end">
                    <div class="d-grid gap-2 d-md-flex">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> Tìm kiếm
                        </button>
                        <a href="categories.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-counterclockwise"></i> Đặt lại
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Categories list -->
    <div class="card">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0">Danh sách danh mục (<?php echo count($categories); ?>)</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="5%">ID</th>
                            <th width="25%">Tên danh mục</th>
                            <th width="20%">Slug</th>
                            <th width="15%">Danh mục cha</th>
                            <th width="10%">Thứ tự</th>
                            <th width="10%">Sản phẩm</th>
                            <th width="10%">Trạng thái</th>
                            <th width="15%">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($categories) > 0): ?>
                            <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td><?php echo $category['id']; ?></td>
                                    <td>
                                        <a href="edit_category.php?id=<?php echo $category['id']; ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($category['ten']); ?>
                                        </a>
                                    </td>
                                    <td><code><?php echo htmlspecialchars($category['slug']); ?></code></td>
                                    <td><?php echo htmlspecialchars($category['parent_name']); ?></td>
                                    <td><?php echo $category['thu_tu']; ?></td>
                                    <td>
                                        <?php if ($category['product_count'] > 0): ?>
                                            <a href="products.php?category=<?php echo $category['id']; ?>" class="text-decoration-none">
                                                <?php echo $category['product_count']; ?> sản phẩm
                                            </a>
                                        <?php else: ?>
                                            0 sản phẩm
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($category['trang_thai'] == 1): ?>
                                            <span class="badge bg-success">Hiển thị</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Ẩn</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="edit_category.php?id=<?php echo $category['id']; ?>" class="btn btn-outline-dark" title="Sửa">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="?toggle_status=1&id=<?php echo $category['id']; ?>" class="btn btn-outline-warning" title="<?php echo $category['trang_thai'] == 1 ? 'Ẩn' : 'Hiển thị'; ?>">
                                                <i class="bi bi-<?php echo $category['trang_thai'] == 1 ? 'eye-slash' : 'eye'; ?>"></i>
                                            </a>
                                            <a href="?delete=1&id=<?php echo $category['id']; ?>" class="btn btn-outline-danger delete-category" title="Xóa"
                                               onclick="return confirm('Bạn có chắc chắn muốn xóa danh mục <?php echo htmlspecialchars($category['ten']); ?>?');">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">Không tìm thấy danh mục nào</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<?php include('includes/footer.php'); ?>
