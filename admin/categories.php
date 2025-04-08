<?php
// Set page title
$page_title = 'Quản lý danh mục';

// Include header (will check for login)
include('includes/header.php');

// Include database connection
include('../config/config.php');

// Check if user has permission to view categories
if (function_exists('checkPermissionRedirect')) {
    checkPermissionRedirect('category_view');
}

// Variables for filtering and searching
$search_keyword = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? (int)$_GET['status'] : -1;
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'id_loai';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'ASC';

// Build query
$query = "SELECT * FROM loaisanpham WHERE 1=1";

// Add search conditions
$where_conditions = [];
if (!empty($search_keyword)) {
    $search_keyword = $conn->real_escape_string($search_keyword);
    $where_conditions[] = "(tenloai LIKE '%$search_keyword%' OR mota LIKE '%$search_keyword%')";
}

if ($status_filter !== -1) {
    $where_conditions[] = "trangthai = $status_filter";
}

// Combine conditions
if (!empty($where_conditions)) {
    $query .= " AND " . implode(" AND ", $where_conditions);
}

// Add sorting
$valid_sort_columns = ['id_loai', 'tenloai', 'trangthai'];
if (!in_array($sort_by, $valid_sort_columns)) {
    $sort_by = 'id_loai';
}

$sort_order = ($sort_order === 'DESC') ? 'DESC' : 'ASC';
$query .= " ORDER BY $sort_by $sort_order";

// Execute query
$result = $conn->query($query);

// Handle category status toggle via AJAX
if (isset($_POST['toggle_status']) && isset($_POST['category_id'])) {
    // Check permission
    if (!function_exists('hasPermission') || hasPermission('category_edit')) {
        $category_id = (int)$_POST['category_id'];
        $new_status = (int)$_POST['new_status'];
        
        if ($new_status !== 0 && $new_status !== 1) {
            echo json_encode(['success' => false, 'message' => 'Trạng thái không hợp lệ']);
            exit;
        }
        
        $update_stmt = $conn->prepare("UPDATE loaisanpham SET trangthai = ? WHERE id_loai = ?");
        $update_stmt->bind_param("ii", $new_status, $category_id);
        
        if ($update_stmt->execute()) {
            // Log action if admin_actions table exists
            if ($conn->query("SHOW TABLES LIKE 'admin_actions'")->num_rows > 0) {
                $admin_id = $_SESSION['admin_id'] ?? 0;
                $action = $new_status ? 'enable' : 'disable';
                $details = ($new_status ? 'Hiển thị' : 'Ẩn') . " danh mục #$category_id";
                $ip = $_SERVER['REMOTE_ADDR'];
                
                $log_stmt = $conn->prepare("
                    INSERT INTO admin_actions (admin_id, action_type, target_type, target_id, details, ip_address) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $target_type = 'category';
                $log_stmt->bind_param("ississ", $admin_id, $action, $target_type, $category_id, $details, $ip);
                $log_stmt->execute();
            }
            
            echo json_encode(['success' => true]);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => 'Lỗi khi cập nhật: ' . $conn->error]);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Không có quyền thực hiện thao tác này']);
        exit;
    }
}

// Handle category delete via AJAX
if (isset($_POST['delete_category']) && isset($_POST['category_id'])) {
    // Check permission
    if (!function_exists('hasPermission') || hasPermission('category_delete')) {
        $category_id = (int)$_POST['category_id'];
        
        // Check if category has products
        $check_products = $conn->prepare("SELECT COUNT(*) as count FROM sanpham WHERE id_loai = ?");
        $check_products->bind_param("i", $category_id);
        $check_products->execute();
        $product_count = $check_products->get_result()->fetch_assoc()['count'];
        
        if ($product_count > 0) {
            echo json_encode(['success' => false, 'message' => "Không thể xóa danh mục này vì có $product_count sản phẩm đang sử dụng!"]);
            exit;
        }
        
        $delete_stmt = $conn->prepare("DELETE FROM loaisanpham WHERE id_loai = ?");
        $delete_stmt->bind_param("i", $category_id);
        
        if ($delete_stmt->execute()) {
            // Log action if admin_actions table exists
            if ($conn->query("SHOW TABLES LIKE 'admin_actions'")->num_rows > 0) {
                $admin_id = $_SESSION['admin_id'] ?? 0;
                $action = 'delete';
                $details = "Xóa danh mục #$category_id";
                $ip = $_SERVER['REMOTE_ADDR'];
                
                $log_stmt = $conn->prepare("
                    INSERT INTO admin_actions (admin_id, action_type, target_type, target_id, details, ip_address) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $target_type = 'category';
                $log_stmt->bind_param("ississ", $admin_id, $action, $target_type, $category_id, $details, $ip);
                $log_stmt->execute();
            }
            
            echo json_encode(['success' => true]);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => 'Lỗi khi xóa: ' . $conn->error]);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Không có quyền thực hiện thao tác này']);
        exit;
    }
}
?>

<!-- Include sidebar -->
<?php include('includes/sidebar.php'); ?>

<!-- Main content -->
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Quản lý danh mục sản phẩm</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <?php if (!function_exists('hasPermission') || hasPermission('category_add')): ?>
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                <i class="bi bi-plus-circle"></i> Thêm danh mục mới
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Alerts for success/error messages -->
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

    <!-- Search and filter form -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-6">
                    <div class="input-group">
                        <input type="text" class="form-control" id="search" name="search" 
                               value="<?php echo htmlspecialchars($search_keyword); ?>" 
                               placeholder="Tìm kiếm theo tên danh mục...">
                        <button class="btn btn-outline-secondary" type="submit">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="status" name="status" onchange="this.form.submit()">
                        <option value="-1" <?php echo $status_filter === -1 ? 'selected' : ''; ?>>Tất cả trạng thái</option>
                        <option value="1" <?php echo $status_filter === 1 ? 'selected' : ''; ?>>Hiển thị</option>
                        <option value="0" <?php echo $status_filter === 0 ? 'selected' : ''; ?>>Ẩn</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <a href="categories.php" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-arrow-repeat"></i> Đặt lại bộ lọc
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Categories table -->
    <div class="card">
        <div class="card-header bg-white">
            <h5 class="card-title mb-0">Danh sách danh mục</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th width="60">
                                <a href="?sort=id_loai&order=<?php echo $sort_by === 'id_loai' && $sort_order === 'ASC' ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($search_keyword); ?>&status=<?php echo $status_filter; ?>" class="text-decoration-none text-dark">
                                    ID
                                    <?php if ($sort_by === 'id_loai'): ?>
                                        <i class="bi bi-sort-<?php echo $sort_order === 'ASC' ? 'down' : 'up'; ?>"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>Hình ảnh</th>
                            <th>
                                <a href="?sort=tenloai&order=<?php echo $sort_by === 'tenloai' && $sort_order === 'ASC' ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($search_keyword); ?>&status=<?php echo $status_filter; ?>" class="text-decoration-none text-dark">
                                    Tên danh mục
                                    <?php if ($sort_by === 'tenloai'): ?>
                                        <i class="bi bi-sort-<?php echo $sort_order === 'ASC' ? 'down' : 'up'; ?>"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th>Mô tả</th>
                            <th>
                                <a href="?sort=trangthai&order=<?php echo $sort_by === 'trangthai' && $sort_order === 'ASC' ? 'DESC' : 'ASC'; ?>&search=<?php echo urlencode($search_keyword); ?>&status=<?php echo $status_filter; ?>" class="text-decoration-none text-dark">
                                    Trạng thái
                                    <?php if ($sort_by === 'trangthai'): ?>
                                        <i class="bi bi-sort-<?php echo $sort_order === 'ASC' ? 'down' : 'up'; ?>"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th width="140">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($category = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $category['id_loai']; ?></td>
                                    <td>
                                        <?php if (!empty($category['hinhanh']) && file_exists("../uploads/categories/" . $category['hinhanh'])): ?>
                                            <img src="../uploads/categories/<?php echo $category['hinhanh']; ?>" 
                                                 alt="<?php echo htmlspecialchars($category['tenloai']); ?>"
                                                 class="img-thumbnail" style="max-width: 60px; max-height: 60px;">
                                        <?php else: ?>
                                            <div class="text-center text-muted">
                                                <i class="bi bi-image" style="font-size: 2rem;"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($category['tenloai']); ?></td>
                                    <td>
                                        <?php 
                                        $mota = htmlspecialchars($category['mota'] ?? '');
                                        echo !empty($mota) ? (strlen($mota) > 50 ? substr($mota, 0, 50) . '...' : $mota) : '<span class="text-muted">Không có mô tả</span>'; 
                                        ?>
                                    </td>
                                    <td>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input toggle-status" type="checkbox" 
                                                   data-id="<?php echo $category['id_loai']; ?>" 
                                                   <?php echo $category['trangthai'] ? 'checked' : ''; ?>
                                                   <?php echo (!function_exists('hasPermission') || hasPermission('category_edit')) ? '' : 'disabled'; ?>>
                                            <label class="form-check-label">
                                                <?php echo $category['trangthai'] ? '<span class="text-success">Hiển thị</span>' : '<span class="text-danger">Ẩn</span>'; ?>
                                            </label>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <?php if (!function_exists('hasPermission') || hasPermission('category_edit')): ?>
                                            <button type="button" class="btn btn-outline-primary edit-category"
                                                    data-id="<?php echo $category['id_loai']; ?>"
                                                    data-name="<?php echo htmlspecialchars($category['tenloai']); ?>"
                                                    data-description="<?php echo htmlspecialchars($category['mota'] ?? ''); ?>"
                                                    data-image="<?php echo htmlspecialchars($category['hinhanh'] ?? ''); ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <?php endif; ?>
                                            
                                            <?php if (!function_exists('hasPermission') || hasPermission('category_delete')): ?>
                                            <button type="button" class="btn btn-outline-danger delete-category"
                                                    data-id="<?php echo $category['id_loai']; ?>"
                                                    data-name="<?php echo htmlspecialchars($category['tenloai']); ?>">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4">
                                    <div class="text-muted">
                                        <i class="bi bi-filter-circle mb-3" style="font-size: 2rem;"></i>
                                        <p>Không tìm thấy danh mục nào phù hợp</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addCategoryModalLabel">Thêm danh mục mới</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addCategoryForm" action="process_category.php" method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="category_name" class="form-label">Tên danh mục <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="category_name" name="category_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="category_description" class="form-label">Mô tả</label>
                        <textarea class="form-control" id="category_description" name="category_description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="category_image" class="form-label">Hình ảnh</label>
                        <input type="file" class="form-control" id="category_image" name="category_image" accept="image/*">
                        <div id="imageHelp" class="form-text">Chọn hình ảnh đại diện cho danh mục (không bắt buộc).</div>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="category_status" name="category_status" value="1" checked>
                        <label class="form-check-label" for="category_status">Hiển thị danh mục</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">Thêm danh mục</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editCategoryModalLabel">Chỉnh sửa danh mục</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editCategoryForm" action="process_category.php" method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="category_id" id="edit_category_id" value="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_category_name" class="form-label">Tên danh mục <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_category_name" name="category_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_category_description" class="form-label">Mô tả</label>
                        <textarea class="form-control" id="edit_category_description" name="category_description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="edit_category_image" class="form-label">Hình ảnh</label>
                        <input type="file" class="form-control" id="edit_category_image" name="category_image" accept="image/*">
                        <div id="editImageHelp" class="form-text">Chọn hình ảnh mới nếu muốn thay đổi (để trống nếu giữ nguyên hình hiện tại).</div>
                        
                        <div class="mt-2" id="current_image_container">
                            <label class="form-label">Hình ảnh hiện tại:</label>
                            <div id="current_image_preview" class="border rounded p-2 text-center">
                                <img src="" alt="Current image" class="img-thumbnail" style="max-height: 100px; display: none;">
                                <div class="text-muted" id="no_image_text">Không có hình ảnh</div>
                            </div>
                        </div>
                        
                        <div class="form-check mt-2">
                            <input type="checkbox" class="form-check-input" id="remove_image" name="remove_image" value="1">
                            <label class="form-check-label" for="remove_image">Xóa hình ảnh hiện tại</label>
                        </div>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="edit_category_status" name="category_status" value="1">
                        <label class="form-check-label" for="edit_category_status">Hiển thị danh mục</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteCategoryModal" tabindex="-1" aria-labelledby="deleteCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteCategoryModalLabel">Xác nhận xóa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Bạn có chắc chắn muốn xóa danh mục <strong id="delete_category_name"></strong>?</p>
                <p class="text-danger">Lưu ý: Thao tác này không thể hoàn tác.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-danger" id="confirm_delete">Xác nhận xóa</button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for the page -->
<?php 
$page_specific_js = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Toggle status switch
    document.querySelectorAll(".toggle-status").forEach(function(toggle) {
        toggle.addEventListener("change", function() {
            const categoryId = this.getAttribute("data-id");
            const newStatus = this.checked ? 1 : 0;
            
            fetch("categories.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                },
                body: `toggle_status=1&category_id=${categoryId}&new_status=${newStatus}`,
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const label = this.nextElementSibling;
                    if (newStatus) {
                        label.innerHTML = \'<span class="text-success">Hiển thị</span>\';
                    } else {
                        label.innerHTML = \'<span class="text-danger">Ẩn</span>\';
                    }
                    
                    // Show toast notification
                    showToast(newStatus ? "Đã hiển thị danh mục" : "Đã ẩn danh mục", "success");
                } else {
                    // Revert the toggle if failed
                    this.checked = !this.checked;
                    showToast("Lỗi: " + data.message, "danger");
                }
            })
            .catch(error => {
                console.error("Error:", error);
                // Revert the toggle if failed
                this.checked = !this.checked;
                showToast("Lỗi khi thực hiện thao tác", "danger");
            });
        });
    });
    
    // Edit category button
    document.querySelectorAll(".edit-category").forEach(function(btn) {
        btn.addEventListener("click", function() {
            const id = this.getAttribute("data-id");
            const name = this.getAttribute("data-name");
            const description = this.getAttribute("data-description");
            const image = this.getAttribute("data-image");
            
            document.getElementById("edit_category_id").value = id;
            document.getElementById("edit_category_name").value = name;
            document.getElementById("edit_category_description").value = description;
            
            // Handle image preview
            const imgPreview = document.querySelector("#current_image_preview img");
            const noImageText = document.getElementById("no_image_text");
            
            if (image && image !== "") {
                imgPreview.src = "../uploads/categories/" + image;
                imgPreview.style.display = "block";
                noImageText.style.display = "none";
            } else {
                imgPreview.style.display = "none";
                noImageText.style.display = "block";
            }
            
            // Reset remove image checkbox
            document.getElementById("remove_image").checked = false;
            
            // Set status checkbox
            const statusCheckbox = document.getElementById("edit_category_status");
            statusCheckbox.checked = this.closest("tr").querySelector(".toggle-status").checked;
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById("editCategoryModal"));
            modal.show();
        });
    });
    
    // Handle remove image checkbox
    document.getElementById("remove_image").addEventListener("change", function() {
        if (this.checked) {
            document.querySelector("#current_image_preview img").style.display = "none";
            document.getElementById("no_image_text").style.display = "block";
        } else {
            const image = document.querySelector(".edit-category[data-id=\'" + document.getElementById("edit_category_id").value + "\']").getAttribute("data-image");
            if (image && image !== "") {
                document.querySelector("#current_image_preview img").style.display = "block";
                document.getElementById("no_image_text").style.display = "none";
            }
        }
    });
    
    // Delete category button
    document.querySelectorAll(".delete-category").forEach(function(btn) {
        btn.addEventListener("click", function() {
            const id = this.getAttribute("data-id");
            const name = this.getAttribute("data-name");
            
            document.getElementById("delete_category_name").textContent = name;
            
            // Set up the delete confirmation
            document.getElementById("confirm_delete").onclick = function() {
                deleteCategory(id);
            };
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById("deleteCategoryModal"));
            modal.show();
        });
    });
    
    function deleteCategory(id) {
        fetch("categories.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
            },
            body: `delete_category=1&category_id=${id}`,
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Hide the modal
                bootstrap.Modal.getInstance(document.getElementById("deleteCategoryModal")).hide();
                
                // Remove the row from the table
                const row = document.querySelector(`.delete-category[data-id="${id}"]`).closest("tr");
                row.classList.add("fade-out");
                
                setTimeout(() => {
                    row.remove();
                    
                    // Check if table is empty
                    if (document.querySelector("tbody").children.length === 0) {
                        document.querySelector("tbody").innerHTML = `
                            <tr>
                                <td colspan="6" class="text-center py-4">
                                    <div class="text-muted">
                                        <i class="bi bi-filter-circle mb-3" style="font-size: 2rem;"></i>
                                        <p>Không tìm thấy danh mục nào phù hợp</p>
                                    </div>
                                </td>
                            </tr>
                        `;
                    }
                    
                    showToast("Đã xóa danh mục thành công", "success");
                }, 300);
            } else {
                bootstrap.Modal.getInstance(document.getElementById("deleteCategoryModal")).hide();
                showToast("Lỗi: " + data.message, "danger");
            }
        })
        .catch(error => {
            console.error("Error:", error);
            bootstrap.Modal.getInstance(document.getElementById("deleteCategoryModal")).hide();
            showToast("Lỗi khi xóa danh mục", "danger");
        });
    }
    
    // Toast notification function
    function showToast(message, type = "info") {
        const toastContainer = document.createElement("div");
        toastContainer.className = "position-fixed bottom-0 end-0 p-3";
        toastContainer.style.zIndex = "5";
        
        toastContainer.innerHTML = `
            <div id="liveToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-header">
                    <strong class="me-auto">${type === "success" ? "Thành công" : type === "danger" ? "Lỗi" : "Thông báo"}</strong>
                    <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body bg-${type} text-white">
                    ${message}
                </div>
            </div>
        `;
        
        document.body.appendChild(toastContainer);
        const toast = new bootstrap.Toast(document.getElementById("liveToast"));
        toast.show();
        
        // Remove the toast element after it\'s hidden
        const toastElement = document.getElementById("liveToast");
        toastElement.addEventListener("hidden.bs.toast", function() {
            document.body.removeChild(toastContainer);
        });
    }
});
</script>
';

// Add a small CSS for fade-out animation
$page_specific_css = '
<style>
.fade-out {
    opacity: 0;
    transition: opacity 0.3s ease-out;
}
</style>
';

// Include footer
include('includes/footer.php');
?>
