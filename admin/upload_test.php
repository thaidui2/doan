<?php
session_start();

// Kiểm tra đăng nhập
if(!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

header('Content-Type: text/html; charset=utf-8');

$result = [
    'success' => false,
    'message' => '',
    'details' => []
];

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Phương thức không hợp lệ");
    }

    if (!isset($_FILES['test_image']) || $_FILES['test_image']['error'] != 0) {
        throw new Exception("Không có file nào được upload hoặc có lỗi: " . 
            (isset($_FILES['test_image']) ? $_FILES['test_image']['error'] : 'Unknown error'));
    }

    $upload_dir = "../uploads/colors/";
    
    // Kiểm tra thư mục upload
    $result['details']['directory_exists'] = file_exists($upload_dir);
    $result['details']['directory_writable'] = is_writable($upload_dir);
    
    // Tạo thư mục nếu chưa tồn tại
    if (!file_exists($upload_dir)) {
        $result['details']['mkdir_attempt'] = true;
        $result['details']['mkdir_result'] = mkdir($upload_dir, 0777, true);
    }

    // Thông tin file
    $file_tmp = $_FILES['test_image']['tmp_name'];
    $file_name = $_FILES['test_image']['name'];
    $file_size = $_FILES['test_image']['size'];
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    $result['details']['file_info'] = [
        'name' => $file_name,
        'size' => $file_size,
        'tmp_name' => $file_tmp,
        'extension' => $file_ext
    ];
    
    // Kiểm tra loại file
    $allowed_exts = ["jpg", "jpeg", "png", "gif", "webp"];
    if (!in_array($file_ext, $allowed_exts)) {
        throw new Exception("File không được chấp nhận. Chỉ cho phép: " . implode(", ", $allowed_exts));
    }
    
    // Tạo tên file duy nhất
    $unique_name = "test_" . time() . "_" . uniqid() . "." . $file_ext;
    $upload_path = $upload_dir . $unique_name;
    
    $result['details']['upload_path'] = $upload_path;
    
    // Thực hiện upload
    if (move_uploaded_file($file_tmp, $upload_path)) {
        $result['success'] = true;
        $result['message'] = "Upload thành công!";
        $result['details']['file_url'] = "../uploads/colors/" . $unique_name;
        $result['details']['file_permissions'] = decoct(fileperms($upload_path) & 0777);
    } else {
        throw new Exception("Không thể upload file. Kiểm tra quyền thư mục.");
    }
    
} catch (Exception $e) {
    $result['message'] = $e->getMessage();
}

?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kết quả test upload - Bug Shop Admin</title>
    <link rel="stylesheet" href="../node_modules/bootstrap/dist/css/bootstrap.css">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="card">
            <div class="card-header <?php echo $result['success'] ? 'bg-success' : 'bg-danger'; ?> text-white">
                <h4><?php echo $result['success'] ? 'Upload thành công' : 'Upload thất bại'; ?></h4>
            </div>
            <div class="card-body">
                <p class="lead"><?php echo $result['message']; ?></p>
                
                <?php if ($result['success']): ?>
                    <div class="mb-4 text-center">
                        <img src="<?php echo $result['details']['file_url']; ?>" alt="Uploaded image" class="img-fluid" style="max-height: 300px;">
                    </div>
                <?php endif; ?>
                
                <h5>Chi tiết:</h5>
                <pre class="bg-light p-3"><?php echo json_encode($result['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></pre>
                
                <a href="color_debug.php" class="btn btn-primary mt-3">Quay lại</a>
            </div>
        </div>
    </div>
</body>
</html>
