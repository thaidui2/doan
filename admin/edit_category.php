<?php
// Start output buffering to prevent header issues
ob_start();

// Set page title
$page_title = 'Chỉnh sửa danh mục';

// Include database connection before header
include('../config/config.php');

// Check if ID is provided and valid
$category_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($category_id <= 0) {
    $_SESSION['error_message'] = "ID danh mục không hợp lệ";
    header("Location: categories.php");
    exit;
}

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data with validation
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
    $order = isset($_POST['order']) ? (int)$_POST['order'] : 0;
    $status = isset($_POST['status']) ? (int)$_POST['status'] : 0;
    $meta_title = trim($_POST['meta_title'] ?? '');
    $meta_description = trim($_POST['meta_description'] ?? '');
    
    // Validation
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Tên danh mục không được để trống";
    }
    
    if (empty($slug)) {
        // Generate slug from name if not provided
        $slug = create_slug($name);
    }
    
    // Check if slug exists for other categories
    $check_slug = $conn->prepare("SELECT id FROM danhmuc WHERE slug = ? AND id != ?");
    $check_slug->bind_param("si", $slug, $category_id);
    $check_slug->execute();
    if ($check_slug->get_result()->num_rows > 0) {
        $errors[] = "Slug đã tồn tại, vui lòng chọn slug khác";
    }
    
    // Prevent category from being its own parent
    if ($parent_id == $category_id) {
        $errors[] = "Danh mục không thể là danh mục cha của chính nó";
    }
    
    // Process image upload if provided
    $image = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $upload_dir = "../uploads/categories/";
        
        // Create directory if not exists
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_tmp = $_FILES['image']['tmp_name'];
        $file_name = basename($_FILES['image']['name']);
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Check file extension
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($file_ext, $allowed_ext)) {
            $new_file_name = 'category_' . uniqid() . '.' . $file_ext;
            $dest_path = $upload_dir . $new_file_name;
            
            if (move_uploaded_file($file_tmp, $dest_path)) {
                $image = 'uploads/categories/' . $new_file_name;
                
                // Delete old image if exists
                if (!empty($_POST['current_image'])) {
                    $old_image_path = '../' . $_POST['current_image'];
                    if (file_exists($old_image_path) && is_file($old_image_path)) {
                        unlink($old_image_path);
                    }
                }
            } else {
                $errors[] = "Không thể tải lên hình ảnh, vui lòng thử lại";
            }
        } else {
            $errors[] = "Định dạng file không được hỗ trợ. Vui lòng sử dụng: " . implode(', ', $allowed_ext);
        }
    } else {
        // Keep current image
        $image = $_POST['current_image'] ?? null;
    }
    
    // Update category if no errors
    if (empty($errors)) {
        // Prepare update query with or without parent_id
        if ($parent_id) {
            $stmt = $conn->prepare("
                UPDATE danhmuc SET 
                ten = ?, 
                slug = ?, 
                mo_ta = ?, 
                danhmuc_cha = ?, 
                thu_tu = ?, 
                meta_title = ?, 
                meta_description = ?, 
                trang_thai = ?,
                hinhanh = ?
                WHERE id = ?
            ");
            $stmt->bind_param("sssiisssis", $name, $slug, $description, $parent_id, $order, $meta_title, $meta_description, $status, $image, $category_id);
        } else {
            $stmt = $conn->prepare("
                UPDATE danhmuc SET 
                ten = ?, 
                slug = ?, 
                mo_ta = ?, 
                danhmuc_cha = NULL, 
                thu_tu = ?, 
                meta_title = ?, 
                meta_description = ?, 
                trang_thai = ?,
                hinhanh = ?
                WHERE id = ?
            ");
            // Fixed the type string - it had 10 characters but we only have 9 parameters
            $stmt->bind_param("sssissisi", $name, $slug, $description, $order, $meta_title, $meta_description, $status, $image, $category_id);
        }
        
        if ($stmt->execute()) {
            // Log activity
            $admin_id = $_SESSION['admin_id'] ?? 0;
            $log_details = "Cập nhật danh mục: $name (ID: $category_id)";
            
            if (function_exists('logAdminActivity')) {
                logAdminActivity($conn, $admin_id, 'update', 'category', $category_id, $log_details);
            }
            
            $_SESSION['success_message'] = "Cập nhật danh mục thành công";
            header("Location: categories.php");
            exit;
        } else {
            $errors[] = "Lỗi khi cập nhật danh mục: " . $conn->error;
        }
    }
}

// Include the header and sidebar
include('includes/header.php');

// Get category data
$stmt = $conn->prepare("SELECT * FROM danhmuc WHERE id = ?");
$stmt->bind_param("i", $category_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "Danh mục không tồn tại";
    header("Location: categories.php");
    exit;
}

$category = $result->fetch_assoc();

// Get parent categories for dropdown (excluding the current category)
$parents_query = "SELECT id, ten FROM danhmuc WHERE id != ? AND (danhmuc_cha != ? OR danhmuc_cha IS NULL) ORDER BY ten ASC";
$parents_stmt = $conn->prepare($parents_query);
$parents_stmt->bind_param("ii", $category_id, $category_id);
$parents_stmt->execute();
$parents_result = $parents_stmt->get_result();

// Helper function to create slug
function create_slug($string) {
    $search = array(
        '#(à|á|ạ|ả|ã|â|ầ|ấ|ậ|ẩ|ẫ|ă|ằ|ắ|ặ|ẳ|ẵ)#',
        '#(è|é|ẹ|ẻ|ẽ|ê|ề|ế|ệ|ể|ễ)#',
        '#(ì|í|ị|ỉ|ĩ)#',
        '#(ò|ó|ọ|ỏ|õ|ô|ồ|ố|ộ|ổ|ỗ|ơ|ờ|ớ|ợ|ở|ỡ)#',
        '#(ù|ú|ụ|ủ|ũ|ư|ừ|ứ|ự|ử|ữ)#',
        '#(ỳ|ý|ỵ|ỷ|ỹ)#',
        '#(đ)#',
        '#[^a-z0-9\s]#',
    );
    
    $replace = array(
        'a',
        'e',
        'i',
        'o',
        'u',
        'y',
        'd',
        '',
    );
    
    $string = preg_replace($search, $replace, mb_strtolower($string, 'UTF-8'));
    $string = preg_replace('/(\s|[^a-z0-9])+/', '-', $string);
    $string = trim($string, '-');
    
    return $string;
}
?>

<!-- Main content -->
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Trang chủ</a></li>
            <li class="breadcrumb-item"><a href="categories.php">Quản lý danh mục</a></li>
            <li class="breadcrumb-item active" aria-current="page">Chỉnh sửa danh mục</li>
        </ol>
    </nav>
    
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Chỉnh sửa danh mục</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="categories.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Quay lại
            </a>
        </div>
    </div>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-body">
            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?id=' . $category_id); ?>" method="post" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-md-8">
                        <!-- Basic Information -->
                        <div class="mb-3">
                            <label for="name" class="form-label">Tên danh mục <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($category['ten']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="slug" class="form-label">Slug</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="slug" name="slug" value="<?php echo htmlspecialchars($category['slug']); ?>">
                                <button class="btn btn-outline-secondary" type="button" id="generate-slug">Tạo tự động</button>
                            </div>
                            <small class="form-text text-muted">Để trống để tự động tạo từ tên danh mục</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Mô tả</label>
                            <textarea class="form-control" id="description" name="description" rows="4"><?php echo htmlspecialchars($category['mo_ta'] ?? ''); ?></textarea>
                        </div>
                        
                        <!-- SEO Information -->
                        <div class="card mt-4 mb-4">
                            <div class="card-header bg-light">
                                <h5 class="card-title mb-0">Thông tin SEO</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="meta_title" class="form-label">Meta Title</label>
                                    <input type="text" class="form-control" id="meta_title" name="meta_title" value="<?php echo htmlspecialchars($category['meta_title'] ?? ''); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="meta_description" class="form-label">Meta Description</label>
                                    <textarea class="form-control" id="meta_description" name="meta_description" rows="3"><?php echo htmlspecialchars($category['meta_description'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <!-- Category Settings -->
                        <div class="card mb-4">
                            <div class="card-header bg-light">
                                <h5 class="card-title mb-0">Cài đặt danh mục</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="parent_id" class="form-label">Danh mục cha</label>
                                    <select class="form-select" id="parent_id" name="parent_id">
                                        <option value="">Không có danh mục cha</option>
                                        <?php while ($parent = $parents_result->fetch_assoc()): ?>
                                            <option value="<?php echo $parent['id']; ?>" <?php echo ($category['danhmuc_cha'] == $parent['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($parent['ten']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="order" class="form-label">Thứ tự hiển thị</label>
                                    <input type="number" class="form-control" id="order" name="order" value="<?php echo (int)$category['thu_tu']; ?>" min="0">
                                    <small class="form-text text-muted">Số nhỏ hơn sẽ hiển thị trước</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="status" class="form-label">Trạng thái</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="1" <?php echo ($category['trang_thai'] == 1) ? 'selected' : ''; ?>>Hiển thị</option>
                                        <option value="0" <?php echo ($category['trang_thai'] == 0) ? 'selected' : ''; ?>>Ẩn</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Category Image -->
                        <div class="card mb-4">
                            <div class="card-header bg-light">
                                <h5 class="card-title mb-0">Hình ảnh</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                    <small class="form-text text-muted">Định dạng cho phép: JPG, PNG, GIF. Tối đa 2MB.</small>
                                    <input type="hidden" name="current_image" value="<?php echo htmlspecialchars($category['hinhanh'] ?? ''); ?>">
                                </div>
                                
                                <?php if (!empty($category['hinhanh'])): ?>
                                    <div class="mt-3">
                                        <p>Hình ảnh hiện tại:</p>
                                        <img src="../<?php echo htmlspecialchars($category['hinhanh']); ?>" class="img-thumbnail" style="max-height: 150px;" alt="Category Image">
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Submit Buttons -->
                <div class="d-flex justify-content-end mt-4">
                    <a href="categories.php" class="btn btn-secondary me-2">Hủy</a>
                    <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
                </div>
            </form>
        </div>
    </div>
</main>

<?php
$page_specific_js = '
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Generate slug from name
    const nameInput = document.getElementById("name");
    const slugInput = document.getElementById("slug");
    const generateSlugBtn = document.getElementById("generate-slug");
    
    generateSlugBtn.addEventListener("click", function() {
        const name = nameInput.value.trim();
        if (name) {
            slugInput.value = generateSlug(name);
        }
    });
    
    // Live slug generation
    nameInput.addEventListener("blur", function() {
        if (slugInput.value === "") {
            const name = nameInput.value.trim();
            if (name) {
                slugInput.value = generateSlug(name);
            }
        }
    });
    
    function generateSlug(text) {
        // Convert Vietnamese text to ASCII
        text = text.normalize("NFD").replace(/[\u0300-\u036f]/g, "");
        
        return text
            .toLowerCase()
            .replace(/[^\w ]+/g, "")
            .replace(/ +/g, "-");
    }
});
</script>';

include('includes/footer.php');
?>
