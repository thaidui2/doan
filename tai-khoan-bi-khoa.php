<?php
session_start();
include('config/config.php');

// Kiểm tra thông tin tài khoản bị khóa
if (!isset($_SESSION['account_locked']) || !isset($_SESSION['locked_user_id'])) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['locked_user_id'];

// Lấy thông tin về lý do khóa tài khoản
$stmt = $conn->prepare("SELECT taikhoan, tenuser, ly_do_khoa FROM users WHERE id_user = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: index.php');
    exit;
}

$user = $result->fetch_assoc();

// Sau khi hiển thị thông báo, xóa thông tin phiên hiện tại
session_destroy();

$page_title = "Tài khoản bị khóa";
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Bug Shop</title>
    <link rel="stylesheet" href="node_modules/bootstrap/dist/css/bootstrap.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/index.css">
    <style>
        .locked-account-container {
            max-width: 600px;
            margin: 100px auto;
        }
    </style>
</head>
<body>
    <?php include('includes/header.php'); ?>
    
    <div class="container py-5">
        <div class="locked-account-container">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4 text-center">
                    <div class="mb-4">
                        <i class="bi bi-lock-fill text-danger" style="font-size: 4rem;"></i>
                    </div>
                    <h2 class="mb-3">Tài khoản đã bị khóa</h2>
                    <p class="text-muted">Chào <strong><?php echo htmlspecialchars($user['tenuser']); ?></strong>, tài khoản của bạn đã bị khóa bởi quản trị viên.</p>
                    
                    <?php if (!empty($user['ly_do_khoa'])): ?>
                    <div class="alert alert-danger my-4">
                        <h5 class="mb-2">Lý do:</h5>
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($user['ly_do_khoa'])); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <p class="mb-4">Nếu bạn cho rằng đây là sự nhầm lẫn hoặc cần biết thêm thông tin, vui lòng liên hệ với bộ phận hỗ trợ khách hàng của chúng tôi.</p>
                    
                    <div class="mt-4">
                        <a href="index.php" class="btn btn-primary me-2">Quay lại trang chủ</a>
                        <a href="lien-he.php" class="btn btn-outline-secondary">Liên hệ hỗ trợ</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include('includes/footer.php'); ?>
    
    <script src="node_modules/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>