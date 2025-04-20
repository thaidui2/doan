<?php
// Include database connection
require_once('../config/config.php');

// Include authentication check
require_once('includes/auth_check.php');

// Get admin ID from session for logging actions
$admin_id = $_SESSION['admin_id'];

// Set current page for sidebar highlighting
$current_page = 'colors';
$page_title = 'Quản lý màu sắc';

// Process form submissions
$message = '';
$message_type = '';

// Delete color
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $color_id = $_GET['delete'];
    
    // Check if color is used in any product variants before deletion
    $check_used = $conn->prepare("SELECT COUNT(*) as count FROM sanpham_bien_the WHERE id_mau = ?");
    $check_used->bind_param("i", $color_id);
    $check_used->execute();
    $result = $check_used->get_result();
    $is_used = $result->fetch_assoc()['count'] > 0;
    
    if ($is_used) {
        $message = 'Không thể xóa màu sắc này vì đang được sử dụng trong sản phẩm.';
        $message_type = 'danger';
    } else {
        $delete = $conn->prepare("DELETE FROM thuoc_tinh WHERE id = ? AND loai = 'color'");
        $delete->bind_param("i", $color_id);
        
        if ($delete->execute()) {
            $message = 'Xóa màu sắc thành công!';
            $message_type = 'success';
            
            // Log the action
            $detail = 'Xóa màu sắc ID: ' . $color_id;
            $log_stmt = $conn->prepare("INSERT INTO nhat_ky (id_user, hanh_dong, doi_tuong_loai, doi_tuong_id, chi_tiet, ip_address) VALUES (?, 'delete', 'color', ?, ?, ?)");
            $log_stmt->bind_param("iiss", $admin_id, $color_id, $detail, $_SERVER['REMOTE_ADDR']);
            $log_stmt->execute();
        } else {
            $message = 'Có lỗi xảy ra: ' . $conn->error;
            $message_type = 'danger';
        }
    }
}

// Add or edit color
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $color_id = isset($_POST['color_id']) ? $_POST['color_id'] : null;
    $color_name = trim($_POST['color_name']);
    $color_value = trim($_POST['color_value']);
    $color_hex = trim($_POST['color_hex']);
    
    if (empty($color_name) || empty($color_value)) {
        $message = 'Vui lòng điền đầy đủ thông tin!';
        $message_type = 'danger';
    } else {
        try {
            // Check if color value already exists - with different queries for new vs update
            if ($color_id) {
                // For update - exclude current record
                $check_sql = "SELECT id FROM thuoc_tinh WHERE loai = 'color' AND gia_tri = ? AND id != ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("si", $color_value, $color_id);
            } else {
                // For new record - check all records
                $check_sql = "SELECT id FROM thuoc_tinh WHERE loai = 'color' AND gia_tri = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("s", $color_value);
            }
            
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $message = 'Giá trị màu sắc này đã tồn tại!';
                $message_type = 'danger';
            } else {
                if ($color_id) {
                    // Update existing color
                    $update = $conn->prepare("UPDATE thuoc_tinh SET ten = ?, gia_tri = ?, ma_mau = ? WHERE id = ? AND loai = 'color'");
                    $update->bind_param("sssi", $color_name, $color_value, $color_hex, $color_id);
                    
                    if ($update->execute()) {
                        $message = 'Cập nhật màu sắc thành công!';
                        $message_type = 'success';
                        
                        // Log the action
                        $detail = 'Cập nhật màu sắc: ' . $color_name;
                        $log_stmt = $conn->prepare("INSERT INTO nhat_ky (id_user, hanh_dong, doi_tuong_loai, doi_tuong_id, chi_tiet, ip_address) VALUES (?, 'update', 'color', ?, ?, ?)");
                        $log_stmt->bind_param("iiss", $admin_id, $color_id, $detail, $_SERVER['REMOTE_ADDR']);
                        $log_stmt->execute();
                    } else {
                        $message = 'Có lỗi xảy ra: ' . $conn->error;
                        $message_type = 'danger';
                    }
                } else {
                    // Add new color
                    $insert = $conn->prepare("INSERT INTO thuoc_tinh (ten, loai, gia_tri, ma_mau) VALUES (?, 'color', ?, ?)");
                    $insert->bind_param("sss", $color_name, $color_value, $color_hex);
                    
                    if ($insert->execute()) {
                        $new_color_id = $conn->insert_id;
                        $message = 'Thêm màu sắc thành công!';
                        $message_type = 'success';
                        
                        // Log the action
                        $detail = 'Thêm màu sắc mới: ' . $color_name;
                        $log_stmt = $conn->prepare("INSERT INTO nhat_ky (id_user, hanh_dong, doi_tuong_loai, doi_tuong_id, chi_tiet, ip_address) VALUES (?, 'create', 'color', ?, ?, ?)");
                        $log_stmt->bind_param("iiss", $admin_id, $new_color_id, $detail, $_SERVER['REMOTE_ADDR']);
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
                $message = 'Giá trị màu sắc này đã tồn tại!';
            } else {
                $message = 'Có lỗi xảy ra: ' . $e->getMessage();
            }
            $message_type = 'danger';
        }
    }
}

// Get color for editing
$edit_id = isset($_GET['edit']) ? $_GET['edit'] : null;
$edit_data = null;

if ($edit_id) {
    $edit_stmt = $conn->prepare("SELECT * FROM thuoc_tinh WHERE id = ? AND loai = 'color'");
    $edit_stmt->bind_param("i", $edit_id);
    $edit_stmt->execute();
    $result = $edit_stmt->get_result();
    
    if ($result->num_rows > 0) {
        $edit_data = $result->fetch_assoc();
    }
}

// Get all colors with error handling
$colors = [];
try {
    $sql = "SELECT * FROM thuoc_tinh WHERE loai = 'color' ORDER BY ten ASC";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $colors[] = $row;
        }
    }
} catch (Exception $e) {
    $message = 'Lỗi database: ' . $e->getMessage();
    $message_type = 'danger';
}

// Count products using each color - with error handling
$color_usage = [];
foreach ($colors as $color) {
    try {
        $usage_stmt = $conn->prepare("SELECT COUNT(*) as count FROM sanpham_bien_the WHERE id_mau = ?");
        if ($usage_stmt) {
            $usage_stmt->bind_param("i", $color['id']);
            $usage_stmt->execute();
            $usage_result = $usage_stmt->get_result();
            $color_usage[$color['id']] = $usage_result->fetch_assoc()['count'];
        } else {
            $color_usage[$color['id']] = 0;
        }
    } catch (Exception $e) {
        $color_usage[$color['id']] = 0;
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
                <h1 class="h2">Quản lý màu sắc</h1>
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
                            <?php echo $edit_id ? 'Sửa màu sắc' : 'Thêm màu sắc mới'; ?>
                        </div>
                        <div class="card-body">
                            <form method="post" action="colors.php">
                                <?php if ($edit_data): ?>
                                    <input type="hidden" name="color_id" value="<?php echo $edit_data['id']; ?>">
                                <?php endif; ?>
                                
                                <div class="mb-3">
                                    <label for="color_name" class="form-label">Tên màu sắc</label>
                                    <input type="text" class="form-control" id="color_name" name="color_name" 
                                           value="<?php echo $edit_data ? htmlspecialchars($edit_data['ten']) : ''; ?>" 
                                           placeholder="VD: Đỏ đậm" required>
                                    <div class="form-text">Tên hiển thị của màu sắc</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="color_value" class="form-label">Giá trị màu sắc</label>
                                    <input type="text" class="form-control" id="color_value" name="color_value" 
                                           value="<?php echo $edit_data ? htmlspecialchars($edit_data['gia_tri']) : ''; ?>" 
                                           placeholder="VD: Đỏ" required>
                                    <div class="form-text">Giá trị hiển thị của màu sắc</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="color_hex" class="form-label">Mã màu</label>
                                    <div class="input-group">
                                        <input type="color" class="form-control form-control-color" id="color_picker" 
                                               value="<?php echo $edit_data && $edit_data['ma_mau'] ? htmlspecialchars($edit_data['ma_mau']) : '#000000'; ?>" 
                                               onchange="document.getElementById('color_hex').value = this.value">
                                        <input type="text" class="form-control" id="color_hex" name="color_hex" 
                                               value="<?php echo $edit_data ? htmlspecialchars($edit_data['ma_mau']) : ''; ?>" 
                                               placeholder="#RRGGBB">
                                    </div>
                                    <div class="form-text">Mã màu HEX (VD: #FF0000 cho màu đỏ)</div>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <?php echo $edit_id ? 'Cập nhật màu sắc' : 'Thêm màu sắc'; ?>
                                    </button>
                                    <?php if ($edit_id): ?>
                                        <a href="colors.php" class="btn btn-outline-secondary">Hủy chỉnh sửa</a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            Danh sách màu sắc
                        </div>
                        <div class="card-body">
                            <?php if (count($colors) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Tên màu sắc</th>
                                                <th>Giá trị</th>
                                                <th>Mã màu</th>
                                                <th>Màu</th>
                                                <th>Sử dụng</th>
                                                <th>Thao tác</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($colors as $color): ?>
                                                <tr>
                                                    <td><?php echo $color['id']; ?></td>
                                                    <td><?php echo htmlspecialchars($color['ten']); ?></td>
                                                    <td><?php echo htmlspecialchars($color['gia_tri']); ?></td>
                                                    <td><?php echo $color['ma_mau'] ? htmlspecialchars($color['ma_mau']) : 'N/A'; ?></td>
                                                    <td>
                                                        <?php if ($color['ma_mau']): ?>
                                                            <div style="width: 30px; height: 30px; background-color: <?php echo htmlspecialchars($color['ma_mau']); ?>; border: 1px solid #ddd;"></div>
                                                        <?php else: ?>
                                                            <div class="text-muted">Không có</div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php echo $color_usage[$color['id']]; ?> sản phẩm
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="colors.php?edit=<?php echo $color['id']; ?>" class="btn btn-outline-primary">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <?php if ($color_usage[$color['id']] == 0): ?>
                                                                <a href="colors.php?delete=<?php echo $color['id']; ?>" 
                                                                   class="btn btn-outline-danger" 
                                                                   onclick="return confirm('Bạn có chắc chắn muốn xóa màu sắc này?');">
                                                                    <i class="fas fa-trash"></i>
                                                                </a>
                                                            <?php else: ?>
                                                                <button class="btn btn-outline-danger" disabled title="Không thể xóa màu sắc đang được sử dụng">
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
                                    Chưa có màu sắc nào. Hãy thêm màu sắc mới!
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
// Script to update hex input when color picker changes and vice versa
document.addEventListener('DOMContentLoaded', function() {
    const colorPicker = document.getElementById('color_picker');
    const hexInput = document.getElementById('color_hex');
    
    // Update color picker when hex input changes
    hexInput.addEventListener('input', function() {
        colorPicker.value = this.value;
    });
});
</script>

<?php 
if (file_exists('includes/footer.php')) {
    include('includes/footer.php');
} else {
    echo "Error: Footer file not found!";
}
?>
