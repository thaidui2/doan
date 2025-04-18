<?php
// Start output buffering to prevent header issues
ob_start();

// Set page title
$page_title = 'Quản lý thương hiệu';

// Include database connection
include('../config/config.php');

// Handle delete operation
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    // Check if brand is being used in products
    $check_usage = $conn->prepare("SELECT COUNT(*) as count FROM sanpham WHERE thuonghieu = ?");
    $check_usage->bind_param("i", $id);
    $check_usage->execute();
    $result = $check_usage->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['count'] > 0) {
        $_SESSION['error_message'] = "Không thể xóa thương hiệu này vì đã được sử dụng trong " . $row['count'] . " sản phẩm!";
    } else {
        $delete = $conn->prepare("DELETE FROM thuong_hieu WHERE id = ?");
        $delete->bind_param("i", $id);
        
        if ($delete->execute()) {
            $_SESSION['success_message'] = "Đã xóa thương hiệu thành công!";
            
            // Log activity
            if (function_exists('logAdminActivity')) {
                logAdminActivity($conn, $_SESSION['admin_id'], 'delete', 'brand', $id, "Xóa thương hiệu ID: $id");
            }
        } else {
            $_SESSION['error_message'] = "Có lỗi xảy ra khi xóa thương hiệu!";
        }
    }
    
    header('Location: brands.php');
    exit;
}

// Get search parameter
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Include header after processing to avoid "headers already sent" issues
include('includes/header.php');

// Build query with filters
$query = "SELECT * FROM thuong_hieu WHERE 1=1";

if (!empty($search)) {
    $query .= " AND (ten LIKE ? OR mo_ta LIKE ?)";
    $search_param = "%" . $search . "%";
}

$query .= " ORDER BY ten ASC";

// Prepare and execute the query
if (!empty($search)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $search_param, $search_param);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($query);
}
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Quản lý thương hiệu</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addBrandModal">
                <i class="bi bi-plus-lg"></i> Thêm thương hiệu mới
            </button>
        </div>
    </div>

    <!-- Search and filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-6">
                    <label for="search" class="form-label">Tìm kiếm</label>
                    <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Tên thương hiệu...">
                </div>
                
                <div class="col-md-6 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="bi bi-search"></i> Tìm kiếm
                    </button>
                    <a href="brands.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-counterclockwise"></i> Đặt lại
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Brands list -->
    <div class="card">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0">Danh sách thương hiệu (<?php echo $result->num_rows; ?>)</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="10%">ID</th>
                            <th width="20%">Logo</th>
                            <th width="30%">Tên thương hiệu</th>
                            <th width="25%">Mô tả</th>
                            <th width="15%">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($brand = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $brand['id']; ?></td>
                                    <td>
                                        <?php if (!empty($brand['logo'])): ?>
                                            <img src="../uploads/brands/<?php echo htmlspecialchars($brand['logo']); ?>" alt="<?php echo htmlspecialchars($brand['ten']); ?>" class="img-thumbnail" style="max-height: 50px;">
                                        <?php else: ?>
                                            <div class="bg-light text-center p-2 rounded">
                                                <i class="bi bi-image text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($brand['ten']); ?></td>
                                    <td><?php echo htmlspecialchars($brand['mo_ta'] ?? ''); ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-dark edit-brand" 
                                                    data-id="<?php echo $brand['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($brand['ten']); ?>"
                                                    data-description="<?php echo htmlspecialchars($brand['mo_ta'] ?? ''); ?>"
                                                    data-logo="<?php echo htmlspecialchars($brand['logo'] ?? ''); ?>"
                                                    data-bs-toggle="modal" data-bs-target="#editBrandModal">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <a href="?delete=1&id=<?php echo $brand['id']; ?>" class="btn btn-outline-danger delete-brand" 
                                               onclick="return confirm('Bạn có chắc chắn muốn xóa thương hiệu <?php echo htmlspecialchars($brand['ten']); ?>?');">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">Không tìm thấy thương hiệu nào</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- Add Brand Modal -->
<div class="modal fade" id="addBrandModal" tabindex="-1" aria-labelledby="addBrandModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="process_brand.php" method="post" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="addBrandModalLabel">Thêm thương hiệu mới</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label for="brand_name" class="form-label">Tên thương hiệu</label>
                        <input type="text" class="form-control" id="brand_name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="brand_description" class="form-label">Mô tả</label>
                        <textarea class="form-control" id="brand_description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="brand_logo" class="form-label">Logo</label>
                        <input type="file" class="form-control" id="brand_logo" name="logo" accept="image/*">
                        <div class="form-text">Định dạng cho phép: JPG, PNG, WEBP. Kích thước tối đa: 2MB</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">Thêm mới</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Brand Modal -->
<div class="modal fade" id="editBrandModal" tabindex="-1" aria-labelledby="editBrandModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="process_brand.php" method="post" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="editBrandModalLabel">Chỉnh sửa thương hiệu</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_brand_id">
                    
                    <div class="mb-3">
                        <label for="edit_brand_name" class="form-label">Tên thương hiệu</label>
                        <input type="text" class="form-control" id="edit_brand_name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_brand_description" class="form-label">Mô tả</label>
                        <textarea class="form-control" id="edit_brand_description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_brand_logo" class="form-label">Logo</label>
                        <input type="file" class="form-control" id="edit_brand_logo" name="logo" accept="image/*">
                        <div class="form-text">Bỏ trống nếu không muốn thay đổi logo hiện tại</div>
                        
                        <div id="current_logo_container" class="mt-2" style="display: none;">
                            <p>Logo hiện tại:</p>
                            <img id="current_logo" src="" class="img-thumbnail" style="max-height: 100px;">
                            <input type="hidden" name="current_logo" id="current_logo_input">
                        </div>
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

<?php
// Add specific JavaScript for this page
$page_specific_js = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Edit brand modal population
    document.querySelectorAll(".edit-brand").forEach(button => {
        button.addEventListener("click", function() {
            const id = this.dataset.id;
            const name = this.dataset.name;
            const description = this.dataset.description;
            const logo = this.dataset.logo;
            
            document.getElementById("edit_brand_id").value = id;
            document.getElementById("edit_brand_name").value = name;
            document.getElementById("edit_brand_description").value = description;
            
            // Display current logo if exists
            const logoContainer = document.getElementById("current_logo_container");
            const logoImg = document.getElementById("current_logo");
            const logoInput = document.getElementById("current_logo_input");
            
            if (logo) {
                logoContainer.style.display = "block";
                logoImg.src = "../uploads/brands/" + logo;
                logoInput.value = logo;
            } else {
                logoContainer.style.display = "none";
                logoImg.src = "";
                logoInput.value = "";
            }
        });
    });
});
</script>';

include('includes/footer.php');
?>
