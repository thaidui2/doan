<?php
// Include database connection
require_once('../config/config.php');

// Include authentication check
require_once('includes/auth_check.php');

// Get admin ID from session for logging actions
$admin_id = $_SESSION['admin_id'];

// Set current page for sidebar highlighting
$current_page = 'sizes';
$page_title = 'Quản lý kích thước';

// Process form submissions
$message = '';
$message_type = '';

// Delete size
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $size_id = $_GET['delete'];
    
    // Check if size is used in any product variants before deletion
    $check_used = $conn->prepare("SELECT COUNT(*) as count FROM sanpham_bien_the WHERE id_size = ?");
    $check_used->bind_param("i", $size_id);
    $check_used->execute();
    $result = $check_used->get_result();
    $is_used = $result->fetch_assoc()['count'] > 0;
    
    if ($is_used) {
        $message = 'Không thể xóa kích thước này vì đang được sử dụng trong sản phẩm.';
        $message_type = 'danger';
    } else {
        $delete = $conn->prepare("DELETE FROM thuoc_tinh WHERE id = ? AND loai = 'size'");
        $delete->bind_param("i", $size_id);
        
        if ($delete->execute()) {
            $message = 'Xóa kích thước thành công!';
            $message_type = 'success';
            
            // Log the action - FIXED: Create detail string beforehand
            $detail = 'Xóa kích thước ID: ' . $size_id;
            $log_stmt = $conn->prepare("INSERT INTO nhat_ky (id_user, hanh_dong, doi_tuong_loai, doi_tuong_id, chi_tiet, ip_address) VALUES (?, 'delete', 'size', ?, ?, ?)");
            $log_stmt->bind_param("iiss", $admin_id, $size_id, $detail, $_SERVER['REMOTE_ADDR']);
            $log_stmt->execute();
        } else {
            $message = 'Có lỗi xảy ra: ' . $conn->error;
            $message_type = 'danger';
        }
    }
}

// Add or edit size
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $size_id = isset($_POST['size_id']) ? $_POST['size_id'] : null;
    $size_name = trim($_POST['size_name']);
    $size_value = trim($_POST['size_value']);
    
    if (empty($size_name) || empty($size_value)) {
        $message = 'Vui lòng điền đầy đủ thông tin!';
        $message_type = 'danger';
    } else {
        try {
            // Check if size value already exists - with different queries for new vs update
            if ($size_id) {
                // For update - exclude current record
                $check_sql = "SELECT id FROM thuoc_tinh WHERE loai = 'size' AND gia_tri = ? AND id != ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("si", $size_value, $size_id);
            } else {
                // For new record - check all records
                $check_sql = "SELECT id FROM thuoc_tinh WHERE loai = 'size' AND gia_tri = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("s", $size_value);
            }
            
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $message = 'Giá trị kích thước này đã tồn tại!';
                $message_type = 'danger';
            } else {
                if ($size_id) {
                    // Update existing size
                    $update = $conn->prepare("UPDATE thuoc_tinh SET ten = ?, gia_tri = ? WHERE id = ? AND loai = 'size'");
                    $update->bind_param("ssi", $size_name, $size_value, $size_id);
                    
                    if ($update->execute()) {
                        $message = 'Cập nhật kích thước thành công!';
                        $message_type = 'success';
                        
                        // Log the action - FIXED: Create detail string beforehand
                        $detail = 'Cập nhật kích thước: ' . $size_name;
                        $log_stmt = $conn->prepare("INSERT INTO nhat_ky (id_user, hanh_dong, doi_tuong_loai, doi_tuong_id, chi_tiet, ip_address) VALUES (?, 'update', 'size', ?, ?, ?)");
                        $log_stmt->bind_param("iiss", $admin_id, $size_id, $detail, $_SERVER['REMOTE_ADDR']);
                        $log_stmt->execute();
                    } else {
                        $message = 'Có lỗi xảy ra: ' . $conn->error;
                        $message_type = 'danger';
                    }
                } else {
                    // Add new size
                    $insert = $conn->prepare("INSERT INTO thuoc_tinh (ten, loai, gia_tri) VALUES (?, 'size', ?)");
                    $insert->bind_param("ss", $size_name, $size_value);
                    
                    if ($insert->execute()) {
                        $new_size_id = $conn->insert_id;
                        $message = 'Thêm kích thước thành công!';
                        $message_type = 'success';
                        
                        // Log the action - FIXED: Create detail string beforehand
                        $detail = 'Thêm kích thước mới: ' . $size_name;
                        $log_stmt = $conn->prepare("INSERT INTO nhat_ky (id_user, hanh_dong, doi_tuong_loai, doi_tuong_id, chi_tiet, ip_address) VALUES (?, 'create', 'size', ?, ?, ?)");
                        $log_stmt->bind_param("iiss", $admin_id, $new_size_id, $detail, $_SERVER['REMOTE_ADDR']);
                        $log_stmt->execute();
                    } else {
                        $message = 'Có lỗi xảy ra: ' . $conn->error;
                        $message_type = 'danger';
                    }
                }
            }
        } catch (mysqli_sql_exception $e) {
            // Handle database errors gracefully
            if ($e->getCode() == 1062) { // Duplicate entry error code
                $message = 'Giá trị kích thước này đã tồn tại!';
            } else {
                $message = 'Có lỗi xảy ra: ' . $e->getMessage();
            }
            $message_type = 'danger';
        }
    }
}

// Get size for editing
$edit_id = isset($_GET['edit']) ? $_GET['edit'] : null;
$edit_data = null;

if ($edit_id) {
    $edit_stmt = $conn->prepare("SELECT * FROM thuoc_tinh WHERE id = ? AND loai = 'size'");
    $edit_stmt->bind_param("i", $edit_id);
    $edit_stmt->execute();
    $result = $edit_stmt->get_result();
    
    if ($result->num_rows > 0) {
        $edit_data = $result->fetch_assoc();
    }
}

// Get all sizes with error handling
$sizes = [];
try {
    $sql = "SELECT * FROM thuoc_tinh WHERE loai = 'size' ORDER BY gia_tri ASC";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $sizes[] = $row;
        }
    }
} catch (Exception $e) {
    $message = 'Lỗi database: ' . $e->getMessage();
    $message_type = 'danger';
}

// Count products using each size - with error handling
$size_usage = [];
foreach ($sizes as $size) {
    try {
        $usage_stmt = $conn->prepare("SELECT COUNT(*) as count FROM sanpham_bien_the WHERE id_size = ?");
        if ($usage_stmt) {
            $usage_stmt->bind_param("i", $size['id']);
            $usage_stmt->execute();
            $usage_result = $usage_stmt->get_result();
            $size_usage[$size['id']] = $usage_result->fetch_assoc()['count'];
        } else {
            $size_usage[$size['id']] = 0;
        }
    } catch (Exception $e) {
        $size_usage[$size['id']] = 0;
    }
}

// Include header with proper error handling
if (file_exists('includes/header.php')) {
    include('includes/header.php');
} else {
    echo "Error: Header file not found!";
    exit;
}
?>

<div class="container-fluid">
    <div class="row">
        <?php 
        if (file_exists('includes/sidebar.php')) {
            include('includes/sidebar.php');
        } else {
            echo "<div class='col-md-2'><p class='text-danger'>Sidebar file not found!</p></div>";
        }
        ?>
        
        <main class="col-md-10 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Quản lý kích thước</h1>
            </div>
            
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <?php echo $edit_id ? 'Sửa kích thước' : 'Thêm kích thước mới'; ?>
                        </div>
                        <div class="card-body">
                            <form method="post" action="sizes.php">
                                <?php if ($edit_data): ?>
                                    <input type="hidden" name="size_id" value="<?php echo $edit_data['id']; ?>">
                                <?php endif; ?>
                                
                                <div class="mb-3">
                                    <label for="size_name" class="form-label">Tên kích thước</label>
                                    <input type="text" class="form-control" id="size_name" name="size_name" 
                                           value="<?php echo $edit_data ? htmlspecialchars($edit_data['ten']) : ''; ?>" 
                                           placeholder="VD: Size 38" required>
                                    <div class="form-text">Tên hiển thị của kích thước</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="size_value" class="form-label">Giá trị kích thước</label>
                                    <input type="text" class="form-control" id="size_value" name="size_value" 
                                           value="<?php echo $edit_data ? htmlspecialchars($edit_data['gia_tri']) : ''; ?>" 
                                           placeholder="VD: 38" required>
                                    <div class="form-text">Giá trị thực của kích thước (VD: 38, 39, XL, XXL...)</div>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <?php echo $edit_id ? 'Cập nhật kích thước' : 'Thêm kích thước'; ?>
                                    </button>
                                    <?php if ($edit_id): ?>
                                        <a href="sizes.php" class="btn btn-outline-secondary">Hủy chỉnh sửa</a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            Danh sách kích thước
                        </div>
                        <div class="card-body">
                            <?php if (count($sizes) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Tên kích thước</th>
                                                <th>Giá trị</th>
                                                <th>Sử dụng</th>
                                                <th>Thao tác</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($sizes as $size): ?>
                                                <tr>
                                                    <td><?php echo $size['id']; ?></td>
                                                    <td><?php echo htmlspecialchars($size['ten']); ?></td>
                                                    <td><?php echo htmlspecialchars($size['gia_tri']); ?></td>
                                                    <td>
                                                        <?php echo $size_usage[$size['id']]; ?> sản phẩm
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="sizes.php?edit=<?php echo $size['id']; ?>" class="btn btn-outline-primary">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <?php if ($size_usage[$size['id']] == 0): ?>
                                                                <a href="sizes.php?delete=<?php echo $size['id']; ?>" 
                                                                   class="btn btn-outline-danger" 
                                                                   onclick="return confirm('Bạn có chắc chắn muốn xóa kích thước này?');">
                                                                    <i class="fas fa-trash"></i>
                                                                </a>
                                                            <?php else: ?>
                                                                <button class="btn btn-outline-danger" disabled title="Không thể xóa kích thước đang được sử dụng">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info mb-0">
                                    Chưa có kích thước nào. Hãy thêm kích thước mới!
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php 
if (file_exists('includes/footer.php')) {
    include('includes/footer.php');
} else {
    echo "Error: Footer file not found!";
}
?>
