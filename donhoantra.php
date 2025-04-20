<?php
// Enable error reporting for troubleshooting
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include('config/config.php');

// Kiểm tra đăng nhập
if (!isset($_SESSION['user'])) {
    $_SESSION['error_message'] = "Vui lòng đăng nhập để tiếp tục.";
    header('Location: dangnhap.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Lấy thông tin người dùng
$user_id = $_SESSION['user']['id'];

// Kiểm tra tham số
if (!isset($_GET['order_id']) || !isset($_GET['product_id'])) {
    $_SESSION['error_message'] = "Thông tin yêu cầu hoàn trả không đầy đủ";
    header('Location: donhang.php');
    exit;
}

$order_id = (int)$_GET['order_id'];
$product_id = (int)$_GET['product_id'];

// Kiểm tra đơn hàng thuộc về người dùng hiện tại và có thể hoàn trả
$check_query = $conn->prepare("
    SELECT dh.id, dh.ma_donhang, dh.trang_thai_don_hang, 
        dc.id as order_detail_id, dc.tensp, dc.thuoc_tinh, dc.da_danh_gia,
        sp.hinhanh
    FROM donhang dh 
    JOIN donhang_chitiet dc ON dh.id = dc.id_donhang
    JOIN sanpham sp ON dc.id_sanpham = sp.id
    WHERE dh.id = ? AND dc.id_sanpham = ? AND dh.id_user = ?
");

$check_query->bind_param("iii", $order_id, $product_id, $user_id);
$check_query->execute();
$result = $check_query->get_result();

if ($result->num_rows === 0) {
    $_SESSION['error_message'] = "Đơn hàng không tồn tại hoặc không thuộc về bạn";
    header('Location: donhang.php');
    exit;
}

$order_data = $result->fetch_assoc();

// Kiểm tra trạng thái đơn hàng (phải là đã giao)
if ($order_data['trang_thai_don_hang'] != 4) {
    $_SESSION['error_message'] = "Đơn hàng chưa được giao thành công, không thể yêu cầu hoàn trả";
    header('Location: chitiet-donhang.php?id=' . $order_id);
    exit;
}

// Kiểm tra xem đã có yêu cầu hoàn trả chưa
// Thêm điều kiện trạng thái khác 5 (từ chối) để cho phép tạo yêu cầu mới nếu yêu cầu trước đó bị từ chối
$existing_query = $conn->prepare("
    SELECT id_hoantra, trangthai FROM hoantra 
    WHERE id_donhang = ? AND id_sanpham = ? AND id_nguoidung = ? AND trangthai != 5
");

$existing_query->bind_param("iii", $order_id, $product_id, $user_id);
$existing_query->execute();
$existing_result = $existing_query->get_result();

if ($existing_result->num_rows > 0) {
    $existing_return = $existing_result->fetch_assoc();
    $_SESSION['error_message'] = "Bạn đã gửi yêu cầu hoàn trả cho sản phẩm này và đang được xử lý";
    header('Location: chitiet-donhang.php?id=' . $order_id);
    exit;
}

// Xử lý form gửi yêu cầu hoàn trả
$error_message = "";
$success_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $reason = trim($_POST['reason']);
    $description = trim($_POST['description']);
    
    // Validate
    if (empty($reason)) {
        $error_message = "Vui lòng chọn lý do hoàn trả";
    } elseif (strlen($description) < 10) {
        $error_message = "Vui lòng mô tả chi tiết lý do hoàn trả (ít nhất 10 ký tự)";
    } else {
        // Lưu yêu cầu hoàn trả vào database
        $insert_query = $conn->prepare("
            INSERT INTO hoantra (
                id_donhang, id_sanpham, id_nguoidung, 
                lydo, mota_chitiet, trangthai, 
                ngaytao
            ) VALUES (?, ?, ?, ?, ?, 1, NOW())
        ");
        
        $insert_query->bind_param("iiiss", $order_id, $product_id, $user_id, $reason, $description);
        
        if ($insert_query->execute()) {
            // Thêm vào lịch sử đơn hàng
            $history_query = $conn->prepare("
                INSERT INTO donhang_lichsu (
                    id_donhang, hanh_dong, nguoi_thuchien, ghi_chu
                ) VALUES (?, ?, ?, ?)
            ");
            
            $action = "Yêu cầu hoàn trả";
            $user_name = $_SESSION['user']['tenuser'];
            $note = "Khách hàng yêu cầu hoàn trả sản phẩm với lý do: $reason";
            
            $history_query->bind_param("isss", $order_id, $action, $user_name, $note);
            $history_query->execute();
            
            // Thông báo thành công
            $success_message = "Yêu cầu hoàn trả của bạn đã được gửi thành công. Chúng tôi sẽ xem xét và phản hồi trong thời gian sớm nhất.";
        } else {
            $error_message = "Đã có lỗi xảy ra khi gửi yêu cầu hoàn trả. Vui lòng thử lại sau.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yêu cầu hoàn trả - Bug Shop</title>
    <link rel="stylesheet" href="node_modules/bootstrap/dist/css/bootstrap.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/index.css">
</head>
<body>
    <?php include('includes/header.php'); ?>
    
    <div class="container py-5">
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Trang chủ</a></li>
                <li class="breadcrumb-item"><a href="donhang.php">Đơn hàng của tôi</a></li>
                <li class="breadcrumb-item"><a href="chitiet-donhang.php?id=<?php echo $order_id; ?>">Đơn hàng #<?php echo $order_data['ma_donhang']; ?></a></li>
                <li class="breadcrumb-item active" aria-current="page">Yêu cầu hoàn trả</li>
            </ol>
        </nav>
        
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h4 class="card-title mb-0">Yêu cầu hoàn trả sản phẩm</h4>
                    </div>
                    
                    <div class="card-body">
                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-danger"><?php echo $error_message; ?></div>
                        <?php endif; ?>
                        
                        <?php if (!empty($success_message)): ?>
                            <div class="alert alert-success">
                                <?php echo $success_message; ?>
                                <div class="mt-3">
                                    <a href="chitiet-donhang.php?id=<?php echo $order_id; ?>" class="btn btn-primary">Quay lại đơn hàng</a>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="product-info mb-4 p-3 border rounded bg-light">
                                <div class="d-flex">
                                    <?php 
                                    $product_image = !empty($order_data['hinhanh']) ? 
                                        (strpos($order_data['hinhanh'], 'uploads/') === 0 ? $order_data['hinhanh'] : 'uploads/products/' . $order_data['hinhanh']) : 
                                        'images/no-image.png';
                                    ?>
                                    <div class="flex-shrink-0 me-3">
                                        <img src="<?php echo $product_image; ?>" alt="<?php echo htmlspecialchars($order_data['tensp']); ?>" class="img-thumbnail" style="width: 80px; height: 80px; object-fit: cover;">
                                    </div>
                                    <div>
                                        <h5 class="mb-1"><?php echo htmlspecialchars($order_data['tensp']); ?></h5>
                                        <?php if (!empty($order_data['thuoc_tinh'])): ?>
                                            <p class="text-muted mb-0 small"><?php echo htmlspecialchars($order_data['thuoc_tinh']); ?></p>
                                        <?php endif; ?>
                                        <p class="text-muted mb-0">Đơn hàng: #<?php echo $order_data['ma_donhang']; ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <form method="post" class="mt-4">
                                <div class="mb-3">
                                    <label for="reason" class="form-label">Lý do hoàn trả <span class="text-danger">*</span></label>
                                    <select class="form-select" id="reason" name="reason" required>
                                        <option value="">-- Chọn lý do hoàn trả --</option>
                                        <option value="Sản phẩm bị hư hỏng">Sản phẩm bị hư hỏng</option>
                                        <option value="Sản phẩm không đúng mô tả">Sản phẩm không đúng mô tả</option>
                                        <option value="Sản phẩm không vừa size">Sản phẩm không vừa size</option>
                                        <option value="Nhận sai sản phẩm">Nhận sai sản phẩm</option>
                                        <option value="Khác">Khác</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">Mô tả chi tiết <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="description" name="description" rows="5" placeholder="Vui lòng mô tả chi tiết vấn đề bạn gặp phải với sản phẩm..." required></textarea>
                                    <div class="form-text">Vui lòng mô tả chi tiết để chúng tôi có thể hỗ trợ bạn tốt nhất.</div>
                                </div>
                                
                                <div class="d-flex justify-content-between mt-4">
                                    <a href="chitiet-donhang.php?id=<?php echo $order_id; ?>" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-left"></i> Quay lại
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-send"></i> Gửi yêu cầu
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card mt-4 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">Chính sách hoàn trả</h5>
                    </div>
                    <div class="card-body">
                        <ul class="mb-0">
                            <li>Thời gian yêu cầu hoàn trả: trong vòng 7 ngày kể từ khi nhận sản phẩm</li>
                            <li>Sản phẩm phải còn nguyên tem, nhãn và chưa qua sử dụng</li>
                            <li>Quá trình xử lý yêu cầu hoàn trả có thể mất từ 3-5 ngày làm việc</li>
                            <li>Hoàn tiền sẽ được thực hiện qua phương thức thanh toán ban đầu hoặc tài khoản ngân hàng của bạn</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include('includes/footer.php'); ?>
    
    <script src="node_modules/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>