<?php
// Start output buffering to prevent header issues
ob_start();

// Set page title
$page_title = 'Quản lý thuộc tính sản phẩm';

// Include database connection
include('../config/config.php');

// Handle delete operation
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    // Check if attribute is being used in product variants
    $check_usage = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM sanpham_bien_the 
        WHERE id_mau = ? OR id_size = ?
    ");
    $check_usage->bind_param("ii", $id, $id);
    $check_usage->execute();
    $result = $check_usage->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['count'] > 0) {
        $_SESSION['error_message'] = "Không thể xóa thuộc tính này vì đã được sử dụng trong " . $row['count'] . " biến thể sản phẩm!";
    } else {
        $delete = $conn->prepare("DELETE FROM thuoc_tinh WHERE id = ?");
        $delete->bind_param("i", $id);
        
        if ($delete->execute()) {
            $_SESSION['success_message'] = "Đã xóa thuộc tính thành công!";
            
            // Log activity
            if (function_exists('logAdminActivity')) {
                logAdminActivity($conn, $_SESSION['admin_id'], 'delete', 'attribute', $id, "Xóa thuộc tính ID: $id");
            }
        } else {
            $_SESSION['error_message'] = "Có lỗi xảy ra khi xóa thuộc tính!";
        }
    }
    
    header('Location: attributes.php');
    exit;
}

// Get search and filter parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$type = isset($_GET['type']) ? $_GET['type'] : '';

// Include header after processing to avoid "headers already sent" issues
include('includes/header.php');

// Build query with filters
$query = "SELECT * FROM thuoc_tinh WHERE 1=1";

$params = [];
$types = "";

if (!empty($search)) {
    $query .= " AND (ten LIKE ? OR gia_tri LIKE ?)";
    $search_param = "%" . $search . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

if (!empty($type)) {
    $query .= " AND loai = ?";
    $params[] = $type;
    $types .= "s";
}

$query .= " ORDER BY loai, gia_tri";

// Prepare and execute the query
$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

// Get attribute types for filter dropdown
$types_query = "SELECT DISTINCT loai FROM thuoc_tinh ORDER BY loai";
$types_result = $conn->query($types_query);
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Quản lý thuộc tính</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addAttributeModal">
                <i class="bi bi-plus-lg"></i> Thêm thuộc tính mới
            </button>
        </div>
    </div>

    <!-- Search and filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-4">
                    <label for="search" class="form-label">Tìm kiếm</label>
                    <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Tên hoặc giá trị...">
                </div>
                
                <div class="col-md-3">
                    <label for="type" class="form-label">Loại thuộc tính</label>
                    <select class="form-select" id="type" name="type">
                        <option value="">Tất cả</option>
                        <?php while($type_row = $types_result->fetch_assoc()): ?>
                            <option value="<?php echo $type_row['loai']; ?>" <?php echo $type == $type_row['loai'] ? 'selected' : ''; ?>>
                                <?php 
                                $type_display = $type_row['loai'];
                                switch($type_row['loai']) {
                                    case 'size': $type_display = 'Kích thước'; break;
                                    case 'color': $type_display = 'Màu sắc'; break;
                                    case 'material': $type_display = 'Chất liệu'; break;
                                }
                                echo htmlspecialchars($type_display); 
                                ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="col-md-5 d-flex align-items-end">
                    <div class="d-grid gap-2 d-md-flex">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> Tìm kiếm
                        </button>
                        <a href="attributes.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-counterclockwise"></i> Đặt lại
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Attributes list -->
    <div class="card">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0">Danh sách thuộc tính (<?php echo $result->num_rows; ?>)</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="10%">ID</th>
                            <th width="20%">Tên</th>
                            <th width="20%">Loại</th>
                            <th width="20%">Giá trị</th>
                            <th width="15%">Mã màu</th>
                            <th width="15%">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($attribute = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $attribute['id']; ?></td>
                                    <td><?php echo htmlspecialchars($attribute['ten']); ?></td>
                                    <td>
                                        <?php 
                                        switch($attribute['loai']) {
                                            case 'size': echo 'Kích thước'; break;
                                            case 'color': echo 'Màu sắc'; break;
                                            case 'material': echo 'Chất liệu'; break;
                                            default: echo htmlspecialchars($attribute['loai']);
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($attribute['gia_tri']); ?></td>
                                    <td>
                                        <?php if (!empty($attribute['ma_mau']) && $attribute['loai'] == 'color'): ?>
                                            <div class="d-flex align-items-center">
                                                <div class="color-preview me-2" style="width: 20px; height: 20px; background-color: <?php echo htmlspecialchars($attribute['ma_mau']); ?>; border: 1px solid #dee2e6; border-radius: 3px;"></div>
                                                <span><?php echo htmlspecialchars($attribute['ma_mau']); ?></span>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-dark edit-attribute" 
                                                    data-id="<?php echo $attribute['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($attribute['ten']); ?>"
                                                    data-type="<?php echo htmlspecialchars($attribute['loai']); ?>"
                                                    data-value="<?php echo htmlspecialchars($attribute['gia_tri']); ?>"
                                                    data-color="<?php echo htmlspecialchars($attribute['ma_mau'] ?? ''); ?>"
                                                    data-bs-toggle="modal" data-bs-target="#editAttributeModal">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <a href="?delete=1&id=<?php echo $attribute['id']; ?>" class="btn btn-outline-danger delete-attribute" 
                                               onclick="return confirm('Bạn có chắc chắn muốn xóa thuộc tính <?php echo htmlspecialchars($attribute['ten']); ?>?');">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">Không tìm thấy thuộc tính nào</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- Add Attribute Modal -->
<div class="modal fade" id="addAttributeModal" tabindex="-1" aria-labelledby="addAttributeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="process_attribute.php" method="post">
                <div class="modal-header">
                    <h5 class="modal-title" id="addAttributeModalLabel">Thêm thuộc tính mới</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label for="attribute_name" class="form-label">Tên thuộc tính</label>
                        <input type="text" class="form-control" id="attribute_name" name="name" required>
                        <div class="form-text">Ví dụ: Size 35, Màu Đỏ...</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="attribute_type" class="form-label">Loại thuộc tính</label>
                        <select class="form-select" id="attribute_type" name="type" required>
                            <option value="">-- Chọn loại thuộc tính --</option>
                            <option value="size">Kích thước</option>
                            <option value="color">Màu sắc</option>
                            <option value="material">Chất liệu</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="attribute_value" class="form-label">Giá trị</label>
                        <input type="text" class="form-control" id="attribute_value" name="value" required>
                        <div class="form-text">Ví dụ: 35, Đỏ, Cotton...</div>
                    </div>
                    
                    <div class="mb-3 color-field" style="display: none;">
                        <label for="attribute_color" class="form-label">Mã màu</label>
                        <div class="input-group">
                            <input type="color" class="form-control form-control-color" id="color_picker" value="#000000">
                            <input type="text" class="form-control" id="attribute_color" name="color" placeholder="#000000">
                        </div>
                        <div class="form-text">Mã màu HEX (ví dụ: #FF0000 cho màu đỏ)</div>
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

<!-- Edit Attribute Modal -->
<div class="modal fade" id="editAttributeModal" tabindex="-1" aria-labelledby="editAttributeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="process_attribute.php" method="post">
                <div class="modal-header">
                    <h5 class="modal-title" id="editAttributeModalLabel">Chỉnh sửa thuộc tính</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_attribute_id">
                    
                    <div class="mb-3">
                        <label for="edit_attribute_name" class="form-label">Tên thuộc tính</label>
                        <input type="text" class="form-control" id="edit_attribute_name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_attribute_type" class="form-label">Loại thuộc tính</label>
                        <select class="form-select" id="edit_attribute_type" name="type" required>
                            <option value="size">Kích thước</option>
                            <option value="color">Màu sắc</option>
                            <option value="material">Chất liệu</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_attribute_value" class="form-label">Giá trị</label>
                        <input type="text" class="form-control" id="edit_attribute_value" name="value" required>
                    </div>
                    
                    <div class="mb-3 edit-color-field">
                        <label for="edit_attribute_color" class="form-label">Mã màu</label>
                        <div class="input-group">
                            <input type="color" class="form-control form-control-color" id="edit_color_picker" value="#000000">
                            <input type="text" class="form-control" id="edit_attribute_color" name="color" placeholder="#000000">
                        </div>
                        <div class="form-text">Mã màu HEX (ví dụ: #FF0000 cho màu đỏ)</div>
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
    // Toggle color field based on type selection
    const attributeType = document.getElementById("attribute_type");
    const colorField = document.querySelector(".color-field");
    const editAttributeType = document.getElementById("edit_attribute_type");
    const editColorField = document.querySelector(".edit-color-field");
    
    // For add modal
    attributeType.addEventListener("change", function() {
        if (this.value === "color") {
            colorField.style.display = "block";
        } else {
            colorField.style.display = "none";
        }
    });
    
    // For edit modal
    editAttributeType.addEventListener("change", function() {
        if (this.value === "color") {
            editColorField.style.display = "block";
        } else {
            editColorField.style.display = "none";
        }
    });
    
    // Color picker syncing
    document.getElementById("color_picker").addEventListener("input", function() {
        document.getElementById("attribute_color").value = this.value;
    });
    
    document.getElementById("edit_color_picker").addEventListener("input", function() {
        document.getElementById("edit_attribute_color").value = this.value;
    });
    
    // Edit attribute modal population
    document.querySelectorAll(".edit-attribute").forEach(button => {
        button.addEventListener("click", function() {
            const id = this.dataset.id;
            const name = this.dataset.name;
            const type = this.dataset.type;
            const value = this.dataset.value;
            const color = this.dataset.color;
            
            document.getElementById("edit_attribute_id").value = id;
            document.getElementById("edit_attribute_name").value = name;
            document.getElementById("edit_attribute_type").value = type;
            document.getElementById("edit_attribute_value").value = value;
            document.getElementById("edit_attribute_color").value = color;
            document.getElementById("edit_color_picker").value = color;
            
            // Toggle color field
            if (type === "color") {
                editColorField.style.display = "block";
            } else {
                editColorField.style.display = "none";
            }
        });
    });
});
</script>';

include('includes/footer.php');
?>
