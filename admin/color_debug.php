<?php
session_start();

// Kiểm tra đăng nhập
if(!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

include('../config/config.php');

// Lấy danh sách màu sắc
$colors_query = $conn->query("SELECT * FROM mausac ORDER BY tenmau");
$colors = [];
while ($color = $colors_query->fetch_assoc()) {
    $colors[] = $color;
}

// Kiểm tra tình trạng thư mục uploads/colors
$color_upload_dir = "../uploads/colors/";
$directory_exists = file_exists($color_upload_dir);
$directory_writable = false;
$uploaded_files = [];

if ($directory_exists) {
    $directory_writable = is_writable($color_upload_dir);
    
    // Lấy danh sách file trong thư mục
    if ($handle = opendir($color_upload_dir)) {
        while (($file = readdir($handle)) !== false) {
            if ($file != "." && $file != "..") {
                $uploaded_files[] = $file;
            }
        }
        closedir($handle);
    }
}

// Tạo thư mục nếu chưa tồn tại
if (!$directory_exists) {
    $mkdir_result = mkdir($color_upload_dir, 0777, true);
}

// Kiểm tra biến thể sản phẩm có hình ảnh màu
$variants_query = $conn->query("
    SELECT spct.id_chitiet, spct.id_sanpham, spct.id_mausac, spct.hinhanh_mau, 
           sp.tensanpham, ms.tenmau, ms.mamau
    FROM sanpham_chitiet spct
    JOIN sanpham sp ON spct.id_sanpham = sp.id_sanpham
    JOIN mausac ms ON spct.id_mausac = ms.id_mausac
    WHERE spct.hinhanh_mau IS NOT NULL
    ORDER BY sp.tensanpham, ms.tenmau
");

$variants_with_images = [];
while ($variant = $variants_query->fetch_assoc()) {
    $variants_with_images[] = $variant;
}

// Kiểm tra quyền file
function get_permissions($filepath) {
    if (!file_exists($filepath)) {
        return "File không tồn tại";
    }
    
    $perms = fileperms($filepath);
    
    switch ($perms & 0xF000) {
        case 0xC000: // socket
            $info = 's';
            break;
        case 0xA000: // symbolic link
            $info = 'l';
            break;
        case 0x8000: // regular
            $info = '-';
            break;
        case 0x6000: // block special
            $info = 'b';
            break;
        case 0x4000: // directory
            $info = 'd';
            break;
        case 0x2000: // character special
            $info = 'c';
            break;
        case 0x1000: // FIFO pipe
            $info = 'p';
            break;
        default: // unknown
            $info = 'u';
    }
    
    // Owner
    $info .= (($perms & 0x0100) ? 'r' : '-');
    $info .= (($perms & 0x0080) ? 'w' : '-');
    $info .= (($perms & 0x0040) ?
               (($perms & 0x0800) ? 's' : 'x' ) :
               (($perms & 0x0800) ? 'S' : '-'));
    
    // Group
    $info .= (($perms & 0x0020) ? 'r' : '-');
    $info .= (($perms & 0x0010) ? 'w' : '-');
    $info .= (($perms & 0x0008) ?
               (($perms & 0x0400) ? 's' : 'x' ) :
               (($perms & 0x0400) ? 'S' : '-'));
    
    // World
    $info .= (($perms & 0x0004) ? 'r' : '-');
    $info .= (($perms & 0x0002) ? 'w' : '-');
    $info .= (($perms & 0x0001) ?
               (($perms & 0x0200) ? 't' : 'x' ) :
               (($perms & 0x0200) ? 'T' : '-'));
               
    return $info;
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kiểm tra màu sắc - Bug Shop Admin</title>
    <link rel="stylesheet" href="../node_modules/bootstrap/dist/css/bootstrap.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../css/admin-styles.css">
</head>
<body class="bg-light">
    <div class="container py-4">
        <h1 class="mb-4">
            <i class="bi bi-bug"></i> Kiểm tra và debug màu sắc
            <a href="edit_product.php" class="btn btn-sm btn-outline-secondary float-end">Quay lại</a>
        </h1>
        
        <div class="row g-4">
            <!-- Thư mục uploads -->
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">Thư mục uploads/colors</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong>Đường dẫn:</strong> <?php echo realpath($color_upload_dir); ?>
                        </div>
                        
                        <div class="mb-3">
                            <strong>Trạng thái:</strong>
                            <?php if ($directory_exists): ?>
                                <span class="badge bg-success">Tồn tại</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Không tồn tại</span>
                                <?php if (isset($mkdir_result)): ?>
                                    <?php if ($mkdir_result): ?>
                                        <span class="badge bg-success">Đã tạo thành công</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Lỗi khi tạo thư mục</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <strong>Quyền ghi:</strong>
                            <?php if ($directory_exists): ?>
                                <?php if ($directory_writable): ?>
                                    <span class="badge bg-success">Có thể ghi</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Không thể ghi</span>
                                <?php endif; ?>
                                <div class="small text-muted">
                                    Permissions: <?php echo get_permissions($color_upload_dir); ?>
                                </div>
                            <?php else: ?>
                                <span class="badge bg-secondary">Không xác định</span>
                            <?php endif; ?>
                        </div>
                        
                        <h6 class="mt-4">Các file đã upload (<?php echo count($uploaded_files); ?>):</h6>
                        <?php if (empty($uploaded_files)): ?>
                            <div class="alert alert-info">Không có file nào trong thư mục</div>
                        <?php else: ?>
                            <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                                <table class="table table-sm table-striped">
                                    <thead>
                                        <tr>
                                            <th>Tên file</th>
                                            <th>Kích thước</th>
                                            <th>Quyền</th>
                                            <th>Xem</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($uploaded_files as $file): ?>
                                            <tr>
                                                <td><?php echo $file; ?></td>
                                                <td><?php echo round(filesize($color_upload_dir . $file) / 1024, 2); ?> KB</td>
                                                <td><?php echo get_permissions($color_upload_dir . $file); ?></td>
                                                <td>
                                                    <a href="#" data-bs-toggle="modal" data-bs-target="#imageModal" 
                                                       data-img-src="../uploads/colors/<?php echo $file; ?>" 
                                                       data-img-title="<?php echo $file; ?>">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Form thử upload -->
                        <hr>
                        <h6>Thử upload ảnh:</h6>
                        <form action="upload_test.php" method="post" enctype="multipart/form-data" class="mt-3">
                            <div class="input-group mb-3">
                                <input type="file" class="form-control" name="test_image" accept="image/*" required>
                                <button type="submit" class="btn btn-primary">Tải lên</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Danh sách màu sắc -->
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">Danh sách màu sắc (<?php echo count($colors); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Màu</th>
                                        <th>Tên</th>
                                        <th>Mã màu</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($colors as $color): ?>
                                        <tr>
                                            <td><?php echo $color['id_mausac']; ?></td>
                                            <td>
                                                <div class="color-swatch" style="background-color: <?php echo $color['mamau']; ?>"></div>
                                            </td>
                                            <td><?php echo $color['tenmau']; ?></td>
                                            <td><?php echo $color['mamau']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Biến thể có hình ảnh màu -->
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">Biến thể sản phẩm có hình ảnh màu (<?php echo count($variants_with_images); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($variants_with_images)): ?>
                            <div class="alert alert-info">Không có biến thể nào có hình ảnh màu</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Sản phẩm</th>
                                            <th>Màu</th>
                                            <th>Hình ảnh</th>
                                            <th>File tồn tại</th>
                                            <th>Xem</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($variants_with_images as $variant): ?>
                                            <?php $file_exists = file_exists($color_upload_dir . $variant['hinhanh_mau']); ?>
                                            <tr>
                                                <td><?php echo $variant['id_chitiet']; ?></td>
                                                <td><?php echo $variant['tensanpham']; ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="color-swatch me-2" style="background-color: <?php echo $variant['mamau']; ?>"></div>
                                                        <?php echo $variant['tenmau']; ?>
                                                    </div>
                                                </td>
                                                <td><?php echo $variant['hinhanh_mau']; ?></td>
                                                <td>
                                                    <?php if ($file_exists): ?>
                                                        <span class="badge bg-success">Tồn tại</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Không tồn tại</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($file_exists): ?>
                                                        <a href="#" data-bs-toggle="modal" data-bs-target="#imageModal" 
                                                           data-img-src="../uploads/colors/<?php echo $variant['hinhanh_mau']; ?>" 
                                                           data-img-title="<?php echo $variant['tenmau']; ?>">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <i class="bi bi-exclamation-triangle text-danger"></i>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Image Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="imageModalTitle">Hình ảnh</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalImage" src="" class="img-fluid" alt="Image">
                </div>
            </div>
        </div>
    </div>
    
    <script src="../node_modules/bootstrap/dist/js/bootstrap.bundle.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const imageModal = document.getElementById('imageModal');
            if (imageModal) {
                imageModal.addEventListener('show.bs.modal', event => {
                    const button = event.relatedTarget;
                    const imgSrc = button.getAttribute('data-img-src');
                    const imgTitle = button.getAttribute('data-img-title');
                    
                    const modalImage = document.getElementById('modalImage');
                    const modalTitle = document.getElementById('imageModalTitle');
                    
                    modalImage.src = imgSrc;
                    modalTitle.textContent = imgTitle;
                });
            }
        });
    </script>
</body>
</html>
