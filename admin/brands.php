<?php
// Include database connection
require_once('../config/config.php');

// Include authentication check
require_once('includes/auth_check.php');

// Get admin ID from session for logging actions
$admin_id = $_SESSION['admin_id'];

// Set current page for sidebar highlighting
$current_page = 'brands';
$page_title = 'Quản lý thương hiệu';

// Process form submissions
$message = '';
$message_type = '';

// Define upload directory
$upload_dir = '../uploads/brands/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Delete brand
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $brand_id = $_GET['delete'];
    
    // Check if brand is used in any products before deletion
    $check_used = $conn->prepare("SELECT COUNT(*) as count FROM sanpham WHERE thuonghieu = ?");
    $check_used->bind_param("i", $brand_id);
    $check_used->execute();
    $result = $check_used->get_result();
    $is_used = $result->fetch_assoc()['count'] > 0;
    
    if ($is_used) {
        $message = 'Không thể xóa thương hiệu này vì đang được sử dụng trong sản phẩm.';
        $message_type = 'danger';
    } else {
        // Get current logo file to delete
        $get_logo = $conn->prepare("SELECT logo FROM thuong_hieu WHERE id = ?");
        $get_logo->bind_param("i", $brand_id);
        $get_logo->execute();
        $logo_result = $get_logo->get_result();
        $logo_data = $logo_result->fetch_assoc();
        
        // Delete from database
        $delete = $conn->prepare("DELETE FROM thuong_hieu WHERE id = ?");
        $delete->bind_param("i", $brand_id);
        
        if ($delete->execute()) {
            // Delete logo file if exists
            if (!empty($logo_data['logo'])) {
                $logo_path = $upload_dir . $logo_data['logo'];
                if (file_exists($logo_path)) {
                    unlink($logo_path);
                }
            }
            
            $message = 'Xóa thương hiệu thành công!';
            $message_type = 'success';
            
            // Log the action
            $detail = 'Xóa thương hiệu ID: ' . $brand_id;
            $log_stmt = $conn->prepare("INSERT INTO nhat_ky (id_user, hanh_dong, doi_tuong_loai, doi_tuong_id, chi_tiet, ip_address) VALUES (?, 'delete', 'brand', ?, ?, ?)");
            $log_stmt->bind_param("iiss", $admin_id, $brand_id, $detail, $_SERVER['REMOTE_ADDR']);
            $log_stmt->execute();
        } else {
            $message = 'Có lỗi xảy ra: ' . $conn->error;
            $message_type = 'danger';
        }
    }
}

// Add or edit brand
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $brand_id = isset($_POST['brand_id']) ? $_POST['brand_id'] : null;
    $brand_name = trim($_POST['brand_name']);
    $brand_description = trim($_POST['brand_description']);
    $logo_file = null;
    
    if (empty($brand_name)) {
        $message = 'Vui lòng điền tên thương hiệu!';
        $message_type = 'danger';
    } else {
        try {
            // Handle file upload if a file was provided
            if (isset($_FILES['brand_logo']) && $_FILES['brand_logo']['error'] == 0) {
                $file_tmp = $_FILES['brand_logo']['tmp_name'];
                $file_name = $_FILES['brand_logo']['name'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                // Check file extension
                $allowed_exts = array('jpg', 'jpeg', 'png', 'gif');
                if (!in_array($file_ext, $allowed_exts)) {
                    throw new Exception('Chỉ chấp nhận file hình ảnh (JPG, JPEG, PNG, GIF)');
                }
                
                // Generate unique filename
                $logo_file = 'brand_' . uniqid() . '.' . $file_ext;
                $upload_path = $upload_dir . $logo_file;
                
                // Move uploaded file
                if (!move_uploaded_file($file_tmp, $upload_path)) {
                    throw new Exception('Không thể tải lên file hình ảnh. Vui lòng thử lại.');
                }
            }
            
            // Check if brand name already exists (for different records)
            if ($brand_id) {
                // For update - exclude current record
                $check_sql = "SELECT id FROM thuong_hieu WHERE ten = ? AND id != ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("si", $brand_name, $brand_id);
            } else {
                // For new record - check all records
                $check_sql = "SELECT id FROM thuong_hieu WHERE ten = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("s", $brand_name);
            }
            
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $message = 'Thương hiệu này đã tồn tại!';
                $message_type = 'danger';
                
                // Delete uploaded file if name check failed
                if ($logo_file && file_exists($upload_dir . $logo_file)) {
                    unlink($upload_dir . $logo_file);
                }
            } else {
                if ($brand_id) {
                    // Get current logo if we need to update
                    $current_logo = '';
                    if ($logo_file === null) {
                        $get_logo = $conn->prepare("SELECT logo FROM thuong_hieu WHERE id = ?");
                        $get_logo->bind_param("i", $brand_id);
                        $get_logo->execute();
                        $logo_result = $get_logo->get_result();
                        $logo_data = $logo_result->fetch_assoc();
                        $current_logo = $logo_data['logo'] ?? '';
                    } else {
                        // If uploading a new logo, delete the old one
                        $get_logo = $conn->prepare("SELECT logo FROM thuong_hieu WHERE id = ?");
                        $get_logo->bind_param("i", $brand_id);
                        $get_logo->execute();
                        $logo_result = $get_logo->get_result();
                        $logo_data = $logo_result->fetch_assoc();
                        
                        if (!empty($logo_data['logo'])) {
                            $old_logo = $upload_dir . $logo_data['logo'];
                            if (file_exists($old_logo)) {
                                unlink($old_logo);
                            }
                        }
                        $current_logo = $logo_file;
                    }
                    
                    // Update existing brand
                    if ($logo_file !== null) {
                        // Update with new logo
                        $update = $conn->prepare("UPDATE thuong_hieu SET ten = ?, mo_ta = ?, logo = ? WHERE id = ?");
                        $update->bind_param("sssi", $brand_name, $brand_description, $logo_file, $brand_id);
                    } else {
                        // Update without changing logo
                        $update = $conn->prepare("UPDATE thuong_hieu SET ten = ?, mo_ta = ? WHERE id = ?");
                        $update->bind_param("ssi", $brand_name, $brand_description, $brand_id);
                    }
                    
                    if ($update->execute()) {
                        $message = 'Cập nhật thương hiệu thành công!';
                        $message_type = 'success';
                        
                        // Log the action
                        $detail = 'Cập nhật thương hiệu: ' . $brand_name;
                        $log_stmt = $conn->prepare("INSERT INTO nhat_ky (id_user, hanh_dong, doi_tuong_loai, doi_tuong_id, chi_tiet, ip_address) VALUES (?, 'update', 'brand', ?, ?, ?)");
                        $log_stmt->bind_param("iiss", $admin_id, $brand_id, $detail, $_SERVER['REMOTE_ADDR']);
                        $log_stmt->execute();
                    } else {
                        $message = 'Có lỗi xảy ra: ' . $conn->error;
                        $message_type = 'danger';
                    }
                } else {
                    // Add new brand
                    $insert = $conn->prepare("INSERT INTO thuong_hieu (ten, mo_ta, logo) VALUES (?, ?, ?)");
                    $insert->bind_param("sss", $brand_name, $brand_description, $logo_file);
                    
                    if ($insert->execute()) {
                        $new_brand_id = $conn->insert_id;
                        $message = 'Thêm thương hiệu thành công!';
                        $message_type = 'success';
                        
                        // Log the action
                        $detail = 'Thêm thương hiệu mới: ' . $brand_name;
                        $log_stmt = $conn->prepare("INSERT INTO nhat_ky (id_user, hanh_dong, doi_tuong_loai, doi_tuong_id, chi_tiet, ip_address) VALUES (?, 'create', 'brand', ?, ?, ?)");
                        $log_stmt->bind_param("iiss", $admin_id, $new_brand_id, $detail, $_SERVER['REMOTE_ADDR']);
                        $log_stmt->execute();
                    } else {
                        $message = 'Có lỗi xảy ra: ' . $conn->error;
                        $message_type = 'danger';
                        
                        // Delete uploaded file if insert failed
                        if ($logo_file && file_exists($upload_dir . $logo_file)) {
                            unlink($upload_dir . $logo_file);
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $message = 'Có lỗi xảy ra: ' . $e->getMessage();
            $message_type = 'danger';
            
            // Delete uploaded file in case of error
            if ($logo_file && file_exists($upload_dir . $logo_file)) {
                unlink($upload_dir . $logo_file);
            }
        }
    }
}

// Get brand for editing
$edit_id = isset($_GET['edit']) ? $_GET['edit'] : null;
$edit_data = null;

if ($edit_id) {
    $edit_stmt = $conn->prepare("SELECT * FROM thuong_hieu WHERE id = ?");
    $edit_stmt->bind_param("i", $edit_id);
    $edit_stmt->execute();
    $result = $edit_stmt->get_result();
    
    if ($result->num_rows > 0) {
        $edit_data = $result->fetch_assoc();
    }
}

// Get all brands with error handling
$brands = [];
try {
    $sql = "SELECT * FROM thuong_hieu ORDER BY ten ASC";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $brands[] = $row;
        }
    }
} catch (Exception $e) {
    $message = 'Lỗi database: ' . $e->getMessage();
    $message_type = 'danger';
}

// Count products using each brand - with error handling
$brand_usage = [];
foreach ($brands as $brand) {
    try {
        $usage_stmt = $conn->prepare("SELECT COUNT(*) as count FROM sanpham WHERE thuonghieu = ?");
        if ($usage_stmt) {
            $usage_stmt->bind_param("i", $brand['id']);
            $usage_stmt->execute();
            $usage_result = $usage_stmt->get_result();
            $brand_usage[$brand['id']] = $usage_result->fetch_assoc()['count'];
        } else {
            $brand_usage[$brand['id']] = 0;
        }
    } catch (Exception $e) {
        $brand_usage[$brand['id']] = 0;
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
                <h1 class="h2">Quản lý thương hiệu</h1>
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
                            <?php echo $edit_id ? 'Sửa thương hiệu' : 'Thêm thương hiệu mới'; ?>
                        </div>
                        <div class="card-body">
                            <form method="post" action="brands.php" enctype="multipart/form-data">
                                <?php if ($edit_data): ?>
                                    <input type="hidden" name="brand_id" value="<?php echo $edit_data['id']; ?>">
                                <?php endif; ?>
                                
                                <div class="mb-3">
                                    <label for="brand_name" class="form-label">Tên thương hiệu</label>
                                    <input type="text" class="form-control" id="brand_name" name="brand_name" 
                                           value="<?php echo $edit_data ? htmlspecialchars($edit_data['ten']) : ''; ?>" 
                                           placeholder="VD: Nike" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="brand_description" class="form-label">Mô tả</label>
                                    <textarea class="form-control" id="brand_description" name="brand_description" 
                                              rows="3" placeholder="Mô tả ngắn về thương hiệu"><?php echo $edit_data ? htmlspecialchars($edit_data['mo_ta']) : ''; ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="brand_logo" class="form-label">Logo</label>
                                    <?php if ($edit_data && $edit_data['logo']): ?>
                                        <div class="mb-2">
                                            <img src="<?php echo '../uploads/brands/' . htmlspecialchars($edit_data['logo']); ?>" 
                                                 alt="<?php echo htmlspecialchars($edit_data['ten']); ?>" 
                                                 class="img-thumbnail" style="max-height: 100px;">
                                        </div>
                                    <?php endif; ?>
                                    <input type="file" class="form-control" id="brand_logo" name="brand_logo">
                                    <div class="form-text">Chọn file hình ảnh để tải lên. Định dạng: JPG, PNG, GIF.</div>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <?php echo $edit_id ? 'Cập nhật thương hiệu' : 'Thêm thương hiệu'; ?>
                                    </button>
                                    <?php if ($edit_id): ?>
                                        <a href="brands.php" class="btn btn-outline-secondary">Hủy chỉnh sửa</a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            Danh sách thương hiệu
                        </div>
                        <div class="card-body">
                            <?php if (count($brands) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Logo</th>
                                                <th>Tên thương hiệu</th>
                                                <th>Mô tả</th>
                                                <th>Sản phẩm</th>
                                                <th>Thao tác</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($brands as $brand): ?>
                                                <tr>
                                                    <td><?php echo $brand['id']; ?></td>
                                                    <td>
                                                        <?php if (!empty($brand['logo'])): ?>
                                                            <img src="<?php echo '../uploads/brands/' . htmlspecialchars($brand['logo']); ?>" 
                                                                 alt="<?php echo htmlspecialchars($brand['ten']); ?>" 
                                                                 class="img-thumbnail" style="max-height: 50px;">
                                                        <?php else: ?>
                                                            <span class="text-muted">Không có logo</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($brand['ten']); ?></td>
                                                    <td><?php echo !empty($brand['mo_ta']) ? htmlspecialchars(substr($brand['mo_ta'], 0, 50)) . (strlen($brand['mo_ta']) > 50 ? '...' : '') : '-'; ?></td>
                                                    <td>
                                                        <?php echo $brand_usage[$brand['id']]; ?> sản phẩm
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="brands.php?edit=<?php echo $brand['id']; ?>" class="btn btn-outline-primary">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <?php if ($brand_usage[$brand['id']] == 0): ?>
                                                                <a href="brands.php?delete=<?php echo $brand['id']; ?>" 
                                                                   class="btn btn-outline-danger" 
                                                                   onclick="return confirm('Bạn có chắc chắn muốn xóa thương hiệu này?');">
                                                                    <i class="fas fa-trash"></i>
                                                                </a>
                                                            <?php else: ?>
                                                                <button class="btn btn-outline-danger" disabled title="Không thể xóa thương hiệu đang được sử dụng">
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
                                    Chưa có thương hiệu nào. Hãy thêm thương hiệu mới!
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
